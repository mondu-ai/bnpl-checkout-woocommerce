<?php
/**
 * Plugin order functions file.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create Order
 *
 * @param WC_Order $order
 * @param          $success_url
 * @return array
 */
function create_order(WC_Order $order, $success_url)
{
    $data = order_data_from_wc_order($order);

    if (is_wc_endpoint_url('order-pay')) {
        $decline_url = $order->get_checkout_payment_url();
        $cancel_url  = $order->get_checkout_payment_url();
    } else {
        $decline_url = wc_get_checkout_url();
        $cancel_url = wc_get_checkout_url();
    }

    $success_url = get_home_url(
        ) . '/?rest_route=/mondu/v1/orders/confirm&external_reference_id=' . $order->get_order_number(
        ) . '&return_url=' . urlencode($success_url);
    $decline_url = get_home_url(
        ) . '/?rest_route=/mondu/v1/orders/decline&external_reference_id=' . $order->get_order_number(
        ) . '&return_url=' . urlencode($decline_url);

    $data['success_url']  = $success_url;
    $data['cancel_url']   = $cancel_url;
    $data['declined_url'] = $decline_url;
    $data['state_flow']   = 'authorization_flow';
    $data['language']     = get_language();

    return $data;
}

/**
 * Invoice Data from WC_Order
 *
 * @param WC_Order $order
 * @return array
 */
function invoice_data_from_wc_order(WC_Order $order)
{
    $invoice_number = get_invoice_number($order);

    $invoice_data = [
        'external_reference_id' => $invoice_number,
        'invoice_url'           => create_invoice_url($order),
        'gross_amount_cents'    => round((float)$order->get_total() * 100),
        'tax_cents'             => round((float)($order->get_total_tax() - $order->get_shipping_tax()) * 100),
        // Considering that is not possible to save taxes that does not belongs to products, removes shipping taxes here
        'discount_cents'        => round((float)$order->get_discount_total() * 100),
        'shipping_price_cents'  => round((float)($order->get_shipping_total() + $order->get_shipping_tax()) * 100),
        // Considering that is not possible to save taxes that does not belongs to products, sum shipping taxes here
    ];

    if ($order->get_shipping_method()) {
        $invoice_data['shipping_info']['shipping_method'] = $order->get_shipping_method();
    }

    if (count($order->get_items()) > 0) {
        $invoice_data['line_items'] = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $line_item = [
                'external_reference_id' => not_null_or_empty($product->get_id()) ? (string)$product->get_id() : null,
                'quantity'              => $item->get_quantity(),
            ];

            $invoice_data['line_items'][] = $line_item;
        }
    }

    return $invoice_data;
}

/**
 * Create Credit note
 *
 * @param WC_Order_Refund $refund
 * @return array
 */
function create_credit_note(WC_Order_Refund $refund)
{
    $credit_note = [
        'gross_amount_cents'    => abs(round((float)$refund->get_total() * 100)),
        'tax_cents'             => abs(round((float)$refund->get_total_tax() * 100)),
        'external_reference_id' => (string)$refund->get_id(),
    ];

    if ($refund->get_reason()) {
        $credit_note['notes'] = $refund->get_reason();
    }

    if (count($refund->get_items()) > 0) {
        $credit_note['line_items'] = [];

        foreach ($refund->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $line_item = [
                'external_reference_id' => not_null_or_empty($product->get_id()) ? (string)$product->get_id() : null,
                'quantity'              => abs($item->get_quantity()), // The quantity will be negative
            ];

            $credit_note['line_items'][] = $line_item;
        }
    }

    return $credit_note;
}

/**
 * Order Data from WC_Order
 *
 * @param WC_Order $order
 * @return array
 */
