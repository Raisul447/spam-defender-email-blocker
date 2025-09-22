=== Spam Defender - Email Blocker ===
Contributors: shagor447
Tags: email, block, registration, comments, woocommerce
Requires at least: 4.8
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block specific email addresses from using your WordPress site. Stop fake orders, spam registrations, and unwanted comments.

== Description ==
= Spam Defender - Email Blocker helps you reduce spam and fake activity by blocking specific email addresses or domains.  
Once blocked, users with those emails will not be able to: =

* Register new accounts
* Login
* Post comments
* Checkout or create accounts in WooCommerce

This plugin is lightweight, easy to use, and comes with an admin settings page where you can manage blocked emails/domains.

**Features:**
* Block emails globally across WordPress.
* Block on login, registration, comments, and WooCommerce checkout.
* Manage blocked emails from settings page.
* Search blocked email lists.
* Prevents fake orders and spam signups.

== Installation ==
1. Upload the `spam-defender-email-blocker` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the ‘Plugins’ menu in WordPress.
3. Navigate to **Dashboard > Settings > Block Email** and click to configure email setup.

== Frequently Asked Questions ==

= Will this block existing users with blocked emails? =
No, it prevents future registrations, logins, comments, and checkouts using blocked emails.

= Does it work with WooCommerce? =
Yes, WooCommerce checkout and account creation are fully supported.

= Is my data secure? =
Yes, all emails data are secured.

== Screenshots ==
1. Admin settings page to manage blocked emails.
2. Blocked email options.
3. Unblocked email options.
4. Search functionality.

== Changelog ==

= 1.0.0 =
* Initial release.
* Block email addresses from registration, login, comments, and WooCommerce checkout.

= 1.0.1 =
* Added Settings link on plugin page.
* Improved settings form design (inline input + button).
* Added serial numbers in blocked email list.
* Added search box with form submit.
* Added pagination (20 per page).

= 1.0.2 =
* Added nonce verification for admin actions (block / unblock email).
* Inserted nonce fields in admin forms to protect against CSRF.
* Used wp_unslash() before sanitizing input from $_POST and $_GET.
* Escaped output properly using esc_html(), wp_kses_post(), and esc_html__().
* Sanitized checkout email field (billing_email) with wp_unslash() + sanitize_email().
* Added // phpcs:ignore to prevent false-positive warnings for nonce checks (WooCommerce handles its own security).

== Upgrade Notice ==

= 1.0.0 =
Initial release.

= 1.0.2 =
Recommended update — introduces better UI and management features (search, pagination, settings link).
