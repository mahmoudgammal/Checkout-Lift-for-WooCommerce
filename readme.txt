=== Checkout Lift for WooCommerce ===
Contributors: mahmoudgammal, designtich
Tags: woocommerce, checkout, fields, abandoned cart, conditional
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.0
WC requires at least: 10.0
WC tested up to: 11.3
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Edit WooCommerce checkout fields, add conditional fields, and see the potential value of unfinished checkouts.

== Description ==

**Checkout Lift for WooCommerce** gives you control over your checkout without requiring a page builder.

Two things live here. The **field editor** decides what your checkout asks for. **Checkout tracking** shows unfinished checkouts and their potential cart value. The separately installed Pro add-on can follow up automatically and restore the saved cart through a protected link.

= The field editor =

Everything WooCommerce puts on the checkout, plus anything you want to add:

* **Reorder** fields by dragging them, within a section or across sections
* **Show, hide, and require** any field
* **Rename** labels and set placeholders
* **Set the width** of each field, from a quarter to full width, on either checkout
* **Add your own fields** in six Lite types: Text, Number, Email, URL, Select, and Textarea
* **Remove the shipping section** entirely, for stores that do not ship
* **Validate phone numbers** per country, with flags
* **Export, import, and reset** your whole configuration
* **Translate everything** through WPML or Polylang

= Conditional fields =

A field can appear only when it is relevant. In Lite, conditions can be built on:

* the billing or shipping **country**
* the **value of another field**, evaluated as the customer types

The separately installed Pro add-on adds conditions based on cart products, product categories, cart total, payment method, and shipping method.

= Checkout Recovery =

Lite records checkout activity so a merchant can see where customers stop:

* **See what the customer actually typed** — name, email, phone, country, how far they got, and what was in their cart
* **Potential abandoned checkout value** based on the captured cart total, clearly separated from guaranteed lost or recoverable revenue
* **Captures supported checkout activity** on Classic Checkout and supported Checkout block flows

The separately installed Pro add-on sends a four-email sequence, restores saved carts through protected links, and attributes recovered product value from final WooCommerce order lines.

= Revenue Analytics =

When checkout recording is enabled, the plugin also measures a privacy-minimized checkout lifecycle without copying names, contact details, addresses, cart contents, IP addresses, or user-agent strings into analytics:

* **Lite overview filters** for 7 or 30 days and currency
* **Checkout attempts, placed orders, placement rate, abandoned attempts, and potential abandoned checkout value**
* **Pro analytics** adds 90-day and custom ranges, checkout and outcome filters, comparisons, recognized revenue, recovery attribution, and CSV breakdowns
* **No external analytics service** and no invented historical backfill; reporting starts with measured activity

The separately installed **Pro add-on** adds Multi-Step Checkout, Google Places Address Autocomplete, Date, Time, DateTime, File Upload, Fee, and Repeater fields; cart/payment/shipping conditions; a four-email recovery sequence; secure cart resume; and advanced revenue analytics. Lite does not ship or load those implementations, and its settings show one contextual upgrade prompt instead of locked controls.

= Built for privacy =

Recovery is off until you turn it on, and how it behaves is yours to decide:

* an opt-in **consent box** on the checkout, unticked, with your wording
* every stored record carries **why it exists** and when consent was given
* a **retention period**, after which records are deleted automatically
* full support for WordPress's **personal data export and erasure** tools for recovery/contact records
* recovery records and analytics stay on your server; Pro's optional Google Places feature sends address-search requests from the shopper's browser to Google when enabled
* analytics attempts contain a random-token hash and operational/revenue facts only, and use the same retention period as recovery records

= External service: Google Maps Platform =

Address Autocomplete requires the separately installed Pro add-on and remains disabled until a merchant enables it and supplies a Google API key. When enabled on checkout, the shopper's browser loads the Google Maps JavaScript API from `maps.googleapis.com`. Address searches are sent to Google Places; using Locate Me also sends the shopper's browser-provided coordinates to Google's geocoder. Google necessarily receives network information such as the shopper's IP address with those requests. Recovery does not send its stored customer records to Google.

