<?php

function wpa_export() {
global $wpdb;

    $client_id = absint($_GET['id']);
    $invoices = $wpdb->get_results($wpdb->prepare("SELECT " . $wpdb->prefix . "accounts_invoices.ID, " . $wpdb->prefix . "accounts_clients.contact, " . $wpdb->prefix . "accounts_clients.company, " . $wpdb->prefix . "accounts_invoices.invoice_date, " . $wpdb->prefix . "accounts_invoices.price1, " . $wpdb->prefix . "accounts_invoices.price2, " . $wpdb->prefix . "accounts_invoices.price3, " . $wpdb->prefix . "accounts_invoices.price4, " . $wpdb->prefix . "accounts_invoices.price5, " . $wpdb->prefix . "accounts_invoices.date_paid, " . $wpdb->prefix . "accounts_invoice_status.status, " . $wpdb->prefix . "accounts_payment_methods.payment_method FROM " . $wpdb->prefix . "accounts_invoices INNER JOIN " . $wpdb->prefix . "accounts_clients ON " . $wpdb->prefix . "accounts_invoices.client = " . $wpdb->prefix . "accounts_clients.ID INNER JOIN " . $wpdb->prefix . "accounts_invoice_status ON " . $wpdb->prefix . "accounts_invoices.invoice_status = " . $wpdb->prefix . "accounts_invoice_status.ID LEFT JOIN " . $wpdb->prefix . "accounts_payment_methods ON " . $wpdb->prefix . "accounts_invoices.payment_method = " . $wpdb->prefix . "accounts_payment_methods.ID WHERE client = %s ORDER BY invoice_date DESC;",$client_id));
    $client_name = '';

    if ($invoices[0]->company) {

        $client_name = $invoices[0]->company . ', ';
        $filename = sanitize_title($invoices[0]->company);

    } else {

        $filename = sanitize_title($invoices[0]->contact);

    }

    $client_name .= $invoices[0]->contact;
    $filename = $filename . '-statement.htm';
    header( 'Content-Type: text/html' );
    header( 'Content-Disposition: attachment;filename='.$filename);

?>

  <h1><?php _e($client_name . ' Statement'); ?></h1>

    <table>
        <thead>
            <tr>
                <th><?php _e('Invoice')?></th>
                <th><?php _e('Date')?></th>
                <th><?php _e('Status')?></th>
                <th><?php _e('Amount')?></th>
            </tr>
        </thead>
        <tbody>

<?php

    if ($invoices) {

        $total_due=0;

        foreach ($invoices as $invoice) {

            if ($invoice->status == 'Unpaid') {

                $total_due = $total_due + $invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5;

            }

?>

            <tr>
                <td><strong<?php if ($invoice->status == 'Cancelled' || $invoice->status == 'Unpaid') { echo ' style="color: red;"'; } ?>><?php echo $invoice->ID; ?></strong></td>
                <td><?php echo date('jS F Y',strtotime($invoice->invoice_date)); ?></td>
                <td><?php

            if ($invoice->status == 'Paid') {

                echo 'Paid by ' . $invoice->payment_method . ' on ' . mysql2date('jS F Y', $invoice->date_paid);

            } else {

                echo '<span style="color: red;">' . $invoice->status . '</span>';

            }

?></td>
            <td<?php if (isset($invoice->invoice_status) && $invoice->invoice_status == 3) { echo ' style="color: red;"'; } ?> align="right">&pound;<?php echo number_format(($invoice->price1 + $invoice->price2 + $invoice->price3 + $invoice->price4 + $invoice->price5),2); ?></td>
        </tr>

<?php

        }

?>

        <tr>
            <td colspan="3"></td>
            <td style="color: red;" align="right">Due Now: <strong>&pound;<?php echo number_format($total_due,2); ?></strong></td>
        </tr>

<?php

    } else {   

?>

        <tr><td colspan="4"><?php _e('There are no invoices.')?></td></tr>

<?php

    }

?>

    </tbody>
</table>

<?php

}

require_once( dirname( dirname( dirname( dirname( __FILE__ )))) . '/wp-load.php' );

check_admin_referer('wpaccounts');

if (current_user_can('manage_options')) {

	wpa_export();

}

?>