function order_data_from_wc_order(WC_Order $order)
{
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name  = $order->get_billing_last_name();
    $billing_email      = $order->get_billing_email();
    $billing_phone      = $order->get_billing_phone();
    $customer_id        = $order->get_customer_id() ?: null;

    $billing_address_line1 = $order->get_billing_address_1();
    $billing_address_line2 = $order->get_billing_address_2();
    $billing_city          = $order->get_billing_city();
    $billing_state         = $order->get_billing_state();
    $billing_zip_code      = $order->get_billing_postcode();
    $billing_country_code  = $order->get_billing_country();

    $order_data = [
        'payment_method'        => array_flip(PAYMENT_METHODS)[$order->get_payment_method()],
        'currency'              => get_woocommerce_currency(),
        'external_reference_id' => (string)$order->get_order_number(),
        'gross_amount_cents'    => round((float)$order->get_total() * 100),
        'net_price_cents'       => round((float)$order->get_subtotal() * 100),
        'tax_cents'             => round((float)$order->get_total_tax() * 100),
        'buyer'                 => [
            'first_name'            => isset($billing_first_name) && not_null_or_empty(
                $billing_first_name
            ) ? $billing_first_name : null,
            'last_name'             => isset($billing_last_name) && not_null_or_empty(
                $billing_last_name
            ) ? $billing_last_name : null,
            'company_name'          => get_company_name_from_wc_order($order),
            'email'                 => isset($billing_email) && not_null_or_empty(
                $billing_email
            ) ? $billing_email : null,
            'phone'                 => isset($billing_phone) && not_null_or_empty(
                $billing_phone
            ) ? $billing_phone : null,
            'external_reference_id' => isset($customer_id) && not_null_or_empty(
                $customer_id
            ) ? (string)$customer_id : null,
            'is_registered'         => is_user_logged_in(),
        ],
        'billing_address'       => [
            'address_line1' => isset($billing_address_line1) && not_null_or_empty(
                $billing_address_line1
            ) ? $billing_address_line1 : null,
            'address_line2' => isset($billing_address_line2) && not_null_or_empty(
                $billing_address_line2
            ) ? $billing_address_line2 : null,
            'city'          => isset($billing_city) && not_null_or_empty($billing_city) ? $billing_city : null,
            'state'         => isset($billing_state) && not_null_or_empty($billing_state) ? $billing_state : null,
            'zip_code'      => isset($billing_zip_code) && not_null_or_empty(
                $billing_zip_code
            ) ? $billing_zip_code : null,
            'country_code'  => isset($billing_country_code) && not_null_or_empty(
                $billing_country_code
            ) ? $billing_country_code : null,
        ],
        'shipping_address'      => get_shipping_address_from_order($order),
        'lines'                 => get_lines_from_order($order),
    ];

    return $order_data;
}

/**
 * Order Data from WC_Order with Amount
 *
 * @param WC_Order $order
 * @return array
 */
function order_data_from_wc_order_with_amount(WC_Order $order)
{
    $data           = order_data_from_wc_order($order);
    $data['amount'] = get_amount_from_wc_order($order);

    return $data;
}

/**
 * Get shipping address from order
 *
 * @param WC_Order $order
 * @return array
 */
