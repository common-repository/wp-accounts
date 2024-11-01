<?php
/*
 * Plugin Name: WP Accounts
 * Version: 1.8.5
 * Plugin URI: https://webd.uk/support/
 * Description: Manage your Clients, Invoices, Receipts and Payments. Send Invoices and Receipts to clients via email.
 * Author: Webd Ltd
 * Author URI: https://webd.uk
 * Text Domain: wp-accounts
 */



if (!defined('ABSPATH')) {
    exit('This isn\'t the page you\'re looking for. Move along, move along.');
}



if (!class_exists('wpaccounts_class')) {

	class wpaccounts_class {

        public static $version = '1.8.5';

        private $cart_message = '';

		function __construct() {

            if (get_option('wp_accounts_purchased')) { delete_option('wp_accounts_purchased'); }

            register_activation_hook(__FILE__, array($this, 'wpa_setup_database'));
			add_action('admin_init', array($this, 'wpa_register_script'));
			add_action('wp_before_admin_bar_render', array($this, 'wpa_admin_bar_render'));
			add_action('admin_menu', array($this, 'wpa_menu'));
            add_action('wp_ajax_wpa_get_attachment_url', array($this, 'wpa_ajax_get_attachment_url'));

            if (is_admin()) {

                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'wpa_add_plugin_action_links'));
                add_action('admin_notices', 'wpaCommon::admin_notices');
                add_action('wp_ajax_dismiss_wp_accounts_notice_handler', 'wpaCommon::ajax_notice_handler');

                add_action('show_user_profile', array($this,'wpa_user_contact_details'));
                add_action('edit_user_profile', array($this,'wpa_user_contact_details'));
                add_action('personal_options_update', array($this,'wpa_save_user_contact_details'));
                add_action('edit_user_profile_update', array($this,'wpa_save_user_contact_details'));

                add_action('wp_dashboard_setup', array($this, 'wp_dashboard_setup'));

            } else {

                add_shortcode('wpa-statement', array($this, 'wpa_statement_shortcode'));

                add_action('woocommerce_init', array($this, 'woocommerce_init'));
                add_action('wp_head', array($this, 'wp_head'));
                add_action('woocommerce_before_cart', array($this, 'woocommerce_before_cart'));
                add_filter('wc_empty_cart_message', array($this, 'wc_empty_cart_message'));
                add_action('woocommerce_before_calculate_totals', 'wpaccounts_class::woocommerce_before_calculate_totals');

            }

                add_filter('woocommerce_email_enabled_customer_on_hold_order', array($this, 'woocommerce_email_enabled_customer_completed_order'), 10, 2);
                add_filter('woocommerce_email_enabled_customer_processing_order', array($this, 'woocommerce_email_enabled_customer_completed_order'), 10, 2);
                add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'woocommerce_email_enabled_customer_completed_order'), 10, 2);
                add_filter('woocommerce_email_enabled_customer_invoice', array($this, 'woocommerce_email_enabled_customer_completed_order'), 10, 2);
                add_filter('woocommerce_email_enabled_customer_invoice_paid', array($this, 'woocommerce_email_enabled_customer_completed_order'), 10, 2);

		}

		function wpa_add_plugin_action_links($links) {

			$settings_links = wpaCommon::plugin_action_links(add_query_arg('page', 'manage-settings', admin_url('admin.php?page=manage-settings')));

			return array_merge($settings_links, $links);

		}

        function wpa_register_script() {

            wp_register_script('wpa-settings-pickers', plugins_url('js/settings-pickers.js', __FILE__), array('wp-color-picker'), false, true );
            wp_register_style('jquery-ui', 'https://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css');
            wp_register_script('wpa-date-pickers', plugins_url('js/date-pickers.js', __FILE__), array('jquery-ui-datepicker'), false, true );

        }

        function wpa_setup_database() {

            global $wpdb;

            $wpa_tables = array();

            $wpa_tables["{$wpdb->prefix}accounts_invoice_status"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_invoice_status (
ID int(11) NOT NULL AUTO_INCREMENT,
status varchar(9) NOT NULL,
PRIMARY KEY  (ID)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;",
                'insert_row' => 'status',
                'insert' => array(
                    1 => 'Unpaid',
                    2 => 'Paid',
                    3 => 'Cancelled'
                )
            );

            $wpa_tables["{$wpdb->prefix}accounts_payment_methods"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_payment_methods (
ID int(11) NOT NULL AUTO_INCREMENT,
payment_method varchar(12) NOT NULL,
PRIMARY KEY  (ID)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;",
                'insert_row' => 'payment_method',
                'insert' => array(
                    1 => 'Paypal',
                    2 => 'BACS',
                    3 => 'Cheque',
                    4 => 'Cash',
                    5 => 'Credit Card',
                    6 => 'Debit Card',
                    7 => 'Direct Debit'
                )
            );

            $wpa_tables["{$wpdb->prefix}accounts_clients"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_clients (
ID int(11) NOT NULL AUTO_INCREMENT,
company varchar(255) DEFAULT NULL,
address1 varchar(255) NOT NULL,
address2 varchar(255) DEFAULT NULL,
address3 varchar(255) DEFAULT NULL,
town varchar(255) NOT NULL,
county varchar(255) DEFAULT NULL,
postcode varchar(13) NOT NULL,
country varchar(255) DEFAULT 'GB',
contact varchar(255) NOT NULL,
telephone varchar(20) DEFAULT NULL,
mobile varchar(20) DEFAULT NULL,
email varchar(255) DEFAULT NULL,
notes text DEFAULT NULL,
PRIMARY KEY  (ID),
KEY company (company)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;"
            );

            $wpa_tables["{$wpdb->prefix}accounts_invoices"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_invoices (
ID int(11) NOT NULL AUTO_INCREMENT,
client int(11) NOT NULL,
invoice_date date NOT NULL,
monthly tinyint(1) NOT NULL DEFAULT '0',
yearly tinyint(1) NOT NULL DEFAULT '0',
item1 varchar(255) NOT NULL,
price1 decimal(10, 2) NOT NULL,
item2 varchar(255) DEFAULT NULL,
price2 decimal(10, 2) DEFAULT NULL,
item3 varchar(255) DEFAULT NULL,
price3 decimal(10, 2) DEFAULT NULL,
item4 varchar(255) DEFAULT NULL,
price4 decimal(10, 2) DEFAULT NULL,
item5 varchar(255) DEFAULT NULL,
price5 decimal(10, 2) DEFAULT NULL,
invoice_status int(11) NOT NULL DEFAULT '1',
date_paid date DEFAULT NULL,
payment_method int(11) DEFAULT NULL,
mileage int(11) DEFAULT '0',
notes text DEFAULT NULL,
PRIMARY KEY  (ID),
KEY client (client),
KEY payment_method (payment_method),
KEY invoice_status (invoice_status)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;"
            );

            $wpa_tables["{$wpdb->prefix}accounts_payments"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_payments (
ID int(11) NOT NULL AUTO_INCREMENT,
supplier varchar(255) NOT NULL,
invoice_date date NOT NULL,
amount decimal(10, 2) NOT NULL,
reference varchar(255) DEFAULT NULL,
expense_type int(11) NOT NULL,
date_paid date NOT NULL,
payment_method int(11) NOT NULL,
mileage int(11) DEFAULT '0',
notes text DEFAULT NULL,
PRIMARY KEY  (ID)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;"
            );

            $wpa_tables["{$wpdb->prefix}accounts_expense_types"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_expense_types (
ID int(11) NOT NULL AUTO_INCREMENT,
expense_type varchar(255) NOT NULL,
expense tinyint(1) NOT NULL DEFAULT '1',
PRIMARY KEY  (ID)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;",
                'insert_row' => 'expense_type',
                'insert' => array(
                    1 => 'Capital',
                    2 => 'Accounting',
                    3 => 'Director\'s Loan',
                    4 => 'Telephone',
                    5 => 'Web Servers and Domain Names',
                    6 => 'Sub-Contract Work',
                    7 => 'Supplies',
                    8 => 'Advertising and Marketing',
                    9 => 'Sundries',
                    10 => 'Printing, Postage and Stationery',
                    11 => 'Bounced Cheques',
                    12 => 'Wages',
                    13 => 'Dividends',
                    14 => 'Subscriptions',
                    15 => 'Repairs and Renewals',
                    16 => 'Business Trip Expenses',
                    17 => 'HMRC',
                    18 => 'Entertaining',
                    19 => 'Insurance',
                    20 => 'Legal and Professional',
                    21 => 'Vehicle Running',
                    22 => 'Purchases',
                    23 => 'Bank / eBay / Paypal Charges'
                )
            );

            $wpa_tables["{$wpdb->prefix}accounts_countries"] = array(
                'create' => "CREATE TABLE {$wpdb->prefix}accounts_countries (
ID int(11) NOT NULL AUTO_INCREMENT,
country_code varchar(2) NOT NULL,
country varchar(255) NOT NULL,
PRIMARY KEY  (ID)
) {$wpdb->get_charset_collate()} AUTO_INCREMENT=1;"
            );

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            foreach ($wpa_tables as $wpa_table => $table_args) {

                dbDelta($table_args['create']);

                if (
                    isset($table_args['insert_row']) &&
                    isset($table_args['insert']) &&
                    // Do not override existing expense types
                    !(
                        "{$wpdb->prefix}accounts_expense_types" === $wpa_table &&
                        $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}accounts_expense_types")
                    )
                ) {

                    $wpdb->query("TRUNCATE TABLE $wpa_table");
                    $wpdb->query("ALTER TABLE $wpa_table AUTO_INCREMENT=0");

                    foreach ($table_args['insert'] as $key => $value) {

                        $wpdb->insert($wpa_table, array('ID' => $key, $table_args['insert_row'] => $value));

                        if (
                            "{$wpdb->prefix}accounts_expense_types" === $wpa_table &&
                            in_array($key, array(3, 13))
                        ) {

                            $wpdb->update(
                                $wpa_table,
                                array('expense' => 0),
                                array('ID' => $key),
                                array('%d'),
	                            array('%d')
                            );

                        }

                    }

                }

            }

            $countries_request = wp_remote_get('http://country.io/names.json');

            if(!is_wp_error($countries_request)) {

                $countries = json_decode(wp_remote_retrieve_body($countries_request), true);

                if ($countries) {

                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}accounts_countries");
                    $wpdb->query("ALTER TABLE {$wpdb->prefix}accounts_countries AUTO_INCREMENT=0");

                    foreach ($countries as $key => $value) {

                        $wpdb->insert("{$wpdb->prefix}accounts_countries", array('country_code' => sanitize_text_field($key), 'country' => sanitize_text_field($value)));

                    }

                }

            }

        	$options = get_option('wp_accounts_options');

            if (!isset($options['email_footer'])) {

                $options['email_footer'] = "<strong>WP Accounts</strong>
We use Wordpress plugin <strong>WP Accounts</strong> to send invoices and receipts. This free Wordpress plugin can be <a href=\"https://wordpress.org/plugins/wp-accounts/\">downloaded from the Wordpress Plugins repository</a>.";
                update_option('wp_accounts_options', $options);

            }

        }

        function wpa_admin_bar_render() {

            global $wp_admin_bar;

            $wp_admin_bar->add_menu(array(
                'parent' => 'new-content',
                'id' => 'new_client',
                'title' => 'Client',
                'href' => add_query_arg(array(
                    'page' => 'manage-clients',
                    'edit' => 'true'
                ), admin_url('admin.php'))
            ));
            $wp_admin_bar->add_menu(array(
                'parent' => 'new-content',
                'id' => 'new_invoice',
                'title' => 'Invoice',
                'href' => add_query_arg(array(
                    'page' => 'manage-invoices',
                    'edit' => 'true'
                ), admin_url('admin.php'))
            ));
            $wp_admin_bar->add_menu(array(
                'parent' => 'new-content',
                'id' => 'new_payment',
                'title' => 'Payment',
                'href' => add_query_arg(array(
                    'page' => 'manage-payments',
                    'edit' => 'true'
                ), admin_url('admin.php'))
            ));

        }

        function wpa_menu() {

            if (is_admin()) {

                add_menu_page('WP Accounts Settings', 'WP Accounts', 'manage_options', 'manage-invoices', array($this, 'wpa_manage_invoices_options'), plugins_url('/images/wp-accounts.png', __FILE__));
                $page = add_submenu_page( 'manage-invoices', 'Manage Invoices', 'Manage Invoices', 'manage_options', 'manage-invoices', array($this, 'wpa_manage_invoices_options'));
        		add_action('admin_print_scripts-' . $page, array($this, 'wpa_enqueue_date_picker'));
        	    add_submenu_page( 'manage-invoices', 'Manage Clients', 'Manage Clients', 'manage_options', 'manage-clients', array($this, 'wpa_manage_clients_options'));
        	    add_submenu_page( null, 'Client Statement', 'Client Statement', 'manage_options', 'client-statement', array($this, 'wpa_client_statement_options'));
                $page = add_submenu_page( 'manage-invoices', 'Manage Payments', 'Manage Payments', 'manage_options', 'manage-payments', array($this, 'wpa_manage_payments_options'));
        		add_action('admin_print_scripts-' . $page, array($this, 'wpa_enqueue_date_picker'));
        	    add_submenu_page( 'manage-invoices', 'Export CSV', 'Export CSV', 'manage_options', 'export-csv', array($this, 'wpa_export_csv_options'));
                $page = add_submenu_page( 'manage-invoices', 'WP Accounts Settings', 'Settings', 'manage_options', 'manage-settings', array($this,'wpa_accounts_settings_page'));
        		add_action('admin_print_scripts-' . $page, array($this, 'wpa_enqueue_settings_script'));
        	    add_action('admin_init', array($this, 'wpa_register_accounts_settings'));

            }

        }

        function wpa_register_accounts_settings() {

        	register_setting( 'wp_accounts_options', 'wp_accounts_options', array($this, 'wpa_options_validate'));
        	add_settings_section('wp_accounts_company_details', 'Company Details', array($this, 'wpa_company_text'), 'wp_accounts');
        	add_settings_field('company_name', 'Company Name', array($this, 'wpa_company_name_string'), 'wp_accounts', 'wp_accounts_company_details');
        	add_settings_field('company_logo', 'Company Logo', array($this, 'wpa_company_logo_id'), 'wp_accounts', 'wp_accounts_company_details');
        	add_settings_field('company_address', 'Company Address', array($this, 'wpa_company_address_string'), 'wp_accounts', 'wp_accounts_company_details');
        	add_settings_field('company_telephone', 'Company Telephone', array($this, 'wpa_company_telephone_string'), 'wp_accounts', 'wp_accounts_company_details');
        	add_settings_field('accounting_period_start', 'Accounting Period Start', array($this, 'wpa_accounting_period_start_string'), 'wp_accounts', 'wp_accounts_company_details');
        	add_settings_field('mileage', 'Enable Mileage', array($this, 'wpa_mileage_string'), 'wp_accounts', 'wp_accounts_company_details');
        	add_settings_section('wp_accounts_account', 'Bank Account', array($this, 'wpa_account_text'), 'wp_accounts');
        	add_settings_field('bank_name', 'Bank Name', array($this, 'wpa_bank_name_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_field('sort_code', 'Sort Code', array($this, 'wpa_sort_code_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_field('account_number', 'Account Number', array($this, 'wpa_account_number_string'), 'wp_accounts', 'wp_accounts_account');
	        add_settings_field('account_name', 'Account Name', array($this, 'wpa_account_name_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_field('cheques', 'Enable Cheques', array($this, 'wpa_cheques_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_field('woocommerce', 'Integrate with WooCommerce', array($this, 'wpa_woocommerce_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_field('paypal', 'Enable Paypal ('.get_bloginfo('admin_email').')', array($this, 'wpa_paypal_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_field('recurrent', 'Enable Recurrent Invoices', array($this, 'wpa_recurrent_string'), 'wp_accounts', 'wp_accounts_account');
        	add_settings_section('wp_accounts_style', 'Email Style', array($this, 'wpa_style_text'), 'wp_accounts');
        	add_settings_field('email_color', 'Email Color', array($this, 'wpa_email_color_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('link_color', 'Link Color', array($this, 'wpa_link_color_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('hover_color', 'Hover Color', array($this, 'wpa_hover_color_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('email_font', 'Email Font', array($this, 'wpa_email_font_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('email_footer', 'Email Footer', array($this, 'wpa_email_footer_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('company_google_place_id', 'Company Google Place ID', array($this, 'wpa_company_google_place_id_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('email_advert', 'Email Advert', array($this, 'wpa_email_advert_id'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_field('advert_url', 'Advert URL', array($this, 'wpa_advert_url_string'), 'wp_accounts', 'wp_accounts_style');
        	add_settings_section('wp_accounts_expense_types', 'Expense Types', array($this, 'wpa_expense_types_text'), 'wp_accounts');

        }

        function wpa_company_text() {

        	echo '<p>Enter company details below.</p>';

        }

        function wpa_account_text() {

        	echo '<p>Enter bank account details below for BACS payments.</p>';

        }

        function wpa_style_text() {

        	echo '<p>Use these settings to style the invoice emails.</p>';

        }

        function wpa_expense_types_text() {

            echo '<p>' . __('Add, edit or delete your expense types.') . '</p>
';

            global $wpdb;

            if (!$wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$wpdb->prefix}accounts_expense_types' AND column_name = 'expense';")) {

                $this->wpa_setup_database();

            }

            $expense_types = $wpdb->get_results("

SELECT
    {$wpdb->prefix}accounts_expense_types.ID,
    {$wpdb->prefix}accounts_expense_types.expense_type,
    {$wpdb->prefix}accounts_expense_types.expense,
    (SELECT COUNT(*) 
        FROM {$wpdb->prefix}accounts_payments 
        WHERE {$wpdb->prefix}accounts_payments.expense_type = {$wpdb->prefix}accounts_expense_types.ID
    ) AS payments,
    (SELECT SUM({$wpdb->prefix}accounts_payments.amount) 
        FROM {$wpdb->prefix}accounts_payments 
        WHERE {$wpdb->prefix}accounts_payments.expense_type = {$wpdb->prefix}accounts_expense_types.ID
    ) AS total_payments
FROM {$wpdb->prefix}accounts_expense_types
ORDER BY {$wpdb->prefix}accounts_expense_types.expense_type ASC;

            ");

            if (is_array($expense_types) && $expense_types) {

                echo '<p>
';

                foreach ($expense_types as $expense_type) {

                    echo '<input type="text" name="wp_accounts_options[expense_types][' . absint($expense_type->ID) . '][expense_type]" value="' . esc_attr($expense_type->expense_type) . '" />
 Expense? <input type="checkbox" name="wp_accounts_options[expense_types][' . absint($expense_type->ID) . '][expense]" value="1"' . checked(1, $expense_type->expense, false) . ' /> ' . 
(absint($expense_type->payments) ? '(' . absint($expense_type->payments) . ' ' . _n('payment', 'payments', absint($expense_type->payments), 'wp-accounts') . ' - &pound;' . number_format($expense_type->total_payments, 2) . ')' : 'Delete: <input type="checkbox" name="wp_accounts_options[expense_types_delete][' . absint($expense_type->ID) . ']" value="delete" />') . '
<br />
';

                }

                echo '</p>
';

            }

            echo '<h3>Add New Expense Type</h3>
<p><input type="text" name="wp_accounts_options[expense_type_new]" /></p>
';

        }

        function wpa_company_name_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='company_name' name='wp_accounts_options[company_name]' size='40' type='text' value='" . ((isset($options['company_name'])) ? esc_html($options['company_name']) : '') . "' />";

        }

        function wpa_company_logo_id() {

        	$options = get_option('wp_accounts_options');

                if (isset($options['company_logo']) && absint($options['company_logo']) > 0) {

                    echo '<img id="company_logo_preview_image" src="' . wp_get_attachment_url($options['company_logo']) . '" />';

                } else {

                    echo '<img id="company_logo_preview_image" />';

                }

?>
<br />
<input id="company_logo" name="wp_accounts_options[company_logo]" type="hidden" value="<?php if (isset($options['company_logo'])) { echo esc_html($options['company_logo']); } ?>" />
<input type="button" class="button-primary" value="<?php esc_attr_e('Select Logo', 'wp-accounts' ); ?>" id="company_logo_media_manager" data-button="<?php echo wp_create_nonce('wpaccounts'); ?>" />
<input type="button" value="<?php esc_attr_e('Clear', 'wp-accounts' ); ?>" onclick="jQuery('input#company_logo').removeAttr('value'); jQuery('img#company_logo_preview_image').removeAttr('src').replaceWith(jQuery('img#company_logo_preview_image').clone());" />
<?php

        }

        function wpa_company_address_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='company_address' name='wp_accounts_options[company_address]' size='40' type='text' value='" . ((isset($options['company_address'])) ? esc_html($options['company_address']) : '') . "' />";

        }

        function wpa_company_telephone_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='company_telephone' name='wp_accounts_options[company_telephone]' size='40' type='text' value='" . ((isset($options['company_telephone'])) ? ($this->wpa_format_telephone($this->wpa_clean_telephone($options['company_telephone']))) : '') . "' />";

        }

        function wpa_accounting_period_start_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='accounting_period_start' name='wp_accounts_options[accounting_period_start]' size='40' type='text' value='" . ((isset($options['accounting_period_start'])) ? ($options['accounting_period_start']) : '') . "' /> MM-DD";

        }

        function wpa_mileage_string() {

        	$options = get_option('wp_accounts_options');

?>
<input id="mileage" name="wp_accounts_options[mileage]" type="checkbox" value="true"<?php if (isset($options['mileage']) && $options['mileage'] == 'true') { ?> checked="checked"<?php } ?>>
<?php

        }

        function wpa_bank_name_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='bank_name' name='wp_accounts_options[bank_name]' size='40' type='text' value='" . ((isset($options['bank_name'])) ? esc_html($options['bank_name']) : '') . "' />";

        }

        function wpa_sort_code_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='sort_code' name='wp_accounts_options[sort_code]' size='40' type='text' value='" . ((isset($options['sort_code'])) ? esc_html($options['sort_code']) : '') . "' />";

        }

        function wpa_account_number_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='account_number' name='wp_accounts_options[account_number]' size='40' type='text' value='" . ((isset($options['account_number'])) ? esc_html($options['account_number']) : '') . "' />";

        }

        function wpa_account_name_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='account_name' name='wp_accounts_options[account_name]' size='40' type='text' value='" . ((isset($options['account_name'])) ? esc_html($options['account_name']) : '') . "' />";

        }

        function wpa_cheques_string() {

        	$options = get_option('wp_accounts_options');

?>
<input id="cheques" name="wp_accounts_options[cheques]" type="checkbox" value="true"<?php if (isset($options['cheques']) && $options['cheques']=='true') { ?> checked="checked"<?php } ?>>
<?php

        }

        function wpa_woocommerce_string() {

            if (class_exists('WooCommerce')) {

            	$options = get_option('wp_accounts_options');

?>
<input id="woocommerce" name="wp_accounts_options[woocommerce]" type="checkbox" value="true"<?php if (isset($options['woocommerce']) && $options['woocommerce']=='true') { ?> checked="checked"<?php } ?>>
<?php

            } else {

?>
<p>Please install, activate and configure WooCommerce to use this option.</p>
<?php

            }

        }

        function wpa_paypal_string() {

        	$options = get_option('wp_accounts_options');

?>
<p>Please note that this is now a legacy option and it is recommended to move over to integrate with WooCommerce.</p>
<input id="paypal" name="wp_accounts_options[paypal]" type="checkbox" value="true"<?php if (isset($options['paypal']) && $options['paypal']=='true') { ?> checked="checked"<?php } ?>>
<?php

        }

        function wpa_recurrent_string() {

        	$options = get_option('wp_accounts_options');

?>
<input id="recurrent" name="wp_accounts_options[recurrent]" type="checkbox" value="true"<?php if (isset($options['recurrent']) && $options['recurrent']=='true') { ?> checked="checked"<?php } ?>>
<?php

        }

        function wpa_email_color_string() {

        	$options = get_option('wp_accounts_options');

?>
<input id="email_color"<?php echo ' class="wpa-email-color"'; ?> name="wp_accounts_options[email_color]" size="40" type="text" value="<?php if (isset($options['email_color'])) { echo esc_html($options['email_color']); } ?>" />
<?php

        }

        function wpa_link_color_string() {

        	$options = get_option('wp_accounts_options');

?>
<input id="link_color"<?php echo ' class="wpa-link-color"'; ?> name="wp_accounts_options[link_color]" size="40" type="text" value="<?php if (isset($options['link_color'])) { echo esc_html($options['link_color']); } ?>" />
<?php

        }

        function wpa_hover_color_string() {

        	$options = get_option('wp_accounts_options');

?>
<input id="hover_color"<?php echo ' class="wpa-hover-color"'; ?> name="wp_accounts_options[hover_color]" size="40" type="text" value="<?php if (isset($options['hover_color'])) { echo esc_html($options['hover_color']); } ?>" />
<?php

        }

        function wpa_email_font_string() {

            $options = get_option('wp_accounts_options');

?>
<p style="font-family: Arial, Helvetica, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="Arial, Helvetica, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "Arial, Helvetica, sans-serif") { echo(' checked'); } ?> /> Arial / Helvetica</p>
<p style="font-family: 'Arial Black', Gadget, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Arial Black', Gadget, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'Arial Black', Gadget, sans-serif") { echo(' checked'); } ?> />Arial Black / Gadget</p>
<p style="font-family: 'Bookman Old Style', serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Bookman Old Style', serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'Bookman Old Style', serif") { echo(' checked'); } ?> />Bookman Old Style</p>
<p style="font-family: 'Comic Sans MS', cursive;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Comic Sans MS', cursive"<?php if (isset($options['email_font']) && $options['email_font'] == "'Comic Sans MS', cursive") { echo(' checked'); } ?> />Comic Sans MS</p>
<p style="font-family: 'Courier New', Courier, monospace;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Courier New', Courier, monospace"<?php if (isset($options['email_font']) && $options['email_font'] == "'Courier New', Courier, monospace") { echo(' checked'); } ?> />Courier New / Courier</p>
<p style="font-family: Garamond, serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="Garamond, serif"<?php if (isset($options['email_font']) && $options['email_font'] == "Garamond, serif") { echo(' checked'); } ?> />Garamond</p>
<p style="font-family: Georgia, serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="Georgia, serif"<?php if (isset($options['email_font']) && $options['email_font'] == "Georgia, serif") { echo(' checked'); } ?> />Georgia</p>
<p style="font-family: Impact, Charcoal, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="Impact, Charcoal, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "Impact, Charcoal, sans-serif") { echo(' checked'); } ?> />Impact / Charcoal</p>
<p style="font-family: 'Lucida Console', Monaco, monospace;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Lucida Console', Monaco, monospace"<?php if (isset($options['email_font']) && $options['email_font'] == "'Lucida Console', Monaco, monospace") { echo(' checked'); } ?> />Lucida Console / Monaco</p>
<p style="font-family: 'Lucida Sans Unicode', 'Lucida Grande', sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Lucida Sans Unicode', 'Lucida Grande', sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'Lucida Sans Unicode', 'Lucida Grande', sans-serif") { echo(' checked'); } ?> />Lucida Sans Unicode / Lucida Grande</p>
<p style="font-family: 'MS Sans Serif', Geneva, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'MS Sans Serif', Geneva, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'MS Sans Serif', Geneva, sans-serif") { echo(' checked'); } ?> />MS Sans Serif / Geneva</p>
<p style="font-family: 'MS Serif', 'New York', sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'MS Serif', 'New York', sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'MS Serif', 'New York', sans-serif") { echo(' checked'); } ?> />MS Serif / New York</p>
<p style="font-family: 'Palatino Linotype', 'Book Antiqua', Palatino, serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Palatino Linotype', 'Book Antiqua', Palatino, serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'Palatino Linotype', 'Book Antiqua', Palatino, serif") { echo(' checked'); } ?> />Palatino Linotype / Book Antiqua / Palatino</p>
<p style="font-family: Tahoma, Geneva, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="Tahoma, Geneva, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "Tahoma, Geneva, sans-serif") { echo(' checked'); } ?> />Tahoma / Geneva</p>
<p style="font-family: 'Times New Roman', Times, serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Times New Roman', Times, serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'Times New Roman', Times, serif") { echo(' checked'); } ?> />Times New Roman / Times</p>
<p style="font-family: 'Trebuchet MS', Helvetica, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="'Trebuchet MS', Helvetica, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "'Trebuchet MS', Helvetica, sans-serif") { echo(' checked'); } ?> />Trebuchet MS / Helvetica</p>
<p style="font-family: Verdana, Geneva, sans-serif;"><input type="radio" name="wp_accounts_options[email_font]" id="email_font_arial" value="Verdana, Geneva, sans-serif"<?php if (isset($options['email_font']) && $options['email_font'] == "Verdana, Geneva, sans-serif") { echo(' checked'); } ?> />Verdana / Geneva</p>
<?php

        }

        function wpa_email_footer_string() {

        	$options = get_option('wp_accounts_options');

            if (isset($options['email_footer'])) { $email_footer = $options['email_footer']; } else { $email_footer = ''; }

            echo wp_editor($email_footer, 'email_footer', array('textarea_name' => 'wp_accounts_options[email_footer]', 'media_buttons' => false));

        }

        function wpa_company_google_place_id_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='company_google_place_id' name='wp_accounts_options[company_google_place_id]' size='40' type='text' value='" . ((isset($options['company_google_place_id'])) ? esc_html($options['company_google_place_id']) : '') . "' />";

