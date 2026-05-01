<?php
require_once __DIR__ . '/../../bootstrap.php';
use App\Auth\Authorization;
Authorization::requireAdminPermission($_SESSION['user'] ?? [], 'auctions.view');
$auctions = $pdo->query('SELECT * FROM auctions ORDER BY id DESC')->fetchAll();
?><!doctype html><html><head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="container py-4"><h1>Admin Auctions</h1><table class="table table-striped"><tr><th>ID</th><th>Title</th><th>Status</th><th>Current</th></tr><?php foreach($auctions as $a): ?><tr><td><?= (int)$a['id'] ?></td><td><?= htmlspecialchars($a['title']) ?></td><td><?= htmlspecialchars($a['status']) ?></td><td>$<?= htmlspecialchars($a['current_price']) ?></td></tr><?php endforeach; ?></table></body></html>
