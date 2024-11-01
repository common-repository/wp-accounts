<?php

function wpa_export() {
global $wpdb;
$options = get_option('wp_accounts_options');
$accounting_period_finish = new DateTime(date('Y')."-".$options['accounting_period_start']);
if ($accounting_period_finish > new DateTime()) {
	$accounting_period_start = new DateTime((date('Y')-2)."-".$options['accounting_period_start']);;
	$accounting_period_finish = new DateTime((date('Y')-1)."-".$options['accounting_period_start']);;
} else {
	$accounting_period_start = new DateTime((date('Y')-1)."-".$options['accounting_period_start']);;
}

$qry = array();

$qry[] = "SELECT " . $wpdb->prefix . "accounts_payments.ID AS Payment_Number, " . $wpdb->prefix . "accounts_payments.supplier AS Supplier, " . $wpdb->prefix . "accounts_payments.notes AS Nature_of_Expense, " . $wpdb->prefix . "accounts_payments.invoice_date AS Invoice_Date, " . $wpdb->prefix . "accounts_payments.amount AS Amount, " . $wpdb->prefix . "accounts_payments.reference AS Supplier_Reference,  " . $wpdb->prefix . "accounts_expense_types.expense_type AS Expense_Type,  " . $wpdb->prefix . "accounts_payments.date_paid AS Date_Paid,  " . $wpdb->prefix . "accounts_payment_methods.payment_method AS Payment_Method";
$qry[] = "FROM " . $wpdb->prefix . "accounts_payments INNER JOIN " . $wpdb->prefix . "accounts_expense_types ON " . $wpdb->prefix . "accounts_payments.expense_type=" . $wpdb->prefix . "accounts_expense_types.ID INNER JOIN " . $wpdb->prefix . "accounts_payment_methods ON " . $wpdb->prefix . "accounts_payments.payment_method=" . $wpdb->prefix . "accounts_payment_methods.ID";
$qry[] = "WHERE invoice_date >= '" . $accounting_period_start->format('Y-m-d') . "' AND invoice_date < '" . $accounting_period_finish->format('Y-m-d')  . "'";
$qry[] = "ORDER BY date_paid";

$result = $wpdb->get_results(implode(" ", $qry), ARRAY_A);

if ($wpdb->num_rows > 0) {

$filename = sanitize_title(get_bloginfo('name'))."-payments-".$accounting_period_start->format('Y-m-d')."-".$accounting_period_finish->format('Y-m-d').".csv";

header( 'Content-Type: text/csv' );
header( 'Content-Disposition: attachment;filename='.$filename);

$fp = fopen('php://output', 'w');

$hrow = $result[0];

fputcsv($fp, array_keys($hrow));

foreach ($result as $data) {
fputcsv($fp, $data);
}

fclose($fp);

}
}

require_once( dirname( dirname( dirname( dirname( __FILE__ )))) . '/wp-load.php' );

check_admin_referer('wpaccounts');

if (current_user_can('manage_options')) {

	wpa_export();

}

?>
