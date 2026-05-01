<?php
require_once __DIR__ . '/../../bootstrap.php';
use App\Auth\Authorization;
Authorization::requireAdminPermission($_SESSION['user'] ?? [], 'auctions.bids.view');
$bids = $pdo->query('SELECT b.*, a.title FROM auction_bids b JOIN auctions a ON a.id=b.auction_id ORDER BY b.id DESC LIMIT 500')->fetchAll();
?><!doctype html><html><head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="container py-4"><h1>Admin Bid History</h1><table class="table table-bordered"><tr><th>ID</th><th>Auction</th><th>User</th><th>Amount</th><th>Time</th></tr><?php foreach($bids as $b): ?><tr><td><?= (int)$b['id'] ?></td><td><?= htmlspecialchars($b['title']) ?></td><td><?= (int)$b['user_id'] ?></td><td>$<?= htmlspecialchars($b['amount']) ?></td><td><?= htmlspecialchars($b['created_at']) ?></td></tr><?php endforeach; ?></table></body></html>
