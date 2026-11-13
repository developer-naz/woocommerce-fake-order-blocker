<div align="center">

# 🛡️ Fake Order Blocker for WooCommerce

**Stop fake and repeated Cash on Delivery orders before they cost you courier charges.**

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat&logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?style=flat&logo=woocommerce&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php&logoColor=white)
![HPOS](https://img.shields.io/badge/HPOS-compatible-success?style=flat)
![Checkout block](https://img.shields.io/badge/Checkout%20block-supported-success?style=flat)
![Tests](https://img.shields.io/badge/end--to--end%20tests-29%20passing-brightgreen?style=flat)
![License](https://img.shields.io/badge/license-GPL--2.0-orange?style=flat)

</div>

---

## 😩 The problem

Cash on Delivery stores get orders that never get accepted: pranks, competitors, the same person ordering five times, wrong numbers. Every fake order costs packaging, courier fees and return charges.

## ✅ What this plugin does

| Protection | How it works |
|---|---|
| 📱 **Orders per phone** | Blocks a phone after X orders in Y hours (default: 3 in 24h). Formats like `01712-345678`, `+8801712345678` and `880 1712 345678` count as the **same** number. |
| 🌐 **Orders per IP** | Optional limit per IP address (off by default, because many mobile users in Bangladesh share one IP). |
| 🚫 **Blacklist** | Block phones, IPs (exact or `103.12.*`) and emails (exact or a whole domain like `@tempmail.com`). |
| ⏸️ **COD on hold** | Put first-time customers' (or all) COD orders **on hold** so you can confirm by phone before shipping. Returning customers with a delivered order go straight through. |
| 🔎 **Customer check box** | On every order: the customer's past orders, delivered vs cancelled count, success rate and a **Trusted / New / Risky / Blacklisted** badge. |
| ⚡ **One-click block** | **Block phone** / **Block IP** buttons (and Unblock) right on the order screen. |
| 🧹 **Clean phone numbers** | Saves Bangladeshi mobiles as `01XXXXXXXXX`, which also helps courier booking. |
| 📋 **Block log** | See the last 50 blocked attempts and why they were blocked. |

Works with the **classic checkout**, the **Checkout block**, **Elementor checkout** and **HPOS**. Admins and shop managers are never blocked.

---

## 🚀 Installation

1. Click **Code → Download ZIP** on this page.
2. In WordPress go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Activate **Fake Order Blocker for WooCommerce**.
4. Go to **WooCommerce → Settings → Fake Orders** to adjust the limits.

The per-phone limit is active right away (3 orders per 24 hours). Everything else is off until you turn it on.

---

## ⚙️ Settings

**WooCommerce → Settings → Fake Orders**

| Setting | Default |
|---|---|
| Limit orders per phone | On, max **3** in **24** hours |
| Limit orders per IP | Off (max 10 in 24 hours when on) |
| Blocked phones / IPs / emails | Empty |
| Put COD orders on hold | Never (options: first-time customers, all) |
| Clean phone numbers | On |
| Message shown when blocked | "Sorry, we could not place your order right now. Please call or message us to complete your order." |

### For developers

~~~php
// Never block customers with a specific role.
add_filter( 'wfob_bypass', function ( $bypass ) {
	return $bypass || current_user_can( 'wholesale_customer' );
} );

// Custom rule: block orders above 50,000 from new phones.
add_filter( 'wfob_check_result', function ( $reason, $phone, $email, $ip ) {
	if ( '' === $reason && WC()->cart && WC()->cart->get_total( 'edit' ) > 50000 && 0 === wfob_phone_stats( $phone )['total'] ) {
		return 'high_value_new_customer';
	}
	return $reason;
}, 10, 4 );

// Get notified (Slack, SMS, log…) whenever an order is blocked.
add_action( 'wfob_order_blocked', function ( $reason, $phone, $email, $ip ) {
	error_log( "Blocked order: $reason $phone $ip" );
}, 10, 4 );

// Override any setting in code, e.g. a stricter phone limit.
add_filter( 'wfob_option_phone_limit', fn() => 2 );
~~~

| Hook | Type | Use |
|---|---|---|
| `wfob_check_result` | filter | Add your own blocking rules |
| `wfob_bypass` | filter | Decide who is never blocked |
| `wfob_option_{key}` | filter | Override a setting in code |
| `wfob_order_blocked` | action | Run code when an order is blocked |

---

## 🧪 Tested

Version 1.0.0 was tested end-to-end on a real WordPress 6.8 + WooCommerce 10.3 site with a browser placing real orders, on **both** legacy order storage and **HPOS** (29/29 checks passing):

- Three orders with the same number in different formats are allowed, saved as `01712345678`, and the 4th is blocked
- Another customer is not affected
- Blacklisted phone (stored as `+880 1999-999999`, ordered as `01999999999`) is blocked
- Blacklisted email domain is blocked
- First-time COD customer goes on hold with a note; returning customer with a delivered order does not
- Customer check box shows the right counts, badge and success rate
- Block / Unblock buttons update the blacklist
- Settings page saves and shows the block log
- Checkout block (Store API): blacklisted and over-limit phones get a `400 wfob_blocked` error, normal customers go through

---

## 📁 Project structure

~~~
woocommerce-fake-order-blocker/
├── woocommerce-fake-order-blocker.php   # Plugin header, compatibility, loader
├── includes/
│   ├── functions.php                    # Settings, phone normalization, blacklist, limits, history
│   ├── checkout-guard.php               # Blocking (classic + block checkout), COD hold
│   ├── settings.php                     # WooCommerce → Settings → Fake Orders
│   └── admin-order.php                  # Customer check box, block / unblock buttons
├── CHANGELOG.md
└── LICENSE
~~~

---

## ❓ FAQ

**Will it block real customers?**
Only if they break a rule you set. The default (3 orders per phone per day) is generous. Blocked customers see a polite message asking them to call you, and every block is listed in the log so you can check.

**Why is the IP limit off by default?**
Mobile networks put many customers behind one shared IP. A strict IP limit can block real buyers. If you turn it on, keep it high.

**Do old orders count toward the limit?**
The per-phone and per-IP limits count orders placed after the plugin is activated. The customer check box and the "first-time customer" hold look at **all** past orders.

**Does it slow down checkout?**
No. Limits are stored in transients (one quick lookup). The order history lookup only runs on the order screen and when "COD on hold for first-time customers" is enabled.

---

## 🗺️ Roadmap

- [ ] OTP phone verification before COD orders
- [ ] Courier success-rate check (Steadfast, Pathao, RedX)
- [ ] Block list import / export (CSV)
- [ ] Order list column with the customer badge

---

## 📄 License

[GPL-2.0-or-later](LICENSE), the same license as WordPress and WooCommerce.

---

<div align="center">

### 👨‍💻 Built by Md Nazmul Islam (Developer Naz)

Web Developer | WordPress & Shopify Expert | SEO Specialist

[![LinkedIn](https://img.shields.io/badge/LinkedIn-developernaz-0A66C2?style=flat&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/developernaz/)
[![GitHub](https://img.shields.io/badge/GitHub-developer--naz-181717?style=flat&logo=github&logoColor=white)](https://github.com/developer-naz)

Also see: [BD Upazila Dropdown](https://github.com/developer-naz/bd-upazila-dropdown-for-woocommerce) · [WooCommerce Checkout Customization](https://github.com/developer-naz/woocommerce-checkout-customization)

⭐ If this plugin saves you from fake orders, please give it a star!

</div>
