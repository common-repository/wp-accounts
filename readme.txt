=== WP Accounts ===
Contributors: domainsupport
Donate link: https://webd.uk/product/support-us/
Tags: accounting, bookkeeping, receipts, invoices, payments
Requires at least: 4.6
Tested up to: 6.7
Requires PHP: 5.6
Stable tag: 1.8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage your Clients, Invoices, Receipts and Payments. Send Invoices and Receipts to clients via email.

== Description ==

= Manage your accounts in Wordpress =

If you are a UK company that is not VAT registered, you may want to use our plugin ...

This plugin does the following:

*   Manages your clients and their contact details
*   Allows you to raise invoices and receipts
*   View client statements
*   Manage your payments / bookkeeping
*   Send invoices and receipts to clients via email
*	Style email invoices / receipts
*	Download HTML statements
*	Download CSV reports
*	Assign a client to a Wordpress user account so they can view their account statements
*   Integrate with WooCommerce to allow clients to pay by Paypal, Stripe etc

When installed you will be able to manage your company accounts from within your Wordpress website.

We have built this plugin for a UK based company that is not VAT registered. If you'd like us to develop it further to fit your business, contact us!

== Installation ==

Start managing your accounts from within Wordpress:

1) Install WP Accounts automatically or by uploading the ZIP file.
2) Activate the plugin through the "Plugins" menu in WordPress.
3) Once activated, visit "WP Accounts" in the admin menu.
4) Start by entering your Company Details
5) Add a new client
6) Start raising invoices!

== Frequently Asked Questions ==

= This plugin doesn't work as I'd like? =

This plugin has been successfully used for several different Sole Trader, LLP and Ltd companies in the UK since 2012 and would be keen to know how you'd like it changed to suit you and your business!

== Changelog ==

= 1.8.5 =
* Fixed bug with Payment CSV export to export based on invoice date rather than paid date, added ability to mark expense types as not expenses, updated invoice search facility to show financial years

= 1.8.4 =
* Improved "Cancelled" invoices to show they are cancelled when sent

= 1.8.3 =
* Added rudemental searching to view stats for invoices containing specific words

= 1.8.2 =
* Invoices are now marked as paid when paid via WooCommerce

= 1.8.1 =
* Improved sending receipts with WooCommerce orders

= 1.8.0 =
* Fixed a bug with WooCommerce session cookies

= 1.7.9 =
* Minor $wpdb->prepare() bug fixed

= 1.7.8 =
* Auto complete WooCommerce paid invoices, mark as paid and send receipt

= 1.7.7 =
* Limit WooCommerce invoices to one per order

= 1.7.6 =
* Fixed dashboard widget bug, added "Bank Name" to settings, integrated WooCommerce a payment method

= 1.7.5 =
* Fixed bug where dividends raised in previous tax year were being shown in the current tax year

= 1.7.4 =
* Added totals to the expense types in settings

= 1.7.3 =
* Added the ability to add, edit and delete expense types

= 1.7.2 =
* Fixed bug with total on cancelled invoice view

= 1.7.1 =
* General housekeeping

= 1.7.0 =
* Updated the Dashboard wiget to show Dividends and Capital Expenditure

= 1.6.9 =
* Added a strikethrough style for cancelled invoices

= 1.6.8 =
* Fixed bug when current date comes before the accounting year end date

= 1.6.7 =
* Resolved 2 minor PHP notices

= 1.6.6 =
* Added a Dashboard Widget
* Added ability to view any supplier from Manage Payments page
* Added ability to move payments to another supplier
* Fixed bug to show cancelled invoices when savinng a cancelled invoice

= 1.6.5 =
* Fixed a bug with Paypal payment links for payments over £1,000

= 1.6.4 =
* Added filter by date range to payments
* Added cancelled invoice total
* Fixed typo on client account shortcode helper

= 1.6.3 =
* Added filter by date range / invoice type to invoices

= 1.6.2 =
* Added ability to view payments by expense type and filter by date range
* Added "Total" row to payment tables
* Bug fixes

= 1.6.1 =
* Added ability to view payments by supplier and filter by date range

= 1.6.0 =
* Preparing for Wordpress v6.0

= 1.5.9 =
* Adding and updating invoices and payments shows ID on success

= 1.5.8 =
* Fixed bug with formating of international phone numbers

= 1.5.7 =
* Removed all PHP short tags

= 1.5.6 =
* Fixed a bug that broke the CSV download links

= 1.5.5 =
* Added a setting field to add a link to the email advert image

= 1.5.4 =
* Corrected price formatting on invoice and receipt emails

= 1.5.3 =
* Merged premium plugin into this plugin

= 1.5.2 =
* Bug fix

= 1.5.1 =
* General housekeeping

= 1.5.0 =
* Added Company Name to BACS details

= 1.4.9 =
* Bug fixes

= 1.4.8 =
* Bug fixes including attached inline images not showing in emails

= 1.4.7 =
* Added "Country" field to Client table

= 1.4.6 =
* Moved all premium options to a new premium plugin

= 1.4.5 =
* Bug fixes

= 1.4.4 =
* Bug fixes

= 1.4.3 =
* Added "total" fields to client statements

= 1.4.2 =
* Bug fixes

= 1.4.1 =
* Bug fix

= 1.4 =
* Added option to accept cheques
* Bug fix

= 1.3.9 =
* Added Statement link to top of Edit Client page
* Bug fixes

= 1.3.8 =
* Added Statement link to Invoice view
* Shows Payment ID when editing Payment
* Cleans and Formats UK telephone numbers
* Bug fixes

= 1.3.7 =
* Settings link added to Plugin page
* Added the ability for upgraded users to insert an advert into invoices
* Added client details to Statement view
* Added Edit Client link to Statement view
* Added client telephone details to Invoice view
* Mileage Export removed if Mileage not activated

= 1.3.6 =
* Bug fixes

= 1.3.5 =
* Bug fixes

= 1.3.4 =
* Added the ability for upgraded users to add their company logo to invoices

= 1.3.3 =
* Bug fixes
* Ability to export mileage CSV for upgraded users

= 1.3.2 =
* Bug fixes
* Added “Send” and “Send Copy” when viewing an invoice or receipt

= 1.3.1 =
* Added bulk copying of monthly and yearly recurring invoices / receipts

= 1.3 =
* Bug fixes
* Upgraded users can add their Google My Business Place ID to allow new invoices and receipts to invite customers to leave a review
* Existing suppliers ordered without “The”

= 1.2 =
* Bug fixes
* Added “Copy Payment” function
* Payments ordered by “Date Paid” not “Invoice Date”
* All mandatory fields shown with asterisk
* Clients listed and ordered without “The”
* “Copy Invoice” link on “Edit Invoice” page
* Invoices linked on “Statement” page
* HTML Statement download for upgraded plugin
* Receipts shown when saving a receipt
* “Date Paid” shown on “Manage Receipts” page

= 1.1.1 =
* Change email sanitisation so client can have multiple emails separated by commas.

= 1.1.0 =
* Added jQuery datepicker to date fields.
* Added existing Supplier dropdown to Payment editing.

= 1.0.0 =
* First public release of the plugin.

== Upgrade Notice ==

= 1.8.5 =
* Fixed bug with Payment CSV export to export based on invoice date rather than paid date, added ability to mark expense types as not expenses, updated invoice search facility to show financial years