function get_shipping_address_from_order(WC_Order $order)
{
    $shipping_data = [];

    $shipping_address_line1 = $order->get_shipping_address_1();
    $shipping_address_line2 = $order->get_shipping_address_2();
    $shipping_city          = $order->get_shipping_city();
    $shipping_state         = $order->get_shipping_state();
    $shipping_zip_code      = $order->get_shipping_postcode();
    $shipping_country_code  = $order->get_shipping_country();

    $billing_address_line1 = $order->get_billing_address_1();
    $billing_address_line2 = $order->get_billing_address_2();
    $billing_city          = $order->get_billing_city();
    $billing_state         = $order->get_billing_state();
    $billing_zip_code      = $order->get_billing_postcode();
    $billing_country_code  = $order->get_billing_country();

    if ($order->needs_shipping_address()) {
        $shipping_data = [
            'address_line1' => isset($shipping_address_line1) && not_null_or_empty(
                $shipping_address_line1
            ) ? $shipping_address_line1 : null,
            'address_line2' => isset($shipping_address_line2) && not_null_or_empty(
                $shipping_address_line2
            ) ? $shipping_address_line2 : null,
            'city'          => isset($shipping_city) && not_null_or_empty($shipping_city) ? $shipping_city : null,
            'state'         => isset($shipping_state) && not_null_or_empty($shipping_state) ? $shipping_state : null,
            'zip_code'      => isset($shipping_zip_code) && not_null_or_empty(
                $shipping_zip_code
            ) ? $shipping_zip_code : null,
            'country_code'  => isset($shipping_country_code) && not_null_or_empty(
                $shipping_country_code
            ) ? $shipping_country_code : null,
        ];
    } else {
        $shipping_data = [
            'address_line1' => isset($billing_address_line1) && not_null_or_empty(
                $billing_address_line1
            ) ? $billing_address_line1 : null,
            'address_line2' => isset($billing_address_line2) && not_null_or_empty(
                $billing_address_line2
            ) ? $billing_address_line2 : null,
            'city'          => isset($billing_city) && not_null_or_empty($billing_city) ? $billing_city : null,
            'state'         => isset($billing_state) && not_null_or_empty($billing_state) ? $billing_state : null,
            'zip_code'      => isset($billing_zip_code) && not_null_or_empty(
                $billing_zip_code
            ) ? $billing_zip_code : null,
            'country_code'  => isset($billing_country_code) && not_null_or_empty(
                $billing_country_code
            ) ? $billing_country_code : null,
        ];
    }

    return $shipping_data;
}

/**
 * Get lines from order
 *
 * @param WC_Order $order
 * @return array
 */
function get_lines_from_order(WC_Order $order)
{
    $line = [
        'discount_cents'       => round($order->get_discount_total() * 100),
        'shipping_price_cents' => round((float)($order->get_shipping_total() + $order->get_shipping_tax()) * 100),
        // Considering that is not possible to save taxes that does not belongs to products, sums shipping taxes here
        'line_items'           => [],
    ];

    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();

        $line_item = [
            'title'                    => $product->get_title(),
            'quantity'                 => $item->get_quantity(),
            'external_reference_id'    => not_null_or_empty($product->get_id()) ? (string)$product->get_id() : null,
            'product_id'               => not_null_or_empty($product->get_id()) ? (string)$product->get_id() : null,
            'product_sku'              => not_null_or_empty($product->get_slug()) ? (string)$product->get_slug() : null,
            'net_price_per_item_cents' => round((float)($item->get_subtotal() / $item->get_quantity()) * 100),
            'net_price_cents'          => round((float)$item->get_subtotal() * 100),
            'tax_cents'                => round((float)$item->get_total_tax() * 100),
            'item_type'                => $product->is_virtual() ? 'VIRTUAL' : 'PHYSICAL',
        ];

        $line['line_items'][] = $line_item;
    }

    return [$line];
}

/**
 * Get amount from WC_Order
 *
 * @param WC_Order $order
 * @return array
 */
function get_amount_from_wc_order(WC_Order $order)
{
    $net_price_cents = 0;
    $tax_cents       = 0;

    foreach ($order->get_items() as $item_id => $item) {
        $net_price_cents += (float)$item->get_subtotal() * 100;
        $tax_cents       += (float)$item->get_total_tax() * 100;
    }

    $amount = [
        'gross_amount_cents' => round((float)$order->get_total() * 100),
        'net_price_cents'    => round($net_price_cents),
        'tax_cents'          => round($tax_cents),
    ];

    return $amount;
}

/**
 * Get company name from WC_Order
 *
 * @param WC_Order $order
 * @return string|null
 */
function get_company_name_from_wc_order(WC_Order $order)
{
    $billing_company_name  = $order->get_billing_company();
    $shipping_company_name = $order->get_shipping_company();

    if (isset($billing_company_name) && not_null_or_empty($billing_company_name)) {
        return $billing_company_name;
    } elseif (isset($shipping_company_name) && not_null_or_empty($shipping_company_name)) {
        return $shipping_company_name;
    } else {
        return null;
    }
}

/**
 * Create invoice url
 *
 * @param WC_Order $order
 * @return mixed|void
 */
