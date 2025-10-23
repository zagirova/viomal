<?php
/*Copyright: © 2017 Webtomizer.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Webtomizer\WCDP\WC_Deposits_Admin_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * @return mixed
 */
function wc_deposits_deposit_breakdown_tooltip()
{

    $display_tooltip = get_option('wc_deposits_breakdown_cart_tooltip') === 'yes';


    $tooltip_html = '';

    if ($display_tooltip && isset(WC()->cart->deposit_info['deposit_breakdown']) && is_array(WC()->cart->deposit_info['deposit_breakdown'])) {

        $labels = apply_filters('wc_deposits_deposit_breakdown_tooltip_labels', array(
            'cart_items' => esc_html__('Cart items', 'woocommerce-deposits'),
            'fees' => esc_html__('Fees', 'woocommerce-deposits'),
            'taxes' => esc_html__('Tax', 'woocommerce-deposits'),
            'shipping' => esc_html__('Shipping', 'woocommerce-deposits'),
        ));

        $deposit_breakdown = WC()->cart->deposit_info['deposit_breakdown'];
        $tip_information = '<ul>';
        foreach ($deposit_breakdown as $component_key => $component) {

            if (!isset($labels[$component_key])) continue;
            if ($component === 0) {
                continue;
            }
            switch ($component_key) {
                case 'cart_items' :
                    $tip_information .= '<li>' . $labels['cart_items'] . ' : ' . wc_price($component) . '</li>';

                    break;
                case 'fees' :
                    $tip_information .= '<li>' . $labels['fees'] . ' : ' . wc_price($component) . '</li>';
                    break;
                case 'taxes' :
                    $tip_information .= '<li>' . $labels['taxes'] . ' : ' . wc_price($component) . '</li>';

                    break;
                case 'shipping' :
                    $tip_information .= '<li>' . $labels['shipping'] . ' : ' . wc_price($component) . '</li>';

                    break;

                default :
                    break;
            }
        }

        $tip_information .= '</ul>';

        $tooltip_html = '<span id="deposit-help-tip" data-tip="' . esc_attr($tip_information) . '">&#63;</span>';
    }

    return apply_filters('woocommerce_deposits_tooltip_html', $tooltip_html);
}


/** http://jaspreetchahal.org/how-to-lighten-or-darken-hex-or-rgb-color-in-php-and-javascript/
 * @param $color_code
 * @param int $percentage_adjuster
 * @return array|string
 * @author Jaspreet Chahal
 */
function wc_deposits_adjust_colour($color_code, $percentage_adjuster = 0)
{
    $percentage_adjuster = round($percentage_adjuster / 100, 2);

    if (is_array($color_code)) {
        $r = $color_code["r"] - (round($color_code["r"]) * $percentage_adjuster);
        $g = $color_code["g"] - (round($color_code["g"]) * $percentage_adjuster);
        $b = $color_code["b"] - (round($color_code["b"]) * $percentage_adjuster);

        $adjust_color = array("r" => round(max(0, min(255, $r))),
            "g" => round(max(0, min(255, $g))),
            "b" => round(max(0, min(255, $b))));
    } elseif (preg_match("/#/", $color_code)) {
        $hex = str_replace("#", "", $color_code);
        $r = (strlen($hex) == 3) ? hexdec(substr($hex, 0, 1) . substr($hex, 0, 1)) : hexdec(substr($hex, 0, 2));
        $g = (strlen($hex) == 3) ? hexdec(substr($hex, 1, 1) . substr($hex, 1, 1)) : hexdec(substr($hex, 2, 2));
        $b = (strlen($hex) == 3) ? hexdec(substr($hex, 2, 1) . substr($hex, 2, 1)) : hexdec(substr($hex, 4, 2));
        $r = round($r - ($r * $percentage_adjuster));
        $g = round($g - ($g * $percentage_adjuster));
        $b = round($b - ($b * $percentage_adjuster));

        $adjust_color = "#" . str_pad(dechex(max(0, min(255, $r))), 2, "0", STR_PAD_LEFT)
            . str_pad(dechex(max(0, min(255, $g))), 2, "0", STR_PAD_LEFT)
            . str_pad(dechex(max(0, min(255, $b))), 2, "0", STR_PAD_LEFT);

    } else {
        $adjust_color = new WP_Error('', 'Invalid Color format');
    }


    return $adjust_color;
}

