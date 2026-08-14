# SILC Fake Order Generator

**Populate your WooCommerce store with realistic test orders — in minutes, not hours.**

Need a store full of orders to test reports, dashboards, emails, or shipping? This plugin generates realistic WooCommerce orders directly in the admin — random customers, products, variations, dates, statuses, shipping methods, and payment gateways — without touching your real data or checkout flow.

---

## ✨ Why SILC Fake Order Generator?

### 🧪 Realistic Data, Zero Manual Entry

Manually creating dozens of orders to test a report or a new email template is tedious and error-prone. This plugin builds complete orders through WooCommerce's own APIs — line items, billing/shipping addresses, payment methods, shipping rates, totals, and status transitions — so generated orders behave like real ones everywhere: admin lists, Analytics, emails, and extensions.

### ⚡ Batch Generation with a Live Progress Bar

Orders are generated in AJAX batches of 5, so large runs (up to 10,000 orders) never hit PHP timeouts or memory limits. A progress bar, per-batch log, and a **Cancel** button keep you in control.

### 📊 Analytics-Ready

Generated orders are pushed into the WooCommerce Analytics lookup tables (`wc_order_stats` and friends) immediately, and `date_paid` / `date_completed` are backdated to the requested date range for statuses that imply payment — so revenue reports and dashboards reflect your test data exactly where you expect it.

---

## 🚀 Quick Start

1. Install the plugin (requires WooCommerce 8.0+ and PHP 7.4+)
2. Go to **WooCommerce → Fake Orders**
3. Pick a **Start Date** and **End Date** for order dates
4. Set the **Number of Orders** and items-per-order range (1–5)
5. Choose which **Shipping Methods**, **Payment Methods**, and **Order Statuses** to randomize from
6. Click **Generate Orders** and watch the progress bar

**Prerequisites:** the store needs at least one customer and at least one published product (simple or variable) — the generator will tell you if either is missing.

---

## 🧩 Features

| Area | What it does |
|------|--------------|
| **Batch queue** | AJAX batches of 5 orders; progress bar, live log, cancel anytime; up to 10,000 orders per run |
| **Random customers** | Picks from existing customer/subscriber accounts (falls back to staff accounts when few customers exist) |
| **Random products** | Simple products *and* variations, 1–5 distinct items per order, quantities 1–5, no duplicate line items |
| **Random dates** | Order dates anywhere between your start and end date, interpreted in the site's timezone |
| **Order statuses** | pending, processing, on-hold, completed, cancelled, refunded, failed — applied through WooCommerce's normal status-transition pipeline |
| **Analytics sync** | `date_paid`/`date_completed` backdated for paid statuses; orders synced to `wc_order_stats` via `OrdersScheduler::import` (with fallback for older versions) |
| **Shipping & payment** | Random rates from your real shipping zones and active payment gateways |
| **Realistic addresses** | Billing/shipping from customer data, with believable random fallbacks (street, city, postcode) |
| **Secure** | Nonce-verified AJAX endpoint, `manage_woocommerce` capability required |

---

## 🔧 How It Works

```
Admin form (dates, count, items, methods, statuses)
        │
        ▼
┌───────────────────────────────┐
│  Batch queue (5 orders/AJAX)  │  ← progress bar + cancel
└───────────────────────────────┘
        │
        ▼
┌───────────────────────────────┐
│  wc_create_order()            │  ← real WooCommerce APIs
│  Random customer + products   │
│  Addresses, shipping, payment │
└───────────────────────────────┘
        │
        ▼
┌───────────────────────────────┐
│  calculate_totals() + save()  │  ← totals, no taxes for simplicity
└───────────────────────────────┘
        │
        ▼
┌───────────────────────────────┐
│  update_status() pipeline     │  ← fires same hooks as a real order
│  Analytics sync (order_stats) │
└───────────────────────────────┘
```

### Why the status pipeline matters

Orders are created as `pending`, then moved to their randomized target status via `WC_Order::update_status()`. That means other plugins, emails, stock adjustments, and reporting see the exact same hooks they would for a real order status change — not a synthetic database write.

---

## 📦 Requirements

| Component | Minimum |
|-----------|---------|
| WordPress | 6.0+ |
| WooCommerce | 8.0+ |
| PHP | 7.4+ |

---

## 🏗️ Development

The plugin has no build step — the admin UI is a single vanilla jQuery script with no dependencies beyond WordPress core.

```bash
# No npm/composer install required for runtime.
# Validate PHP syntax after changes:
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

### Releasing

A GitHub Actions workflow (`.github/workflows/build-plugin.yml`) builds a distributable ZIP on every `v*` tag push:

```bash
git tag v1.0.0
git push origin v1.0.0 --tags
```

The workflow assembles a clean plugin folder (excluding `.git`, `.github`, docs, and build config), zips it, uploads it as a run artifact, and attaches it to a GitHub Release when triggered by a tag.

---

## 📄 License

GPL v2 or later. See [LICENSE](./LICENSE) for full text.

---

## 🔗 Links

- **Plugin URI**: https://silc.com/
- **Source**: available in the SILC plugin repository (see the plugin header for the canonical URI)
