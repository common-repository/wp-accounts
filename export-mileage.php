<?php

function wpa_export() {

    global $wpdb;
    $options = get_option('wp_accounts_options');
    $accounting_period_finish = new DateTime(date('Y')."-".$options['accounting_period_start']);

    if ($accounting_period_finish > new DateTime()) {

    	$accounting_period_start = new DateTime((date('Y')-2)."-".$options['accounting_period_start']);
    	$accounting_period_finish = new DateTime((date('Y')-1)."-".$options['accounting_period_start']);

    } else {

    	$accounting_period_start = new DateTime((date('Y')-1)."-".$options['accounting_period_start']);

    }

    $qry = array();
    $qry[] = "SELECT IFNULL({$wpdb->prefix}accounts_clients.company, {$wpdb->prefix}accounts_clients.contact) AS Client, {$wpdb->prefix}accounts_invoices.invoice_date AS Date, {$wpdb->prefix}accounts_invoices.mileage AS Miles";
    $qry[] = "FROM {$wpdb->prefix}accounts_invoices";
    $qry[] = "INNER JOIN {$wpdb->prefix}accounts_clients ON {$wpdb->prefix}accounts_invoices.client = {$wpdb->prefix}accounts_clients.ID";
    $qry[] = "WHERE {$wpdb->prefix}accounts_invoices.invoice_date >= '" . $accounting_period_start->format('Y-m-d') . "' AND {$wpdb->prefix}accounts_invoices.invoice_date < '" . $accounting_period_finish->format('Y-m-d')  . "'";
    $qry[] = "AND {$wpdb->prefix}accounts_invoices.mileage > 0";
    $qry[] = "UNION ALL";
    $qry[] = "SELECT {$wpdb->prefix}accounts_payments.supplier AS Client, {$wpdb->prefix}accounts_payments.invoice_date AS Date, {$wpdb->prefix}accounts_payments.mileage AS Miles";
    $qry[] = "FROM {$wpdb->prefix}accounts_payments";
    $qry[] = "WHERE {$wpdb->prefix}accounts_payments.invoice_date >= '" . $accounting_period_start->format('Y-m-d') . "' AND {$wpdb->prefix}accounts_payments.invoice_date < '" . $accounting_period_finish->format('Y-m-d')  . "'";
    $qry[] = "AND {$wpdb->prefix}accounts_payments.mileage > 0";
    $qry[] = "ORDER BY Date";
    $result = $wpdb->get_results(implode(" ", $qry), ARRAY_A);

    if ($wpdb->num_rows > 0) {

        $filename = sanitize_title(get_bloginfo('name'))."-mileage-".$accounting_period_start->format('Y-m-d')."-".$accounting_period_finish->format('Y-m-d').".csv";
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment;filename='.$filename);
        $fp = fopen('php://output', 'w');
        $hrow = $result[0];
        fputcsv($fp, array_keys($hrow));

        foreach ($result as $data) {

            fputcsv($fp, $data);

        }

        fclose($fp);

    } else {

echo $wpdb->last_query;

    }

}

require_once( dirname( dirname( dirname( dirname( __FILE__ )))) . '/wp-load.php' );

check_admin_referer('wpaccounts');

if (current_user_can('manage_options')) {

	wpa_export();

}

?>