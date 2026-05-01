<?php
require_once __DIR__ . '/../../bootstrap.php';
$auctions = $pdo->query("SELECT * FROM auctions WHERE status IN ('scheduled','active','unpaid') ORDER BY ends_at ASC")->fetchAll();
?>
<!doctype html><html><head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="container py-4">
<h1>Auctions</h1>
<div class="row g-3">
<?php foreach ($auctions as $auction): ?>
<div class="col-md-4"><div class="card h-100"><div class="card-body">
<h5><?= htmlspecialchars($auction['title']) ?></h5>
<p>Current: $<?= htmlspecialchars($auction['current_price']) ?></p>
<p>Ends: <?= htmlspecialchars($auction['ends_at']) ?></p>
<a class="btn btn-primary" href="detail.php?id=<?= (int)$auction['id'] ?>">View</a>
</div></div></div>
<?php endforeach; ?>
</div></body></html>
