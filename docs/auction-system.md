# Auction System

## Implemented
- Auction entity with required fields and status flow.
- Bid placement with transaction + `SELECT ... FOR UPDATE` to prevent race conditions.
- Bid validation (`current_price + bid_step` minimum), starts/ends/status checks.
- Anti-spam rate limiting (3 bids / 10 sec per user-auction).
- Buy-now and winner reassignment extension points included in service methods.
- Auto-close CLI script for cron.
- Notifications for winner/outbid users.
- Watchlist table with unique `(user_id, auction_id)`.
- Admin permission keys required:
  - `auctions.view`
  - `auctions.create`
  - `auctions.update`
  - `auctions.cancel`
  - `auctions.bids.view`
  - `auctions.assign_winner`
- Audit log action keys:
  - `auction_created`
  - `bid_placed`
  - `auction_ended`
  - `winner_assigned`
  - `buy_now_used`
  - `winner_reassigned`

## Cron
Run every minute:

```bash
* * * * * /usr/bin/php /path/to/project/scripts/close_expired_auctions.php
```
