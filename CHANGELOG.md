# Changelog

All notable changes to SILC Fake Order Generator will be documented in this file.

## [1.1.0] — 2026

### Added
- **Coupons** — published WooCommerce coupons are listed as selectable checkboxes on the generator page; each generated order gets one randomly chosen coupon applied before totals are calculated. Coupons that fail their own conditions (minimum/maximum spend, usage limits, product/category restrictions, expiry, etc.) are skipped silently, so order generation is never interrupted

## [1.0.0] — 2026

### Added
- **Batch order generation** — orders are created 5 per AJAX request with a live progress bar, per-batch log, and a cancel button; supports up to 10,000 orders per run without PHP timeouts
- **Random customers** — picks from existing customer/subscriber accounts, falling back to staff accounts when few customers exist
- **Random products** — published simple products and variations, 1–5 distinct items per order, quantities 1–5, no duplicate line items
- **Random order dates** — generated between a configurable start/end date range, interpreted in the site's timezone
- **Order status selection** — pending, processing, on-hold, completed, cancelled, refunded, and failed, applied through WooCommerce's normal `update_status()` transition pipeline
- **Analytics readiness** — `date_paid` / `date_completed` backdated for paid statuses; orders synced to WooCommerce Analytics lookup tables (`wc_order_stats`) via `OrdersScheduler::import` with a fallback for older WooCommerce versions
- **Shipping & payment** — random shipping rates drawn from real shipping zones and active payment gateways
- **Realistic addresses** — billing/shipping from customer data with random fallback street, city, and postcode
- **Security** — nonce-verified AJAX endpoint (`silc_fog_generate_batch`) requiring the `manage_woocommerce` capability; all inputs sanitized and validated server-side
- **Documentation & release tooling** — README, WordPress readme.txt, GPL v2 license, and a GitHub Actions workflow that builds a distributable ZIP on version tags
