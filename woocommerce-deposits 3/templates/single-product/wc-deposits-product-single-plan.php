<?php

/**
 * The Template for displaying single payment plan display on single product page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/wc-deposits-product-single-plan.php.
 *
 * @package Webtomizer\WCDP\Templates
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

?>
<li>
    <input data-total_am="<?php echo $payment_plan['plan_total'];?>" data-id="<?php echo $plan_id; ?>" <?php echo $count === 0 ? 'checked' : ''; ?>
           type="radio" class="option-input" value="<?php echo $plan_id; ?>"
           name="<?php echo $product->get_id(); ?>-selected-plan"/>
    <label><?php echo $payment_plan['name']; ?></label>


    <span> <a data-expanded="no"
              data-view-text="<?php esc_html_e('View details', 'woocommerce-deposits'); ?>"
              data-hide-text="<?php esc_html_e('Hide details', 'woocommerce-deposits'); ?>"
              data-id="<?php echo $plan_id; ?>"
              class="wcdp-view-plan-details"><?php esc_html_e('View details', 'woocommerce-deposits'); ?></a></span>

    <div style="display:none" class="wcdp-single-plan plan-details-<?php echo $plan_id; ?>"
    >


        <div>
            <p><?php esc_html_e($payment_plan['description'], 'woocommerce-deposits'); ?></p>
        </div>

        <?php if ($product->get_type() !== 'grouped') { ?>


            <div>
                <p><strong><?php esc_html_e('Payments Total', 'woocommerce-deposits'); ?>
                        : <?php echo wc_price($payment_plan['plan_total']); ?></strong></p>
            </div>

            <div>
                <p><?php esc_html_e('Deposit', 'woocommerce-deposits'); ?>
                    : <?php echo wc_price($payment_plan['deposit_amount']); ?></p>
            </div>

            <table>
                <thead>
                <th><?php esc_html_e('Payment Date', 'woocommerce-deposits') ?></th>
                <th><?php esc_html_e('Amount', 'woocommerce-deposits') ?></th>
                </thead>
                <tbody>
                <?php
                $payment_timestamp = current_time('timestamp');
                foreach ($payment_plan['details']['payment-plan'] as $plan_line) {

                    if (isset($plan_line['date']) && !empty($plan_line['date'])) {
                        $payment_timestamp = strtotime($plan_line['date']);
                    } else {
                        $after = $plan_line['after'];
                        $after_term = $plan_line['after-term'];
                        $payment_timestamp = strtotime(date('Y-m-d', $payment_timestamp) . "+{$plan_line['after']} {$plan_line['after-term']}s");
                    }

                    ?>
                    <tr class="kamimbt" data-taotal_plan="<?php echo $plan_line['line_amount']; ?>">
                        <td><?php echo date_i18n(get_option('date_format'), $payment_timestamp) ?></td>
                        <td><?php echo wc_price($plan_line['line_amount']); ?></td>
                    </tr>
                    <?php


                }

                ?>
                </tbody>
            </table>
            <script>
            jQuery(document).ready(function(){
               //jQuery('.option-input').trigger("change"); 
                // var checkedValue = $('.option-input:checked').attr('data-total_am');
                //         var roundedValue = Math.round(parseFloat(checkedValue) * 100) / 100;
                //         var totalElement = document.querySelector('.elementor-widget-container .price .woocommerce-Price-amount.amount');
                //             totalElement.innerHTML = '<span class="woocommerce-Price-currencySymbol">RD$</span>' + roundedValue.toFixed(2);


            });
            jQuery('.option-input').on('change',function(){
//alert('changed');
    total_am = jQuery(this).attr('data-total_am');
 var totalElement = document.querySelector('.elementor-widget-container .price .woocommerce-Price-amount.amount');
console.log(totalElement);
    // Update the innerHTML with the new value
     var roundedValue = Math.round(parseFloat(total_am) * 100) / 100;
    totalElement.innerHTML = '<span class="woocommerce-Price-currencySymbol">RD$</span>' + numberWithCommas(roundedValue.toFixed(2));
    //alert(total_am);
});

jQuery('.pay-full-amount-label').on('click',function(){

    total_am = jQuery(this).attr('data-full-payment');
    var roundedValue = Math.round(parseFloat(total_am) * 100) / 100;
 var totalElement = document.querySelector('.elementor-widget-container .price .woocommerce-Price-amount.amount');
console.log(totalElement);
    // Update the innerHTML with the new value
    totalElement.innerHTML = '<span class="woocommerce-Price-currencySymbol">RD$</span>' + numberWithCommas(roundedValue.toFixed(2));
    //alert(total_am);
});

jQuery('.pay-deposit-label').on('click',function(){
    jQuery('.option-input[type="radio"]').first().prop('checked', true).trigger('change');;
});
function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
</script>
        <?php } ?>
    </div>
</li>