function create_invoice_url(WC_Order $order)
{
    if (has_action('generate_wpo_wcpdf')) {
        $invoice_url = add_query_arg(
            '_wpnonce',
            wp_create_nonce('generate_wpo_wcpdf'),
            add_query_arg(
                [
                    'action'        => 'generate_wpo_wcpdf',
                    'document_type' => 'invoice',
                    'order_ids'     => $order->get_id(),
                    'my-account'    => true,
                ],
                admin_url('admin-ajax.php')
            )
        );
    } else {
        $invoice_url = $order->get_view_order_url();
    }

    /**
     * Invoice Url Sent to Mondu API
     *
     * @since 1.3.2
     */
    return apply_filters('mondu_invoice_url', $invoice_url);
}

/**
 * Get invoice WCPDF document
 *
 * @param WC_Order $order
 * @return mixed
 */
function get_invoice(WC_Order $order)
{
    if (function_exists('wcpdf_get_invoice')) {
        return wcpdf_get_invoice($order, false);
    } else {
        return $order;
    }
}

/**
 * Get invoice number
 *
 * @param WC_Order $order
 * @return string
 */
function get_invoice_number(WC_Order $order)
{
    if (function_exists('wcpdf_get_invoice')) {
        $document = wcpdf_get_invoice($order, false);
        if ($document->get_number()) {
            $invoice_number = $document->get_number()->get_formatted();
        } else {
            $invoice_number = $order->get_order_number();
        }
    } else {
        $invoice_number = $order->get_order_number();
    }

    /**
     * Reference ID for invoice
     *
     * @since 1.3.2
     */
    return apply_filters('mondu_invoice_reference_id', $invoice_number);
}

/**
 * Get order from order number
 *
 * @param int|string $order_number
 * @return false|WC_Order
 */
function get_order_from_order_number($order_number)
{
    $order = wc_get_order($order_number);
    if ($order) {
        return $order;
    }

    $search_key  = '_order_number';
    $search_term = $order_number;

    if (is_plugin_active('custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php')) {
        $search_key = '_alg_wc_full_custom_order_number';
    }

    if (is_plugin_active('wp-lister-amazon/wp-lister-amazon.php')) {
        $search_key = '_wpla_amazon_order_id';
    }

    if (is_plugin_active('yith-woocommerce-sequential-order-number-premium/init.php')) {
        $search_key = '_ywson_custom_number_order_complete';
    }

    if (is_plugin_active('woocommerce-jetpack/woocommerce-jetpack.php') || is_plugin_active(
            'booster-plus-for-woocommerce/booster-plus-for-woocommerce.php'
        )) {
        $wcj_order_numbers_enabled = get_option('wcj_order_numbers_enabled');

        // Get prefix and suffix options
        $prefix = do_shortcode(get_option('wcj_order_number_prefix', ''));
        $prefix .= date_i18n(get_option('wcj_order_number_date_prefix', ''));
        $suffix = do_shortcode(get_option('wcj_order_number_suffix', ''));
        $suffix .= date_i18n(get_option('wcj_order_number_date_suffix', ''));

        // Ignore suffix and prefix from search input
        $search_no_suffix            = preg_replace("/\A{$prefix}/i", '', $order_number);
        $search_no_suffix_and_prefix = preg_replace("/{$suffix}\z/i", '', $search_no_suffix);
        $final_search                = empty($search_no_suffix_and_prefix) ? $order_number : $search_no_suffix_and_prefix;

        $search_term_fallback = substr($final_search, strlen($prefix));
        $search_term_fallback = ltrim($search_term_fallback, 0);

        if (strlen($suffix) > 0) {
            $search_term_fallback = substr($search_term_fallback, 0, -strlen($suffix));
        }

        if ('yes' == $wcj_order_numbers_enabled) {
            if ('no' == get_option('wcj_order_number_sequential_enabled')) {
                $order_id = $final_search;
            } else {
                $search_key  = '_wcj_order_number';
                $search_term = $final_search;
            }
        }
    }

    if (!isset($order_id)) {
        $args  = [
            'numberposts'            => 1,
            'post_type'              => 'shop_order',
            'fields'                 => 'ids',
            'post_status'            => 'any',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'     => $search_key,
                    'value'   => $search_term,
                    'compare' => '=',
                ],
            ]
        ];
        $query = new WP_Query($args);

        if (!empty($query->posts)) {
            $order_id = $query->posts[0];
        } elseif (isset($search_term_fallback)) {
            $args  = [
                'numberposts'            => 1,
                'post_type'              => 'shop_order',
                'fields'                 => 'ids',
                'post_status'            => 'any',
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'     => $search_key,
                        'value'   => $search_term_fallback,
                        'compare' => '=',
                    ],
                ]
            ];
            $query = new WP_Query($args);

            if (!empty($query->posts)) {
                $order_id = $query->posts[0];
            }
        }
    }

    if ( !isset( $order_id ) ) {
        Mondu_WC()->log(
            [
                'message'              => 'Error trying to fetch the order',
                'order_id_isset'       => isset($order_id),
                'order_number'         => $order_number,
                'search_key'           => $search_key,
                'search_term'          => $search_term,
                'search_term_fallback' => isset($search_term_fallback),
            ]
        );
        return false;
    }

    return wc_get_order( $order_id );
}

