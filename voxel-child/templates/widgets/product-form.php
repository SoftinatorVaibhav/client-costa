<?php
if ( ! defined('ABSPATH') ) {
    exit;
} ?>

<?php if ( ! $is_purchasable ): ?>
    <div class="ts-form ts-product-form vx-loading">
        <div class="ts-product-main vx-loading-screen">
            <div class="ts-form-group ts-no-posts">
                <?= \Voxel\get_icon_markup( $this->get_settings_for_display('nostock_ico') ) ?: \Voxel\svg( 'box-remove.svg' ) ?>
                <p><?= $error_message ?></p>
            </div>
        </div>
    </div>
<?php else: ?>
    <script type="text/json" class="vxconfig"><?= wp_specialchars_decode( wp_json_encode( $config ) ) ?></script>
    <div class="ts-form ts-product-form vx-loading">
        <div class="ts-product-main vx-loading-screen">

            <div class="ts-no-posts">
                <span class="ts-loader"></span>
            </div>
        </div>
        <div class="ts-product-main">

            <template v-for="field in config.props.fields">
                <component
                    :is="field.component_key"
                    :field="field"
                    :ref="'field:'+field.key"
                ></component>
            </template>

            <div v-if="config.props.cart.enabled" class="ts-form-group product-actions">
                <a href="#" class="ts-btn form-btn ts-btn-2" @click.prevent="!processing ? ( $event.shiftKey ? directCart() : addToCart() ) : null" :class="{'ts-loading-btn': processing}">
                    <div v-if="processing" class="ts-loader-wrapper">
                        <span class="ts-loader"></span>
                    </div>
                    <?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_add_cart_icon') ) ?: \Voxel\svg( 'plus.svg' ) ?>
                    <?= _x( 'Add to cart', 'product form', 'voxel' ) ?>
                </a>

            </div>
            <div v-else class="ts-form-group product-actions">
                <a href="#" class="ts-btn form-btn ts-btn-2" @click.prevent="!processing ? directCart() : null" :class="{'ts-loading-btn': processing}">
                    <div v-if="processing" class="ts-loader-wrapper">
                        <span class="ts-loader"></span>
                    </div>
                    <?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_checkout_icon') ) ?: \Voxel\svg( 'bag-2.svg' ) ?>
                    <?= _x( 'Continue', 'product form', 'voxel' ) ?>
                </a>
            </div>

            <div v-if="pricing_summary.visible_items.length" class="ts-form-group tcc-container">
                <ul class="ts-cost-calculator simplify-ul flexify">
                    <template v-for="item in pricing_summary.visible_items">
                        <li v-if="!item.hidden">
                            <div class="ts-item-name">
                                <p>
                                    {{ item.label }}
                                </p>
                            </div>
                            <div class="ts-item-price vaibhav">
                                <p>{{ item.value ? item.value : currencyFormat( item.amount ) }}</p>
                            </div>
                        </li>
                    </template>
                    
                    <?php
                        if(is_user_logged_in()){
                            $user = \Voxel\current_user();
                            $membership = $user->get_membership();
                            $member = get_user_plan();
                            $user_id = get_current_user_id();
                            $traveller_num = traveller_num(); 
                        }
                    ?>
                    <?php if(is_user_logged_in()): ?>
                        <li class="ts-total" style="margin: 5px 0px;padding-top: 10px;">
                            <div class="ts-item-name">
                                <p><?= _x( 'Subtotal', 'product form', 'voxel' ) ?></p>
                            </div>
                            <div class="ts-item-price put-value">
                                <p>{{ currencyFormat( 0 ) }}</p>
                            </div>
                        </li>
                        <li class="ts-total" style="margin: 5px 0px;padding-top: 10px;">
                            <div class="ts-item-name">
                                <p><?= _x( 'Deposit amount', 'product form', 'voxel' ) ?></p>
                            </div>
                            <div class="ts-item-price deposit-amount">
                                <p>{{ currencyFormat( 0 ) }}</p>
                            </div>
                        </li>
                        <li class="sin-discount">
                            <div class="item-name">
                                <div style="display: flex;align-items: center;">
                                    <!-- <div class="bui-group__item" style="margin-right: 5px;">
                                        <svg class="bk-icon -streamline-accounting_bills" fill="#008009" height="20" width="20" viewBox="0 0 24 24" role="presentation" aria-hidden="true" focusable="false"><path d="M4.125 8.25a.375.375 0 1 1 0-.75.375.375 0 0 1 0 .75.75.75 0 0 0 0-1.5 1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25.75.75 0 0 0 0 1.5zm12.75 5.25a.375.375 0 1 1 0-.75.375.375 0 0 1 0 .75.75.75 0 0 0 0-1.5 1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25.75.75 0 0 0 0 1.5zm2.625-3V15a.75.75 0 0 1-.75.75H2.25A.75.75 0 0 1 1.5 15V6a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 .75.75v4.5zm1.5 0V6a2.25 2.25 0 0 0-2.25-2.25H2.25A2.25 2.25 0 0 0 0 6v9a2.25 2.25 0 0 0 2.25 2.25h16.5A2.25 2.25 0 0 0 21 15v-4.5zm-8.25 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0zm1.5 0a3.75 3.75 0 1 0-7.5 0 3.75 3.75 0 0 0 7.5 0zM22.5 9v9a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 0 0 1.5h16.5A2.25 2.25 0 0 0 24 18V9a.75.75 0 0 0-1.5 0z"></path></svg>
                                    </div> -->
                                    <?php if ($member !='default' && $membership): ?>
                                        <p style="color: #00826F;"><strong><?= _x(traveller_num() . ' Traveller Membership Rate', 'product form', 'voxel') ?></strong></p>
                                    <?php elseif ($member =='default' && $membership): ?>
                                        <p style="color: #00826F;"><strong><?= _x(' Non Membership Rate', 'product form', 'voxel') ?></strong></p>
                                    <?php endif; ?>

                                </div>
                            </div>
                            <div class="ts-item-price sin-discount-no" style="display: flex;align-items: center; flex-direction: row-reverse;">
                                <p style="color: #008009;"></p>
                            </div>
                        </li>
                        <li class="ts-total">
                            <div class="ts-item-name">
                                <p style="font-weight:600"><?= _x( 'Due Today', 'product form', 'voxel' ) ?></p>
                            </div>
                            <div class="ts-item-price due-today">
                                <p style="font-weight:600">{{ currencyFormat( 0 ) }}</p>
                            </div>
                        </li>  
                    <?php else: ?>
                        <li class="ts-total">
                            <div class="ts-item-name">
                                <p><?= _x( 'Subtotal', 'product form', 'voxel' ) ?></p>
                            </div>
                            <div class="ts-item-price">
                                <p>{{ currencyFormat( pricing_summary.total_amount ) }}</p>
                            </div>
                        </li>
                    <?php endif; ?>                 
                </ul>
            </div>
            <div class="membership-banner" style="display:none;margin-top: 20px;">
                <a href="https://everythingcostarica.com/current-plan/">
                    <div class ="membership-msg" style="padding: 13px;color: white;text-align: center;border-radius: 6px"></div>
                </a>
            </div>

            <!-- <teleport to="#pf-dbg">
                <pre debug>{{ config }}</pre>
            </teleport> -->
        </div>
    </div>
    <!-- <div id="pf-dbg" style="margin-top: 50px;"></div> -->
<?php endif ?>
