<?php

/**
 * Plugin Name: Paypal Email Blocker
 * Plugin URI: https://github.com/abolix/woocommerce-paypal-blocker
 * Description: this plugin will block the delivering of product if the paypal addesss is set to be blocked
 * Version: 1.0
 * Author: Abolix
 * Author URI: https://github.com/abolix/woocommerce-paypal-blocker
 */

// Register a custom menu page.
function AddWordPressMenu()
{
    add_menu_page(
        __('Paypal Email Blocker', 'Paypal Blocker'),
        'Paypal Blocker',
        'manage_options',
        'PPMailBlock.php',
        'AddAdminPage'
    );
}

function RegisterFields()
{
    // register our fields
    register_setting('pluginFields', 'PPbanList');
}

function SetPostMeta($post_id, $field_name, $value = '')
{
    if (empty($value) or !$value) {
        delete_post_meta($post_id, $field_name);
    } elseif (!get_post_meta($post_id, $field_name)) {
        add_post_meta($post_id, $field_name, $value);
    } else {
        update_post_meta($post_id, $field_name, $value);
    }
}

function AddAdminPage()
{

?>
    <div class="wrap">
        <h1>PayPal Email Blocker</h1>
        <p>Add Paypal emails that you want to be blocked</p>
        <div class="form-wrap">
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('pluginFields');
                do_settings_sections('pluginFields');
                ?>
                <textarea name="PPbanList" rows="10" cols="80"><?php echo get_option('PPbanList') ?></textarea>
                <p>One Email in each line</p>
                <?php
                submit_button();
                ?>
            </form>
        </div>
    </div>


<?php
} // End of AdminPage function


add_action('admin_menu', 'AddWordPressMenu');
add_action('admin_init', 'RegisterFields');



add_action('valid-paypal-standard-ipn-request', 'OnIPNhook', 9);
function OnIPNhook($posted)
{
    // if (!empty($posted['custom']) && ($order = GetPayPalOrder($posted['custom']))) {
    if (!empty($posted['custom'])) {
        // Lowercase returned variables.
        $posted['payment_status'] = strtolower($posted['payment_status']);
        // $posted Data : {"mc_gross":"12.00","invoice":"WC-3893","protection_eligibility":"Ineligible","item_number1":"","payer_id":"ULLxxxxxKL","tax":"0.00","payment_date":"09:08:02 Jun 03, 2020 PDT","payment_status":"completed","charset":"windows-1252","first_name":"John","mc_fee":"0.89","notify_version":"3.9","custom":"{\"order_id\":3893,\"order_key\":\"wc_order_uDMhxxxxxF5g\"}","payer_status":"verified","business":"xxxxxxxxxxxx@business.example.com","num_cart_items":"1","verify_sign":"AYWSaLhaxXcxxxxxxxxxxxgKNdSaK4K-c8","payer_email":"xxxxxxxx@personal.example.com","txn_id":"2R64xxxxxxx459","payment_type":"instant","last_name":"Doe","item_name1":"Your Product Title","receiver_email":"xxxxxxxxxxx@business.example.com","payment_fee":"0.89","shipping_discount":"0.00","quantity1":"1","insurance_amount":"0.00","receiver_id":"33CGxxxxxWSWJ","txn_type":"cart","discount":"0.00","mc_gross_1":"12.00","mc_currency":"USD","residence_country":"MY","test_ipn":"1","shipping_method":"Default","transaction_subject":"","payment_gross":"12.00","ipn_track_id":"e51xxxxx9a8b"}
        $OrderID = (int) json_decode($posted['custom'], true)['order_id'];
        $PayerEmail = $posted['payer_email'];

        SetPostMeta($OrderID, 'Sender_Paypal', $PayerEmail); // Set Sender Paypal as Order Meta
        // if ('completed' == $posted['payment_status']) {
        //     // Completed Transaction
        // }
    }
}

function onOrderComplete($OrderID)
{
    $BannedPaypals = explode(PHP_EOL, get_option('PPbanList'));
    $JSP = json_encode($BannedPaypals);
    $SenderPayPal = get_post_meta($OrderID, 'Sender_Paypal', true); // Beacuse of Single = true , it will return string
    foreach ($BannedPaypals as $BannedPaypal) {
        $BannedPaypal = str_replace('\r', '', $BannedPaypal);
        $BannedPaypal = trim($BannedPaypal);
        if (strcasecmp($BannedPaypal, $SenderPayPal) == 0) {
            $Order = new WC_Order($OrderID);
            $Order->update_status('on-hold');
            $Order->add_order_note('Acesss Blocked'); // Add a note as order notes
            // Status List
            // wc-pending
            // wc-processing
            // wc-on-hold
            // wc-completed
            // wc-cancelled
            // wc-refunded
            // wc-failed
            break;
        }
    }
}
add_action('woocommerce_order_status_completed', 'onOrderComplete', 10, 1);