This service is provided by Google, not by this plugin. Its use is subject to the [Google Maps Platform Terms of Service](https://cloud.google.com/maps-platform/terms) and [Google Privacy Policy](https://policies.google.com/privacy). The merchant is responsible for deciding whether and how to disclose or obtain consent for that service in the jurisdictions where the store operates.

= What it does to your site's speed =

* the checkout scripts are **plain JavaScript**, not a frontend framework
* the Checkout block recovery path does not load jQuery; classic-checkout modules use WordPress's bundled jQuery where their WooCommerce integrations require it
* nothing is loaded on pages that do not need it
* recovery writes are throttled and never block the customer. A failure there can never stop an order being placed
* cart contents and totals are always read on the server. What the browser reports is never trusted with anything that touches money

= Where to find it =

After activating, look for **Checkout Lift** in the admin menu:

* **Checkout Lift → Checkout Fields** — the field editor and import/export/reset tools
* **Checkout Lift → Recovery** — captured checkouts and recovery results
* **Checkout Lift → Revenue Analytics** — Lite checkout funnel and potential abandoned value; Pro adds recognized revenue, recovery attribution, comparisons, and detailed filters
* **Checkout Lift → Settings** — module switches, checkout experience, recovery, and privacy settings

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen
2. Activate it through the **Plugins** screen
3. Go to **Checkout Lift → Checkout Fields**
4. Drag to reorder, use the toggles to show, hide, or require a field, and **Add field** for your own
5. Save, then open your checkout and confirm it reads the way you want

To turn on Checkout Recovery, go to **Checkout Lift → Settings → Recovery** and enable tracking. Read the privacy settings on that screen before you do: they decide whether a consent box appears and how long records are kept.

== Frequently Asked Questions ==

= Does this work with the new Checkout block? =

Support is partial. This release does not declare full WooCommerce Checkout Blocks compatibility. Use Classic Checkout when every listed editor and conditional-field capability is required.

**Available on supported block flows:** checkout activity capture; hiding, requiring, renaming, reordering, and resizing supported built-in address fields; custom Text and Select fields; and country-based conditions.

**Does not work on the block checkout:** multi-step checkout and field-to-field conditions. Number, Email and URL fields appear as plain text. The Pro-only Date, Time, File upload, Fee and Repeater field types and some Pro condition rules, such as product-category conditions, are also classic-checkout only.

Anything in that second list is **marked in the editor** when your store uses the Checkout block, so you can see it while you are configuring rather than afterwards. The classic checkout is unaffected and keeps all of it.

= Will it work with my theme? =

Yes. The plugin works through WooCommerce's own hooks and filters rather than replacing templates, so anything that renders a normal WooCommerce checkout will render this.

= Where does the data go? =

Recovery records and privacy-minimized revenue analytics stay in your own database. Lite does not send recovery emails. When the separately installed Pro add-on is active, recovery emails are sent by the merchant's WordPress installation. Analytics does not copy contact details, addresses, cart contents, IP addresses, or user-agent strings. If Pro Address Autocomplete is enabled, the shopper's browser loads Google Maps Platform and sends address or location requests to Google under Google's terms and privacy policy. The plugin does not include its own external analytics service.

= How long is a captured checkout kept? =

As long as you choose, and no longer. The default is 60 days. Scheduled bounded sweeps delete recovery and analytics facts past the limit. Contact-linked recovery records are also covered by WordPress's personal data export and erasure tools; anonymous analytics rows cannot be matched back to a person.

= Does the customer have to agree before anything is stored? =

That is your decision for recovery/contact data, and the plugin makes it explicit. With the consent box on, no name, email, phone, address, or recoverable cart record is stored until the customer ticks it, and the box is never pre-ticked. When checkout recording is enabled, a separate anonymous analytics row may still record lifecycle and server-confirmed order facts without contact details. The merchant remains responsible for deciding the appropriate legal basis and disclosure for measurement in their jurisdiction.

= Does Lite send reminder emails? =

No. Lite records eligible unfinished checkouts and shows their potential cart value. Automated recovery emails and protected cart-resume links require the separately installed Pro add-on.

= Can a customer's browser fake an order total? =

No. Every capture reads the cart, its contents, and its total on the server. Recovered revenue is calculated later from the final WooCommerce order lines and only for quantities added by the recovery link. Anything the browser claims about money is discarded.

= What field types can I add in Lite? =

Text, Number, Email, URL, Select, and Textarea. The separately installed Pro add-on supplies Date, Time, Date and time, File upload, Fee, and Repeater.

= Are uploaded files safe? =

File Upload fields require the separately installed Pro add-on. Pro checks uploads against the file types and size limit the merchant allows, and validates their actual contents rather than trusting the file name.

= Is it compatible with WPML or Polylang? =

Yes. Labels, placeholders, and select options are registered for translation when you save. Translate them in WPML String Translation or in Polylang's Strings screen.

= Can I move a configuration between sites? =

Yes. **Checkout Fields → Tools** exports your whole configuration to a file and imports it again, so a staging site can be set up once and copied over.

== Screenshots ==

1. The field editor, with fields grouped by section and reordered by dragging
2. Adding a custom field and choosing its type
3. Building a condition, so a field appears only when it is relevant
4. The Recovery screen, showing captured checkout activity and potential abandoned value
5. Recovery settings, including recording, consent, abandonment timing, and retention
6. The checkout, with field widths laid out on a responsive grid
7. Lite Revenue Analytics, with date and currency filters, checkout funnel, and potential abandoned checkout value

== Changelog ==

= 1.0.3 =
* New: Unfinished checkouts are marked abandoned after the idle time you set, so abandoned totals are counted
* New: Recent checkout activity lists the products that were in each cart
* New: Arabic translation included with the plugin
* Fix: Country flags now show in the phone field
* Fix: Phone numbers are saved with their country code
* Fix: Saving fields no longer shows "The shipping setting could not be saved" when the shipping section is removed
* Tweak: Shipping fields are hidden in the editor while the shipping section is removed
* Tweak: Settings is now the last item in the Checkout Lift menu
* Tweak: Condition operators, module names, field types and checkout steps in the admin can now be translated

= 1.0.2 =
* Fix: The phone field now stays where you place it in the checkout fields editor instead of moving to the end of the form
* Fix: A saved phone number without a country code now shows the customer's country flag and code instead of a wrong country

= 1.0.1 =
* Fix: A saved phone number is no longer re-read as a number from another country when the checkout page reloads
* Fix: A condition rule saved without an operator is now treated as "is" everywhere, so a field hidden in the browser can no longer block checkout as a required field
* Fix: Changing a field's width no longer removes WooCommerce classes, so checkout totals refresh again when the address changes
* Fix: Potential abandoned checkout value now records the cart total of unfinished checkouts instead of zero
* Fix: A checkout that ended in an order is no longer reopened by later activity in the same session
* Fix: When the consent checkbox is shown and the customer unticks it, the saved unfinished checkout and its queued messages are deleted

= 1.0.0 =
* Initial release
* Drag and drop reordering, show, hide, and require
* Custom labels, placeholders, and field widths
* Custom fields: Text, Number, Email, URL, Select, and Textarea
* Country-based conditional fields
* Field dependencies based on another checkout field's value
* International phone field with per-country validation
* Option to remove the shipping section
* Custom field values shown on the order screen
* Export and import of checkout settings
* WPML and Polylang support
* Consent-aware unfinished-checkout tracking and potential abandoned cart value
* Privacy-minimized checkout funnel analytics with date and currency filters
* Atomic per-session, per-address, and site-wide capture limits with bounded retention
* Complete removal of plugin-created options when data deletion is enabled on uninstall

== Upgrade Notice ==

= 1.0.3 =
Marks abandoned checkouts on the free version, shows cart products, fixes phone flags and saved phone numbers, and adds an Arabic translation.

= 1.0.2 =
Fixes the phone field moving to the end of the checkout form and saved phone numbers showing the wrong country. Recommended for all stores.

= 1.0.1 =
Fixes checkout being blocked by a hidden required field, totals not refreshing after a width change, and saved phone numbers changing country. Recommended for all stores.

= 1.0.0 =
Initial release of the checkout field editor, unfinished-checkout tracking, and privacy-minimized funnel analytics.

== Additional Info ==

**Support and documentation:** [Design Tich](https://designtich.com/)

**Requirements:** WordPress 7.0 or newer, WooCommerce 10.0 or newer, PHP 8.0 or newer.

**Development source:** Editable JavaScript sources and build tooling are maintained in the public [GitHub repository](https://github.com/mahmoudgammal/Checkout-Lift-for-WooCommerce). From the main branch, run `npm install` and then `npm run build` to reproduce the dashboard bundle.
