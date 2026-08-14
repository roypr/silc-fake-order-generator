=== SILC Fake Order Generator ===
Contributors: silc
Tags: woocommerce, orders, testing, development, dummy data, sample data, test orders
Requires at least: 6.0
Tested up to: 7.0.2
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 10.9.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic fake WooCommerce orders for testing — random customers, products, dates, statuses, shipping, and payment methods, in batches with a live progress bar.

== Description ==

**SILC Fake Order Generator** fills your WooCommerce store with realistic test orders so you can test reports, dashboards, emails, and extensions without manually creating data. Orders are built through WooCommerce's own APIs — complete with line items, addresses, payment methods, shipping rates, totals, and status transitions — so they behave exactly like real orders everywhere.

= Batch Generation with Progress Bar =

Orders are generated in AJAX batches of 5, so large runs (up to 10,000 orders) never hit PHP timeouts or memory limits. A live progress bar, per-batch log, and a **Cancel** button keep you in control from the WooCommerce admin.

= Realistic, Random Data =

* **Customers** — random selection from existing customer/subscriber accounts (with staff fallback when few customers exist)
* **Products** — simple products and variations, 1–5 distinct items per order, quantities 1–5, no duplicate line items
* **Dates** — random order dates within your chosen range, interpreted in the site's timezone
* **Statuses** — pending, processing, on-hold, completed, cancelled, refunded, failed, applied through WooCommerce's normal status-transition pipeline
* **Shipping & payment** — random rates from your real shipping zones and active payment gateways
* **Addresses** — billing/shipping from customer data with believable random fallbacks

= Analytics-Ready =

Generated orders are pushed into the WooCommerce Analytics lookup tables (`wc_order_stats` and friends) immediately, and `date_paid` / `date_completed` are backdated for statuses that imply payment — so revenue reports and dashboards reflect your test data exactly where you expect it.

= Built on WooCommerce APIs =

Orders are created with `wc_create_order()`, populated with `WC_Order` methods, totals calculated via `calculate_totals()`, and statuses applied via `update_status()` — the same pipeline a real order goes through. Other plugins, emails, stock adjustments, and reporting see the same hooks they would for a real order.

= Secure by Design =

* AJAX endpoint is nonce-verified
* `manage_woocommerce` capability required for all requests
* All inputs sanitized; dates and ranges validated server-side
* No third-party services — everything runs locally on your server

== Installation ==

1. Upload the `silc-fake-order-generator` folder to `/wp-content/plugins/`, or install the plugin ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WooCommerce → Fake Orders**.
4. Choose your start/end dates, number of orders, items per order, shipping methods, payment methods, and order statuses.
5. Click **Generate Orders** and watch the progress bar.

**Prerequisites:** the store needs at least one customer and at least one published product (simple or variable). The generator shows a friendly notice if either is missing.

== Frequently Asked Questions ==

= Does this affect real customers or orders? =

No. The generator only *creates new* orders and never modifies or deletes existing orders, customers, or products. It picks existing customers and products to attach to the new orders.

= How many orders can I generate at once? =

Up to 10,000 per run. Orders are created in batches of 5 via AJAX, so large runs avoid PHP timeouts and memory limits. You can cancel at any time.

= Why are my generated orders missing from WooCommerce Analytics? =

Older versions could miss the async Analytics pipeline. This plugin syncs each generated order into the Analytics lookup tables explicitly (`OrdersScheduler::import`, with a fallback for older WooCommerce versions) and backdates `date_paid`/`date_completed` so reports cover your chosen date range.

= Does it work with variable products? =

Yes. Published variations of variable products are included in the item pool, and variation attributes are stored on the line items for correct display.

= Is tax calculated on generated orders? =

Totals are calculated without taxes for simplicity (`calculate_totals( false )`), keeping generated data predictable.

= Can I choose which statuses to generate? =

Yes. The status checkboxes let you restrict generation to any subset of pending, processing, on-hold, completed, cancelled, refunded, and failed. Each order is randomly assigned one of the selected statuses.

== Screenshots ==

1. Generator form — dates, order count, items per order, shipping, payment, and status options.
2. Generation in progress — progress bar, live log, and cancel button.

== Changelog ==

= 1.0.0 =
* Initial release
* Batch order generation (5 per AJAX request) with live progress bar, per-batch log, and cancel support
* Random customers, simple products, and variations with quantities
* Random order dates within a configurable range (site-timezone aware)
* Configurable order statuses applied through WooCommerce's status-transition pipeline
* Backdating of `date_paid` / `date_completed` for paid statuses
* Immediate WooCommerce Analytics sync (`wc_order_stats`) with fallback for older versions
* Random shipping methods (from real zones) and payment gateways
* Billing/shipping addresses from customer data with random fallbacks
* Nonce-verified AJAX endpoint requiring `manage_woocommerce` capability

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade path required — nothing to migrate.

== Credits ==

Built by the SILC team. Uses WordPress core and WooCommerce APIs only — no third-party dependencies.
