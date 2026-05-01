#!/usr/bin/env php
<?php
require_once __DIR__ . '/../bootstrap.php';
use App\Auctions\AuctionService;
$count = (new AuctionService($pdo))->closeExpiredAuctions();
echo "Closed {$count} auctions\n";