/**
 * @brief returns the frontend colours from the WooCommerce settings page, or the defaults.
 *
 * @return array
 */

function wc_deposits_woocommerce_frontend_colours()
{
    $colors = (array)get_option('woocommerce_colors');
    if (empty($colors['primary']))
        $colors['primary'] = '#ad74a2';
    if (empty($colors['secondary']))
        $colors['secondary'] = '#f7f6f7';
    if (empty($colors['highlight']))
        $colors['highlight'] = '#85ad74';
    if (empty($colors['content_bg']))
        $colors['content_bg'] = '#ffffff';
    return $colors;
}


/**
 * @return bool
 */
function wcdp_checkout_mode()
{

    return get_option('wc_deposits_checkout_mode_enabled') === 'yes';
}

/**
 * @param $product
 * @return float
 */
function wc_deposits_calculate_product_deposit($product)
{


    $deposit_enabled = wc_deposits_is_product_deposit_enabled($product->get_id());
    $product_type = $product->get_type();
    if ($deposit_enabled) {


        $deposit = wc_deposits_get_product_deposit_amount($product->get_id());
        $amount_type = wc_deposits_get_product_deposit_amount_type($product->get_id());


        $woocommerce_prices_include_tax = get_option('woocommerce_prices_include_tax');

        if ($woocommerce_prices_include_tax === 'yes') {

            $amount = wc_get_price_including_tax($product);

        } else {
            $amount = wc_get_price_excluding_tax($product);

        }

        switch ($product_type) {


            case 'subscription' :
                if (class_exists('WC_Subscriptions_Product')) {

                    $amount = \WC_Subscriptions_Product::get_sign_up_fee($product);
                    if ($amount_type === 'fixed') {
                    } else {
                        $deposit = $amount * ($deposit / 100.0);
                    }

                }
                break;
            case 'yith_bundle' :
                $amount = $product->price_per_item_tot;
                if ($amount_type === 'fixed') {
                } else {
                    $deposit = $amount * ($deposit / 100.0);
                }
                break;
            case 'variable' :

                if ($amount_type === 'fixed') {
                } else {
                    $deposit = $amount * ($deposit / 100.0);
                }
                break;

            default:


                if ($amount_type !== 'fixed') {

                    $deposit = $amount * ($deposit / 100.0);
                }

                break;
        }

        return floatval($deposit);
    }
}

/**
 * @brief checks if deposit is enabled for product
 * @param $product_id
 * @return mixed
 */
function wc_deposits_is_product_deposit_enabled($product_id)
{
    $enabled = false;
    $product = wc_get_product($product_id);
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $enabled = get_option('wc_deposits_storewide_deposit_enabled', 'no') === 'yes';
            } else {
                $override = $product->get_meta('_wc_deposits_override_product_settings', true) === 'yes';

                if ($override) {
                    $enabled = $product->get_meta('_wc_deposits_enable_deposit', true) === 'yes';
                } else {
                    $enabled = $parent->get_meta('_wc_deposits_enable_deposit', true) === 'yes';
                }
            }


        } else {

            $inherit = $product->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $enabled = get_option('wc_deposits_storewide_deposit_enabled', 'no') === 'yes';
            } else {
                $enabled = $product->get_meta('_wc_deposits_enable_deposit', true) === 'yes';
            }
        }


    }

    return apply_filters('wc_deposits_product_enable_deposit', $enabled, $product_id);

}

