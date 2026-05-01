<?php
require_once __DIR__ . '/../../bootstrap.php';
use App\Core\Csrf;
use App\Auctions\AuctionService;

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM auctions WHERE id=:id');
$stmt->execute(['id'=>$id]);
$auction = $stmt->fetch();
if (!$auction) { http_response_code(404); exit('Not found'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) { http_response_code(419); exit('CSRF'); }
    (new AuctionService($pdo))->placeBid($id, (int)$_SESSION['user']['id'], (string)$_POST['amount']);
    header('Location: detail.php?id=' . $id); exit;
}
$bids = $pdo->prepare('SELECT b.*, u.name FROM auction_bids b LEFT JOIN users u ON u.id=b.user_id WHERE auction_id=:id ORDER BY b.id DESC');
$bids->execute(['id'=>$id]);
$history = $bids->fetchAll();
?>
<!doctype html><html><head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="container py-4">
<h1><?= htmlspecialchars($auction['title']) ?></h1>
<p>Current: $<?= htmlspecialchars($auction['current_price']) ?></p>
<p>Minimum next bid: $<?= number_format((float)$auction['current_price'] + (float)$auction['bid_step'],2) ?></p>
<p>Ends in: <span id="countdown"></span></p>
<form method="post" class="row g-2">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
<div class="col-auto"><input type="number" step="0.01" min="0" class="form-control" name="amount" required></div>
<div class="col-auto"><button class="btn btn-success">Place bid</button></div>
</form>
<h3 class="mt-4">Bid History</h3><ul class="list-group">
<?php foreach($history as $bid): ?><li class="list-group-item"><?= htmlspecialchars($bid['name'] ?? ('User #'.$bid['user_id'])) ?> bid $<?= htmlspecialchars($bid['amount']) ?></li><?php endforeach; ?>
</ul>
<script>
const end = new Date('<?= $auction['ends_at'] ?>').getTime();
setInterval(()=>{const d=end-Date.now();document.getElementById('countdown').innerText=d<=0?'Ended':Math.floor(d/1000)+'s';},1000);
</script>
</body></html>
