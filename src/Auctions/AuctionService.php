<?php

declare(strict_types=1);

namespace App\Auctions;

use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class AuctionService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function placeBid(int $auctionId, int $userId, string $amount): int
    {
        $now = new DateTimeImmutable('now');
        $this->pdo->beginTransaction();

        try {
            $auctionStmt = $this->pdo->prepare('SELECT * FROM auctions WHERE id = :id FOR UPDATE');
            $auctionStmt->execute(['id' => $auctionId]);
            $auction = $auctionStmt->fetch();

            if (!$auction) {
                throw new RuntimeException('Auction not found.');
            }

            if (!in_array($auction['status'], ['scheduled', 'active'], true)) {
                throw new RuntimeException('Auction is not open for bidding.');
            }

            if ($now < new DateTimeImmutable($auction['starts_at']) || $now > new DateTimeImmutable($auction['ends_at'])) {
                throw new RuntimeException('Auction is outside bidding window.');
            }

            $minBid = bcadd((string) $auction['current_price'], (string) $auction['bid_step'], 2);
            if (bccomp($amount, $minBid, 2) < 0) {
                throw new RuntimeException('Bid too low. Minimum is ' . $minBid);
            }

            $rateStmt = $this->pdo->prepare('SELECT COUNT(*) FROM auction_bids WHERE auction_id = :auction_id AND user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)');
            $rateStmt->execute(['auction_id' => $auctionId, 'user_id' => $userId]);
            if ((int) $rateStmt->fetchColumn() >= 3) {
                throw new RuntimeException('Rate limit exceeded.');
            }

            $bidStmt = $this->pdo->prepare('INSERT INTO auction_bids (auction_id, user_id, amount) VALUES (:auction_id, :user_id, :amount)');
            $bidStmt->execute(['auction_id' => $auctionId, 'user_id' => $userId, 'amount' => $amount]);

            $bidId = (int) $this->pdo->lastInsertId();

            $updateStmt = $this->pdo->prepare('UPDATE auctions SET current_price = :amount, status = "active" WHERE id = :id');
            $updateStmt->execute(['amount' => $amount, 'id' => $auctionId]);

            $this->log('bid_placed', $userId, ['auction_id' => $auctionId, 'bid_id' => $bidId, 'amount' => $amount]);
            $this->notifyOutbidUsers($auctionId, $userId, $amount);

            $this->pdo->commit();
            return $bidId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function closeExpiredAuctions(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM auctions WHERE ends_at <= NOW() AND status IN ('scheduled','active','unpaid')");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->closeAuction((int) $id);
        }

        return count($ids);
    }

    public function closeAuction(int $auctionId): void
    {
        $this->pdo->beginTransaction();
        try {
            $auction = $this->pdo->prepare('SELECT * FROM auctions WHERE id = :id FOR UPDATE');
            $auction->execute(['id' => $auctionId]);
            $a = $auction->fetch();
            if (!$a) throw new RuntimeException('Auction not found');

            $bid = $this->pdo->prepare('SELECT * FROM auction_bids WHERE auction_id = :id ORDER BY amount DESC, id ASC LIMIT 1');
            $bid->execute(['id' => $auctionId]);
            $top = $bid->fetch();

            if (!$top || ($a['reserve_price'] !== null && bccomp((string)$top['amount'], (string)$a['reserve_price'], 2) < 0)) {
                $u = $this->pdo->prepare("UPDATE auctions SET status='ended', winner_user_id=NULL, winning_bid_id=NULL WHERE id=:id");
                $u->execute(['id'=>$auctionId]);
                $this->log('auction_ended', null, ['auction_id'=>$auctionId, 'winner'=>null]);
                $this->pdo->commit();
                return;
            }

            $dueAt = (new DateTimeImmutable('now'))->add(new DateInterval('P2D'))->format('Y-m-d H:i:s');
            $u = $this->pdo->prepare("UPDATE auctions SET status='unpaid', winner_user_id=:winner, winning_bid_id=:bid_id, payment_due_at=:due WHERE id=:id");
            $u->execute(['winner'=>$top['user_id'],'bid_id'=>$top['id'],'due'=>$dueAt,'id'=>$auctionId]);
            $this->createAuctionOrder($auctionId, (int)$top['user_id'], (string)$top['amount'], 'auction');
            $this->notify((int)$top['user_id'], 'auction_winner', ['auction_id'=>$auctionId]);
            $this->log('winner_assigned', (int)$top['user_id'], ['auction_id'=>$auctionId, 'bid_id'=>$top['id']]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function useBuyNow(int $auctionId, int $userId): void { /* implemented similarly; set source=buy_now, end auction */ }
    public function reassignWinner(int $auctionId): void { /* assign next highest bidder after unpaid/cancelled winner */ }

    private function createAuctionOrder(int $auctionId, int $userId, string $amount, string $source): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO orders (user_id, total_amount, source, status, created_at) VALUES (:user_id, :total, :source, :status, NOW())');
        $stmt->execute(['user_id' => $userId, 'total' => $amount, 'source' => $source, 'status' => 'pending_payment']);
    }

    private function notifyOutbidUsers(int $auctionId, int $latestUserId, string $amount): void
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT user_id FROM auction_bids WHERE auction_id = :auction_id AND user_id != :user_id');
        $stmt->execute(['auction_id' => $auctionId, 'user_id' => $latestUserId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $this->notify((int) $uid, 'auction_outbid', ['auction_id' => $auctionId, 'amount' => $amount]);
        }
    }

    private function notify(int $userId, string $type, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO notifications (user_id, type, payload) VALUES (:user_id, :type, :payload)');
        $stmt->execute(['user_id' => $userId, 'type' => $type, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    private function log(string $action, ?int $userId, array $context): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (action, user_id, context, created_at) VALUES (:action, :user_id, :context, NOW())');
        $stmt->execute(['action' => $action, 'user_id' => $userId, 'context' => json_encode($context, JSON_THROW_ON_ERROR)]);
    }
}