?>
<br />To invite customers to review your business, locate the <a href="https://developers.google.com/places/place-id#find-id" title="Find your Google Place ID">Google Place ID for your company</a> (you'll need a verified <a href="https://www.google.co.uk/intl/en/business/" title="Google My Business">Google My Business</a> listing).
<?php

        }

        function wpa_email_advert_id() {

        	$options = get_option('wp_accounts_options');

            if (isset($options['email_advert']) && absint($options['email_advert']) > 0) {

                echo '<img id="email_advert_preview_image" src="' . wp_get_attachment_url($options['email_advert']) . '" />';

            } else {

                echo '<img id="email_advert_preview_image" />';

            }

?>
<br />
<input id="email_advert" name="wp_accounts_options[email_advert]" type="hidden" value="<?php if (isset($options['email_advert'])) { echo esc_html($options['email_advert']); } ?>" />
<input type="button" class="button-primary" value="<?php esc_attr_e('Select Advert', 'wp-accounts' ); ?>" id="email_advert_media_manager" data-button="<?php echo wp_create_nonce('wpaccounts'); ?>" />
<input type="button" value="<?php esc_attr_e('Clear', 'wp-accounts' ); ?>" onclick="jQuery('input#email_advert').removeAttr('value'); jQuery('img#email_advert_preview_image').removeAttr('src').replaceWith(jQuery('img#email_advert_preview_image').clone());" />
<?php

        }

        function wpa_advert_url_string() {

        	$options = get_option('wp_accounts_options');
        	echo "<input id='advert_url' name='wp_accounts_options[advert_url]' size='40' type='url' value='" . ((isset($options['advert_url'])) ? esc_html($options['advert_url']) : '') . "' />";

?>
<br />If entered, this link will be placed on the advert image above.
<?php

        }

        function wpa_options_validate($input) {

        	$options = get_option('wp_accounts_options');
        	$options['company_name'] = sanitize_text_field($input['company_name']);
        	$options['company_address'] = sanitize_text_field($input['company_address']);
	        $options['company_telephone'] = $this->wpa_clean_telephone($input['company_telephone']);
	        $options['accounting_period_start'] = sanitize_text_field($input['accounting_period_start']);

        	if (isset($input['mileage']) && $input['mileage']) { $options['mileage'] = sanitize_text_field($input['mileage']); } else { unset($options['mileage']); }

        	$options['bank_name'] = sanitize_text_field($input['bank_name']);
        	$options['sort_code'] = sanitize_text_field($input['sort_code']);
        	$options['account_number'] = sanitize_text_field($input['account_number']);
        	$options['account_name'] = preg_replace("/[^A-Z ]+/", "", strtoupper($input['account_name']));

        	if (isset($input['cheques']) && $input['cheques']) { $options['cheques'] = sanitize_text_field($input['cheques']); } else { unset($options['cheques']); }

        	if (isset($input['woocommerce']) && $input['woocommerce']) { $options['woocommerce'] = sanitize_text_field($input['woocommerce']); } else { unset($options['woocommerce']); }

        	if (isset($input['paypal']) && $input['paypal']) { $options['paypal'] = sanitize_text_field($input['paypal']); } else { unset($options['paypal']); }

        	if (isset($input['recurrent']) && $input['recurrent']) { $options['recurrent'] = sanitize_text_field($input['recurrent']); } else { unset($options['recurrent']); }

        	if (isset($input['company_logo'])) { $options['company_logo'] = (absint($input['company_logo']) == 0) ? NULL : absint($input['company_logo']); }

        	$options['email_color'] = sanitize_text_field($input['email_color']);
        	$options['link_color'] = sanitize_text_field($input['link_color']);
        	$options['hover_color'] = sanitize_text_field($input['hover_color']);
        	$options['email_font'] = sanitize_text_field(isset($input['email_font']) ? $input['email_font'] : '');
        	$options['email_footer'] = wp_kses_post($input['email_footer']);
        	$options['company_google_place_id'] = sanitize_text_field($input['company_google_place_id']);

            if (isset($input['email_advert'])) { $options['email_advert'] = (absint($input['email_advert']) == 0) ? NULL : absint($input['email_advert']); }

            if (isset($input['advert_url']) && esc_url_raw($input['advert_url'])) {

                $options['advert_url'] = esc_url_raw($input['advert_url']);

            } else {

                unset($options['advert_url']);

            }

            global $wpdb;

            if (isset($input['expense_types']) && is_array($input['expense_types']) && $input['expense_types']) {

                foreach ($input['expense_types'] as $key => $value) {

                    $wpdb->update(
                        "{$wpdb->prefix}accounts_expense_types",
	                    array(
	                        'expense_type' => sanitize_text_field($value['expense_type']),
	                        'expense' => absint($value['expense'])
	                    ),
	                    array('ID' => $key),
	                    array('%s', '%d'),
	                    array('%d')
                    );

                }

            }

            if (isset($input['expense_types_delete']) && is_array($input['expense_types_delete']) && $input['expense_types_delete']) {

                foreach ($input['expense_types_delete'] as $key => $value) {

                    $wpdb->delete(
                        "{$wpdb->prefix}accounts_expense_types",
	                    array('ID' => $key),
	                    array('%d')
                    );

                }

            }

            if (isset($input['expense_type_new']) && sanitize_text_field($input['expense_type_new'])) {

                $wpdb->insert(
	                "{$wpdb->prefix}accounts_expense_types",
	                array('expense_type' => sanitize_text_field($input['expense_type_new'])),
	                array('%s')
                );

            }

        	return $options;

        }

        function wpa_ajax_get_attachment_url() {

            check_ajax_referer('wpaccounts', 'security');

            if (isset($_GET['id']) && absint($_GET['id']) > 0) {

                $url = wp_get_attachment_url(absint($_GET['id']));
                $data = array(
                    'url'    => $url
                );
                wp_send_json_success( $data );

            } else {

                wp_send_json_error();

            }

        }

        function wpa_enqueue_settings_script($hook_suffix) {

            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wpa-settings-pickers');

        }

        function wpa_enqueue_date_picker($hook_suffix) {

            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui');
            wp_enqueue_script('wpa-date-pickers');

        }

        function wpa_accounts_settings_page() {

?>
<div>
<h2>WP Accounts Settings</h2>
<p>Set these options before using this plugin.</p>
<form action="options.php" method="post">
<?php wp_nonce_field('wpaccounts'); ?>
<?php settings_fields('wp_accounts_options'); ?>
<?php do_settings_sections('wp_accounts'); ?>
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
</form></div>
<?php

        }

        function wpa_accounts_get_data($database, $receipt = false, $object_id = false, $date_from = false, $date_to = false, $field = 'supplier') {

            global $wpdb;

            if ($database == 'accounts_payments') {

                if (!$wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$wpdb->prefix}accounts_expense_types' AND column_name = 'expense';")) {

                    $this->wpa_setup_database();

                }

                if ($object_id && $date_from && $date_to) {

                    $data = $wpdb->get_results(
$wpdb->prepare(
"SELECT {$wpdb->prefix}$database.ID, {$wpdb->prefix}$database.supplier, {$wpdb->prefix}$database.invoice_date, {$wpdb->prefix}$database.amount, {$wpdb->prefix}$database.reference, {$wpdb->prefix}$database.expense_type, {$wpdb->prefix}accounts_expense_types.expense, {$wpdb->prefix}$database.date_paid, {$wpdb->prefix}accounts_payment_methods.payment_method, {$wpdb->prefix}$database.mileage, {$wpdb->prefix}$database.notes
FROM {$wpdb->prefix}$database
INNER JOIN {$wpdb->prefix}accounts_payment_methods ON {$wpdb->prefix}$database.payment_method = {$wpdb->prefix}accounts_payment_methods.ID 
INNER JOIN {$wpdb->prefix}accounts_expense_types ON {$wpdb->prefix}$database.expense_type = {$wpdb->prefix}accounts_expense_types.ID 
WHERE {$wpdb->prefix}$database.$field = %s AND
{$wpdb->prefix}$database.date_paid >= %s AND
{$wpdb->prefix}$database.date_paid <= %s
ORDER BY {$wpdb->prefix}$database.payment_method ASC, {$wpdb->prefix}$database.date_paid DESC, {$wpdb->prefix}$database.invoice_date DESC;"
, array($object_id, $date_from, $date_to))

);

                } elseif ($object_id) {

                    $data = $wpdb->get_results(
$wpdb->prepare(
"SELECT {$wpdb->prefix}$database.ID, {$wpdb->prefix}$database.supplier, {$wpdb->prefix}$database.invoice_date, {$wpdb->prefix}$database.amount, {$wpdb->prefix}$database.reference, {$wpdb->prefix}$database.expense_type, {$wpdb->prefix}accounts_expense_types.expense, {$wpdb->prefix}$database.date_paid, {$wpdb->prefix}accounts_payment_methods.payment_method, {$wpdb->prefix}$database.mileage, {$wpdb->prefix}$database.notes
FROM {$wpdb->prefix}$database
INNER JOIN {$wpdb->prefix}accounts_payment_methods ON {$wpdb->prefix}$database.payment_method = {$wpdb->prefix}accounts_payment_methods.ID 
INNER JOIN {$wpdb->prefix}accounts_expense_types ON {$wpdb->prefix}$database.expense_type = {$wpdb->prefix}accounts_expense_types.ID 
WHERE {$wpdb->prefix}$database.$field = %s
ORDER BY {$wpdb->prefix}$database.payment_method ASC, {$wpdb->prefix}$database.date_paid DESC, {$wpdb->prefix}$database.invoice_date DESC;"
, array($object_id))

);

                } else {

                    if ($date_from && $date_to) {

                        $data = $wpdb->get_results($wpdb->prepare(
"SELECT {$wpdb->prefix}$database.ID, {$wpdb->prefix}$database.supplier, {$wpdb->prefix}$database.invoice_date, {$wpdb->prefix}$database.amount, {$wpdb->prefix}$database.reference, {$wpdb->prefix}$database.expense_type, {$wpdb->prefix}accounts_expense_types.expense, {$wpdb->prefix}$database.date_paid, {$wpdb->prefix}accounts_payment_methods.payment_method, {$wpdb->prefix}$database.mileage, {$wpdb->prefix}$database.notes
FROM {$wpdb->prefix}$database
INNER JOIN {$wpdb->prefix}accounts_payment_methods ON {$wpdb->prefix}$database.payment_method = {$wpdb->prefix}accounts_payment_methods.ID 
INNER JOIN {$wpdb->prefix}accounts_expense_types ON {$wpdb->prefix}$database.expense_type = {$wpdb->prefix}accounts_expense_types.ID 
WHERE {$wpdb->prefix}$database.invoice_date >= %s AND
{$wpdb->prefix}$database.invoice_date <= %s
ORDER BY {$wpdb->prefix}$database.payment_method ASC, {$wpdb->prefix}$database.date_paid DESC, {$wpdb->prefix}$database.invoice_date DESC;"
, array($date_from, $date_to)
                        ));

                    } else {

                        $data = $wpdb->get_results(
"SELECT {$wpdb->prefix}$database.ID, {$wpdb->prefix}$database.supplier, {$wpdb->prefix}$database.invoice_date, {$wpdb->prefix}$database.amount, {$wpdb->prefix}$database.reference, {$wpdb->prefix}$database.expense_type, {$wpdb->prefix}accounts_expense_types.expense, {$wpdb->prefix}$database.date_paid, {$wpdb->prefix}accounts_payment_methods.payment_method, {$wpdb->prefix}$database.mileage, {$wpdb->prefix}$database.notes
FROM {$wpdb->prefix}$database
INNER JOIN {$wpdb->prefix}accounts_payment_methods ON {$wpdb->prefix}$database.payment_method = {$wpdb->prefix}accounts_payment_methods.ID 
INNER JOIN {$wpdb->prefix}accounts_expense_types ON {$wpdb->prefix}$database.expense_type = {$wpdb->prefix}accounts_expense_types.ID 
WHERE ({$wpdb->prefix}$database.invoice_date >= (CURDATE() - INTERVAL 1 YEAR) OR {$wpdb->prefix}$database.date_paid >= (CURDATE() - INTERVAL 1 YEAR))
ORDER BY {$wpdb->prefix}$database.payment_method ASC, {$wpdb->prefix}$database.date_paid DESC, {$wpdb->prefix}$database.invoice_date DESC;"
                        );

                    }

                }

            } elseif ($database == 'accounts_clients') {

                $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}$database ORDER BY SUBSTRING(UPPER(company), IF (UPPER(company) LIKE 'THE %', 5, 1)), contact ASC;");

            } elseif ($database == 'accounts_invoices_last_year') {

                $data = $wpdb->get_results("
SELECT * FROM {$wpdb->prefix}accounts_invoices
WHERE invoice_date >= STR_TO_DATE(CONCAT('1-',MONTH(DATE_ADD(CURDATE(), INTERVAL 1 MONTH)),'-',YEAR(DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), INTERVAL 1 MONTH))),'%d-%m-%Y')
AND invoice_date < STR_TO_DATE(CONCAT('1-',MONTH(DATE_ADD(CURDATE(), INTERVAL 2 MONTH)),'-',YEAR(DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), INTERVAL 2 MONTH))),'%d-%m-%Y')
AND yearly = 1
ORDER BY invoice_date
                ");

            } elseif ($database == 'accounts_invoices_last_month') {

                $data = $wpdb->get_results("
SELECT * FROM {$wpdb->prefix}accounts_invoices
WHERE invoice_date >= STR_TO_DATE(CONCAT('1-',MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),'-',YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))),'%d-%m-%Y')
AND invoice_date < STR_TO_DATE(CONCAT('1-',MONTH(CURDATE()),'-',YEAR(CURDATE())),'%d-%m-%Y')
AND monthly = 1
ORDER BY invoice_date
                ");

            } else {

                if ($date_from && $date_to) {

                    $data = $wpdb->get_results($wpdb->prepare(
"SELECT * FROM {$wpdb->prefix}$database
WHERE {$wpdb->prefix}$database.invoice_status = " . ($object_id ? absint($object_id) : 1) . " AND
(
(
{$wpdb->prefix}$database.invoice_date >= %s AND 
{$wpdb->prefix}$database.invoice_date <= %s
)" . /* Disabled this as it was placing invoices in the wrong year
OR 
(
{$wpdb->prefix}$database.date_paid >= %s AND 
{$wpdb->prefix}$database.date_paid <= %s
) */
")
ORDER BY {$wpdb->prefix}$database.ID DESC, {$wpdb->prefix}$database.invoice_date DESC;"
, array($date_from, $date_to) // , $date_from, $date_to)
                    ));

                } elseif ($object_id) {

                    $data = $wpdb->get_results(
"SELECT * FROM {$wpdb->prefix}$database
WHERE {$wpdb->prefix}$database.invoice_status = " . ($object_id ? absint($object_id) : 1) . " AND
(
invoice_date >= (CURDATE() - INTERVAL 1 YEAR) OR
date_paid >= (CURDATE() - INTERVAL 1 YEAR)
)
ORDER BY {$wpdb->prefix}$database.ID DESC, {$wpdb->prefix}$database.invoice_date DESC;"

                    );

                } else {

                    $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}$database WHERE invoice_status = 1 ORDER BY ID DESC, invoice_date DESC;");

                }

            }

            return $data;

        }

        function wpa_client_statement_get_data($client_id) {

            global $wpdb;

            $data = $wpdb->get_results($wpdb->prepare("SELECT {$wpdb->prefix}accounts_invoices.ID, {$wpdb->prefix}accounts_clients.contact, {$wpdb->prefix}accounts_clients.company, {$wpdb->prefix}accounts_clients.address1, {$wpdb->prefix}accounts_clients.address2, {$wpdb->prefix}accounts_clients.address3, {$wpdb->prefix}accounts_clients.town, {$wpdb->prefix}accounts_clients.county, {$wpdb->prefix}accounts_clients.postcode, {$wpdb->prefix}accounts_clients.country, {$wpdb->prefix}accounts_clients.email, {$wpdb->prefix}accounts_invoices.invoice_date, {$wpdb->prefix}accounts_invoices.item1, {$wpdb->prefix}accounts_invoices.item2, {$wpdb->prefix}accounts_invoices.item3, {$wpdb->prefix}accounts_invoices.item4, {$wpdb->prefix}accounts_invoices.item5, {$wpdb->prefix}accounts_invoices.price1, {$wpdb->prefix}accounts_invoices.price2, {$wpdb->prefix}accounts_invoices.price3, {$wpdb->prefix}accounts_invoices.price4, {$wpdb->prefix}accounts_invoices.price5, {$wpdb->prefix}accounts_invoices.date_paid, {$wpdb->prefix}accounts_invoice_status.status, {$wpdb->prefix}accounts_payment_methods.payment_method FROM {$wpdb->prefix}accounts_invoices INNER JOIN {$wpdb->prefix}accounts_clients ON {$wpdb->prefix}accounts_invoices.client = {$wpdb->prefix}accounts_clients.ID INNER JOIN {$wpdb->prefix}accounts_invoice_status ON {$wpdb->prefix}accounts_invoices.invoice_status = {$wpdb->prefix}accounts_invoice_status.ID LEFT JOIN {$wpdb->prefix}accounts_payment_methods ON {$wpdb->prefix}accounts_invoices.payment_method = {$wpdb->prefix}accounts_payment_methods.ID WHERE client = %s ORDER BY invoice_date DESC;",$client_id));

            return $data;

        }

        function wpa_accounts_get_row($id, $database) {

            global $wpdb;

            $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}$database WHERE ID=%d;",$id));

            if(!empty($data[0])) {

                return $data[0];

            }

            return;
        }

        function wpa_client_from_id($id) {

            global $wpdb;

            $data = $wpdb->get_results($wpdb->prepare("SELECT company, contact, email, telephone, mobile FROM {$wpdb->prefix}accounts_clients WHERE ID=%d;",$id));

            if(!empty($data[0])) {

                return $data[0];

            }

            return;

        }

        function wpa_client_meta_box() {

            global $wpdb, $edit_clients;

?>
<p>Company: <input type="text" name="accounts_clients_company" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->company);?>" /></p>
<p>Address1:* <input type="text" name="accounts_clients_address1" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->address1);?>" /></p>
<p>Address2: <input type="text" name="accounts_clients_address2" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->address2);?>" /></p>
<p>Address3: <input type="text" name="accounts_clients_address3" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->address3);?>" /></p>
<p>Town:* <input type="text" name="accounts_clients_town" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->town);?>" /></p>
<p>County: <input type="text" name="accounts_clients_county" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->county);?>" /></p>
<p>Postcode:* <input type="text" name="accounts_clients_postcode" maxlength="13" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->postcode);?>" /></p>
<?php

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'accounts_countries')) != $wpdb->prefix . 'accounts_countries') {

?>
<input type="hidden" name="accounts_clients_country" maxlength="2" value="GB" />
<?php

                $this->wpa_setup_database();

            } else {

                $countries = $wpdb->get_results("SELECT country_code, country FROM {$wpdb->prefix}accounts_countries ORDER BY country ASC;");

                if ($countries) {

?>
<p>Country: <select name="accounts_clients_country">
<?php

                    foreach($countries as $country) {

?>
    <option value="<?php echo $country->country_code; ?>"<?php if ((isset($edit_clients) && $edit_clients->country == $country->country_code) || (!isset($edit_clients) && $country->country_code == 'GB')) { echo ' selected="selected"'; } ?>><?php echo $country->country; ?></option>
<?php

                    }

?>
</select></p>
<?php

                }

            }

