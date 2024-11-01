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

$qry[] = "SELECT " . $wpdb->prefix . "accounts_invoices.ID AS Invoice_Number, IFNULL(" . $wpdb->prefix . "accounts_clients.company," . $wpdb->prefix . "accounts_clients.contact) AS Client, " . $wpdb->prefix . "accounts_invoices.invoice_date AS Invoice_Date, ";

if (isset($options['recurrent']) && $options['recurrent'] == 'true') {
	$qry[] = "IF(" . $wpdb->prefix . "accounts_invoices.monthly<>0, 'True', 'False') AS Monthly, IF(" . $wpdb->prefix . "accounts_invoices.yearly<>0, 'True', 'False') AS Yearly, ";
}

$qry[] = "(IFNULL(" . $wpdb->prefix . "accounts_invoices.price1,0)+IFNULL(" . $wpdb->prefix . "accounts_invoices.price2,0)+IFNULL(" . $wpdb->prefix . "accounts_invoices.price3,0)+IFNULL(" . $wpdb->prefix . "accounts_invoices.price4,0)+IFNULL(" . $wpdb->prefix . "accounts_invoices.price5,0)) AS Amount, " . $wpdb->prefix . "accounts_invoice_status.status AS Invoice_Status,  " . $wpdb->prefix . "accounts_invoices.date_paid AS Date_Paid,  " . $wpdb->prefix . "accounts_payment_methods.payment_method AS Payment_Method";
$qry[] = "FROM " . $wpdb->prefix . "accounts_invoices INNER JOIN " . $wpdb->prefix . "accounts_clients ON " . $wpdb->prefix . "accounts_invoices.client=" . $wpdb->prefix . "accounts_clients.ID INNER JOIN " . $wpdb->prefix . "accounts_invoice_status ON " . $wpdb->prefix . "accounts_invoices.invoice_status=" . $wpdb->prefix . "accounts_invoice_status.ID LEFT JOIN " . $wpdb->prefix . "accounts_payment_methods ON " . $wpdb->prefix . "accounts_invoices.payment_method=" . $wpdb->prefix . "accounts_payment_methods.ID";
$qry[] = "WHERE invoice_date >= '" . $accounting_period_start->format('Y-m-d') . "' AND invoice_date < '" . $accounting_period_finish->format('Y-m-d')  . "'";
$qry[] = "ORDER BY invoice_date";

$result = $wpdb->get_results(implode(" ", $qry), ARRAY_A);

if ($wpdb->num_rows > 0) {

$filename = sanitize_title(get_bloginfo('name'))."-invoices-raised-".$accounting_period_start->format('Y-m-d')."-".$accounting_period_finish->format('Y-m-d').".csv";

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