<?php
require_once __DIR__ . '/../../bootstrap.php';
use App\Core\Csrf;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) { http_response_code(419); exit('CSRF'); }
$userId = (int)($_SESSION['user']['id'] ?? 0);
$auctionId = (int)($_POST['auction_id'] ?? 0);
$insert = $pdo->prepare('INSERT IGNORE INTO auction_watchlist (auction_id, user_id) VALUES (:auction_id,:user_id)');
$insert->execute(['auction_id'=>$auctionId, 'user_id'=>$userId]);
header('Location: detail.php?id=' . $auctionId);