/**
 * @param $mondu_order_uuid
 * @return bool|WC_Order
 */
function get_order_from_mondu_uuid( $mondu_order_uuid ) {
    $search_key = ORDER_ID_KEY;
    $search_term = $mondu_order_uuid;

    $args = array(
        'numberposts'            => 1,
        'post_type'              => 'shop_order',
        'fields'                 => 'ids',
        'post_status'            => 'any',
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => array(
            array(
                'key'     => $search_key,
                'value'   => $search_term,
                'compare' => '=',
            ),
        )
    );
    $query = new WP_Query( $args );
    $order_id = $query->posts[ 0 ];
    $order =  wc_get_order( $order_id );

    if ( !$order ) {
        Mondu_WC()->log([
            'message'              => 'Error trying to fetch the order',
            'order_id_isset'       => isset( $order_id ),
            'mondu_order_uuid'     => $mondu_order_uuid,
            'search_key'           => $search_key,
            'search_term'          => $search_term,
            'search_term_fallback' => isset( $search_term_fallback ),
        ]);
    }

    return $order;
}

/**
 * @param $order_number
 * @param $mondu_order_uuid
 * @return bool|WC_Order
 */
function get_order_from_order_number_or_uuid( $order_number = null, $mondu_order_uuid = null) {
    if ( Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        // HPOS usage is enabled.
        return get_order_hpos($order_number, $mondu_order_uuid);
    } else {
        // Traditional CPT-based orders are in use.
        return get_order_cpt($order_number, $mondu_order_uuid);
    }
}

/**
 * @param $order_number
 * @param $mondu_order_uuid
 * @return bool|WC_Order
 */
function get_order_hpos($order_number, $mondu_order_uuid) {
    $order = wc_get_order($order_number);

    if ( $order ) {
        return $order;
    }

    $orders = wc_get_orders([
        'meta_key' => ORDER_ID_KEY,
        'meta_value' => $mondu_order_uuid
    ]);

    if ( !empty($orders) ) {
        return $orders[0];
    }

    return false;
}

/**
 * @param $order_number
 * @param $mondu_order_uuid
 * @return bool|WC_Order
 */
function get_order_cpt( $order_number = null, $mondu_order_uuid = null ) {
    $order = false;

    if ( $order_number ) {
        $order = get_order_from_order_number( $order_number );
    }

    if ( !$order && $mondu_order_uuid ) {
        $order = get_order_from_mondu_uuid( $mondu_order_uuid );
    }

    return $order;
}

/**
 * @param WC_Order $order
 * @return bool
 */
function order_has_mondu(WC_Order $order)
{
    if (!in_array($order->get_payment_method(), PAYMENT_METHODS, true)) {
        return false;
    }

    return true;
}
