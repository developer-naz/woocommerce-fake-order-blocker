# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-14

### Added
- Order limit per phone (default 3 in 24 hours), with Bangladeshi phone formats treated as the same number.
- Optional order limit per IP address.
- Blacklist for phones, IPs (exact or prefix with `*`) and emails (exact or `@domain`).
- Put Cash on Delivery orders on hold: never, first-time customers only, or all.
- "Customer check" box on the order screen with order history, success rate, badge and Block / Unblock buttons.
- Phone number cleanup to `01XXXXXXXXX` on checkout.
- Settings tab (WooCommerce → Settings → Fake Orders) with a log of the last 50 blocked attempts.
- Support for the classic checkout and the Checkout block (Store API).
- HPOS compatibility.
- Hooks: `wfob_check_result`, `wfob_bypass`, `wfob_option_{key}`, `wfob_order_blocked`.