function wc_deposits_is_product_deposit_forced($product_id)
{
    $forced = false;
    $product = wc_get_product($product_id);
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $forced = get_option('wc_deposits_storewide_deposit_force_deposit', 'no') === 'yes';
            } else {
                $override = $product->get_meta('_wc_deposits_override_product_settings', true) === 'yes';

                if ($override) {
                    $forced = $product->get_meta('_wc_deposits_force_deposit', true) === 'yes';
                } else {
                    $forced = $parent->get_meta('_wc_deposits_force_deposit', true) === 'yes';
                }
            }


        } else {

            $inherit = $product->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $forced = get_option('wc_deposits_storewide_deposit_force_deposit', 'no') === 'yes';
            } else {
                $forced = $product->get_meta('_wc_deposits_force_deposit', true) === 'yes';
            }
        }
    }

    return apply_filters('wc_deposits_product_force_deposit', $forced, $product_id);

}

function wc_deposits_get_product_deposit_amount($product_id)
{
    $amount = false;
    $product = wc_get_product($product_id);

    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount = get_option('wc_deposits_storewide_deposit_amount', '50');
            } else {
                $override = $product->get_meta('_wc_deposits_override_product_settings', true) === 'yes';

                if ($override) {
                    $amount = $product->get_meta('_wc_deposits_deposit_amount', true);
                } else {
                    $amount = $parent->get_meta('_wc_deposits_deposit_amount', true);
                }
            }


        } else {

            $inherit = $product->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount = get_option('wc_deposits_storewide_deposit_amount', '50');
            } else {
                $amount = $product->get_meta('_wc_deposits_deposit_amount', true);
            }
        }
    }


    return apply_filters('wc_deposits_product_deposit_amount', $amount, $product_id);

}

function wc_deposits_get_product_deposit_amount_type($product_id)
{

    $amount_type = false;
    $product = wc_get_product($product_id);


    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount_type = get_option('wc_deposits_storewide_deposit_amount_type', 'percent');
            } else {

                $override = $product->get_meta('_wc_deposits_override_product_settings', true) === 'yes';

                if ($override) {
                    $amount_type = $product->get_meta('_wc_deposits_amount_type', true);
                } else {
                    $amount_type = $parent->get_meta('_wc_deposits_amount_type', true);
                }
            }


        } else {

            $inherit = $product->get_meta('_wc_deposits_inherit_storewide_settings');
            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount_type = get_option('wc_deposits_storewide_deposit_amount_type', 'percent');
            } else {
                $amount_type = $product->get_meta('_wc_deposits_amount_type', true);
            }
        }
    }
    return apply_filters('wc_deposits_product_deposit_amount_type', $amount_type, $product_id);
}


function wc_deposits_get_product_available_plans($product_id)
{
    $product = wc_get_product($product_id);
    $plans = array();
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_wc_deposits_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $plans = get_option('wc_deposits_storewide_deposit_payment_plans', array());
            } else {
                $override = $product->get_meta('_wc_deposits_override_product_settings', true) === 'yes';
                if ($override) {
                    $plans = $product->get_meta('_wc_deposits_payment_plans', true);
                } else {
                    $plans = $parent->get_meta('_wc_deposits_payment_plans', true);
                }
            }

        } else {

            $inherit = $product->get_meta('_wc_deposits_inherit_storewide_settings');
            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $plans = get_option('wc_deposits_storewide_deposit_payment_plans', array());
            } else {
                $plans = $product->get_meta('_wc_deposits_payment_plans', true);
            }
        }
    }

    return apply_filters('wc_deposits_product_deposit_available_plans', $plans, $product_id);
}

function wc_deposits_delete_current_schedule($order)
{

    $payments = wcdp_get_order_partial_payments($order->get_id(), [], false);
    foreach ($payments as $payment) {
        wp_delete_post(absint($payment), true);
    }

    $order->delete_meta_data('_wc_deposits_payment_schedule');
    $order->save();

}