?>
<p>Contact:* <input type="text" name="accounts_clients_contact" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->contact);?>" /></p>
<p>Telephone: <input type="text" name="accounts_clients_telephone" maxlength="20" value="<?php if(isset($edit_clients)) echo $this->wpa_format_telephone($this->wpa_clean_telephone($edit_clients->telephone));?>" /></p>
<p>Mobile: <input type="text" name="accounts_clients_mobile" maxlength="20" value="<?php if(isset($edit_clients)) echo $this->wpa_format_telephone($this->wpa_clean_telephone($edit_clients->mobile));?>" /></p>
<p>Email: <input type="text" name="accounts_clients_email" maxlength="255" value="<?php if(isset($edit_clients)) echo esc_html($edit_clients->email);?>" /></p>
<p>Notes: <textarea name="accounts_clients_notes"><?php if(isset($edit_clients)) echo esc_html($edit_clients->notes);?></textarea></p>
<p>(* required fields)</p>
<?php 

        }

        function wpa_invoice_meta_box() {

            global $edit_invoices, $wpdb;

            $options = get_option('wp_accounts_options');
            $clients = $wpdb->get_results("SELECT ID, company, contact FROM {$wpdb->prefix}accounts_clients ORDER BY SUBSTRING(UPPER(company), IF (UPPER(company) LIKE 'THE %', 5, 1)), contact ASC;");

            if ($clients) {

?>
<p>Client:* <select name="accounts_invoices_client">
<?php

                foreach($clients as $client) { 

?>
    <option value="<?php echo $client->ID; ?>"<?php if (isset($edit_invoices) && $edit_invoices->client == $client->ID) { echo ' selected="selected"'; } ?>><?php if ($client->company) { if (strpos(strtolower($client->company), 'the ') === 0) { echo substr($client->company, 4) . ' (The), '; } else { echo $client->company . ', '; } } echo $client->contact; ?></option>
<?php

                }

?>
</select></p>
<p>Invoice Date:* <input type="text" name="accounts_invoices_invoice_date" maxlength="10" class='wpa-invoice-date' value="<?php if(isset($edit_invoices)) echo $edit_invoices->invoice_date;?>" /> YYYY-MM-DD</p>
<?php

            if (isset($options['recurrent']) && $options['recurrent'] == 'true') {

?>
<p>Recurrence: <select name="accounts_invoices_recurrence">
    <option value=""<?php if ((isset($edit_invoices) && $edit_invoices->monthly == false && $edit_invoices->yearly == false) || !isset($edit_invoices)) { echo ' selected="selected"'; } ?>>None</option>
    <option value="monthly"<?php if (isset($edit_invoices) && $edit_invoices->monthly == true) { echo ' selected="selected"'; } ?>>Monthly</option>
    <option value="yearly"<?php if (isset($edit_invoices) && $edit_invoices->yearly == true) { echo ' selected="selected"'; } ?>>Yearly</option>
</select></p>
<?php

            }

?>
<p>Item 1:* <input type="text" name="accounts_invoices_item1" maxlength="255" value="<?php if(isset($edit_invoices)) echo esc_html($edit_invoices->item1);?>" /></p>
<p>Price 1:* &pound;<input type="text" name="accounts_invoices_price1" maxlength="13" value="<?php if(isset($edit_invoices)) echo $edit_invoices->price1;?>" /></p>
<p>Item 2: <input type="text" name="accounts_invoices_item2" maxlength="255" value="<?php if(isset($edit_invoices)) echo esc_html($edit_invoices->item2);?>" /></p>
<p>Price 2: &pound;<input type="text" name="accounts_invoices_price2" maxlength="13" value="<?php if(isset($edit_invoices)) echo $edit_invoices->price2;?>" /></p>
<p>Item 3: <input type="text" name="accounts_invoices_item3" maxlength="255" value="<?php if(isset($edit_invoices)) echo esc_html($edit_invoices->item3);?>" /></p>
<p>Price 3: &pound;<input type="text" name="accounts_invoices_price3" maxlength="13" value="<?php if(isset($edit_invoices)) echo $edit_invoices->price3;?>" /></p>
<p>Item 4: <input type="text" name="accounts_invoices_item4" maxlength="255" value="<?php if(isset($edit_invoices)) echo  esc_html($edit_invoices->item4);?>" /></p>
<p>Price 4: &pound;<input type="text" name="accounts_invoices_price4" maxlength="13" value="<?php if(isset($edit_invoices)) echo $edit_invoices->price4;?>" /></p>
<p>Item 5: <input type="text" name="accounts_invoices_item5" maxlength="255" value="<?php if(isset($edit_invoices)) echo esc_html($edit_invoices->item5);?>" /></p>
<p>Price 5: &pound;<input type="text" name="accounts_invoices_price5" maxlength="13" value="<?php if(isset($edit_invoices)) echo $edit_invoices->price5;?>" /></p>
<?php

            $invoice_status = $wpdb->get_results("SELECT ID, status FROM {$wpdb->prefix}accounts_invoice_status ORDER BY status ASC;");

?>
<p>Invoice Status: <select id="accounts_invoices_invoice_status" name="accounts_invoices_invoice_status">
<?php

            foreach ($invoice_status as $status) { 

?>
    <option value="<?php echo $status->ID; ?>"<?php if ((isset($edit_invoices) && $edit_invoices->invoice_status == $status->ID && !isset($_GET['copy'])) || ((!isset($edit_invoices) || isset($_GET['copy'])) && $status->status == 'Unpaid')) { echo ' selected="selected"'; } ?>><?php echo $status->status; ?></option>
<?php

            }

?>
</select></p>
<p>Date Paid: <input type="text" name="accounts_invoices_date_paid" maxlength="10" class='wpa-paid-date' value="<?php if(isset($edit_invoices) && !isset($_GET['copy'])) echo $edit_invoices->date_paid;?>" /> YYYY-MM-DD</p>
<?php

            $payment_methods = $wpdb->get_results("SELECT ID, payment_method FROM {$wpdb->prefix}accounts_payment_methods ORDER BY payment_method ASC;");
?>
<p>Payment Method: <select name="accounts_invoices_payment_method">
    <option value=""<?php if (empty($edit_invoices->payment_method) || isset($_GET['copy']) || !isset($edit_invoices)) { echo ' selected="selected"'; } ?>>Unselected</option>
<?php

            foreach($payment_methods as $payment_method) { 

?>
    <option value="<?php echo $payment_method->ID; ?>"<?php if (isset($edit_invoices) && $edit_invoices->payment_method == $payment_method->ID && !isset($_GET['copy'])) { echo ' selected="selected"'; } ?>><?php echo $payment_method->payment_method; ?></option>
<?php

            }

?>
</select></p>
<?php

            if (isset($options['mileage']) && $options['mileage'] == 'true') {

?>
<p>Mileage: <input type="text" name="accounts_invoices_mileage" maxlength="11" value="<?php if(isset($edit_invoices)) echo $edit_invoices->mileage;?>" /></p>
<?php

        	}

?>
<p>Notes: <textarea name="accounts_invoices_notes"><?php if(isset($edit_invoices)) echo $edit_invoices->notes;?></textarea></p>
<p>(* required fields)</p>
<?php

            } else {

?>
<p>First you need to add a client to the client database.</p>
<?php

            }

        }

        function wpa_payment_meta_box() {

            global $edit_payments, $wpdb;

            $options = get_option('wp_accounts_options');

?>
<p>Supplier:* <input type="text" name="accounts_payments_supplier" maxlength="255" id="accounts_payments_supplier" value="<?php if(isset($edit_payments)) echo esc_html($edit_payments->supplier);?>" />
<?php

            $existing_suppliers = $wpdb->get_results("SELECT supplier FROM {$wpdb->prefix}accounts_payments GROUP BY supplier ORDER BY SUBSTRING(UPPER(supplier), IF (UPPER(supplier) LIKE 'THE %', 5, 1));");

?>
<select name="accounts_payments_existing_suppliers" onchange="document.getElementById('accounts_payments_supplier').value = this.value;">
    <option value="" selected="selected">Existing suppliers ...</option>
<?php

            foreach($existing_suppliers as $existing_supplier) { 

?>
    <option value="<?php echo esc_html($existing_supplier->supplier); ?>"><?php 

                if (strpos(strtolower($existing_supplier->supplier), 'the ') === 0) { echo substr($existing_supplier->supplier, 4) . ' (The)'; } else { echo $existing_supplier->supplier; }

?></option>
<?php

            }

?>
</select></p>
<p>Invoice Date:* <input type="text" name="accounts_payments_invoice_date" maxlength="10" class='wpa-invoice-date' value="<?php if(isset($edit_payments) && !isset($_GET['copy'])) echo $edit_payments->invoice_date;?>" /> YYYY-MM-DD</p>
<p>Amount:* &pound;<input type="text" name="accounts_payments_amount" maxlength="13" value="<?php if(isset($edit_payments)) echo $edit_payments->amount;?>" /></p>
<p>Reference: <input type="text" name="accounts_payments_reference" maxlength="255" value="<?php if(isset($edit_payments)) echo esc_html($edit_payments->reference);?>" /> (Their invoice number)</p>
<?php

            $expense_types = $wpdb->get_results("SELECT ID, expense_type FROM {$wpdb->prefix}accounts_expense_types ORDER BY expense_type ASC;");

?>
<p>Expense Type:* <select name="accounts_payments_expense_type">
    <option value=""<?php if (empty($edit_payments->expense_type) || !isset($edit_payments)) { echo ' selected="selected"'; } ?>>Select ...</option>
<?php

            foreach($expense_types as $expense_type) { 

?>
    <option value="<?php echo $expense_type->ID; ?>"<?php if (isset($edit_payments) && $edit_payments->expense_type == $expense_type->ID) { echo ' selected="selected"'; } ?>><?php echo $expense_type->expense_type; ?></option>
<?php

            }

?>
</select></p>
<p>Date Paid:* <input type="text" name="accounts_payments_date_paid" maxlength="10" class='wpa-paid-date' value="<?php if(isset($edit_payments) && !isset($_GET['copy'])) echo $edit_payments->date_paid;?>" /> YYYY-MM-DD</p>
<?php

            $payment_methods = $wpdb->get_results("SELECT ID, payment_method FROM {$wpdb->prefix}accounts_payment_methods ORDER BY payment_method ASC;");

?>
<p>Payment Method*: <select name="accounts_payments_payment_method">
    <option value=""<?php if (empty($edit_payments->payment_method) || !isset($edit_payments)) { echo ' selected="selected"'; } ?>>Select ...</option>
<?php

            foreach ($payment_methods as $payment_method) { 

?>
    <option value="<?php echo $payment_method->ID; ?>"<?php if (isset($edit_payments) && $edit_payments->payment_method == $payment_method->ID) { echo ' selected="selected"'; } ?>><?php echo $payment_method->payment_method; ?></option>
<?php

            }

?>
</select></p>
<p>Nature of Expense: <textarea name="accounts_payments_notes"><?php if(isset($edit_payments)) echo $edit_payments->notes;?></textarea></p>
<?php

            if (isset($options['mileage']) && $options['mileage'] == 'true') {

?>
<p>Mileage: <input type="text" name="accounts_payments_mileage" maxlength="11" value="<?php if(isset($edit_payments)) echo $edit_payments->mileage;?>" /></p>
<?php

        	}

?>
<p>(* required fields)</p>
<?php 

        }

        function wpa_manage_invoices_options(){

            $invoice_status = (isset($_GET['invoice-type']) && absint($_GET['invoice-type']) ? absint($_GET['invoice-type']) : 0);
            $invoice_status = $this->wpa_invoices_action($invoice_status);

            if ($invoice_status) { $_GET['invoice-type'] = $invoice_status; }

            if (empty($_GET['edit'])) {

                if (!isset($_GET['copy_yearly']) && !isset($_GET['copy_monthly'])) {

                    $this->wpa_manage_invoices($invoice_status);

                }

            } else {

                $this->wpa_add_invoice();   

            }

        }