function wc_deposits_create_payment_schedule($order, $sorted_schedule = array())
{

    /**   START BUILD PAYMENT SCHEDULE**/
    try {

        //fix wpml language
        $wpml_lang = $order->get_meta('wpml_language', true);
        $partial_payments_structure = apply_filters('wc_deposits_partial_payments_structure', get_option('wc_deposits_partial_payments_structure', 'single'), 'order');

        foreach ($sorted_schedule as $partial_key => $payment) {

            $partial_payment = new WCDP_Payment();


            //migrate all fields from parent order


            $partial_payment->set_customer_id($order->get_user_id());


            if ($partial_payments_structure === 'single') {
                $amount = $payment['total'];
                //allow partial payments to be inserted only as a single fee without item details
                $name = esc_html__('Partial Payment for order %s', 'woocommerce-deposits');
                $partial_payment_name = apply_filters('wc_deposits_partial_payment_name', sprintf($name, $order->get_order_number()), $payment, $order->get_id());


                $item = new WC_Order_Item_Fee();
                $item->set_props(
                    array(
                        'total' => $amount
                    )
                );
                $item->set_name($partial_payment_name);
                $partial_payment->add_item($item);
                $partial_payment->set_total($amount);


            } else {
                Wc_Deposits_Admin_order::create_partial_payment_items($partial_payment, $order, $payment['details']);
                $partial_payment->save();
//                $partial_payment->recalculate_coupons();
                $partial_payment->calculate_totals(false);
                $partial_payment->add_meta_data('_wc_deposits_partial_payment_itemized', 'yes');

            }
            $is_vat_exempt = $order->get_meta('is_vat_exempt', true);

            if (isset($payment['timestamp']) && is_numeric($payment['timestamp'])) {
                $partial_payment->add_meta_data('_wc_deposits_partial_payment_date', $payment['timestamp']);
            }

            if (isset($payment['details'])) {
                $partial_payment->add_meta_data('_wc_deposits_partial_payment_details', $payment['details']);
            }
            $partial_payment->set_parent_id($order->get_id());
            $partial_payment->add_meta_data('is_vat_exempt', $is_vat_exempt);
            $partial_payment->add_meta_data('_wc_deposits_payment_type', $payment['type'], true);
            $partial_payment->set_currency($order->get_currency());
            $partial_payment->set_prices_include_tax($order->get_prices_include_tax());
            $partial_payment->set_customer_ip_address($order->get_customer_ip_address());
            $partial_payment->set_customer_user_agent($order->get_customer_user_agent());

            if ($order->get_status() === 'partially-paid' && $payment['type'] === 'deposit') {

                //we need to save to generate id first
                $partial_payment->set_status('completed');

            }

            if (!empty($wpml_lang)) {
                $partial_payment->update_meta_data('wpml_language', $wpml_lang);
            }


            if (floatval($partial_payment->get_total()) == 0.0) $partial_payment->set_status('completed');

            $partial_payment->save();
            do_action('wc_deposits_partial_payment_created', $partial_payment->get_id(), 'backend');

            $sorted_schedule[$partial_key]['id'] = $partial_payment->get_id();

        }
        return $sorted_schedule;
    } catch (\Exception $e) {
        print_r(new WP_Error('error', $e->getMessage()));
    }

}

function wcdp_get_order_partial_payments($order_id, $args = array(), $object = true)
{
    $default_args = array(
        'parent' => $order_id,
        'type' => 'wcdp_payment',
        'limit' => -1,
        'status' => array_keys(wc_get_order_statuses())
    );

    $args = ($args) ? wp_parse_args($args, $default_args) : $default_args;

    $orders = array();

    //get children of order
    $partial_payments = wc_get_orders($args);
    foreach ($partial_payments as $partial_payment) {
        $orders[] = ($object) ? wc_get_order($partial_payment->get_id()) : $partial_payment->get_id();
    }
    return $orders;
}