function wpa_invoices_action() {

    global $wpdb;

    $invoice_status = NULL;

    if(current_user_can('manage_options') && isset($_GET['delete'])) {

		check_admin_referer('wpaccounts');

        $invoice_id = absint($_GET['delete']);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}accounts_invoices WHERE ID=%d;",$invoice_id));
    }

    if(current_user_can('manage_options') && isset($_POST['accounts_invoices_action']) && isset($_POST['accounts_invoices_action-2']) && ($_POST['accounts_invoices_action'] == 'send' || $_POST['accounts_invoices_action-2'] == 'send')) {

        foreach ($_POST['invoice_id'] as $invoice_id) {

            $this->wpa_send_invoice($invoice_id);

        }

    }

    if(current_user_can('manage_options') && isset($_GET['send'])) {

        $invoice_id = absint($_GET['send']);
        $this->wpa_send_invoice($invoice_id);
    }

    if(current_user_can('manage_options') && isset($_POST['accounts_invoices_action']) && isset($_POST['accounts_invoices_action-2']) && ($_POST['accounts_invoices_action'] == 'send_copy' || $_POST['accounts_invoices_action-2'] == 'send_copy')) {

        foreach ($_POST['invoice_id'] as $invoice_id) {

            $this->wpa_send_invoice($invoice_id, true);

        }

    }

    if(current_user_can('manage_options') && isset($_GET['send_copy'])) {

        $invoice_id = absint($_GET['send_copy']);
        $this->wpa_send_invoice($invoice_id, true);

    }

    if(current_user_can('manage_options') && isset($_POST['accounts_add_invoice']) and isset($_POST['accounts_invoices_client']) and isset($_POST['accounts_invoices_invoice_date']) and isset($_POST['accounts_invoices_item1']) and isset($_POST['accounts_invoices_price1']) and isset($_POST['accounts_invoices_item2']) and isset($_POST['accounts_invoices_price2']) and isset($_POST['accounts_invoices_item3']) and isset($_POST['accounts_invoices_price3']) and isset($_POST['accounts_invoices_item4']) and isset($_POST['accounts_invoices_price4']) and isset($_POST['accounts_invoices_item5']) and isset($_POST['accounts_invoices_price5']) and isset($_POST['accounts_invoices_invoice_status']) and isset($_POST['accounts_invoices_date_paid']) and isset($_POST['accounts_invoices_payment_method']) and isset($_POST['accounts_invoices_notes']) ) {

		check_admin_referer('wpaccounts');

		$_POST = array_map( 'stripslashes_deep', $_POST);

        $client = (is_numeric($_POST['accounts_invoices_client']) && !empty($_POST['accounts_invoices_client'])) ? $_POST['accounts_invoices_client'] : NULL;
        $invoice_date = (strtotime(sanitize_text_field($_POST['accounts_invoices_invoice_date'])) && !empty($_POST['accounts_invoices_invoice_date'])) ? sanitize_text_field($_POST['accounts_invoices_invoice_date']) : NULL;

        if (isset($_POST['accounts_invoices_recurrence']) && $_POST['accounts_invoices_recurrence'] == 'monthly') {
		$monthly = 1;
		$yearly = 0;
	} elseif (isset($_POST['accounts_invoices_recurrence']) && $_POST['accounts_invoices_recurrence'] == 'yearly') {
		$monthly = 0;
		$yearly = 1;
	} else {
		$monthly = 0;
		$yearly = 0;
	}

        $item1 = empty($_POST['accounts_invoices_item1']) ? NULL : sanitize_text_field($_POST['accounts_invoices_item1']);
        $price1 = (is_numeric($_POST['accounts_invoices_price1']) && !empty($_POST['accounts_invoices_price1'])) ? $_POST['accounts_invoices_price1'] : NULL;
        $item2 = empty($_POST['accounts_invoices_item2']) ? NULL : sanitize_text_field($_POST['accounts_invoices_item2']);
        $price2 = (is_numeric($_POST['accounts_invoices_price2']) && !empty($_POST['accounts_invoices_price2'])) ? $_POST['accounts_invoices_price2'] : NULL;
        $item3 = empty($_POST['accounts_invoices_item3']) ? NULL : sanitize_text_field($_POST['accounts_invoices_item3']);
        $price3 = (is_numeric($_POST['accounts_invoices_price3']) && !empty($_POST['accounts_invoices_price3'])) ? $_POST['accounts_invoices_price3'] : NULL;
        $item4 = empty($_POST['accounts_invoices_item4']) ? NULL : sanitize_text_field($_POST['accounts_invoices_item4']);
        $price4 = (is_numeric($_POST['accounts_invoices_price4']) && !empty($_POST['accounts_invoices_price4'])) ? $_POST['accounts_invoices_price4'] : NULL;
        $item5 = empty($_POST['accounts_invoices_item5']) ? NULL : sanitize_text_field($_POST['accounts_invoices_item5']);
        $price5 = (is_numeric($_POST['accounts_invoices_price5']) && !empty($_POST['accounts_invoices_price5'])) ? $_POST['accounts_invoices_price5'] : NULL;
        $invoice_status = (is_numeric($_POST['accounts_invoices_invoice_status']) && !empty($_POST['accounts_invoices_invoice_status'])) ? $_POST['accounts_invoices_invoice_status'] : NULL;
        $date_paid = (strtotime(sanitize_text_field($_POST['accounts_invoices_date_paid'])) && !empty($_POST['accounts_invoices_date_paid'])) ? sanitize_text_field($_POST['accounts_invoices_date_paid']) : NULL;
        $payment_method = (is_numeric($_POST['accounts_invoices_payment_method']) && !empty($_POST['accounts_invoices_payment_method'])) ? $_POST['accounts_invoices_payment_method'] : NULL;
        $mileage = (isset($_POST['accounts_invoices_mileage']) && is_numeric($_POST['accounts_invoices_mileage'])) ? $_POST['accounts_invoices_mileage'] : NULL;
        $notes = empty($_POST['accounts_invoices_notes']) ? NULL : sanitize_text_field($_POST['accounts_invoices_notes']);

        if(empty($_POST['accounts_invoices_id'])) {
            if ($wpdb->insert(
	        $wpdb->prefix . 'accounts_invoices',
	            array(
		        'client' => $client,
		        'invoice_date' => $invoice_date,
	        	'monthly' => $monthly,
	        	'yearly' => $yearly,
	        	'item1' => $item1,
	        	'price1' => $price1,
	        	'item2' => $item2,
	        	'price2' => $price2,
	        	'item3' => $item3,
	        	'price3' => $price3,
	        	'item4' => $item4,
	        	'price4' => $price4,
	        	'item5' => $item5,
	        	'price5' => $price5,
	        	'invoice_status' => $invoice_status,
	        	'date_paid' => $date_paid,
	        	'payment_method' => $payment_method,
	        	'mileage' => $mileage,
	        	'notes' => $notes
	            ), 
	            array(
	        	'%d',
	        	'%s',
	        	'%d',
	        	'%d',
	        	'%s',
	        	'%f',
	        	'%s',
	        	'%f',
	        	'%s',
	        	'%f',
	        	'%s',
        		'%f',
	        	'%s',
	        	'%f',
	        	'%d',
	        	'%s',
	        	'%d',
	        	'%d',
        		'%s'
	            )
            ) != false) {
                echo '<p style="color: green;">Invoice ' . absint($wpdb->insert_id) . ' added successfully.</p>';
            } else {

                echo '<p style="color: red; font-size: 2em;">Invoice Insert Fail</p>';
                echo $wpdb->last_error;

            }
        } elseif (is_numeric($_POST['accounts_invoices_id'])) {
            if ($wpdb->update(
	        $wpdb->prefix . 'accounts_invoices',
	            array(
		        'client' => $client,
		        'invoice_date' => $invoice_date,
	        	'monthly' => $monthly,
	        	'yearly' => $yearly,
	        	'item1' => $item1,
	        	'price1' => $price1,
	        	'item2' => $item2,
	        	'price2' => $price2,
	        	'item3' => $item3,
	        	'price3' => $price3,
	        	'item4' => $item4,
	        	'price4' => $price4,
	        	'item5' => $item5,
	        	'price5' => $price5,
	        	'invoice_status' => $invoice_status,
	        	'date_paid' => $date_paid,
	        	'payment_method' => $payment_method,
	        	'mileage' => $mileage,
	        	'notes' => $notes
	            ),
	            array( 'ID' => $_POST['accounts_invoices_id'] ),
	            array(
	        	'%d',
	        	'%s',
	        	'%d',
	        	'%d',
	        	'%s',
	        	'%f',
	        	'%s',
	        	'%f',
	        	'%s',
	        	'%f',
	        	'%s',
        		'%f',
	        	'%s',
	        	'%f',
	        	'%d',
	        	'%s',
	        	'%d',
	        	'%d',
        		'%s'
	            ), 
	            array( '%d' )
            ) != false) {
                echo '<p style="color: green;">Invoice ' . absint($_POST['accounts_invoices_id']) . ' updated successfully.</p>';
            } elseif ($wpdb->last_error) {

                echo '<p style="color: red; font-size: 2em;">Invoice Update Fail</p>';
                echo $wpdb->last_error;

            } else {

                echo '<p style="color: red; font-size: 2em;">Nothing was changed</p>';

            }
        }
    }

    if(current_user_can('manage_options') && isset($_GET['copy_yearly'])) {

        if ($_GET['copy_yearly'] == 'true') {

            $this->wpa_locate_last_years_invoices();

        }

    }

    if(current_user_can('manage_options') && isset($_GET['copy_monthly'])) {

        if ($_GET['copy_monthly'] == 'true') {

            $this->wpa_locate_last_months_invoices();

        }

    }

    if(current_user_can('manage_options') && isset($_POST['accounts_invoices_action']) && isset($_POST['accounts_invoices_action-2']) && ($_POST['accounts_invoices_action'] == 'copy_yearly' || $_POST['accounts_invoices_action-2'] == 'copy_yearly')) {

        foreach ($_POST['invoice_id'] as $invoice_id) {

            $wpdb->query($wpdb->prepare("

INSERT INTO {$wpdb->prefix}accounts_invoices
SELECT NULL AS ID, client, DATE_ADD(invoice_date, INTERVAL 1 YEAR) AS invoice_date, monthly, yearly, item1, price1, item2, price2, item3, price3, item4, price4, item5, price5, 1 AS invoice_status, NULL AS date_paid, NULL AS payment_method, NULL AS notes, NULL AS mileage
FROM {$wpdb->prefix}accounts_invoices
WHERE ID = %d

            ", $invoice_id));

        }

    }

    if(current_user_can('manage_options') && isset($_POST['accounts_invoices_action']) && isset($_POST['accounts_invoices_action-2']) && ($_POST['accounts_invoices_action'] == 'copy_monthly' || $_POST['accounts_invoices_action-2'] == 'copy_monthly')) {

        foreach ($_POST['invoice_id'] as $invoice_id) {

            $wpdb->query($wpdb->prepare("

INSERT INTO {$wpdb->prefix}accounts_invoices
SELECT NULL AS ID, client, DATE_ADD(invoice_date, INTERVAL 1 MONTH) AS invoice_date, monthly, yearly, item1, price1, item2, price2, item3, price3, item4, price4, item5, price5, 1 AS invoice_status, NULL AS date_paid, NULL AS payment_method, NULL AS notes, NULL AS mileage
FROM {$wpdb->prefix}accounts_invoices
WHERE ID = %d

            ", $invoice_id));

        }

    }

    return absint($invoice_status);

}

function wpa_add_invoice() {

    $invoice_id = 0;
    if(isset($_GET['id']) && !isset($_GET['copy'])) { $invoice_id = absint($_GET['id']); }

    global $edit_invoices;
    if (isset($_GET['id'])) $edit_invoices = $this->wpa_accounts_get_row(absint($_GET['id']),'accounts_invoices');   

    add_meta_box('accounts-meta', __('Invoice Details'), array($this, 'wpa_invoice_meta_box'), 'accounts-invoices', 'normal', 'core' );
?>

    <div class="wrap">
      <div id="faq-wrapper">
        <form method="post" id="wpa_invoice_form" action="?page=manage-invoices">

<?php wp_nonce_field('wpaccounts'); ?>

          <h2>
          <?php if( $invoice_id == 0 ) {
                $tf_title = __('Add Invoice');
          }else {

            if (is_object($edit_invoices) && $edit_invoices->invoice_status != 1) {

                $tf_title = __('Edit Receipt ' . $invoice_id);

            } else {

                $tf_title = __('Edit Invoice ' . $invoice_id);

            }

          }
          echo $tf_title;
          ?>
          </h2>

<?php
        if (!isset($_GET['copy']) && $invoice_id != 0) {
?>

            <p>
                <a href="?page=manage-invoices&amp;id=<?php echo $invoice_id ?>&amp;edit=true&amp;copy=true" title="Copy invoice <?php echo $invoice_id; ?>">Copy</a> |
                <a href="?page=manage-invoices&amp;send=<?php echo $invoice_id ?>" onclick="return confirm('Are you sure you want to send this invoice?');">Send</a> |
                <a href="?page=manage-invoices&amp;send_copy=<?php echo $invoice_id ?>" onclick="return confirm('Are you sure you want to send a copy of this invoice?');">Send Copy</a> |
                <a href="?page=client-statement&amp;id=<?php echo $edit_invoices->client; ?>">Statement</a>
            </p>

<?php
        }
?>

          <div id="poststuff" class="metabox-holder">
            <?php do_meta_boxes('accounts-invoices', 'normal','low'); ?>
          </div>
          <input type="hidden" name="accounts_invoices_id" value="<?php echo $invoice_id; ?>" />
          <input type="submit" value="<?php echo $tf_title;?>" name="accounts_add_invoice" id="accounts_add_invoice" class="button-secondary" />

        </form>
      </div>
    </div>
<?php
}

function wpa_locate_last_years_invoices() {

    $options = get_option('wp_accounts_options');
?>
<div class="wrap">
  <div class="icon32" id="icon-edit"><br></div>
  <h2>Yearly Recurrent Invoices from <?php echo date('F Y', strtotime('-1 year +1 month')); ?></h2>

  <form method="post" action="?page=manage-invoices" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_invoices_action">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
            <option value="send"><?php _e('Send')?></option>
            <option value="send_copy"><?php _e('Send Copy')?></option>
            <option value="copy_yearly"><?php _e('Copy into ' . ((date('n') == 12) ? date('Y') + 1 : date('Y'))); ?></option>
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID')?></th>
          <th class="manage-column"><?php _e('Client')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID')?></th>
          <th class="manage-column"><?php _e('Client')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          $invoices = $this->wpa_accounts_get_data('accounts_invoices_last_year');
          if($invoices){
           $i=0;
           $total_price=0;
           $total_cancelled=0;
           foreach($invoices as $invoice) { 
               $i++;
               if ($invoice->invoice_status != 3) { $total_price = $total_price + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; } else { $total_cancelled = $total_cancelled + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; }
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $invoice->ID; ?>" name="invoice_id[]" />
        </th>
          <td>
          <strong<?php if ($invoice->invoice_status == 3) { echo ' style="color: red; text-decoration: line-through;"'; } ?>><?php echo $invoice->ID; ?></strong>

<?php
	if ($options['recurrent'] == 'true' && $invoice->monthly == true) {
		echo '(monthly)';
	} elseif ($options['recurrent'] == 'true' && $invoice->yearly == true) {
		echo '(yearly)';
	} 
?>

          <div class="row-actions-visible">
          <a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true">Edit</a> | 
          <a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true&amp;copy=true">Copy</a> |
          <!--<span class="delete"><a href="?page=manage-invoices&amp;delete=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to delete this invoice?');">Delete</a> | </span>-->
          <a href="?page=manage-invoices&amp;send=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to send this invoice?');">Send</a> |
          <a href="?page=manage-invoices&amp;send_copy=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to send a copy of this invoice?');">Send Copy</a>
          </div>
          </td>
          <td><?php $client=$this->wpa_client_from_id($invoice->client); if ($client->company) { echo $client->company . ', '; } echo $client->contact; ?><br />

          <span class="statement"><a href="?page=client-statement&amp;id=<?php echo $invoice->client; ?>">Statement</a> |
          <span class="edit"><a href="?page=manage-clients&amp;id=<?php echo $invoice->client; ?>&amp;edit=true">Edit</a></span><?php if ($client->email) { ?> |
          <span class="email"><a href="mailto:<?php echo $client->email; ?>">Email</a></span><?php } ?></td>
          <td>Raised: <?php echo date('jS F Y',strtotime($invoice->invoice_date)); ?><?php

        if ($invoice->date_paid && absint($invoice->invoice_status) != 3) { echo('<br />Paid: ' . date('jS F Y',strtotime($invoice->date_paid))); }

          ?></td>
          <td<?php if ($invoice->invoice_status == 3) { echo ' style="color: red; text-decoration: line-through;"'; } ?>>&pound;<?php echo number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2); ?></td>
        </tr>
        <?php
           }
           $i++;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row"></th>
          <td></td>
          <td></td>

<?php if ($total_cancelled) { ?>

          <td style="color: red;">Cancelled: &pound;<?php echo number_format($total_cancelled, 2); ?></td>

<?php } else { ?>

          <td></td>

<?php } ?>

          <td>Total: <strong>&pound;<?php echo number_format($total_price, 2); ?></strong></td>
        </tr>
        <?php
        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no invoices.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_invoices_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
            <option value="send"><?php _e('Send')?></option>
            <option value="send_copy"><?php _e('Send Copy')?></option>
            <option value="copy_yearly"><?php _e('Copy into ' . ((date('n') == 12) ? date('Y') + 1 : date('Y'))); ?></option>
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>

  </form>
</div>
<?php
}

function wpa_locate_last_months_invoices() {

    $options = get_option('wp_accounts_options');
?>
<div class="wrap">
  <div class="icon32" id="icon-edit"><br></div>
  <h2>Monthly Recurrent Invoices from <?php echo date('F Y', strtotime('-1 month')); ?></h2>

  <form method="post" action="?page=manage-invoices" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_invoices_action">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
            <option value="send"><?php _e('Send')?></option>
            <option value="send_copy"><?php _e('Send Copy')?></option>
            <option value="copy_monthly"><?php _e('Copy into ' . date('F Y')); ?></option>
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID')?></th>
          <th class="manage-column"><?php _e('Client')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID')?></th>
          <th class="manage-column"><?php _e('Client')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          $invoices = $this->wpa_accounts_get_data('accounts_invoices_last_month');
          if($invoices){
           $i=0;
           $total_price=0;
           $total_cancelled=0;
           foreach($invoices as $invoice) { 
               $i++;
               if ($invoice->invoice_status != 3) { $total_price = $total_price + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; } else { $total_cancelled = $total_cancelled + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; }
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $invoice->ID; ?>" name="invoice_id[]" />
        </th>
          <td>
          <strong<?php if ($invoice->invoice_status == 3) { echo ' style="color: red; text-decoration: line-through;"'; } ?>><?php echo $invoice->ID; ?></strong>

<?php
	if ($options['recurrent'] == 'true' && $invoice->monthly == true) {
		echo '(monthly)';
	} elseif ($options['recurrent'] == 'true' && $invoice->yearly == true) {
		echo '(yearly)';
	} 
?>

          <div class="row-actions-visible">
          <span class="edit"><a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true">Edit</a> | 
          <span class="edit"><a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true&amp;copy=true">Copy</a> | </span>
          <!--<span class="delete"><a href="?page=manage-invoices&amp;delete=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to delete this invoice?');">Delete</a> | </span>-->
          <span class="send"><a href="?page=manage-invoices&amp;send=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to send this invoice?');">Send</a> |
          <span class="send-copy"><a href="?page=manage-invoices&amp;send_copy=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to send a copy of this invoice?');">Send Copy</a></span>
          </div>
          </td>
          <td><?php $client=$this->wpa_client_from_id($invoice->client); if ($client->company) { echo $client->company . ', '; } echo $client->contact; ?><br />

          <span class="statement"><a href="?page=client-statement&amp;id=<?php echo $invoice->client; ?>">Statement</a> |
          <span class="edit"><a href="?page=manage-clients&amp;id=<?php echo $invoice->client; ?>&amp;edit=true">Edit</a></span><?php if ($client->email) { ?> |
          <span class="email"><a href="mailto:<?php echo $client->email; ?>">Email</a></span><?php } ?></td>
          <td>Raised: <?php echo date('jS F Y',strtotime($invoice->invoice_date)); ?><?php

        if ($invoice->date_paid && absint($invoice->invoice_status) != 3) { echo('<br />Paid: ' . date('jS F Y',strtotime($invoice->date_paid))); }

          ?></td>
          <td<?php if ($invoice->invoice_status == 3) { echo ' style="color: red; text-decoration: line-through;"'; } ?>>&pound;<?php echo number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2); ?></td>
        </tr>
        <?php
           }
           $i++;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row"></th>
          <td></td>
          <td></td>

<?php if ($invoice->date_paid) { ?>

          <td style="color: red;">Cancelled: &pound;<?php echo number_format($total_cancelled, 2); ?></td>

<?php } else { ?>

          <td></td>

<?php } ?>

          <td>Total: <strong>&pound;<?php echo number_format($total_price, 2); ?></strong></td>
        </tr>
        <?php
        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no invoices.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_invoices_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
            <option value="send"><?php _e('Send')?></option>
            <option value="send_copy"><?php _e('Send Copy')?></option>
            <option value="copy_monthly"><?php _e('Copy into ' . date('F Y')); ?></option>
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>

  </form>
</div>
<?php
}

function wpa_manage_invoices($invoice_status){

	$options = get_option('wp_accounts_options');

    $date_ranges = array(
        'month-to-date' => array(
            'title' => 'Month to date',
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d')
        ),
        'quarter-to-date' => array(
            'title' => 'Quarter to date',
            'from' => date(sprintf('Y-%s-01', floor((date('n') - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d')
        ),
        'year-to-date' => array(
            'title' => 'Year to date',
            'from' => date('Y-01-01'),
            'to' => date('Y-m-d')
        )
    );

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['accounting-period-to-date'] = array(
            'title' => 'Accounting period to date',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y-') . $options['accounting_period_start']),
            'to' => date('Y-m-d')
        );

    }

    $date_ranges = array_merge($date_ranges, array(
        'last-month' => array(
            'title' => 'Last month',
            'from' => date('Y-m-d', strtotime('first day of previous month')),
            'to' => date('Y-m-d', strtotime('last day of previous month'))
        ),
        'last-quarter' => array(
            'title' => 'Last quarter',
            'from' => date(sprintf('%s-%s-01', date('Y', strtotime('-3 month')), floor((date('n', strtotime('-3 month')) - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d', strtotime('last day of -' . (((date('n') - 1) % 3) + 1) . ' month'))
        ),
        'last-year' => array(
            'title' => 'Last year',
            'from' => date('Y-01-01', strtotime('-1 year')),
            'to' => date('Y-12-31', strtotime('-1 year')),
        )
    ));

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['last-accounting-period'] = array(
            'title' => 'Last accounting period',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 2) . '-' . $options['accounting_period_start'] : (date('Y') - 1) . '-' . $options['accounting_period_start']),
            'to' => date('Y-m-d', strtotime(((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y') . '-' . $options['accounting_period_start']) . ' -1 day'))
        );

    }

?>
<div class="wrap">
  <div class="icon32" id="icon-edit"><br></div>
  <h2><?php if (2 === $invoice_status) { _e('Manage Receipts'); } else { _e('Manage Invoices'); } ?></h2>
    <p><label for="date-range">Date Range:</label>
    <select name="date-range" id="date-range">
        <option value=""<?php selected(isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]), false); ?>><?php echo (!(isset($_GET['invoice-type']) && $_GET['invoice-type']) ? 'All time' : 'Last 365 days'); ?></option>
<?php

    foreach ($date_ranges as $key => $date_range) {

?>
        <option value="<?php echo esc_attr($key); ?>"<?php selected((isset($_GET['date-range']) ? $_GET['date-range'] : ''), $key); ?>><?php echo esc_html($date_range['title']); ?></option>
<?php

    }

?>
    </select></p>
    <p><label for="invoice-type">Invoice Type:</label>
    <select name="invoice-type" id="invoice-type">
        <option value=""<?php selected((isset($_GET['invoice-type']) && isset($date_ranges[$_GET['invoice-type']])), false); ?>>Invoices</option>
        <option value="2"<?php selected((isset($_GET['invoice-type']) ? absint($_GET['invoice-type']) : ''), 2); ?>>Paid invoices (receipts)</option>
        <option value="3"<?php selected((isset($_GET['invoice-type']) ? absint($_GET['invoice-type']) : ''), 3); ?>>Cancelled invoices</option>
    </select></p>
<script type="text/javascript">
(function($) {
    $('#date-range, #invoice-type').change(function() {
        window.location.href = '<?php echo add_query_arg(array('page' => 'manage-invoices'), admin_url('admin.php')); ?>&date-range=' + $('#date-range').val() + '&invoice-type=' + $('#invoice-type').val();
    });
})(jQuery);
</script>
<?php
	if (isset($options['recurrent']) && $options['recurrent'] == 'true') {
?>
<p><a href="?page=manage-invoices&amp;copy_yearly=true">View yearly recurrent invoices from <?php echo date('F Y', strtotime('-1 year +1 month')); ?></a></p>
<p><a href="?page=manage-invoices&amp;copy_monthly=true">View monthly recurrent invoices from <?php echo date('F Y', strtotime('-1 month')); ?></a></p>
<?php
	} 
?>

  <form method="get" action="" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p><input type="hidden" name="page" value="manage-invoices" />Edit <?php if (2 === $invoice_status) { _e('Receipt'); } else { _e('Invoice'); } ?>: <input name="id" size="10" type="text" value="" /> <input type="submit" name="submit" class="button-secondary" value="Edit" /><input type="hidden" name="edit" value="true" /></p>
  </form>

  <form method="get" action="">

<?php wp_nonce_field('view-stats-for'); ?>

    <p><input type="hidden" name="page" value="manage-invoices" />View stats for invoices with <input name="item" size="10" type="text" value="" /> in the items' description <input type="submit" name="submit" class="button-secondary" value="View" /></p>
  </form>
<?php

    if (
        isset($_GET['item']) &&
        sanitize_text_field($_GET['item']) &&
        check_ajax_referer('view-stats-for', false, false)
    ) {

        $item = sanitize_text_field($_GET['item']);
        $accounting_period_start = '04-01';

        if (isset($options['accounting_period_start']) && preg_match("/^(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $options['accounting_period_start'])) {

            $accounting_period_start = $options['accounting_period_start'];

        }

        $accounting_period_start = explode('-', $accounting_period_start);

        global $wpdb;

        $report = $wpdb->get_results($wpdb->prepare("

SELECT IF (
    DATE_ADD(DATE_ADD(MAKEDATE(YEAR({$wpdb->prefix}accounts_invoices.invoice_date), 1), INTERVAL (MONTH({$wpdb->prefix}accounts_invoices.invoice_date))-1 MONTH), INTERVAL (DAY({$wpdb->prefix}accounts_invoices.invoice_date))-1 DAY)
    <
    DATE_ADD(DATE_ADD(MAKEDATE(YEAR({$wpdb->prefix}accounts_invoices.invoice_date), 1), INTERVAL (%d)-1 MONTH), INTERVAL (%d)-1 DAY),
    YEAR({$wpdb->prefix}accounts_invoices.invoice_date) - 1,
    YEAR({$wpdb->prefix}accounts_invoices.invoice_date)
) AS invoice_year, IFNULL(SUM({$wpdb->prefix}accounts_invoices.price1),0)+IFNULL(SUM({$wpdb->prefix}accounts_invoices.price2),0)+IFNULL(SUM({$wpdb->prefix}accounts_invoices.price3),0)+IFNULL(SUM({$wpdb->prefix}accounts_invoices.price4),0)+IFNULL(SUM({$wpdb->prefix}accounts_invoices.price5),0) AS invoice_total
FROM {$wpdb->prefix}accounts_invoices
WHERE {$wpdb->prefix}accounts_invoices.item1 LIKE %s
OR {$wpdb->prefix}accounts_invoices.item2 LIKE %s
OR {$wpdb->prefix}accounts_invoices.item3 LIKE %s
OR {$wpdb->prefix}accounts_invoices.item4 LIKE %s
OR {$wpdb->prefix}accounts_invoices.item5 LIKE %s
GROUP BY invoice_year;

",
        $accounting_period_start[0],
        $accounting_period_start[1],
        '%' . $item . '%',
        '%' . $item . '%',
        '%' . $item . '%',
        '%' . $item . '%',
        '%' . $item . '%'
        ));

?>
<h3>Stats for invoices containing "<?php echo esc_html($item); ?>"</h3>
<?php

        if ($report) {

?>
<ul>
<?php

            foreach ($report as $invoice_year) {

?>
<li><strong><?php echo esc_html($invoice_year->invoice_year . '/' . ($invoice_year->invoice_year + 1)); ?></strong>: &pound;<?php echo esc_html(number_format($invoice_year->invoice_total, 2)); ?></li>
<?php

            }

?>
</ul>
<?php

        } else {

?>
<p>Sorry, no invoices found!</p>
<?php

        }

    }

?>
  <form method="post" action="?page=manage-invoices" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_invoices_action">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
            <option value="send"><?php _e('Send')?></option>
            <option value="send_copy"><?php _e('Send Copy')?></option>
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID')?></th>
          <th class="manage-column"><?php _e('Client')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID')?></th>
          <th class="manage-column"><?php _e('Client')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
<?php

          $invoices = $this->wpa_accounts_get_data(
                'accounts_invoices',
                (2 === $invoice_status),
                (isset($_GET['invoice-type']) && absint($_GET['invoice-type']) ? absint($_GET['invoice-type']) : 1),
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['from'] : false),
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['to'] : false)
          );

          if ($invoices) {
           $i=0;
           $total_price=0;
           $total_cancelled=0;
           foreach($invoices as $invoice) { 
               $i++;
               if ($invoice->invoice_status != 3) { $total_price = $total_price + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; } else { $total_cancelled = $total_cancelled + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; }
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $invoice->ID; ?>" name="invoice_id[]" />
        </th>
          <td>
          <strong<?php if ($invoice->invoice_status == 3) { echo ' style="color: red; text-decoration: line-through;"'; } ?>><?php echo $invoice->ID; ?></strong>

<?php
	if (isset($options['recurrent']) && $options['recurrent'] == 'true' && $invoice->monthly == true) {
		echo '(monthly)';
	} elseif (isset($options['recurrent']) && $options['recurrent'] == 'true' && $invoice->yearly == true) {
		echo '(yearly)';
	} 
?>

          <div class="row-actions-visible">
          <span class="edit"><a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true">Edit</a> | 
          <span class="edit"><a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true&amp;copy=true">Copy</a> | </span>
          <!--<span class="delete"><a href="?page=manage-invoices&amp;delete=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to delete this invoice?');">Delete</a> | </span>-->
          <span class="send"><a href="?page=manage-invoices&amp;send=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to send this invoice?');">Send</a> |
          <span class="send-copy"><a href="?page=manage-invoices&amp;send_copy=<?php echo $invoice->ID; ?>" onclick="return confirm('Are you sure you want to send a copy of this invoice?');">Send Copy</a></span>
          </div>
          </td>
          <td><strong><?php $client=$this->wpa_client_from_id($invoice->client); if ($client->company) { echo $client->company . ', '; } echo $client->contact; ?></strong><br />
<?php

    if (isset($client->telephone) || isset($client->mobile)) {

?>
          <span class="telephone">
<?php

        if (isset($client->telephone)) {

?>
Tel: <?php echo $this->wpa_format_telephone($this->wpa_clean_telephone($client->telephone)); ?>
<?php

            if (isset($client->mobile)) {

?>
 | 
<?php

            }

        }

        if (isset($client->mobile)) {

?>
Mob: <?php echo $this->wpa_format_telephone($this->wpa_clean_telephone($client->mobile)); ?>
<?php

        }

?>
</span><br />
<?php

    }

?>

          <span class="statement"><a href="?page=client-statement&amp;id=<?php echo $invoice->client; ?>">Statement</a> |
          <span class="edit"><a href="?page=manage-clients&amp;id=<?php echo $invoice->client; ?>&amp;edit=true">Edit</a></span><?php if ($client->email) { ?> |
          <span class="email"><a href="mailto:<?php echo $client->email; ?>">Email</a></span><?php } ?></td>
          <td>Raised: <?php echo date('jS F Y',strtotime($invoice->invoice_date)); ?><?php

        if (absint($invoice->invoice_status) == 2) { echo('<br />Paid: ' . date('jS F Y',strtotime($invoice->date_paid))); }

          ?></td>
          <td<?php if ($invoice->invoice_status == 3) { echo ' style="color: red; text-decoration: line-through;"'; } ?>>&pound;<?php echo number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2); ?></td>
        </tr>
        <?php
           }
           $i++;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row"></th>
          <td></td>
          <td></td>
          <td></td>

<?php if (isset($_GET['invoice-type']) && 3 === (int) $_GET['invoice-type']) { ?>

          <td style="color: red;">Total: &pound;<?php echo number_format($total_cancelled, 2); ?></td>

<?php } else { ?>

          <td>Total: <strong>&pound;<?php echo number_format($total_price, 2); ?></strong></td>

<?php } ?>

        </tr>
        <?php
        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no invoices.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_invoices_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
            <option value="send"><?php _e('Send')?></option>
            <option value="send_copy"><?php _e('Send Copy')?></option>
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>

  </form>
</div>
<?php
}

function wpa_manage_clients_options(){
    $this->wpa_clients_action();
    if (empty($_GET['edit'])) {
        $this->wpa_manage_clients();
    } else {
        $this->wpa_add_client();   
    }
}

function wpa_clients_action(){
    global $wpdb;

    if(current_user_can('manage_options') && isset($_GET['delete'])) {

		check_admin_referer('wpaccounts');

        $client_id = absint($_GET['delete']);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}accounts_clients WHERE ID=%d;",$client_id));
    }

    if(current_user_can('manage_options') && isset($_POST['accounts_add_client']) and isset($_POST['accounts_clients_company']) and isset($_POST['accounts_clients_address1']) and isset($_POST['accounts_clients_address2']) and isset($_POST['accounts_clients_address3']) and isset($_POST['accounts_clients_town']) and isset($_POST['accounts_clients_county']) and isset($_POST['accounts_clients_postcode']) and isset($_POST['accounts_clients_country']) and isset($_POST['accounts_clients_contact']) and isset($_POST['accounts_clients_telephone']) and isset($_POST['accounts_clients_mobile']) and isset($_POST['accounts_clients_email']) and isset($_POST['accounts_clients_notes']) ) {

		check_admin_referer('wpaccounts');

		$_POST = array_map( 'stripslashes_deep', $_POST);

        $company = empty($_POST['accounts_clients_company']) ? NULL : sanitize_text_field($_POST['accounts_clients_company']);
        $address1 = empty($_POST['accounts_clients_address1']) ? NULL : sanitize_text_field($_POST['accounts_clients_address1']);
        $address2 = empty($_POST['accounts_clients_address2']) ? NULL : sanitize_text_field($_POST['accounts_clients_address2']);
        $address3 = empty($_POST['accounts_clients_address3']) ? NULL : sanitize_text_field($_POST['accounts_clients_address3']);
        $town = empty($_POST['accounts_clients_town']) ? NULL : sanitize_text_field($_POST['accounts_clients_town']);
        $county = empty($_POST['accounts_clients_county']) ? NULL : sanitize_text_field($_POST['accounts_clients_county']);
        $postcode = empty($_POST['accounts_clients_postcode']) ? NULL : sanitize_text_field($_POST['accounts_clients_postcode']);
        $country = empty($_POST['accounts_clients_country']) ? NULL : sanitize_text_field($_POST['accounts_clients_country']);
        $contact = empty($_POST['accounts_clients_contact']) ? NULL : sanitize_text_field($_POST['accounts_clients_contact']);
        $telephone = empty($_POST['accounts_clients_telephone']) ? NULL : $this->wpa_clean_telephone($_POST['accounts_clients_telephone']);
        $mobile = empty($_POST['accounts_clients_mobile']) ? NULL : $this->wpa_clean_telephone($_POST['accounts_clients_mobile']);
        $email = empty($_POST['accounts_clients_email']) ? NULL : sanitize_text_field($_POST['accounts_clients_email']);
        $notes = empty($_POST['accounts_clients_notes']) ? NULL : sanitize_text_field($_POST['accounts_clients_notes']);

        if(empty($_POST['accounts_clients_id'])) {
            if ($wpdb->insert(
	        $wpdb->prefix . 'accounts_clients',
	            array(
		        'company' => $company,
		        'address1' => $address1,
	        	'address2' => $address2,
	        	'address3' => $address3,
	        	'town' => $town,
	        	'county' => $county,
	        	'postcode' => $postcode,
	        	'country' => $country,
	        	'contact' => $contact,
	        	'telephone' => $telephone,
	        	'mobile' => $mobile,
	        	'email' => $email,
	        	'notes' => $notes
	            ), 
	            array(
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
        		'%s'
	            )
            ) != false) {
                echo '<p style="color: green;">Client Insert Success</p>';
            } else {
                echo '<p style="color: red; font-size: 2em;">Client Insert Fail</p>';
echo $wpdb->last_error;
            }
        } elseif (is_numeric($_POST['accounts_clients_id'])) {
            if ($wpdb->update(
	        $wpdb->prefix . 'accounts_clients',
	            array(
		        'company' => $company,
		        'address1' => $address1,
	        	'address2' => $address2,
	        	'address3' => $address3,
	        	'town' => $town,
	        	'county' => $county,
	        	'postcode' => $postcode,
	        	'country' => $country,
	        	'contact' => $contact,
	        	'telephone' => $telephone,
	        	'mobile' => $mobile,
	        	'email' => $email,
	        	'notes' => $notes
	            ),
	            array( 'ID' => $_POST['accounts_clients_id'] ),
	            array(
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
	        	'%s',
        		'%s'
	            ), 
	            array( '%d' )
            ) != false) {
                echo '<p style="color: green;">Client Update Success</p>';
            } elseif ($wpdb->last_error) {

                echo '<p style="color: red; font-size: 2em;">Client Update Fail</p>';
                echo $wpdb->last_error;

            } else {

                echo '<p style="color: red; font-size: 2em;">Nothing was changed</p>';

            }
        }
    }  
}

function wpa_add_client(){
    $client_id = 0;
    if(isset($_GET['id'])) $client_id = absint($_GET['id']);

    global $edit_clients;
    if ($client_id) $edit_clients = $this->wpa_accounts_get_row($client_id,'accounts_clients');   

    add_meta_box('accounts-meta', __('Client Details'), array($this, 'wpa_client_meta_box'), 'accounts-clients', 'normal', 'core' );
?>

    <div class="wrap">
      <div id="faq-wrapper">
        <form method="post" action="?page=manage-clients">

<?php wp_nonce_field('wpaccounts'); ?>

          <h2>
          <?php if( $client_id == 0 ) {
                $tf_title = __('Add Client');
          }else {
                $tf_title = __('Edit Client');
          }
          echo $tf_title;
          ?>
          </h2>

<?php
        if ($client_id != 0) {
?>

            <p>
                <a href="?page=client-statement&amp;id=<?php echo $client_id; ?>">Statement</a>
            </p>

<?php
        }
?>

          <div id="poststuff" class="metabox-holder">
            <?php do_meta_boxes('accounts-clients', 'normal','low'); ?>
          </div>
          <input type="hidden" name="accounts_clients_id" value="<?php echo $client_id; ?>" />
          <input type="submit" value="<?php echo $tf_title;?>" name="accounts_add_client" id="accounts_add_client" class="button-secondary">
        </form>
      </div>
    </div>
<?php
}

function wpa_manage_clients() {
?>
<div class="wrap">
  <div class="icon32" id="icon-edit"><br></div>
  <h2><?php _e('Manage Clients') ?></h2>
  <form method="post" action="?page=manage-clients" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_clients_action">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&amp;edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('Company')?></th>
          <th class="manage-column"><?php _e('Town')?></th>
          <th class="manage-column"><?php _e('Telephone')?></th>
          <th class="manage-column"><?php _e('Mobile')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('Company')?></th>
          <th class="manage-column"><?php _e('Town')?></th>
          <th class="manage-column"><?php _e('Telephone')?></th>
          <th class="manage-column"><?php _e('Mobile')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          $clients = $this->wpa_accounts_get_data('accounts_clients', false);
          if($clients){
           $i=0;
           foreach($clients as $client) { 
               $i++;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $client->ID; ?>" name="client_id[]" />
        </th>
          <td>
          <strong><?php if ($client->company) { if (strpos(strtolower($client->company), 'the ') === 0) { echo substr($client->company, 4) . ' (The), '; } else { echo $client->company . ', '; } } echo $client->contact; ?></strong>
          <div class="row-actions-visible">
          <span class="statement"><a href="?page=client-statement&amp;id=<?php echo $client->ID; ?>">Statement</a> |
          <span class="edit"><a href="?page=manage-clients&amp;id=<?php echo $client->ID; ?>&amp;edit=true">Edit</a></span><?php if ($client->email) { ?> |
          <span class="email"><a href="mailto:<?php echo $client->email; ?>">Email</a></span><?php } ?>
          <!--<span class="delete"><a href="?page=manage-clients&amp;delete=<?php echo $client->ID; ?>" onclick="return confirm('Are you sure you want to delete this client?');">Delete</a></span>-->
          </div>
          </td>
          <td><?php echo $client->town; ?></td>
          <td><?php echo $this->wpa_format_telephone($this->wpa_clean_telephone($client->telephone)); ?></td>
          <td><?php echo $this->wpa_format_telephone($this->wpa_clean_telephone($client->mobile)); ?></td>
        </tr>
        <?php
           }
        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no clients.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_clients_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Client')?>" onclick="window.location='?page=manage-clients&amp;edit=true'" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Invoice')?>" onclick="window.location='?page=manage-invoices&amp;edit=true'" />
    </p>

  </form>
</div>
<?php
}

function wpa_client_statement_options(){
    $this->wpa_clients_action();
    if (empty($_GET['id'])) {
        $this->wpa_manage_clients();
    } else {
        $this->wpa_client_statement();   
    }
}

function wpa_client_statement() {
    $client_id = absint($_GET['id']);
    $invoices = $this->wpa_client_statement_get_data($client_id);
    $options = get_option('wp_accounts_options');
    $client_name = '';
?>
<div class="wrap">
  <div class="icon32" id="icon-edit"><br></div>

<?php
    if ($invoices[0]->company) { $client_name = $invoices[0]->company . ', '; }
    $client_name .= $invoices[0]->contact;
    $client = $this->wpa_client_from_id($client_id);
?>

  <h2><?php _e($client_name . ' Statement'); ?></h2>

<p>
<?php

    if (isset($client->telephone)) {

?>
Telphone: <?php echo $this->wpa_format_telephone($this->wpa_clean_telephone($client->telephone)); ?><br />
<?php

    }

    if (isset($client->mobile)) {

?>
Mobile: <?php echo $this->wpa_format_telephone($this->wpa_clean_telephone($client->mobile)); ?><br />
<?php

    }

    if (isset($client->email)) {

?>
Email: <a href="mailto:<?php echo $client->email; ?>"><?php echo $client->email; ?></a><br />
<?php

    }
?>
<a href="?page=manage-clients&amp;id=<?php echo $client_id; ?>&amp;edit=true">Edit Client</a></p>

    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
          <th class="manage-column"><?php _e('Invoice')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Status')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <th class="manage-column"><?php _e('Invoice')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Status')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          if($invoices){
           $i=0;
           $total_due=0;
           $total_price=0;
           $total_cancelled=0;
           foreach($invoices as $invoice) { 
               $i++;
               if ($invoice->status == 'Unpaid') { $total_due = $total_due + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; }
               elseif ($invoice->status == 'Paid') { $total_price = $total_price + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; }
               else { $total_cancelled = $total_cancelled + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5; }
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
          <td>
          <strong><a href="?page=manage-invoices&amp;id=<?php echo $invoice->ID; ?>&amp;edit=true" title="Edit invoice <?php echo $invoice->ID; ?>"<?php if ($invoice->status == 'Cancelled' || $invoice->status == 'Unpaid') { echo ' style="color: red;"'; } ?>><?php echo $invoice->ID; ?></a></strong>
          </td>
          <td><?php echo date('jS F Y',strtotime($invoice->invoice_date)); ?></td>
          <td><?php

if ($invoice->status == 'Paid') {
  echo 'Paid by ' . $invoice->payment_method . ' on ' . mysql2date('jS F Y', $invoice->date_paid);
} else {
  echo '<span style="color: red;">' . $invoice->status . '</span>';
}

?></td>
          <td<?php if ('Cancelled' === $invoice->status) { echo ' style="color: red; text-decoration: line-through;"'; } elseif ('Unpaid' === $invoice->status) { echo ' style="color: red;"'; } ?> align="right">&pound;<?php echo number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2); ?></td>
        </tr>
        <?php
           }
           $i++;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
          <td></td>
          <td></td>
          <td style="color: red;" align="right">Cancelled: &pound;<?php echo number_format($total_cancelled, 2); ?></td>
          <td align="right">Total: <strong>&pound;<?php echo number_format($total_price, 2); ?></strong></td>
        </tr>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
          <td></td>
          <td></td>
          <td></td>
          <td style="color: red;" align="right">Due Now: <strong>&pound;<?php echo number_format($total_due, 2); ?></strong></td>
        </tr>
        <?php
        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no invoices.')?></td></tr>   
        <?php
      }
        ?>
      </tbody>
    </table>

</div>

<div class="wrap">
<h2><?php _e('Download Statement'); ?></h2>

<p>Use the button below to download this statement in HTML format.</p>
<form method="post" name="download_statement_form" action="<?php echo rtrim(plugin_dir_url(__FILE__), '/'); ?>/download-client-statement.php?id=<?php echo $client_id; ?>">

<?php wp_nonce_field('wpaccounts'); ?>

<p class="submit"><input type="submit" name="Submit" value="Download Statement" /></p>
</form>
</div>

<?php
}

function wpa_export_csv_options(){
    $this->wpa_export_csv();
}

function wpa_export_csv() {

    $options = get_option('wp_accounts_options');

?>
<div class="wrap">
<h2>Export Accounts</h2>

<p>Use the buttons below to export last year's accounts in CSV format.</p>
<form method="post" name="export_raised_form" action="<?php echo rtrim(plugin_dir_url(__FILE__), '/'); ?>/export-invoices-raised.php">

<?php wp_nonce_field('wpaccounts'); ?>

<p class="submit"><input type="submit" name="Submit" value="Export Invoices Raised" /></p>
</form>
<form method="post" name="export_paid_form" action="<?php echo rtrim(plugin_dir_url(__FILE__), '/'); ?>/export-invoices-paid.php">

<?php wp_nonce_field('wpaccounts'); ?>

<p class="submit"><input type="submit" name="Submit" value="Export Invoices Paid" /></p>
</form>
<form method="post" name="export_payments_form" action="<?php echo rtrim(plugin_dir_url(__FILE__), '/'); ?>/export-payments.php">

<?php wp_nonce_field('wpaccounts'); ?>

<p class="submit"><input type="submit" name="Submit" value="Export Payments" /></p>
</form>

<?php
    if (isset($options['mileage']) && $options['mileage'] == 'true') {
?>

<form method="post" name="export_payments_form" action="<?php echo rtrim(plugin_dir_url(__FILE__), '/'); ?>/export-mileage.php">

<?php wp_nonce_field('wpaccounts'); ?>

<p class="submit"><input type="submit" name="Submit" value="Export Mileage" /></p>
</form>

<?php
	}
?>

</div>
<?php
}

function wpa_send_invoice($id, $send_copy = false, $output = true) {
    global $wpdb;

    $invoice = $wpdb->get_row($wpdb->prepare("SELECT {$wpdb->prefix}accounts_invoices.ID, {$wpdb->prefix}accounts_invoices.client, {$wpdb->prefix}accounts_clients.contact, {$wpdb->prefix}accounts_clients.company, {$wpdb->prefix}accounts_clients.address1, {$wpdb->prefix}accounts_clients.address2, {$wpdb->prefix}accounts_clients.address3, {$wpdb->prefix}accounts_clients.town, {$wpdb->prefix}accounts_clients.county, {$wpdb->prefix}accounts_clients.postcode, {$wpdb->prefix}accounts_clients.country, {$wpdb->prefix}accounts_clients.email, {$wpdb->prefix}accounts_invoices.invoice_date, {$wpdb->prefix}accounts_invoices.item1, {$wpdb->prefix}accounts_invoices.item2, {$wpdb->prefix}accounts_invoices.item3, {$wpdb->prefix}accounts_invoices.item4, {$wpdb->prefix}accounts_invoices.item5, {$wpdb->prefix}accounts_invoices.price1, {$wpdb->prefix}accounts_invoices.price2, {$wpdb->prefix}accounts_invoices.price3, {$wpdb->prefix}accounts_invoices.price4, {$wpdb->prefix}accounts_invoices.price5, {$wpdb->prefix}accounts_invoices.date_paid, {$wpdb->prefix}accounts_invoice_status.status, {$wpdb->prefix}accounts_payment_methods.payment_method FROM {$wpdb->prefix}accounts_invoices INNER JOIN {$wpdb->prefix}accounts_clients ON {$wpdb->prefix}accounts_invoices.client = {$wpdb->prefix}accounts_clients.ID INNER JOIN {$wpdb->prefix}accounts_invoice_status ON {$wpdb->prefix}accounts_invoices.invoice_status = {$wpdb->prefix}accounts_invoice_status.ID LEFT JOIN {$wpdb->prefix}accounts_payment_methods ON {$wpdb->prefix}accounts_invoices.payment_method = {$wpdb->prefix}accounts_payment_methods.ID WHERE {$wpdb->prefix}accounts_invoices.ID = %d;",$id));
    if ($invoice) {

      if (empty($invoice->email) || $send_copy == true) {

        $current_user = wp_get_current_user();

        if (isset($current_user->user_email) && $current_user->user_email) {

            $invoice_to = $current_user->user_email;

        } else {

            $invoice_to = get_option('admin_email');

        }

      } else {
        $invoice_to = $invoice->email;
      }

$days_late = floor((strtotime(Date("Y-m-d")) - strtotime($invoice->invoice_date)) / 86400);

if ('Paid' === $invoice->status) {
  $invoice_subject = 'Receipt ' . $invoice->ID . ' from ' . get_bloginfo('name');
} elseif ('Cancelled' === $invoice->status) {
  $invoice_subject = 'Cancelled Invoice ' . $invoice->ID . ' from ' . get_bloginfo('name');
} elseif ($days_late > 90) {
  $invoice_subject = 'FOR YOUR IMMEDIATE ATTENTION';
} elseif ($days_late > 30) {
  $invoice_subject = 'Overdue Invoice ' . $invoice->ID . ' from ' . get_bloginfo('name');
} else {
  $invoice_subject = 'Invoice ' . $invoice->ID . ' from ' . get_bloginfo('name');
}

$invoice_body = file_get_contents(plugin_dir_path(__FILE__) . 'email-header.inc');

$options = get_option('wp_accounts_options');

if ($invoice->status == 'Paid') {
  $invoice_body = str_replace("!*type*!", 'Receipt', $invoice_body);
} elseif ($days_late > 30) {
  $invoice_body = str_replace("!*type*!", 'Overdue Invoice', $invoice_body);
} else {
  $invoice_body = str_replace("!*type*!", 'Invoice', $invoice_body);
}

$invoice_body = str_replace("!*invoice*!", $invoice->ID, $invoice_body);
$invoice_body = str_replace("!*company*!", get_bloginfo('name'), $invoice_body);
$invoice_body = str_replace("!*email_font*!", $options['email_font'], $invoice_body);
$invoice_body = str_replace("!*email_color*!", $options['email_color'], $invoice_body);
$invoice_body = str_replace("!*link_color*!", $options['link_color'], $invoice_body);
$invoice_body = str_replace("!*hover_color*!", $options['hover_color'], $invoice_body);

if ($invoice->status == 'Unpaid' && $days_late > 90) {

    $invoice_body .= '<p style="color: red; font-weight: bold;">The invoice below is now more than 90 days overdue. Please make payment now or contact us to make other financial arrangements. Thank you.</p>' . "\r\n";

} elseif (($invoice->status == 'Paid' || $days_late <= 30) && isset($options['company_google_place_id']) && $options['company_google_place_id'] != '') {

    $invoice_body .= '<p>Thank you for using our services and feel free to reply to this email with any comments or concerns you have. If we did well, please take a moment to review us on <a href="https://search.google.com/local/writereview?placeid=' . $options['company_google_place_id'] . '" title="Google Review">Google My Business</a>!</p>' . "\r\n";

}

if (isset($options['company_logo'])) {

    $invoice_body .= '<h1><a href="' . home_url() . '" title="' . get_bloginfo('name') . '"><img src="cid:logo" title="' . get_bloginfo('name') . '" alt="' . get_bloginfo('name') . '" /></a></h1>' . "\r\n";

} else {

    $invoice_body .= '<h1><a href="' . home_url() . '" title="' . get_bloginfo('name') . '">' . get_bloginfo('name') . '</a></h1>' . "\r\n";

}

$invoice_body .= '<p>' . $options['company_address']. '<br />Telephone: ' . $this->wpa_format_telephone($this->wpa_clean_telephone($options['company_telephone'])) . '</p>' . "\r\n";

if ('Paid' === $invoice->status) {
  $invoice_body .= '<h2>Receipt</h2>' . "\r\n";
} elseif ('Cancelled' === $invoice->status) {
  $invoice_body .= '<h2><span style="color: red;">Cancelled</span> Invoice</h2>' . "\r\n";
} elseif ($days_late > 30) {
  $invoice_body .= '<h2><span style="color: red;">Overdue</span> Invoice</h2>' . "\r\n";
} else {
  $invoice_body .= '<h2>Invoice</h2>' . "\r\n";
}

$invoice_body .= '<p><strong>Invoice Date:</strong> ' . mysql2date('jS F Y', $invoice->invoice_date) . '</p>' . "\r\n";
$invoice_body .= '<p><strong>Invoice Number:</strong> ' . $invoice->ID . '</p>' . "\r\n";
$invoice_body .= '<p><strong>To:</strong></p>' . "\r\n";
$invoice_body .= '<p>' . $invoice->contact . '<br />' . "\r\n";
if ($invoice->company) { $invoice_body .= $invoice->company . '<br />' . "\r\n"; }
if ($invoice->address1) { $invoice_body .= $invoice->address1 . '<br />' . "\r\n"; }
if ($invoice->address2) { $invoice_body .= $invoice->address2 . '<br />' . "\r\n"; }
if ($invoice->address3) { $invoice_body .= $invoice->address3 . '<br />' . "\r\n"; }
$invoice_body .= $invoice->town . '<br />' . "\r\n";
if ($invoice->county) { $invoice_body .= $invoice->county . '<br />' . "\r\n"; }
$invoice_body .= $invoice->postcode . '</p>' . "\r\n";
$invoice_body .= '<table class="invoice" cellspacing=0>' . "\r\n" . '<tr><td><strong>Description:</strong></td><td><strong>Price:</strong></td></tr>' . "\r\n";
$invoice_body .= '<tr><td>' . $invoice->item1 . '</td><td' . ('Cancelled' === $invoice->status ? ' style="color:red;text-decoration:line-through"' : '') . '>&pound;' . number_format($invoice->price1, 2) . '</td></tr>' . "\r\n";
if ($invoice->item2) { $invoice_body .= '<tr><td>' . $invoice->item2 . '</td><td' . ('Cancelled' === $invoice->status ? ' style="color:red;text-decoration:line-through"' : '') . '>&pound;' . number_format($invoice->price2, 2) . '</td></tr>' . "\r\n"; }
if ($invoice->item3) { $invoice_body .= '<tr><td>' . $invoice->item3 . '</td><td' . ('Cancelled' === $invoice->status ? ' style="color:red;text-decoration:line-through"' : '') . '>&pound;' . number_format($invoice->price3, 2) . '</td></tr>' . "\r\n"; }
if ($invoice->item4) { $invoice_body .= '<tr><td>' . $invoice->item4 . '</td><td' . ('Cancelled' === $invoice->status ? ' style="color:red;text-decoration:line-through"' : '') . '>&pound;' . number_format($invoice->price4, 2) . '</td></tr>' . "\r\n"; }
if ($invoice->item5) { $invoice_body .= '<tr><td>' . $invoice->item5 . '</td><td' . ('Cancelled' === $invoice->status ? ' style="color:red;text-decoration:line-through"' : '') . '>&pound;' . number_format($invoice->price5, 2) . '</td></tr>' . "\r\n"; }
$invoice_body .= '<tr><td><strong>Total:</strong></td><td' . ('Cancelled' === $invoice->status ? ' style="color:red;text-decoration:line-through"' : '') . '><strong>&pound;' . number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2) . '</strong></td></tr>' . "\r\n" . '</table>' . "\r\n";

if (!in_array($invoice->status, array('Paid', 'Cancelled'), true)) {
$invoice_body .= '<p><strong>To pay by BACS</strong>, use' . ((isset($options['bank_name']) && $options['bank_name']) ? esc_html(' ' . $options['bank_name']) : '') . ' sort code <strong>' . esc_html($options['sort_code']) . '</strong>, account number <strong>' . esc_html($options['account_number']) . '</strong> and account name <strong>' . esc_html($options['account_name']) . '</strong>, using reference <strong>' . substr($invoice->ID . '-' . strtoupper(preg_replace('/[^a-zA-Z"\']/', '', $invoice->contact)),0,18) . '</strong> where possible. Please remember to send remittance advice so we know to look out for your payment.</p>' . "\r\n";

if (isset($options['cheques']) && $options['cheques'] == 'true') {

$invoice_body .= '<p><strong>To pay by Cheque</strong>, make your cheque payable to <strong>' . $options['company_name']. '</strong>, ' . $options['company_address']. '.</p>' . "\r\n";

} else {

$invoice_body .= '<p><strong>We regret that we cannot accept payments by Cheque</strong>.' . "\r\n";

}

if (
    class_exists('WooCommerce') &&
    isset($options['woocommerce']) &&
    'true' === $options['woocommerce']) {

$invoice_body .= '<p><a href="' . esc_url(add_query_arg(array(
    'invoice' => $invoice->ID,
    'client' => $invoice->client
), wc_get_page_permalink('cart'))) . '" title="Pay now by Credit Card or Debit Card"><img src="' . esc_url(plugins_url() . '/woocommerce/src/Internal/ReceiptRendering/CardIcons/unknown.svg') . '" title="Pay now by Credit Card or Debit Card" style="width:100px" /><br />
<strong>To pay by Credit Card or Debit Card</strong>, follow this link</a>.</p>' . "\r\n";

}

if (isset($options['paypal']) && 'true' === $options['paypal']) {

$invoice_body .= '<p><strong>To pay by Credit Card, Debit Card or Paypal</strong>, <a href="' . esc_url(add_query_arg(array(
    'cmd' => '_xclick',
    'business' => urlencode(get_bloginfo('admin_email')),
    'item_name' => urlencode(get_bloginfo('name') . ' Invoice No. ' . $invoice->ID),
    'item_number' => $invoice->ID,
    'amount' => urlencode(number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2, '.', '')),
    'currency_code' => 'GBP'
), 'https://www.paypal.com/cgi-bin/webscr')) . '" title="Pay now by Credit Card, Debit Card or Paypal">follow this link</a>.</p>' . "\r\n";

}

$invoice_body .= '<p>Please settle this account within 30 days of the invoice date.</p>' . "\r\n";
} elseif ('Paid' === $invoice->status && $invoice->payment_method) {
$invoice_body .= '<p>Payment received with thanks on ' . mysql2date('jS F Y', $invoice->date_paid) . ' by ' . $invoice->payment_method . '.</p>' . "\r\n";
} elseif ('Cancelled' === $invoice->status) {
$invoice_body .= '<p>This invoice has been cancelled.</p>' . "\r\n";
}

if (isset($options['email_advert'])) {

    $invoice_body .= '<p>';

if (isset($options['advert_url'])) {

    $invoice_body .= '<a href="' . esc_url($options['advert_url']) . '">';

}

    $invoice_body .= '<img src="cid:advert" width="100%" />';

if (isset($options['advert_url'])) {

    $invoice_body .= '</a>';

}

    $invoice_body .= '</p>' . "\r\n";

}

$invoice_body .= file_get_contents(plugin_dir_path(__FILE__) . 'email-footer.inc');

$invoice_body = str_replace("!*email_footer*!", apply_filters('the_content', $options['email_footer']), $invoice_body);

$invoice_headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";

if ($invoice->status == 'Unpaid' && $days_late > 30) {
  $invoice_headers .= "X-Priority: 1 (Highest)\r\n";
  $invoice_headers .= "X-MSMail-Priority: High\r\n";
  $invoice_headers .= "Importance: High\r\n";
}

add_filter('wp_mail_content_type', array($this, 'wpa_set_html_content_type'));
add_filter('phpmailer_init', array($this, 'wpa_set_phpmailer_content_disposition'));
wp_mail($invoice_to, $invoice_subject, $invoice_body, $invoice_headers);
remove_filter('wp_mail_content_type', array($this, 'wpa_set_html_content_type'));
remove_filter('phpmailer_init', array($this, 'wpa_set_phpmailer_content_disposition'));

        if ($output) {

?>
<p>Invoice sent to <strong><?php echo $invoice_to; ?></strong>.</p>
<?php

        }

    }

}

function wpa_set_html_content_type() {
	return 'text/html';
}

function wpa_set_phpmailer_content_disposition($phpmailer) {

	$options = get_option('wp_accounts_options');
    $invoice_attachments = array();

    if (isset($options['company_logo'])) {

        $invoice_attachments[] = array(
            'file' => get_attached_file($options['company_logo']),
            'cid' => 'logo'
        );

    }

    if (isset($options['email_advert'])) {

        $invoice_attachments[] = array(
            'file' => get_attached_file($options['email_advert']),
            'cid' => 'advert'
        );

    }

    foreach($invoice_attachments as $invoice_attachment) {

        $phpmailer->SMTPKeepAlive = true;
        $phpmailer->AddEmbeddedImage($invoice_attachment['file'], $invoice_attachment['cid']);

    }

}

function wpa_manage_payments_options(){
    $this->wpa_payments_action();
    if (isset($_GET['supplier']) && sanitize_text_field($_GET['supplier'])) {
        $this->wpa_show_supplier();
    } elseif (isset($_GET['expense-type']) && absint($_GET['expense-type'])) {
        $this->wpa_show_expense_type();
    } elseif (empty($_GET['edit'])) {
        $this->wpa_manage_payments();
    } else {
        $this->wpa_add_payment();   
    }
}

function wpa_payments_action(){
    global $wpdb;

    if(current_user_can('manage_options') && isset($_GET['delete'])) {

		check_admin_referer('wpaccounts');

        $payment_id = absint($_GET['delete']);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}accounts_payments WHERE ID=%d;",$payment_id));
    }

    if(current_user_can('manage_options') && isset($_POST['accounts_add_payment']) and isset($_POST['accounts_payments_supplier']) and isset($_POST['accounts_payments_invoice_date']) and isset($_POST['accounts_payments_amount']) and isset($_POST['accounts_payments_expense_type']) and isset($_POST['accounts_payments_date_paid']) and isset($_POST['accounts_payments_payment_method']) ) {

		check_admin_referer('wpaccounts');

		$_POST = array_map( 'stripslashes_deep', $_POST);

        $supplier = empty($_POST['accounts_payments_supplier']) ? NULL : sanitize_text_field($_POST['accounts_payments_supplier']);
        $invoice_date = (strtotime(sanitize_text_field($_POST['accounts_payments_invoice_date'])) && !empty($_POST['accounts_payments_invoice_date'])) ? sanitize_text_field($_POST['accounts_payments_invoice_date']) : NULL;
        $amount = (is_numeric($_POST['accounts_payments_amount']) && !empty($_POST['accounts_payments_amount'])) ? $_POST['accounts_payments_amount'] : NULL;
        $reference = empty($_POST['accounts_payments_reference']) ? NULL : sanitize_text_field($_POST['accounts_payments_reference']);
        $expense_type = (is_numeric($_POST['accounts_payments_expense_type']) && !empty($_POST['accounts_payments_expense_type'])) ? $_POST['accounts_payments_expense_type'] : NULL;
        $date_paid = (strtotime(sanitize_text_field($_POST['accounts_payments_date_paid'])) && !empty($_POST['accounts_payments_date_paid'])) ? sanitize_text_field($_POST['accounts_payments_date_paid']) : NULL;
        $payment_method = (is_numeric($_POST['accounts_payments_payment_method']) && !empty($_POST['accounts_payments_payment_method'])) ? $_POST['accounts_payments_payment_method'] : NULL;
        $notes = empty($_POST['accounts_payments_notes']) ? NULL : sanitize_text_field($_POST['accounts_payments_notes']);
        $mileage = (isset($_POST['accounts_payments_mileage']) && is_numeric($_POST['accounts_payments_mileage'])) ? $_POST['accounts_payments_mileage'] : NULL;

        if(empty($_POST['accounts_payments_id'])) {
            if ($wpdb->insert(
	        $wpdb->prefix . 'accounts_payments',
	            array(
		        'supplier' => $supplier,
		        'invoice_date' => $invoice_date,
		        'amount' => $amount,
	        	'reference' => $reference,
	        	'expense_type' => $expense_type,
	        	'date_paid' => $date_paid,
	        	'payment_method' => $payment_method,
	        	'notes' => $notes,
	        	'mileage' => $mileage
	            ), 
	            array(
	        	'%s',
	        	'%s',
	        	'%f',
	        	'%s',
	        	'%d',
	        	'%s',
	        	'%d',
	        	'%s',
				'%d'
	            )
            ) != false) {
                echo '<p style="color: green;">Payment ' . $wpdb->insert_id . ' added successfully.</p>';
            } else {
                echo '<p style="color: red; font-size: 2em;">Payment Insert Fail</p>';
echo $wpdb->last_error;
            }
        } elseif (is_numeric($_POST['accounts_payments_id'])) {
            if ($wpdb->update(
	        $wpdb->prefix . 'accounts_payments',
	            array(
		        'supplier' => $supplier,
		        'invoice_date' => $invoice_date,
		        'amount' => $amount,
	        	'reference' => $reference,
	        	'expense_type' => $expense_type,
	        	'date_paid' => $date_paid,
	        	'payment_method' => $payment_method,
	        	'notes' => $notes,
	        	'mileage' => $mileage
	            ),
	            array( 'ID' => $_POST['accounts_payments_id'] ),
	            array(
	        	'%s',
	        	'%s',
	        	'%f',
	        	'%s',
	        	'%d',
	        	'%s',
	        	'%d',
	        	'%s',
				'%d'
	            ), 
	            array( '%d' )
            ) != false) {
                echo '<p style="color: green;">Payment ' . absint($_POST['accounts_payments_id']) . ' updated successfully.</p>';
            } elseif ($wpdb->last_error) {

                echo '<p style="color: red; font-size: 2em;">Payment Update Fail</p>';
                echo $wpdb->last_error;

            } else {

                echo '<p style="color: red; font-size: 2em;">Nothing was changed</p>';

            }
        }
    }
}

function wpa_show_supplier() {

    $options = get_option('wp_accounts_options');

    $date_ranges = array(
        'month-to-date' => array(
            'title' => 'Month to date',
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d')
        ),
        'quarter-to-date' => array(
            'title' => 'Quarter to date',
            'from' => date(sprintf('Y-%s-01', floor((date('n') - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d')
        ),
        'year-to-date' => array(
            'title' => 'Year to date',
            'from' => date('Y-01-01'),
            'to' => date('Y-m-d')
        )
    );

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['accounting-period-to-date'] = array(
            'title' => 'Accounting period to date',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y-') . $options['accounting_period_start']),
            'to' => date('Y-m-d')
        );

    }

    $date_ranges = array_merge($date_ranges, array(
        'last-month' => array(
            'title' => 'Last month',
            'from' => date('Y-m-d', strtotime('first day of previous month')),
            'to' => date('Y-m-d', strtotime('last day of previous month'))
        ),
        'last-quarter' => array(
            'title' => 'Last quarter',
            'from' => date(sprintf('%s-%s-01', date('Y', strtotime('-3 month')), floor((date('n', strtotime('-3 month')) - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d', strtotime('last day of -' . (((date('n') - 1) % 3) + 1) . ' month'))
        ),
        'last-year' => array(
            'title' => 'Last year',
            'from' => date('Y-01-01', strtotime('-1 year')),
            'to' => date('Y-12-31', strtotime('-1 year')),
        )
    ));

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['last-accounting-period'] = array(
            'title' => 'Last accounting period',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 2) . '-' . $options['accounting_period_start'] : (date('Y') - 1) . '-' . $options['accounting_period_start']),
            'to' => date('Y-m-d', strtotime(((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y') . '-' . $options['accounting_period_start']) . ' -1 day'))
        );

    }

    $supplier = stripslashes(sanitize_text_field($_GET['supplier']));

    if (isset($_GET['merge']) && stripslashes(sanitize_text_field($_GET['merge']))) {

        global $wpdb;

        $wpdb->update($wpdb->prefix . 'accounts_payments', array('supplier' => $supplier), array('supplier' => stripslashes(sanitize_text_field($_GET['merge']))));

    }

?>
<div class="wrap">
    <h2><?php echo __('Supplier: ', 'wp-accounts') . esc_html($supplier); ?></h2>
    <label for="date-range">Date Range:</label>
    <select name="date-range" id="date-range">
        <option value=""<?php echo selected(isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]), false); ?>>All time</option>
<?php

    foreach ($date_ranges as $key => $date_range) {

?>
        <option value="<?php echo esc_attr($key); ?>"<?php echo selected((isset($_GET['date-range']) ? $_GET['date-range'] : ''), $key); ?>><?php echo esc_html($date_range['title']); ?></option>
<?php

    }

?>
    </select>
<script type="text/javascript">
(function($) {
    $('#date-range').change(function() {
        window.location.href = '<?php echo add_query_arg(array('page' => 'manage-payments', 'supplier' => urlencode($supplier)), admin_url('admin.php')); ?>&date-range=' + $(this).val();
    });
})(jQuery);
</script>
  <form method="post" action="?page=manage-payments" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_payments_action">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Payment')?>" onclick="window.location='?page=manage-payments&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID / Payment Method')?></th>
          <th class="manage-column"><?php _e('Expense Type', 'wp-accounts')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID / Payment Method')?></th>
          <th class="manage-column"><?php _e('Expense Type', 'wp-accounts')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          $payments = $this->wpa_accounts_get_data(
                'accounts_payments',
                false,
                $supplier,
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['from'] : false),
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['to'] : false)
            );
          if($payments){

            global $wpdb;

            $expense_types_data = $wpdb->get_results("SELECT ID, expense_type FROM {$wpdb->prefix}accounts_expense_types ORDER BY expense_type ASC;");
            $expense_types = array();
            $total_expense = 0;

            foreach ($expense_types_data as $expense_type) {

                $expense_types[$expense_type->ID] = $expense_type->expense_type;

            }

           $i=0;
           foreach($payments as $payment) { 
               $i++;
               $total_expense += $payment->amount;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $payment->ID; ?>" name="payment_id[]" />
        </th>
          <td>
          <strong><?php echo $payment->ID; ?></strong> - <?php echo $payment->payment_method; ?>
          <div class="row-actions-visible">
          <span class="edit"><a href="?page=manage-payments&amp;id=<?php echo $payment->ID; ?>&amp;edit=true">Edit</a> | </span>
          <span class="edit"><a href="?page=manage-payments&amp;id=<?php echo $payment->ID; ?>&amp;edit=true&amp;copy=true">Copy</a></span>
          </div>
          </td>
          <td><a href="<?php echo esc_url(add_query_arg(array('page' => 'manage-payments', 'expense-type' => urlencode($payment->expense_type)), admin_url('admin.php'))); ?>" title=""><?php echo esc_html($expense_types[$payment->expense_type]); ?></a></td>
          <td>Invoice Date: <?php echo date('jS F Y',strtotime($payment->invoice_date)); ?><br />
          Date Paid: <?php echo date('jS F Y',strtotime($payment->date_paid)); ?></td>
          <td>&pound;<?php echo number_format($payment->amount, 2); ?></td>
        </tr>
<?php

           }

?>
        <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
            <th class="check-column" scope="row"></th>
            <td colspan="2"></td>
            <td style="text-align: right;">Total: </td>
            <td><strong>&pound;<?php echo number_format($total_expense, 2); ?></strong></td>
        </tr>
<?php

        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no payments.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_payments_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Payment')?>" onclick="window.location='?page=manage-payments&amp;edit=true'" />
    </p>

  </form>
<?php

    global $wpdb;
    $existing_suppliers = $wpdb->get_results("SELECT supplier FROM {$wpdb->prefix}accounts_payments GROUP BY supplier ORDER BY SUBSTRING(UPPER(supplier), IF (UPPER(supplier) LIKE 'THE %', 5, 1));");

?>
<p>Move payments to <select name="accounts_payments_existing_suppliers" onchange="if (confirm('Are you sure you want to delete this supplier and move all payments to &quot;' + decodeURIComponent(this.value.replace(/\+/g, '%20')) + '&quot;?')) { window.location.href = location.href + '&supplier=' + this.value + '&merge=<?php echo esc_attr(urlencode($supplier)); ?>'; }">
    <option value="" selected="selected">Select ...</option>
<?php

        if ($existing_suppliers) {

            foreach ($existing_suppliers as $existing_supplier) {

                if ($supplier !== $existing_supplier->supplier) {

?>
    <option value="<?php echo esc_attr(urlencode($existing_supplier->supplier)); ?>"><?php 

                    if (strpos(strtolower($existing_supplier->supplier), 'the ') === 0) { echo substr($existing_supplier->supplier, 4) . ' (The)'; } else { echo $existing_supplier->supplier; }

?></option>
<?php

                }

            }

        }

?>
</select> and delete supplier.</p>

</div>
<?php

}

function wpa_show_expense_type() {

    $options = get_option('wp_accounts_options');

    $date_ranges = array(
        'month-to-date' => array(
            'title' => 'Month to date',
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d')
        ),
        'quarter-to-date' => array(
            'title' => 'Quarter to date',
            'from' => date(sprintf('Y-%s-01', floor((date('n') - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d')
        ),
        'year-to-date' => array(
            'title' => 'Year to date',
            'from' => date('Y-01-01'),
            'to' => date('Y-m-d')
        )
    );

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['accounting-period-to-date'] = array(
            'title' => 'Accounting period to date',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y-') . $options['accounting_period_start']),
            'to' => date('Y-m-d')
        );

    }

    $date_ranges = array_merge($date_ranges, array(
        'last-month' => array(
            'title' => 'Last month',
            'from' => date('Y-m-d', strtotime('first day of previous month')),
            'to' => date('Y-m-d', strtotime('last day of previous month'))
        ),
        'last-quarter' => array(
            'title' => 'Last quarter',
            'from' => date(sprintf('%s-%s-01', date('Y', strtotime('-3 month')), floor((date('n', strtotime('-3 month')) - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d', strtotime('last day of -' . (((date('n') - 1) % 3) + 1) . ' month'))
        ),
        'last-year' => array(
            'title' => 'Last year',
            'from' => date('Y-01-01', strtotime('-1 year')),
            'to' => date('Y-12-31', strtotime('-1 year')),
        )
    ));

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['last-accounting-period'] = array(
            'title' => 'Last accounting period',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 2) . '-' . $options['accounting_period_start'] : (date('Y') - 1) . '-' . $options['accounting_period_start']),
            'to' => date('Y-m-d', strtotime(((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y') . '-' . $options['accounting_period_start']) . ' -1 day'))
        );

    }

    $expense_type_id = absint($_GET['expense-type']);

    global $wpdb;

    $expense_type = $wpdb->get_results($wpdb->prepare("SELECT expense_type FROM {$wpdb->prefix}accounts_expense_types WHERE ID=%d;", $expense_type_id));

    if ($expense_type) {

        $expense_type = $expense_type[0]->expense_type;

    } else {

        return false;

    }

?>
<div class="wrap">
    <h2><?php echo __('Expense Type: ', 'wp-accounts') . esc_html($expense_type); ?></h2>
    <label for="date-range">Date Range:</label>
    <select name="date-range" id="date-range">
        <option value=""<?php echo selected(isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]), false); ?>>All time</option>
<?php

    foreach ($date_ranges as $key => $date_range) {

?>
        <option value="<?php echo esc_attr($key); ?>"<?php echo selected((isset($_GET['date-range']) ? $_GET['date-range'] : ''), $key); ?>><?php echo esc_html($date_range['title']); ?></option>
<?php

    }

?>
    </select>
<script type="text/javascript">
(function($) {
    $('#date-range').change(function() {
        window.location.href = '<?php echo add_query_arg(array('page' => 'manage-payments', 'expense-type' => urlencode($expense_type_id)), admin_url('admin.php')); ?>&date-range=' + $(this).val();
    });
})(jQuery);
</script>
  <form method="post" action="?page=manage-payments" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_payments_action">
            <option value="actions"><?php _e('Actions')?></option>
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Payment')?>" onclick="window.location='?page=manage-payments&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID / Payment Method')?></th>
          <th class="manage-column"><?php _e('Supplier', 'wp-accounts')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID / Payment Method')?></th>
          <th class="manage-column"><?php _e('Supplier', 'wp-accounts')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          $payments = $this->wpa_accounts_get_data(
                'accounts_payments',
                false,
                $expense_type_id,
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['from'] : false),
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['to'] : false),
                'expense_type'
            );
          if($payments){

            $total_expense = 0;

           $i=0;
           foreach($payments as $payment) { 
               $i++;
               $total_expense += $payment->amount;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $payment->ID; ?>" name="payment_id[]" />
        </th>
          <td>
          <strong><?php echo $payment->ID; ?></strong> - <?php echo $payment->payment_method; ?>
          <div class="row-actions-visible">
          <span class="edit"><a href="?page=manage-payments&amp;id=<?php echo $payment->ID; ?>&amp;edit=true">Edit</a> | </span>
          <span class="edit"><a href="?page=manage-payments&amp;id=<?php echo $payment->ID; ?>&amp;edit=true&amp;copy=true">Copy</a></span>
          </div>
          </td>
          <td><a href="<?php echo esc_url(add_query_arg(array('page' => 'manage-payments', 'supplier' => urlencode($payment->supplier)), admin_url('admin.php'))); ?>" title=""><?php echo esc_html($payment->supplier); ?></a></td>
          <td>Invoice Date: <?php echo date('jS F Y',strtotime($payment->invoice_date)); ?><br />
          Date Paid: <?php echo date('jS F Y',strtotime($payment->date_paid)); ?></td>
          <td>&pound;<?php echo number_format($payment->amount, 2); ?></td>
        </tr>
<?php

           }

?>
        <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
            <th class="check-column" scope="row"></th>
            <td colspan="2"></td>
            <td style="text-align: right;">Total: </td>
            <td><strong>&pound;<?php echo number_format($total_expense, 2); ?></strong></td>
        </tr>
<?php

        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no payments.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_payments_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Payment')?>" onclick="window.location='?page=manage-payments&amp;edit=true'" />
    </p>

  </form>
</div>
<?php

}

function wpa_add_payment() {

    $payment_id = 0;
    if(isset($_GET['id']) && !isset($_GET['copy'])) { $payment_id = absint($_GET['id']); }

    global $edit_payments;
    if (isset($_GET['id'])) $edit_payments = $this->wpa_accounts_get_row(absint($_GET['id']),'accounts_payments');   

    add_meta_box('accounts-meta', __('Payment Details'), array($this, 'wpa_payment_meta_box'), 'accounts-payments', 'normal', 'core' );
?>

    <div class="wrap">
      <div id="faq-wrapper">
        <form method="post" action="?page=manage-payments">

<?php wp_nonce_field('wpaccounts'); ?>

          <h2>
          <?php if ($payment_id == 0) {
                $tf_title = __('Add Payment');
          }else {
                $tf_title = __('Edit Payment') . ' ' . $payment_id;
          }
          echo $tf_title;
          ?>
          </h2>
          <div id="poststuff" class="metabox-holder">
            <?php do_meta_boxes('accounts-payments', 'normal','low'); ?>
          </div>
          <input type="hidden" name="accounts_payments_id" value="<?php echo $payment_id; ?>" />
          <input type="submit" value="<?php echo $tf_title;?>" name="accounts_add_payment" id="accounts_add_payment" class="button-secondary">
        </form>
      </div>
    </div>
<?php
}

function wpa_manage_payments() {

    $options = get_option('wp_accounts_options');

    $date_ranges = array(
        'month-to-date' => array(
            'title' => 'Month to date',
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d')
        ),
        'quarter-to-date' => array(
            'title' => 'Quarter to date',
            'from' => date(sprintf('Y-%s-01', floor((date('n') - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d')
        ),
        'year-to-date' => array(
            'title' => 'Year to date',
            'from' => date('Y-01-01'),
            'to' => date('Y-m-d')
        )
    );

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['accounting-period-to-date'] = array(
            'title' => 'Accounting period to date',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y-') . $options['accounting_period_start']),
            'to' => date('Y-m-d')
        );

    }

    $date_ranges = array_merge($date_ranges, array(
        'last-month' => array(
            'title' => 'Last month',
            'from' => date('Y-m-d', strtotime('first day of previous month')),
            'to' => date('Y-m-d', strtotime('last day of previous month'))
        ),
        'last-quarter' => array(
            'title' => 'Last quarter',
            'from' => date(sprintf('%s-%s-01', date('Y', strtotime('-3 month')), floor((date('n', strtotime('-3 month')) - 1) / 3) * 3 + 1)),
            'to' => date('Y-m-d', strtotime('last day of -' . (((date('n') - 1) % 3) + 1) . ' month'))
        ),
        'last-year' => array(
            'title' => 'Last year',
            'from' => date('Y-01-01', strtotime('-1 year')),
            'to' => date('Y-12-31', strtotime('-1 year')),
        )
    ));

    if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

        $date_ranges['last-accounting-period'] = array(
            'title' => 'Last accounting period',
            'from' => ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 2) . '-' . $options['accounting_period_start'] : (date('Y') - 1) . '-' . $options['accounting_period_start']),
            'to' => date('Y-m-d', strtotime(((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y') . '-' . $options['accounting_period_start']) . ' -1 day'))
        );

    }

    global $wpdb;
    $existing_suppliers = $wpdb->get_results("SELECT supplier FROM {$wpdb->prefix}accounts_payments GROUP BY supplier ORDER BY SUBSTRING(UPPER(supplier), IF (UPPER(supplier) LIKE 'THE %', 5, 1));");

?>
<div class="wrap">
  <div class="icon32" id="icon-edit"><br></div>
  <h2><?php _e('Manage Payments') ?></h2>
<p>View Supplier: <select name="accounts_payments_existing_suppliers" onchange="window.location.href = location.href + '&supplier=' + this.value;">
    <option value="" selected="selected">Select ...</option>
<?php

        if ($existing_suppliers) {

            foreach ($existing_suppliers as $existing_supplier) {

?>
    <option value="<?php echo esc_html(urlencode($existing_supplier->supplier)); ?>"><?php 

                if (strpos(strtolower($existing_supplier->supplier), 'the ') === 0) { echo substr($existing_supplier->supplier, 4) . ' (The)'; } else { echo $existing_supplier->supplier; }

?></option>
<?php

            }

        }

?>
</select></p>
<p>Date Range: <select name="date-range" id="date-range">
        <option value=""<?php echo selected(isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]), false); ?>>Last 365 days</option>
<?php

    foreach ($date_ranges as $key => $date_range) {

?>
        <option value="<?php echo esc_attr($key); ?>"<?php echo selected((isset($_GET['date-range']) ? $_GET['date-range'] : ''), $key); ?>><?php echo esc_html($date_range['title']); ?></option>
<?php

    }

?>
    </select></p>
<script type="text/javascript">
(function($) {
    $('#date-range').change(function() {
        window.location.href = '<?php echo add_query_arg(array('page' => 'manage-payments'), admin_url('admin.php')); ?>&date-range=' + $(this).val();
    });
})(jQuery);
</script>
  <form method="post" action="?page=manage-payments" id="accounts_form_action">

<?php wp_nonce_field('wpaccounts'); ?>

    <p>
        <select name="accounts_payments_action">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
      </select>
      <input type="submit" name="accounts_form_action_changes" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Payment')?>" onclick="window.location='?page=manage-payments&amp;edit=true'" />
    </p>
    <table class="widefat page fixed" cellpadding="0">
      <thead>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID / Payment Method')?></th>
          <th class="manage-column"><?php _e('Supplier / Expense Type')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
        <th id="cb" class="manage-column column-cb check-column" style="" scope="col">
          <input type="checkbox"/>
        </th>
          <th class="manage-column"><?php _e('ID / Payment Method')?></th>
          <th class="manage-column"><?php _e('Supplier / Expense Type')?></th>
          <th class="manage-column"><?php _e('Date')?></th>
          <th class="manage-column"><?php _e('Amount')?></th>
        </tr>
      </tfoot>
      <tbody>
        <?php
          $payments = $this->wpa_accounts_get_data(
                'accounts_payments',
                false,
                false,
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['from'] : false),
                (isset($_GET['date-range']) && isset($date_ranges[$_GET['date-range']]) ? $date_ranges[$_GET['date-range']]['to'] : false)
            );
          if($payments){

            global $wpdb;

            $expense_types_data = $wpdb->get_results("SELECT ID, expense_type FROM {$wpdb->prefix}accounts_expense_types ORDER BY expense_type ASC;");
            $expense_types = array();
            $total_expense = 0;

            foreach ($expense_types_data as $expense_type) {

                $expense_types[$expense_type->ID] = $expense_type->expense_type;

            }

           $i=0;
           foreach($payments as $payment) { 
               $i++;
               $total_expense += $payment->amount;
        ?>
      <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
        <th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $payment->ID; ?>" name="payment_id[]" />
        </th>
          <td>
          <strong><?php echo $payment->ID; ?></strong> - <?php echo $payment->payment_method; ?>
          <div class="row-actions-visible">
          <span class="edit"><a href="?page=manage-payments&amp;id=<?php echo $payment->ID; ?>&amp;edit=true">Edit</a> | </span>
          <span class="edit"><a href="?page=manage-payments&amp;id=<?php echo $payment->ID; ?>&amp;edit=true&amp;copy=true">Copy</a></span>
          </div>
          </td>
          <td><a href="<?php echo esc_url(add_query_arg(array('page' => 'manage-payments', 'supplier' => urlencode($payment->supplier)), admin_url('admin.php'))); ?>" title=""><?php echo esc_html($payment->supplier); ?></a><br />
          <a href="<?php echo esc_url(add_query_arg(array('page' => 'manage-payments', 'expense-type' => urlencode($payment->expense_type)), admin_url('admin.php'))); ?>" title=""><?php echo esc_html($expense_types[$payment->expense_type]); ?></a></td>
          <td>Invoice Date: <?php echo date('jS F Y',strtotime($payment->invoice_date)); ?><br />
          Date Paid: <?php echo date('jS F Y',strtotime($payment->date_paid)); ?></td>
          <td>&pound;<?php echo number_format($payment->amount, 2); ?></td>
        </tr>
        <?php
           }

?>
        <tr class="<?php echo (ceil($i/2) == ($i/2)) ? "" : "alternate"; ?>">
            <th class="check-column" scope="row"></th>
            <td colspan="2"></td>
            <td style="text-align: right;">Total: </td>
            <td><strong>&pound;<?php echo number_format($total_expense, 2); ?></strong></td>
        </tr>
<?php

        }
        else{   
      ?>
        <tr><td colspan="4"><?php _e('There are no payments.')?></td></tr>   
        <?php
      }
        ?>   
      </tbody>
    </table>
    <p>
        <select name="accounts_payments_action-2">
            <option value="actions"><?php _e('Actions')?></option>
            <!--<option value="delete"><?php _e('Delete')?></option>-->
        </select>
        <input type="submit" name="accounts_form_action_changes-2" class="button-secondary" value="<?php _e('Apply')?>" />
        <input type="button" class="button-secondary" value="<?php _e('Add a new Payment')?>" onclick="window.location='?page=manage-payments&amp;edit=true'" />
    </p>

  </form>
</div>
<?php
}

        function wpa_clean_telephone($telephone) {

        	$clean_telephone = '';

        	if (strlen($telephone) > 0) {

        		for ($i=0; $i<strlen($telephone); $i++) {

        			if ($i == 0 && substr($telephone, $i, 1) == '+') {

        				$clean_telephone = '00';

        			}

        			if (strpos('0123456789', substr($telephone, $i, 1)) !== false) {

        				$clean_telephone .= substr($telephone, $i, 1);

        			}

        		}

        	}

        	if (strlen($clean_telephone) > 5) {

        		if (substr($clean_telephone, 0, 5) == '00440') {

        			$clean_telephone = '0' . substr($clean_telephone, 5);

        		}

        	}

        	if (strlen($clean_telephone) > 4) {

        		if (substr($clean_telephone, 0, 4) == '0044') {

        			$clean_telephone = '0' . substr($clean_telephone, 4);

        		}

        	}

        	return $clean_telephone;

        }

        function wpa_format_telephone($telephone) {

        	if (strlen($telephone)>8) {

        		if (substr($telephone,0, 2) == '01' || (substr($telephone,0, 2) == '07' && substr($telephone,0,3) <> '070')) {

        			$telephone = substr($telephone,0,5).' '.substr($telephone,5,3).' '.substr($telephone,8);

        		} elseif (substr($telephone,0, 2) == '02' || substr($telephone,0,3) == '055' || substr($telephone,0,3) == '056' || substr($telephone,0,3) == '070') {

        			$telephone = substr($telephone,0,3).' '.substr($telephone,3,4).' '.substr($telephone,7);

        		} elseif (substr($telephone,0, 2) == '03' || substr($telephone,0,3) == '050' || substr($telephone,0, 2) == '08' || substr($telephone,0, 2) == '09') {

        			$telephone = substr($telephone,0,4).' '.substr($telephone,4,3).' '.substr($telephone,7);

        		}

        	}

        	if (strlen($telephone)>2) {

        		if (substr($telephone,0, 2) == '00') {

        			$telephone = '+'.substr($telephone,2);

        		}

        	}

        	return $telephone;

        }

		function wpa_user_contact_details($profileuser) {

            wp_nonce_field('wpa_user_meta_' . $profileuser->ID, 'wpa_nonce');

            if (current_user_can('manage_options')) {

                global $wpdb;

?>

	<h3>WP Accounts</h3>

    <p>Allow this user to view an account statement when they sign into this website. Add the account statement functionality to a page on your site by using the shortcode [wpa-statement]. Account information will only be shown when a user who has permission to view account information is signed in. If a user is not signed in then they will be prompted to do so.</p> 

	<table class="form-table">

		<tr>
			<th><label for="client">Client:</label></th>
			<td>
                <select name="user_client">
                    <option value=""<?php if (!get_the_author_meta('user_client', $profileuser->ID)) { echo ' selected="selected"'; } ?>>None</option>
<?php

                $clients = $wpdb->get_results("SELECT ID, company, contact FROM {$wpdb->prefix}accounts_clients ORDER BY SUBSTRING(UPPER(company), IF (UPPER(company) LIKE 'THE %', 5, 1)), contact ASC;");

                if ($clients) {

                    foreach($clients as $client) { 

?>
                    <option value="<?php echo $client->ID; ?>"<?php if (get_the_author_meta('user_client', $profileuser->ID) && get_the_author_meta('user_client', $profileuser->ID) == $client->ID) { echo ' selected="selected"'; } ?>><?php if ($client->company) { if (strpos(strtolower($client->company), 'the ') === 0) { echo substr($client->company, 4) . ' (The), '; } else { echo $client->company . ', '; } } echo $client->contact; ?></option>
<?php

                    }

                }

?>
                </select></p>
			</td>
		</tr>

	</table>

<?php

            }

		}

        function wpa_save_user_contact_details($user_id) {

            if (isset($_POST['wpa_nonce']) && wp_verify_nonce($_POST['wpa_nonce'], 'wpa_user_meta_' . $user_id) && current_user_can('manage_options')) {

                if (isset($_POST['user_client']) && $_POST['user_client']) {

                    update_user_meta($user_id, 'user_client', absint($_POST['user_client']));

                } else {

                    delete_user_meta($user_id, 'user_client');

                }

            }

        }

        function wpa_statement_shortcode($atts = array(), $content = null) {

            $current_user = wp_get_current_user();

            if (!$current_user->ID) {

                $statement = '<p><a href="' . esc_url(wp_login_url(get_permalink())) . '">Login to access your account statement.</a></p>';

            } else {

                global $WPAccounts_Object, $wpdb;

                $client_id = get_user_meta($current_user->ID, 'user_client', true);
                $client = $WPAccounts_Object->wpa_client_from_id($client_id);
                $client_name = '';

                if (is_object($client) && $client->company) {

                    $client_name = $client->company . ', ';

                }

                if (is_object($client)) {

                    $client_name .= $client->contact;

                }

                if ($client_id) {

                    $invoices = $wpdb->get_results($wpdb->prepare("SELECT " . $wpdb->prefix . "accounts_invoices.ID, " . $wpdb->prefix . "accounts_clients.contact, " . $wpdb->prefix . "accounts_clients.company, " . $wpdb->prefix . "accounts_invoices.invoice_date, " . $wpdb->prefix . "accounts_invoices.price1, " . $wpdb->prefix . "accounts_invoices.price2, " . $wpdb->prefix . "accounts_invoices.price3, " . $wpdb->prefix . "accounts_invoices.price4, " . $wpdb->prefix . "accounts_invoices.price5, " . $wpdb->prefix . "accounts_invoices.date_paid, " . $wpdb->prefix . "accounts_invoice_status.status, " . $wpdb->prefix . "accounts_payment_methods.payment_method FROM " . $wpdb->prefix . "accounts_invoices INNER JOIN " . $wpdb->prefix . "accounts_clients ON " . $wpdb->prefix . "accounts_invoices.client = " . $wpdb->prefix . "accounts_clients.ID INNER JOIN " . $wpdb->prefix . "accounts_invoice_status ON " . $wpdb->prefix . "accounts_invoices.invoice_status = " . $wpdb->prefix . "accounts_invoice_status.ID LEFT JOIN " . $wpdb->prefix . "accounts_payment_methods ON " . $wpdb->prefix . "accounts_invoices.payment_method = " . $wpdb->prefix . "accounts_payment_methods.ID WHERE client = %s ORDER BY invoice_date DESC;",$client_id));
                    $statement = '<h2>' . $client_name . ' Statement</h2>
';

                    if ($invoices) {

                        $statement .= '<table>
    <thead>
        <tr>
            <th>Invoice</th>
            <th>Date</th>
            <th>Status</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>
';

                        $total_due=0;

                        foreach ($invoices as $invoice) {

                            if ($invoice->status == 'Unpaid') {

                                $total_due = $total_due + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5;

                            }

                            $statement .= '        <tr>
            <td><strong' . (($invoice->status == 'Cancelled' || $invoice->status == 'Unpaid') ? ' style="color: red;"' : '') . '>' . $invoice->ID . '</strong></td>
            <td>' . date('jS F Y',strtotime($invoice->invoice_date)) . '</td>
            <td>' . (($invoice->status == 'Paid') ? 'Paid by ' . $invoice->payment_method . ' on ' . mysql2date('jS F Y', $invoice->date_paid) : '<span style="color: red;">' . $invoice->status . '</span>') . '</td>
            <td' . ((isset($invoice->invoice_status) && $invoice->invoice_status == 3) ? ' style="color: red; text-decoration: line-through;"' : '') . ' align="right">&pound;' . number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5), 2) . '</td>
        </tr>
';

                        }

                        $statement .= '        <tr>
            <td colspan="3"></td>
            <td style="color: red;" align="right">Due Now: <strong>&pound;' . number_format($total_due, 2) . '</strong></td>
        </tr>
    </tbody>
</table>
';

                    } else {   

                        $statement .= '<p>There are no invoices.</p>
';

                    }

                } else {

                    $statement = '<p>You do not have permission to view a client statement.</p>';

                }

            }

            return $statement;

        }

        public function wp_dashboard_setup() {

            wp_add_dashboard_widget('wp_accounts_widget', 'WP Accounts Widget', array($this, 'wp_accounts_widget'));

        }

        public function wp_accounts_widget() {

			$options = get_option('wp_accounts_options');

			if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

                $date_from = ((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y-') . $options['accounting_period_start']);
				$date_to = date('Y-m-d');
				$total_invoices = 0;
				$total_payments = 0;
				$total_dividends = 0;
				$total_capital = 0;

				$invoices = $this->wpa_accounts_get_data(
					'accounts_invoices',
					false,
					1,
					$date_from,
					$date_to
				);

				if ($invoices) {

					foreach ($invoices as $invoice) {

						$total_invoices = $total_invoices + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5;

					}

				}

				$receipts = $this->wpa_accounts_get_data(
					'accounts_invoices',
					false,
					2,
					$date_from,
					$date_to
				);

				if ($receipts) {

					foreach ($receipts as $invoice) {

						$total_invoices = $total_invoices + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5;

					}

				}

                $payments = $this->wpa_accounts_get_data(
                    'accounts_payments',
                    false,
                    false,
					$date_from,
					$date_to
			    );

                if ($payments) {

					foreach ($payments as $payment) {

                        if ($payment->expense) {

                            if (1 == $payment->expense_type) {

                                $total_capital += $payment->amount;
                                $total_payments += ($payment->amount * 0.18);

                            } else {

                                $total_payments += $payment->amount;

                            }

                        } elseif (13 == $payment->expense_type) {

                            $total_dividends += $payment->amount;

                        }

					}

				}

?>
<p><strong>Current Financial Year</strong><?php

                if (isset($options['accounting_period_start']) && $options['accounting_period_start']) {

?><br />
<?php

echo date(get_option('date_format'), strtotime(((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y') - 1) . '-' . $options['accounting_period_start'] : date('Y') . '-' . $options['accounting_period_start'])));
echo ' - ';
echo date(get_option('date_format'), strtotime(((date('Y-') . $options['accounting_period_start']) > date('Y-m-d') ? (date('Y')) . '-' . $options['accounting_period_start'] : (date('Y') + 1) . '-' . $options['accounting_period_start']) . '- 1 Day'));

                }

?></p>
<p>Total Invoices: &pound;<?php echo number_format($total_invoices, 2); ?><br />
Total Expenses: &pound;<?php echo number_format($total_payments, 2); ?><br />
<?php

                if ($total_capital) {

?>
Total Capital Expenditure: &pound;<?php echo number_format($total_capital, 2); ?> (18% = &pound;<?php echo number_format($total_capital * 0.18, 2); ?>)<br />
<?php

                }

                if ($total_dividends) {

?>
Total Dividends: &pound;<?php echo number_format($total_dividends, 2); ?><br />
<?php

                }

?>
Net Profit: &pound;<?php

                echo number_format($total_invoices - $total_payments, 2);

				$invoices = $this->wpa_accounts_get_data(
					'accounts_invoices',
					false,
					1,
					false,
					false
				);

                if ($invoices) {

?><br />
<a href="<?php echo esc_url(add_query_arg('page', 'manage-invoices', admin_url('admin.php'))); ?>" title="Manage Invoices"><?php echo count($invoices); ?> unpaid invoice<?php echo (count($invoices) > 1 ? 's' : ''); ?></a><?php

                }

?></p>
<?php

			} else {

?>
<p>Please add your accounting period start date in <a href="<?php echo esc_url(add_query_arg('page', 'manage-settings', admin_url('admin.php'))); ?>" title="WP Accounts Settings">WP Accounts Settings</a>.</p>
<?php

			}

        }

        public function woocommerce_init() {

            if (
                isset($_GET['invoice']) &&
                absint($_GET['invoice']) &&
                isset($_GET['client']) &&
                absint($_GET['client']) &&
                !is_user_logged_in() &&
                !is_admin() &&
                isset(WC()->session) &&
                !WC()->session->has_session()
            ) {

                WC()->session->set_customer_session_cookie(true); 

            }

        }

        public function wp_head() {

            if (
                function_exists('is_cart') &&
                is_cart() &&
                isset($_GET['invoice']) &&
                absint($_GET['invoice']) &&
                isset($_GET['client']) &&
                absint($_GET['client'])
            ) {

                global $wpdb;

                foreach (WC()->cart->get_cart() as $cart_item_key => $item) {

                    if(
            	        array_key_exists('wp_accounts_invoice_id', $item) &&
            	        absint($item['wp_accounts_invoice_id'])
                    ) {

                        $invoice = $wpdb->get_row($wpdb->prepare("SELECT invoice_status, price1, price2, price3, price4, price5 FROM {$wpdb->prefix}accounts_invoices WHERE ID=%d;", absint($item['wp_accounts_invoice_id'])));

                        if ('1' !== $invoice->invoice_status) {

                            WC()->cart->remove_cart_item($cart_item_key);

                        }

            		}

                }

                $invoice = $wpdb->get_row($wpdb->prepare("SELECT invoice_status, price1, price2, price3, price4, price5 FROM {$wpdb->prefix}accounts_invoices WHERE ID=%d AND client=%d;", absint($_GET['invoice']), absint($_GET['client'])));

                if ($invoice) {

                    if ('1' === $invoice->invoice_status) {

                        $invoice_product_id = self::get_woocommerce_product_id();

                        if ($invoice_product_id) {

                            $invoice_in_cart = false;

                	        foreach (WC()->cart->get_cart() as $item) {

                                if(
                		            array_key_exists('wp_accounts_invoice_id', $item) &&
                		            $item['wp_accounts_invoice_id'] === absint($_GET['invoice'])
                                ) {

                                    $invoice_in_cart = true;

                        		}

                	        }

                            if (!$invoice_in_cart) {

                                WC()->cart->add_to_cart($invoice_product_id, 1, 0, array(), array(
                                    'wp_accounts_invoice_id' => absint($_GET['invoice']),
                                    'wp_accounts_invoice_total' => ($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5)
                                ));

                            }

                        } else {

                            $this->cart_message = 'Sorry, invoice ' . absint($_GET['invoice']) . ' cannot be found.';

                        }

                    } else {

                        foreach (WC()->cart->get_cart() as $cart_item_key => $item) {

                            if(
                		        array_key_exists('wp_accounts_invoice_id', $item) &&
                		        $item['wp_accounts_invoice_id'] === absint($_GET['invoice'])
                            ) {

                                WC()->cart->remove_cart_item($cart_item_key);

                    		}

            	        }

                        $this->cart_message = 'Sorry, invoice ' . absint($_GET['invoice']) . ' has already been paid.';

                    }

                } else {

                    $this->cart_message = 'Sorry, there has been an issue retrieving the WooCommerce Invoice product.';

                }

            }

        }

        public function woocommerce_before_cart() {

            if ($this->cart_message) {

?>
<p><?php echo esc_html($this->cart_message); ?></p>
<?php

            }

        }

        public function wc_empty_cart_message($message) {

            if ($this->cart_message) {

                $message = $this->cart_message;

            }

            return $message;

        }

        private static function get_woocommerce_product_id() {

            $invoice_product_id = get_posts(array(
                'fields' => 'ids',
                'post_count' => '1',
                'post_type' => 'product',
                'post_title' => 'Invoice',
                'meta_key' => '_sku',
                'meta_value' => 'wp-accounts'
            ));

            if (!$invoice_product_id) {

                $invoice_product_id = wp_insert_post(array(
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'post_title' => 'Invoice',
                    'meta_input' => array(
                        '_sku' => 'wp-accounts',
                        '_virtual' => 'yes',
                        '_downloadable' => 'no',
                        '_stock_status' => 'instock',
                        '_price' => '0',
                        '_sold_individually' => 'yes'
                    )
                ));

                if ($invoice_product_id) {

                    wp_set_post_terms(
                        $invoice_product_id,
                        array('exclude-from-search', 'exclude-from-catalog'),
                        'product_visibility'
                    );

                }

            } else {

                $invoice_product_id = $invoice_product_id[0];

            }

            return $invoice_product_id;

        }

        public static function woocommerce_before_calculate_totals($cart_object) {

	        foreach ($cart_object->get_cart() as $item) {

		        if(
                    array_key_exists('wp_accounts_invoice_total', $item) &&
                    array_key_exists('wp_accounts_invoice_id', $item)
                ) {

        			$item['data']->set_price($item['wp_accounts_invoice_total']);
                    $item['data']->set_name('Invoice ' . $item['wp_accounts_invoice_id']);

        		}

        	}

        }

        public function woocommerce_email_enabled_customer_completed_order($recipient, $order) {

            global $wpdb;

            $send_customer_completed_order = false;
            $payment_method = get_post_meta($order->get_id(), '_payment_method', true);

            if (!$payment_method) { $payment_method = ''; }

            foreach ($order->get_items() as $item) {

                $item_name = $item->get_name();
                $item_invoice_id = false;

                if (0 === strpos($item_name, 'Invoice ')) {

                    $item_invoice_id = absint(explode(' ', $item_name, 2)[1]);

                    if (!$item_invoice_id) {

                        $item_invoice_id = false;

                    }

                } else {

                    $item_invoice_id = false;

                }

                if($item_invoice_id) {

                    $invoice = $wpdb->get_row($wpdb->prepare("SELECT invoice_status FROM {$wpdb->prefix}accounts_invoices WHERE ID=%d;", $item_invoice_id));

                    if (
                        $invoice &&
                        '1' === $invoice->invoice_status
                    ) {

                        $wpdb->update(
                            $wpdb->prefix . 'accounts_invoices',
            	            array(
                                'invoice_status' => 2,
                                'date_paid' => date('Y-m-d'),
                                'payment_method' => (str_contains($payment_method, 'paypal') || str_contains($payment_method, 'ppcp') ? 1 : 5),
                            ),
                            array(
                                'ID' => $item_invoice_id
                            ),
    	                    array(
                                '%d',
                                '%s',
                                '%d'
                            ), 
                            array('%d')
                        );

                        $this->wpa_send_invoice($item_invoice_id, false, false);
                        $this->wpa_send_invoice($item_invoice_id, true, false);

        		    }

                } else {

                    $send_customer_completed_order = true;

                }

            }

            if ($send_customer_completed_order) {

                return $recipient;

            } else {

                if ('completed' !== $order->get_status()) {

                	$order->update_status('completed');

                }

                return false;

            }

        }

	}

    if (!class_exists('wpaCommon')) {

        require_once(dirname(__FILE__) . '/includes/class-wpa-common.php');

    }

	$WPAccounts_Object = new wpaccounts_class();

}

?>