add_action('woocommerce_after_dashboard_status_widget', 'wcdp_status_widget_partially_paid');
function wcdp_status_widget_partially_paid()
{
    if (!current_user_can('edit_shop_orders')) {
        return;
    }
    $partially_paid_count = 0;
    foreach (wc_get_order_types('order-count') as $type) {
        $counts = (array)wp_count_posts($type);
        $partially_paid_count += isset($counts['wc-partially-paid']) ? $counts['wc-partially-paid'] : 0;
    }
    ?>
    <li class="partially-paid-orders">
        <a href="<?php echo admin_url('edit.php?post_status=wc-partially-paid&post_type=shop_order'); ?>">
            <?php
            printf(
                _n('<strong>%s order</strong> partially paid', '<strong>%s orders</strong> partially paid', $partially_paid_count, 'woocommerce-deposits'),
                $partially_paid_count
            );
            ?>
        </a>
    </li>
    <style>
        #woocommerce_dashboard_status .wc_status_list li.partially-paid-orders a::before {
            content: '\e011';
            color: #ffba00;
    </style>
    <?php
}

function wc_deposits_remove_order_deposit_data($order)
{

    $order->delete_meta_data('_wc_deposits_order_version');
    $order->delete_meta_data('_wc_deposits_order_has_deposit');
    $order->delete_meta_data('_wc_deposits_deposit_paid');
    $order->delete_meta_data('_wc_deposits_second_payment_paid');
    $order->delete_meta_data('_wc_deposits_deposit_amount');
    $order->delete_meta_data('_wc_deposits_second_payment');
    $order->delete_meta_data('_wc_deposits_deposit_breakdown');
    $order->delete_meta_data('_wc_deposits_deposit_payment_time');
    $order->delete_meta_data('_wc_deposits_second_payment_reminder_email_sent');
    $order->save();

}

function wc_deposits_validate_customer_eligibility($enabled)
{
    //user restriction
    $allow_deposit_for_guests = get_option('wc_deposits_restrict_deposits_for_logged_in_users_only', 'no');

    if ($allow_deposit_for_guests !== 'no' && is_user_logged_in() && isset($_POST['createaccount']) && $_POST['createaccount'] == 1) {
        //account created during checkout
        $enabled = false;

    } elseif (is_user_logged_in()) {

        $disabled_user_roles = get_option('wc_deposits_disable_deposit_for_user_roles', array());
        if (!empty($disabled_user_roles)) {

            foreach ($disabled_user_roles as $disabled_user_role) {

                if (wc_current_user_has_role($disabled_user_role)) {

                    $enabled = false;
                }
            }
        }
    } else {
        if ($allow_deposit_for_guests !== 'no') {
            $enabled = false;
        }
    }

    return $enabled;
}

add_filter('wc_deposits_deposit_enabled_for_customer', 'wc_deposits_validate_customer_eligibility');

function wcdp_valid_parent_statuses_for_partial_payment()
{
    return apply_filters('wc_deposits_valid_parent_statuses_for_partial_payment', array('partially-paid'));
}

function wcdp_partial_payment_complete_order_status()
{
    return apply_filters('wc_deposits_partial_payment_complete_order_status', 'partially-paid');
}

function WCDP()
{
    return \Webtomizer\WCDP\WC_Deposits::get_singleton();
}

add_action('wc_deposits_job_scheduler', 'wcdp_backward_compatibility_cron_trigger');
function wcdp_backward_compatibility_cron_trigger()
{
    do_action('woocommerce_deposits_second_payment_reminder');
}


function wcdp_is_wcdp_payment_screen()
{


    if (!function_exists('get_current_screen')) return false;
    $screen = get_current_screen();

    $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    $screen_name = $hpos_enabled && function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('wcdp_payment') : 'wcdp_payment';
    return $screen->id == $screen_name;

}


function wcdp_is_shop_order_screen()
{


    if (!function_exists('get_current_screen')) return false;
    $screen = get_current_screen();

    $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    $screen_name = $hpos_enabled && function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

    return $screen->id == $screen_name;
}