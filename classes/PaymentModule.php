<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
use PrestaShop\PrestaShop\Adapter\MailTemplate\MailPartialTemplateRenderer;
use PrestaShop\PrestaShop\Adapter\StockManager;

abstract class PaymentModuleCore extends Module
{
    /** @var int Current order's id */
    public $currentOrder;
    public $currentOrderReference;
    public $currencies = true;
    public $currencies_mode = 'checkbox';

    public const DEBUG_MODE = false;

    /** @var MailPartialTemplateRenderer|null */
    protected $partialRenderer;

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Insert currencies availability
        if ($this->currencies_mode == 'checkbox') {
            if (!$this->addCheckboxCurrencyRestrictionsForModule()) {
                return false;
            }
        } elseif ($this->currencies_mode == 'radio') {
            if (!$this->addRadioCurrencyRestrictionsForModule()) {
                return false;
            }
        } else {
            return $this->trans('No currency mode for payment module', [], 'Admin.Payment.Notification');
        }

        // Insert countries availability
        $return = $this->addCheckboxCountryRestrictionsForModule();

        // Insert carrier availability
        $return &= $this->addCheckboxCarrierRestrictionsForModule();

        if (!Configuration::get('CONF_' . strtoupper($this->name) . '_FIXED')) {
            Configuration::updateValue('CONF_' . strtoupper($this->name) . '_FIXED', '0.2');
        }
        if (!Configuration::get('CONF_' . strtoupper($this->name) . '_VAR')) {
            Configuration::updateValue('CONF_' . strtoupper($this->name) . '_VAR', '2');
        }
        if (!Configuration::get('CONF_' . strtoupper($this->name) . '_FIXED_FOREIGN')) {
            Configuration::updateValue('CONF_' . strtoupper($this->name) . '_FIXED_FOREIGN', '0.2');
        }
        if (!Configuration::get('CONF_' . strtoupper($this->name) . '_VAR_FOREIGN')) {
            Configuration::updateValue('CONF_' . strtoupper($this->name) . '_VAR_FOREIGN', '2');
        }

        return $return;
    }

    public function uninstall()
    {
        if (!Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_country` WHERE id_module = ' . (int) $this->id)
            || !Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_currency` WHERE id_module = ' . (int) $this->id)
            || !Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_group` WHERE id_module = ' . (int) $this->id)
            || !Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_carrier` WHERE id_module = ' . (int) $this->id)) {
            return false;
        }

        return parent::uninstall();
    }

    /**
     * Add checkbox currency restrictions for a new module.
     *
     * @param array $shops
     *
     * @return bool
     */
    public function addCheckboxCurrencyRestrictionsForModule(array $shops = [])
    {
        if (!$shops) {
            $shops = Shop::getShops(true, null, true);
        }

        foreach ($shops as $s) {
            if (!Db::getInstance()->execute('
                    INSERT INTO `' . _DB_PREFIX_ . 'module_currency` (`id_module`, `id_shop`, `id_currency`)
                    SELECT ' . (int) $this->id . ', "' . (int) $s . '", `id_currency` FROM `' . _DB_PREFIX_ . 'currency` WHERE deleted = 0')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add radio currency restrictions for a new module.
     *
     * @param array $shops
     *
     * @return bool
     */
    public function addRadioCurrencyRestrictionsForModule(array $shops = [])
    {
        if (!$shops) {
            $shops = Shop::getShops(true, null, true);
        }

        foreach ($shops as $s) {
            if (!Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'module_currency` (`id_module`, `id_shop`, `id_currency`)
                VALUES (' . (int) $this->id . ', "' . (int) $s . '", -2)')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add checkbox country restrictions for a new module.
     *
     * @param array $shops
     *
     * @return bool
     */
    public function addCheckboxCountryRestrictionsForModule(array $shops = [])
    {
        $countries = Country::getCountries((int) Context::getContext()->language->id, true); // get only active country

        return Country::addModuleRestrictions($shops, $countries, [['id_module' => (int) $this->id]]);
    }

    /**
     * Add checkbox carrier restrictions for a new module.
     *
     * @param array $shops
     *
     * @return bool
     */
    public function addCheckboxCarrierRestrictionsForModule(array $shops = [])
    {
        if (!$shops) {
            $shops = Shop::getShops(true, null, true);
        }

        $carriers = Carrier::getCarriers((int) Context::getContext()->language->id, false, false, false, null, Carrier::ALL_CARRIERS);
        $carrier_ids = [];
        foreach ($carriers as $carrier) {
            $carrier_ids[] = $carrier['id_reference'];
        }

        foreach ($shops as $s) {
            foreach ($carrier_ids as $id_carrier) {
                if (!Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'module_carrier` (`id_module`, `id_shop`, `id_reference`)
				VALUES (' . (int) $this->id . ', "' . (int) $s . '", ' . (int) $id_carrier . ')')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate an order in database
     * Function called from a payment module.
     *
     * @param int $id_cart
     * @param int $id_order_state
     * @param float $amount_paid Amount really paid by customer (in the default currency)
     * @param string $payment_method Payment method (eg. 'Credit card')
     * @param string|null $message Message to attach to order
     * @param array $extra_vars
     * @param int|null $currency_special
     * @param bool $dont_touch_amount
     * @param string|bool $secure_key
     * @param Shop $shop
     * @param string|null $order_reference if this parameter is not provided, a random order reference will be generated
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = [],
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        ?Shop $shop = null,
        ?string $order_reference = null
    ) {
        if (self::DEBUG_MODE) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Function called', 1, null, 'Cart', (int) $id_cart, true);
        }

        Hook::exec('actionValidateOrderBefore', [
            'cart' => $this->context->cart,
            'customer' => $this->context->customer,
            'currency' => $this->context->currency,
            'id_order_state' => &$id_order_state,
            'payment_method' => $payment_method,
        ]);

        $this->context->cart = new Cart((int) $id_cart);
        $this->context->customer = new Customer((int) $this->context->cart->id_customer);
        // The tax cart is loaded before the customer so re-cache the tax calculation method
        $this->context->cart->setTaxCalculationMethod();

        $this->context->language = $this->context->cart->getAssociatedLanguage();
        $this->context->shop = ($shop ? $shop : new Shop((int) $this->context->cart->id_shop));
        ShopUrl::resetMainDomainCache();
        $id_currency = $currency_special ? (int) $currency_special : (int) $this->context->cart->id_currency;
        $this->context->currency = new Currency((int) $id_currency, null, (int) $this->context->shop->id);
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
            $context_country = $this->context->country;
        }

        $order_status = new OrderState((int) $id_order_state, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($order_status)) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Order Status cannot be loaded', 3, null, 'Cart', (int) $id_cart, true);

            throw new PrestaShopException('Error processing order. Can\'t load Order status.');
        }

        if (!$this->active) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Module is not active', 3, null, 'Cart', (int) $id_cart, true);
            die(Tools::displayError('Error processing order. Payment module is not active.'));
        }

        // Make sure cart is loaded and not related to an existing order
        $cart_is_loaded = Validate::isLoadedObject($this->context->cart);
        if (!$cart_is_loaded || $this->context->cart->OrderExists()) {
            $error = $this->trans('Cart cannot be loaded or an order has already been placed using this cart', [], 'Admin.Payment.Notification');
            PrestaShopLogger::addLog($error, 4, 1, 'Cart', (int) $this->context->cart->id);
            die(Tools::displayError($error));
        }

        if ($secure_key !== false && $secure_key != $this->context->cart->secure_key) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Secure key does not match', 3, null, 'Cart', (int) $id_cart, true);
            die(Tools::displayError('Error processing order. Secure key does not match.'));
        }

        // For each package, generate an order
        $delivery_option_list = $this->context->cart->getDeliveryOptionList();
        $package_list = $this->context->cart->getPackageList();
        $cart_delivery_option = $this->context->cart->getDeliveryOption();

        // If some delivery options are not defined, or not valid, use the first valid option
        foreach ($delivery_option_list as $id_address => $package) {
            if (!isset($cart_delivery_option[$id_address]) || !array_key_exists($cart_delivery_option[$id_address], $package)) {
                foreach ($package as $key => $val) {
                    $cart_delivery_option[$id_address] = $key;

                    break;
                }
            }
        }

        $order_list = [];
        $order_detail_list = [];

        if ($order_reference === null) {
            do {
                $reference = Order::generateReference();
            } while (Order::getByReference($reference)->count());
        } else {
            $reference = $order_reference;
        }

        $this->currentOrderReference = $reference;

        $cart_total_paid = (float) Tools::ps_round(
            (float) $this->context->cart->getOrderTotal(true, Cart::BOTH),
            Context::getContext()->getComputingPrecision()
        );

        foreach ($cart_delivery_option as $id_address => $key_carriers) {
            foreach ($delivery_option_list[$id_address][$key_carriers]['carrier_list'] as $id_carrier => $data) {
                foreach ($data['package_list'] as $id_package) {
                    $package_list[$id_address][$id_package]['id_carrier'] = $id_carrier;
                }
            }
        }
        // Make sure CartRule caches are empty
        CartRule::cleanCache();
        $cart_rules = $this->context->cart->getCartRules();
        foreach ($cart_rules as $cart_rule) {
            $rule = new CartRule((int) $cart_rule['obj']->id);
            if (Validate::isLoadedObject($rule)) {
                if ($error = $rule->checkValidity($this->context, true, true)) {
                    $this->context->cart->removeCartRule((int) $rule->id);
                    if (isset($this->context->cookie, $this->context->cookie->id_customer) && $this->context->cookie->id_customer && !empty($rule->code)) {
                        Tools::redirect($this->context->link->getPageLink(
                            'order',
                            null,
                            null,
                            [
                                'submitAddDiscount' => 1,
                                'discount_name' => $rule->code,
                            ]
                        ));
                    } else {
                        $rule_name = isset($rule->name[(int) $this->context->cart->id_lang]) ? $rule->name[(int) $this->context->cart->id_lang] : $rule->code;
                        $error = $this->trans('The cart rule named "%1s" (ID %2s) used in this cart is not valid and has been withdrawn from cart', [htmlspecialchars($rule_name), (int) $rule->id], 'Admin.Payment.Notification');
                        PrestaShopLogger::addLog($error, 3, 2, 'Cart', (int) $this->context->cart->id);
                    }
                }
            }
        }

        // Amount paid by customer is not the right one -> Status = payment error
        // We don't use the following condition to avoid the float precision issues : http://www.php.net/manual/en/language.types.float.php
        // if ($order->total_paid != $order->total_paid_real)
        // We use number_format to convert the numbers to strings and strict inequality to compare them to avoid auto reconversions to numbers in PHP < 8.0
        $comp_precision = Context::getContext()->getComputingPrecision();
        if ($order_status->logable && (number_format($cart_total_paid, $comp_precision) !== number_format($amount_paid, $comp_precision))) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Total paid amount does not match cart total', 3, null, 'Cart', (int) $id_cart, true);
            $id_order_state = Configuration::get('PS_OS_ERROR');
        }

        foreach ($package_list as $id_address => $packageByAddress) {
            foreach ($packageByAddress as $id_package => $package) {
                $orderData = $this->createOrderFromCart(
                    $this->context->cart,
                    $this->context->currency,
                    $package['product_list'],
                    $id_address,
                    $this->context,
                    $reference,
                    $secure_key,
                    $payment_method,
                    $this->name,
                    $dont_touch_amount,
                    $amount_paid,
                    0,
                    $cart_total_paid,
                    self::DEBUG_MODE,
                    $order_status,
                    $id_order_state,
                    isset($package['id_carrier']) ? $package['id_carrier'] : null
                );
                $order = $orderData['order'];
                $order_list[] = $order;
                $order_detail_list[] = $orderData['orderDetail'];
            }
        }

        // The country can only change if the address used for the calculation is the delivery address, and if multi-shipping is activated
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery' && isset($context_country)) {
            $this->context->country = $context_country;
        }

        if (!$this->context->country->active) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Country is not active', 3, null, 'Cart', (int) $id_cart, true);

            throw new PrestaShopException('The order address country is not active.');
        }

        if (self::DEBUG_MODE) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Payment is about to be added', 1, null, 'Cart', (int) $id_cart, true);
        }

        // Register Payment only if the order status validate the order
        if ($order_status->logable) {
            // $order is the last order loop in the foreach
            // The method addOrderPayment of the class Order make a create a paymentOrder
            // linked to the order reference and not to the order id
            if (isset($extra_vars['transaction_id'])) {
                $transaction_id = $extra_vars['transaction_id'];
            } else {
                $transaction_id = null;
            }

            if (!isset($order) || !$order->addOrderPayment($amount_paid, null, $transaction_id)) {
                PrestaShopLogger::addLog('PaymentModule::validateOrder - Cannot save Order Payment', 3, null, 'Cart', (int) $id_cart, true);

                throw new PrestaShopException('Can\'t save Order Payment');
            }
        }

        // Next !
        $products = $this->context->cart->getProducts();

        // Make sure CartRule caches are empty
        CartRule::cleanCache();
        foreach ($order_detail_list as $key => $order_detail) {
            /** @var Order $order */
            $order = $order_list[$key];
            if (!isset($order->id)) {
                $error = $this->trans('Order creation failed', [], 'Admin.Payment.Notification');
                PrestaShopLogger::addLog($error, 4, 2, 'Cart', (int) $order->id_cart);
                die(Tools::displayError($error));
            }
            if (!$secure_key) {
                $message .= '<br />' . $this->trans('Warning: the secure key is empty, check your payment account before validation', [], 'Admin.Payment.Notification');
            }
            // Optional message to attach to this order
            if (!empty($message)) {
                $message = strip_tags($message, '<br>');
                if (Validate::isCleanHtml($message)) {
                    if (self::DEBUG_MODE) {
                        PrestaShopLogger::addLog('PaymentModule::validateOrder - Message is about to be added', 1, null, 'Cart', (int) $id_cart, true);
                    }
                    $msg = new Message();
                    $msg->message = $message;
                    $msg->id_cart = (int) $id_cart;
                    $msg->id_customer = (int) $order->id_customer;
                    $msg->id_order = (int) $order->id;
                    $msg->private = true;
                    $msg->add();
                }
            }

            // Insert new Order detail list using cart for the current order
            // $orderDetail = new OrderDetail(null, null, $this->context);
            // $orderDetail->createList($order, $this->context->cart, $id_order_state);

            // Construct order detail table for the email
            $virtual_product = true;

            $product_var_tpl_list = [];
            foreach ($order->product_list as $product) {
                $price = Product::getPriceStatic((int) $product['id_product'], false, $product['id_product_attribute'] ? (int) $product['id_product_attribute'] : null, 6, null, false, true, $product['cart_quantity'], false, (int) $order->id_customer, (int) $order->id_cart, (int) $order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}, $specific_price, true, true, null, true, $product['id_customization']);
                $price_wt = Product::getPriceStatic((int) $product['id_product'], true, $product['id_product_attribute'] ? (int) $product['id_product_attribute'] : null, 2, null, false, true, $product['cart_quantity'], false, (int) $order->id_customer, (int) $order->id_cart, (int) $order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}, $specific_price, true, true, null, true, $product['id_customization']);

                $product_price = Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, Context::getContext()->getComputingPrecision()) : $price_wt;

                $product_var_tpl = [
                    'id_product' => $product['id_product'],
                    'id_product_attribute' => $product['id_product_attribute'],
                    'reference' => $product['reference'],
                    'name' => $product['name'] . (!empty($product['attributes']) ? ' - ' . $product['attributes'] : ''),
                    'price' => Tools::getContextLocale($this->context)->formatPrice($product_price * $product['quantity'], $this->context->currency->iso_code),
                    'quantity' => $product['quantity'],
                    'customization' => [],
                ];

                if (isset($product['price']) && $product['price']) {
                    $product_var_tpl['unit_price'] = Tools::getContextLocale($this->context)->formatPrice($product_price, $this->context->currency->iso_code);
                    $product_var_tpl['unit_price_full'] = Tools::getContextLocale($this->context)->formatPrice($product_price, $this->context->currency->iso_code)
                        . ' ' . $product['unity'];
                } else {
                    $product_var_tpl['unit_price'] = $product_var_tpl['unit_price_full'] = '';
                }

                $customized_datas = Product::getAllCustomizedDatas((int) $order->id_cart, null, true, null, (int) $product['id_customization']);
                if (isset($customized_datas[$product['id_product']][$product['id_product_attribute']])) {
                    $product_var_tpl['customization'] = [];
                    foreach ($customized_datas[$product['id_product']][$product['id_product_attribute']][$order->id_address_delivery] as $customization) {
                        $customization_text = '';
                        if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD])) {
                            foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text) {
                                $customization_text .= '<strong>' . $text['name'] . '</strong>: ' . $text['value'] . '<br />';
                            }
                        }

                        if (isset($customization['datas'][Product::CUSTOMIZE_FILE])) {
                            $customization_text .= $this->trans('%d image(s)', [count($customization['datas'][Product::CUSTOMIZE_FILE])], 'Admin.Payment.Notification') . '<br />';
                        }

                        $customization_quantity = (int) $customization['quantity'];

                        $product_var_tpl['customization'][] = [
                            'customization_text' => $customization_text,
                            'customization_quantity' => $customization_quantity,
                            'quantity' => Tools::getContextLocale($this->context)->formatPrice($customization_quantity * $product_price, $this->context->currency->iso_code),
                        ];
                    }
                }

                $product_var_tpl_list[] = $product_var_tpl;
                // Check if is not a virtual product for the displaying of shipping
                if (!$product['is_virtual']) {
                    $virtual_product &= false;
                }
            }

            $product_list_txt = '';
            $product_list_html = '';
            if (count($product_var_tpl_list) > 0) {
                $product_list_txt = $this->getEmailTemplateContent('order_conf_product_list.txt', Mail::TYPE_TEXT, $product_var_tpl_list);
                $product_list_html = $this->getEmailTemplateContent('order_conf_product_list.tpl', Mail::TYPE_HTML, $product_var_tpl_list);
            }

            $total_reduction_value_ti = 0;
            $total_reduction_value_tex = 0;

            $cart_rules_list = $this->createOrderCartRules(
                $order,
                $this->context->cart,
                $order_list,
                $total_reduction_value_ti,
                $total_reduction_value_tex,
                $id_order_state
            );

            $cart_rules_list_txt = '';
            $cart_rules_list_html = '';
            if (count($cart_rules_list) > 0) {
                $cart_rules_list_txt = $this->getEmailTemplateContent('order_conf_cart_rules.txt', Mail::TYPE_TEXT, $cart_rules_list);
                $cart_rules_list_html = $this->getEmailTemplateContent('order_conf_cart_rules.tpl', Mail::TYPE_HTML, $cart_rules_list);
            }

            // Specify order id for message
            $old_message = Message::getMessageByCartId((int) $this->context->cart->id);
            if ($old_message && !$old_message['private']) {
                $update_message = new Message((int) $old_message['id_message']);
                $update_message->id_order = (int) $order->id;
                $update_message->update();

                // Add this message in the customer thread
                $customer_thread = new CustomerThread();
                $customer_thread->id_contact = 0;
                $customer_thread->id_customer = (int) $order->id_customer;
                $customer_thread->id_shop = (int) $this->context->shop->id;
                $customer_thread->id_order = (int) $order->id;
                $customer_thread->id_lang = (int) $this->context->language->id;
                $customer_thread->email = $this->context->customer->email;
                $customer_thread->status = 'open';
                $customer_thread->token = Tools::passwdGen(12);
                $customer_thread->add();

                $customer_message = new CustomerMessage();
                $customer_message->id_customer_thread = $customer_thread->id;
                $customer_message->id_employee = 0;
                $customer_message->message = $update_message->message;
                $customer_message->private = false;

                if (!$customer_message->add()) {
                    $this->_errors[] = $this->trans('An error occurred while saving message', [], 'Admin.Payment.Notification');
                }
            }

            if (self::DEBUG_MODE) {
                PrestaShopLogger::addLog('PaymentModule::validateOrder - Hook validateOrder is about to be called', 1, null, 'Cart', (int) $id_cart, true);
            }

            // Hook validate order
            Hook::exec('actionValidateOrder', [
                'cart' => $this->context->cart,
                'order' => $order,
                'customer' => $this->context->customer,
                'currency' => $this->context->currency,
                'orderStatus' => $order_status,
            ]);

            if ($order_status->logable) {
                foreach ($this->context->cart->getProducts() as $product) {
                    ProductSale::addProductSale((int) $product['id_product'], (int) $product['cart_quantity']);
                }
            }

            if (self::DEBUG_MODE) {
                PrestaShopLogger::addLog('PaymentModule::validateOrder - Order Status is about to be added', 1, null, 'Cart', (int) $id_cart, true);
            }

            // Set the order status
            $new_history = new OrderHistory();
            $new_history->id_order = (int) $order->id;
            $new_history->changeIdOrderState((int) $id_order_state, $order, true);
            $new_history->addWithemail(true, $extra_vars);

            // Switch to back order if needed
            if (Configuration::get('PS_STOCK_MANAGEMENT')
                    && Configuration::get('PS_ENABLE_BACKORDER_STATUS')
                    && ($order_detail->getStockState()
                    || $order_detail->product_quantity_in_stock < 0)) {
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                $history->changeIdOrderState(
                    (int) Configuration::get($order->hasBeenPaid() ? 'PS_OS_OUTOFSTOCK_PAID' : 'PS_OS_OUTOFSTOCK_UNPAID'),
                    $order,
                    true
                );
                $history->addWithemail();
            }

            unset($order_detail);

            // Order is reloaded because the status just changed
            $order = new Order((int) $order->id);

            // Send an e-mail to customer (one order = one email)
            if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && $this->context->customer->id) {
                $invoice = new Address((int) $order->id_address_invoice);
                $delivery = new Address((int) $order->id_address_delivery);
                $delivery_state = $delivery->id_state ? new State((int) $delivery->id_state) : false;
                $invoice_state = $invoice->id_state ? new State((int) $invoice->id_state) : false;
                $carrier = $order->id_carrier ? new Carrier($order->id_carrier) : false;
                $orderLanguage = new Language((int) $order->id_lang);

                // Join PDF invoice
                if ((int) Configuration::get('PS_INVOICE') && $order_status->invoice && $order->invoice_number) {
                    $currentLanguage = $this->context->language;
                    $this->context->language = $orderLanguage;
                    $this->context->getTranslator()->setLocale($orderLanguage->locale);
                    $order_invoice_list = $order->getInvoicesCollection();
                    Hook::exec('actionPDFInvoiceRender', ['order_invoice_list' => $order_invoice_list]);
                    $pdf = new PDF($order_invoice_list, PDF::TEMPLATE_INVOICE, $this->context->smarty);
                    $file_attachement['content'] = $pdf->render(false);
                    $file_attachement['name'] = $pdf->getFilename();
                    $file_attachement['mime'] = 'application/pdf';
                    $this->context->language = $currentLanguage;
                    $this->context->getTranslator()->setLocale($currentLanguage->locale);
                } else {
                    $file_attachement = null;
                }

                if (self::DEBUG_MODE) {
                    PrestaShopLogger::addLog('PaymentModule::validateOrder - Mail is about to be sent', 1, null, 'Cart', (int) $id_cart, true);
                }

                if (Validate::isEmail($this->context->customer->email)) {
                    $data = [
                        '{firstname}' => $this->context->customer->firstname,
                        '{lastname}' => $this->context->customer->lastname,
                        '{email}' => $this->context->customer->email,
                        '{delivery_block_txt}' => $this->_getFormatedAddress($delivery, AddressFormat::FORMAT_NEW_LINE),
                        '{invoice_block_txt}' => $this->_getFormatedAddress($invoice, AddressFormat::FORMAT_NEW_LINE),
                        '{delivery_block_html}' => $this->_getFormatedAddress($delivery, '<br />', [
                            'firstname' => '<span style="font-weight:bold;">%s</span>',
                            'lastname' => '<span style="font-weight:bold;">%s</span>',
                        ]),
                        '{invoice_block_html}' => $this->_getFormatedAddress($invoice, '<br />', [
                            'firstname' => '<span style="font-weight:bold;">%s</span>',
                            'lastname' => '<span style="font-weight:bold;">%s</span>',
                        ]),
                        '{delivery_company}' => $delivery->company,
                        '{delivery_firstname}' => $delivery->firstname,
                        '{delivery_lastname}' => $delivery->lastname,
                        '{delivery_address1}' => $delivery->address1,
                        '{delivery_address2}' => $delivery->address2,
                        '{delivery_city}' => $delivery->city,
                        '{delivery_postal_code}' => $delivery->postcode,
                        '{delivery_country}' => $delivery->country,
                        '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
                        '{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
                        '{delivery_other}' => $delivery->other,
                        '{invoice_company}' => $invoice->company,
                        '{invoice_vat_number}' => $invoice->vat_number,
                        '{invoice_firstname}' => $invoice->firstname,
                        '{invoice_lastname}' => $invoice->lastname,
                        '{invoice_address2}' => $invoice->address2,
                        '{invoice_address1}' => $invoice->address1,
                        '{invoice_city}' => $invoice->city,
                        '{invoice_postal_code}' => $invoice->postcode,
                        '{invoice_country}' => $invoice->country,
                        '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
                        '{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
                        '{invoice_other}' => $invoice->other,
                        '{order_name}' => $order->getUniqReference(),
                        '{id_order}' => $order->id,
                        '{date}' => Tools::displayDate(date('Y-m-d H:i:s'), true),
                        '{carrier}' => ($virtual_product || !isset($carrier->name)) ? $this->trans('No carrier', [], 'Admin.Payment.Notification') : $carrier->name,
                        '{payment}' => Tools::substr($order->payment, 0, 255) . ($order->hasBeenPaid() ? '' : '&nbsp;' . $this->trans('(waiting for validation)', [], 'Emails.Body')),
                        '{products}' => $product_list_html,
                        '{products_txt}' => $product_list_txt,
                        '{discounts}' => $cart_rules_list_html,
                        '{discounts_txt}' => $cart_rules_list_txt,
                        '{total_paid}' => Tools::getContextLocale($this->context)->formatPrice($order->total_paid, $this->context->currency->iso_code),
                        '{total_paid_tax_excl}' => Tools::getContextLocale($this->context)->formatPrice($order->total_paid_tax_excl, $this->context->currency->iso_code),
                        '{total_shipping_tax_excl}' => Tools::getContextLocale($this->context)->formatPrice($order->total_shipping_tax_excl, $this->context->currency->iso_code),
                        '{total_shipping_tax_incl}' => Tools::getContextLocale($this->context)->formatPrice($order->total_shipping_tax_incl, $this->context->currency->iso_code),
                        '{total_tax_paid}' => Tools::getContextLocale($this->context)->formatPrice($order->total_paid_tax_incl - $order->total_paid_tax_excl, $this->context->currency->iso_code),
                        '{recycled_packaging_label}' => $order->recyclable ? $this->trans('Yes', [], 'Shop.Theme.Global') : $this->trans('No', [], 'Shop.Theme.Global'),
                        '{message}' => $order->getFirstMessage(),
                    ];

                    if (Product::getTaxCalculationMethod() == PS_TAX_EXC) {
                        $data = array_merge($data, [
                            '{total_products}' => Tools::getContextLocale($this->context)->formatPrice($order->total_products, $this->context->currency->iso_code),
                            '{total_discounts}' => Tools::getContextLocale($this->context)->formatPrice($order->total_discounts_tax_excl, $this->context->currency->iso_code),
                            '{total_shipping}' => Tools::getContextLocale($this->context)->formatPrice($order->total_shipping_tax_excl, $this->context->currency->iso_code),
                            '{total_wrapping}' => Tools::getContextLocale($this->context)->formatPrice($order->total_wrapping_tax_excl, $this->context->currency->iso_code),
                        ]);
                    } else {
                        $data = array_merge($data, [
                            '{total_products}' => Tools::getContextLocale($this->context)->formatPrice($order->total_products_wt, $this->context->currency->iso_code),
                            '{total_discounts}' => Tools::getContextLocale($this->context)->formatPrice($order->total_discounts, $this->context->currency->iso_code),
                            '{total_shipping}' => Tools::getContextLocale($this->context)->formatPrice($order->total_shipping, $this->context->currency->iso_code),
                            '{total_wrapping}' => Tools::getContextLocale($this->context)->formatPrice($order->total_wrapping, $this->context->currency->iso_code),
                        ]);
                    }

                    if (is_array($extra_vars)) {
                        $data = array_merge($data, $extra_vars);
                    }

                    Mail::Send(
                        (int) $order->id_lang,
                        'order_conf',
                        $this->context->getTranslator()->trans(
                            'Order confirmation',
                            [],
                            'Emails.Subject',
                            $orderLanguage->locale
                        ),
                        $data,
                        $this->context->customer->email,
                        $this->context->customer->firstname . ' ' . $this->context->customer->lastname,
                        null,
                        null,
                        $file_attachement,
                        null,
                        _PS_MAIL_DIR_,
                        false,
                        (int) $order->id_shop
                    );
                }
            }

            $order->updateOrderDetailTax();

            // sync all stock
            (new StockManager())->updatePhysicalProductQuantity(
                (int) $order->id_shop,
                (int) Configuration::get('PS_OS_ERROR'),
                (int) Configuration::get('PS_OS_CANCELED'),
                null,
                (int) $order->id
            );
        } // End foreach $order_detail_list

        // Use the last order as currentOrder
        if (isset($order) && $order->id) {
            $this->currentOrder = (int) $order->id;
        }

        if (self::DEBUG_MODE) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - End of validateOrder', 1, null, 'Cart', (int) $id_cart, true);
        }

        Hook::exec(
            'actionValidateOrderAfter',
            [
                'cart' => $this->context->cart,
                'order' => $order ?? null,
                'orders' => $order_list,
                'customer' => $this->context->customer,
                'currency' => $this->context->currency,
                'orderStatus' => new OrderState(isset($order) ? $order->current_state : null),
            ]
        );

        return true;
    }

    /**
     * @param Address $the_address that needs to be txt formatted
     *
     * @return string the txt formatted address block
     */
    protected function _getTxtFormatedAddress($the_address)
    {
        $adr_fields = AddressFormat::getOrderedAddressFields($the_address->id_country, false, true);
        $r_values = [];
        foreach ($adr_fields as $fields_line) {
            $tmp_values = [];
            foreach (explode(' ', $fields_line) as $field_item) {
                $field_item = trim($field_item);
                $tmp_values[] = $the_address->{$field_item};
            }
            $r_values[] = implode(' ', $tmp_values);
        }

        $out = implode(AddressFormat::FORMAT_NEW_LINE, $r_values);

        return $out;
    }

    /**
     * @param Address $the_address that needs to be txt formatted
     * @param string $line_sep
     * @param array $fields_style
     *
     * @return string the txt formated address block
     */
    protected function _getFormatedAddress(Address $the_address, $line_sep, $fields_style = [])
    {
        return AddressFormat::generateAddress($the_address, ['avoid' => []], $line_sep, ' ', $fields_style);
    }

    /**
     * @param int $current_id_currency
     *
     * @return Currency|array|false
     */
    public function getCurrency($current_id_currency = null)
    {
        if (!(int) $current_id_currency) {
            $current_id_currency = Context::getContext()->currency->id;
        }

        if (!$this->currencies) {
            return false;
        }
        if ($this->currencies_mode == 'checkbox') {
            return Currency::getPaymentCurrencies($this->id);
        }

        if ($this->currencies_mode == 'radio') {
            $currencies = Currency::getPaymentCurrenciesSpecial($this->id);
            $currency = $currencies['id_currency'];
            if ($currency == -1) {
                $id_currency = (int) $current_id_currency;
            } elseif ($currency == -2) {
                $id_currency = Currency::getDefaultCurrencyId();
            } else {
                $id_currency = $currency;
            }
        }
        if (!isset($id_currency) || empty($id_currency)) {
            return false;
        }

        return Currency::getCurrencyInstance((int) $id_currency);
    }

    /**
     * Allows specified payment modules to be used by a specific currency.
     *
     * @since 1.4.5
     *
     * @param int $id_currency
     * @param array $id_module_list
     *
     * @return bool
     */
    public static function addCurrencyPermissions($id_currency, array $id_module_list = [])
    {
        $values = '';
        if (count($id_module_list) == 0) {
            // fetch all installed module ids
            $modules = static::getInstalledPaymentModules();
            foreach ($modules as $module) {
                $id_module_list[] = $module['id_module'];
            }
        }

        foreach ($id_module_list as $id_module) {
            $values .= '(' . (int) $id_module . ',' . (int) $id_currency . '),';
        }

        if (!empty($values)) {
            return Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'module_currency` (`id_module`, `id_currency`)
            VALUES ' . rtrim($values, ','));
        }

        return true;
    }

    /**
     * List all installed and active payment modules.
     *
     * @see Module::getPaymentModules() if you need a list of module related to the user context
     * @since 1.4.5
     *
     * @return array module information
     */
    public static function getInstalledPaymentModules()
    {
        $hook_payment = 'Payment';
        if (Db::getInstance()->getValue('SELECT `id_hook` FROM `' . _DB_PREFIX_ . 'hook` WHERE `name` = \'paymentOptions\'')) {
            $hook_payment = 'paymentOptions';
        }

        return Db::getInstance()->executeS('
        SELECT DISTINCT m.`id_module`, h.`id_hook`, m.`name`, hm.`position`
        FROM `' . _DB_PREFIX_ . 'module` m
        LEFT JOIN `' . _DB_PREFIX_ . 'hook_module` hm ON hm.`id_module` = m.`id_module`'
        . Shop::addSqlRestriction(false, 'hm') . '
        LEFT JOIN `' . _DB_PREFIX_ . 'hook` h ON hm.`id_hook` = h.`id_hook`
        INNER JOIN `' . _DB_PREFIX_ . 'module_shop` ms ON (m.`id_module` = ms.`id_module` AND ms.id_shop=' . (int) Context::getContext()->shop->id . ')
        WHERE h.`name` = \'' . pSQL($hook_payment) . '\'');
    }

    public static function preCall($module_name)
    {
        if ($module_instance = Module::getInstanceByName($module_name)) {
            /** @var PaymentModule $module_instance */
            if (!$module_instance->currencies || count(Currency::checkPaymentCurrencies($module_instance->id))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return MailPartialTemplateRenderer
     */
    protected function getPartialRenderer()
    {
        if (!$this->partialRenderer) {
            $this->partialRenderer = new MailPartialTemplateRenderer($this->context->smarty);
        }

        return $this->partialRenderer;
    }

    /**
     * Fetch the content of $template_name inside the folder
     * current_theme/mails/current_iso_lang/ if found, otherwise in
     * mails/current_iso_lang.
     *
     * @param string $template_name template name with extension
     * @param int $mail_type Mail::TYPE_HTML or Mail::TYPE_TEXT
     * @param array $var sent to smarty as 'list'
     *
     * @return string
     */
    protected function getEmailTemplateContent($template_name, $mail_type, $var)
    {
        $email_configuration = Configuration::get('PS_MAIL_TYPE');
        if ($email_configuration != $mail_type && $email_configuration != Mail::TYPE_BOTH) {
            return '';
        }

        return $this->getPartialRenderer()->render($template_name, $this->context->language, $var);
    }

    protected function createOrderFromCart(
        Cart $cart,
        Currency $currency,
        $productList,
        $addressId,
        $context,
        $reference,
        $secure_key,
        $payment_method,
        $name,
        $dont_touch_amount,
        $amount_paid,
        $warehouseId,
        $cart_total_paid,
        $debug,
        $order_status,
        $id_order_state,
        $carrierId = null
    ) {
        $order = new Order();
        $order->product_list = $productList;

        $computingPrecision = Context::getContext()->getComputingPrecision();

        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
            $address = new Address((int) $addressId);
            $context->country = new Country((int) $address->id_country, (int) $cart->id_lang);
            if (!$context->country->active) {
                throw new PrestaShopException('The delivery address country is not active.');
            }
        }

        $carrier = null;
        if (!$cart->isVirtualCart() && isset($carrierId)) {
            $carrier = new Carrier((int) $carrierId, (int) $cart->id_lang);
            $order->id_carrier = (int) $carrier->id;
            $carrierId = (int) $carrier->id;
        } else {
            $order->id_carrier = 0;
            $carrierId = 0;
        }

        $order->id_customer = (int) $cart->id_customer;
        $order->id_address_invoice = (int) $cart->id_address_invoice;
        $order->id_address_delivery = (int) $addressId;
        $order->id_currency = $currency->id;
        $order->id_lang = (int) $cart->id_lang;
        $order->id_cart = (int) $cart->id;
        $order->reference = $reference;
        $order->id_shop = (int) $context->shop->id;
        $order->id_shop_group = (int) $context->shop->id_shop_group;

        $order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($context->customer->secure_key));
        $order->payment = $payment_method;
        if (isset($name)) {
            $order->module = $name;
        }
        $order->recyclable = $cart->recyclable;
        $order->gift = (bool) $cart->gift;
        $order->gift_message = $cart->gift_message;
        $order->conversion_rate = $currency->conversion_rate;
        $amount_paid = !$dont_touch_amount ? Tools::ps_round((float) $amount_paid, $computingPrecision) : $amount_paid;
        $order->total_paid_real = 0;

        $order->total_products = Tools::ps_round(
            (float) $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $order->product_list, $carrierId),
            $computingPrecision
        );
        $order->total_products_wt = Tools::ps_round(
            (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $order->product_list, $carrierId),
            $computingPrecision
        );
        $order->total_discounts_tax_excl = Tools::ps_round(
            (float) abs($cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $order->product_list, $carrierId)),
            $computingPrecision
        );
        $order->total_discounts_tax_incl = Tools::ps_round(
            (float) abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $order->product_list, $carrierId)),
            $computingPrecision
        );
        $order->total_discounts = $order->total_discounts_tax_incl;

        $order->total_shipping_tax_excl = Tools::ps_round(
            (float) $cart->getPackageShippingCost($carrierId, false, null, $order->product_list),
            $computingPrecision
        );
        $order->total_shipping_tax_incl = Tools::ps_round(
            (float) $cart->getPackageShippingCost($carrierId, true, null, $order->product_list),
            $computingPrecision
        );
        $order->total_shipping = $order->total_shipping_tax_incl;

        if (null !== $carrier && Validate::isLoadedObject($carrier)) {
            $order->carrier_tax_rate = $carrier->getTaxesRate(new Address((int) $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));
        }

        $order->total_wrapping_tax_excl = Tools::ps_round(
            (float) abs($cart->getOrderTotal(false, Cart::ONLY_WRAPPING, $order->product_list, $carrierId)),
            $computingPrecision
        );
        $order->total_wrapping_tax_incl = Tools::ps_round(
            (float) abs($cart->getOrderTotal(true, Cart::ONLY_WRAPPING, $order->product_list, $carrierId)),
            $computingPrecision
        );
        $order->total_wrapping = $order->total_wrapping_tax_incl;

        $order->total_paid_tax_excl = Tools::ps_round(
            (float) $cart->getOrderTotal(false, Cart::BOTH, $order->product_list, $carrierId),
            $computingPrecision
        );
        $order->total_paid_tax_incl = Tools::ps_round(
            (float) $cart->getOrderTotal(true, Cart::BOTH, $order->product_list, $carrierId),
            $computingPrecision
        );
        $order->total_paid = $order->total_paid_tax_incl;
        $order->round_mode = (int) Configuration::get('PS_PRICE_ROUND_MODE');
        $order->round_type = (int) Configuration::get('PS_ROUND_TYPE');

        $order->invoice_date = '0000-00-00 00:00:00';
        $order->delivery_date = '0000-00-00 00:00:00';

        if ($debug) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Order is about to be added', 1, null, 'Cart', (int) $cart->id, true);
        }

        // Creating order
        $result = $order->add();

        if (!$result) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - Order cannot be created', 3, null, 'Cart', (int) $cart->id, true);
            throw new PrestaShopException('Can\'t save Order');
        }

        // Amount paid by customer is not the right one -> Status = payment error
        // We don't use the following condition to avoid the float precision issues : https://www.php.net/manual/en/language.types.float.php
        // if ($order->total_paid != $order->total_paid_real)
        // We use number_format in order to compare two string
        if ($order_status->logable
            && number_format(
                $cart_total_paid,
                $computingPrecision
            ) != number_format(
                $amount_paid,
                $computingPrecision
            )
        ) {
            $id_order_state = Configuration::get('PS_OS_ERROR');
        }

        if ($debug) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - OrderDetail is about to be added', 1, null, 'Cart', (int) $cart->id, true);
        }

        // Insert new Order detail list using cart for the current order
        $order_detail = new OrderDetail(null, null, $context);
        $order_detail->createList($order, $cart, $id_order_state, $order->product_list, 0, true);

        if ($debug) {
            PrestaShopLogger::addLog('PaymentModule::validateOrder - OrderCarrier is about to be added', 1, null, 'Cart', (int) $cart->id, true);
        }

        // Adding an entry in order_carrier table
        if (null !== $carrier) {
            $order_carrier = new OrderCarrier();
            $order_carrier->id_order = (int) $order->id;
            $order_carrier->id_carrier = $carrierId;
            $order_carrier->weight = (float) $order->getTotalWeight();
            $order_carrier->shipping_cost_tax_excl = (float) $order->total_shipping_tax_excl;
            $order_carrier->shipping_cost_tax_incl = (float) $order->total_shipping_tax_incl;
            $order_carrier->add();
        }

        return ['order' => $order, 'orderDetail' => $order_detail];
    }

    protected function createOrderCartRules(
        Order $order,
        Cart $cart,
        $order_list,
        $total_reduction_value_ti,
        $total_reduction_value_tex,
        $id_order_state
    ) {
        $cart_rule_used = [];
        $computingPrecision = Context::getContext()->getComputingPrecision();

        // prepare cart calculator to correctly get the value of each cart rule
        $calculator = $cart->newCalculator($order->product_list, $cart->getCartRules(), $order->id_carrier, $computingPrecision);
        $calculator->processCalculation();
        $cartRulesData = $calculator->getCartRulesData();

        $cart_rules_list = [];
        foreach ($cartRulesData as $cartRuleData) {
            $cartRule = $cartRuleData->getCartRule();
            // Here we need to get actual values from cart calculator
            $values = [
                'tax_incl' => $cartRuleData->getDiscountApplied()->getTaxIncluded(),
                'tax_excl' => $cartRuleData->getDiscountApplied()->getTaxExcluded(),
            ];

            // If the reduction is not applicable to this order, then continue with the next one
            if (!$values['tax_excl'] && empty($cartRule->gift_product)) {
                continue;
            }

            // IF
            //  This is not multi-shipping
            //  The value of the voucher is greater than the total of the order
            //  Partial use is allowed
            //  This is an "amount" reduction, not a reduction in % or a gift
            // THEN
            //  The voucher is cloned with a new value corresponding to the remainder
            $cartRuleReductionAmountConverted = $cartRule->reduction_amount;
            if ((int) $cartRule->reduction_currency !== $cart->id_currency) {
                $cartRuleReductionAmountConverted = Tools::convertPriceFull(
                    $cartRule->reduction_amount,
                    new Currency((int) $cartRule->reduction_currency),
                    new Currency($cart->id_currency)
                );
            }
            $remainingValue = $cartRuleReductionAmountConverted - $values[$cartRule->reduction_tax ? 'tax_incl' : 'tax_excl'];
            $remainingValue = Tools::ps_round($remainingValue, Context::getContext()->getComputingPrecision());
            if (count($order_list) == 1 && $remainingValue > 0 && $cartRule->partial_use == 1 && $cartRuleReductionAmountConverted > 0) {
                // Create a new voucher from the original
                $voucher = new CartRule((int) $cartRule->id); // We need to instantiate the CartRule without lang parameter to allow saving it
                unset($voucher->id);

                // Set a new voucher code
                $voucher->code = empty($voucher->code) ? substr(md5($order->id . '-' . $order->id_customer . '-' . $cartRule->id), 0, 16) : $voucher->code . '-2';
                if (preg_match('/\-([0-9]{1,2})\-([0-9]{1,2})$/', $voucher->code, $matches) && $matches[1] == $matches[2]) {
                    $voucher->code = preg_replace('/' . $matches[0] . '$/', '-' . (intval($matches[1]) + 1), $voucher->code);
                }

                // Set the new voucher value
                $voucher->reduction_amount = $remainingValue;
                if ($voucher->reduction_tax) {
                    // Add total shipping amount only if reduction amount > total shipping
                    if ($voucher->free_shipping == 1 && $voucher->reduction_amount >= $order->total_shipping_tax_incl) {
                        $voucher->reduction_amount -= $order->total_shipping_tax_incl;
                    }
                } else {
                    // Add total shipping amount only if reduction amount > total shipping
                    if ($voucher->free_shipping == 1 && $voucher->reduction_amount >= $order->total_shipping_tax_excl) {
                        $voucher->reduction_amount -= $order->total_shipping_tax_excl;
                    }
                }
                if ($voucher->reduction_amount <= 0) {
                    continue;
                }

                if ($this->context->customer->isGuest()) {
                    $voucher->id_customer = 0;
                } else {
                    $voucher->id_customer = $order->id_customer;
                }

                $voucher->quantity = 1;
                $voucher->reduction_currency = $order->id_currency;
                $voucher->quantity_per_user = 1;
                if ($voucher->add()) {
                    // If the voucher has conditions, they are now copied to the new voucher
                    CartRule::copyConditions($cartRule->id, $voucher->id);
                    $orderLanguage = new Language((int) $order->id_lang);

                    $params = [
                        '{voucher_amount}' => Tools::getContextLocale($this->context)->formatPrice($voucher->reduction_amount, $this->context->currency->iso_code),
                        '{voucher_num}' => $voucher->code,
                        '{firstname}' => $this->context->customer->firstname,
                        '{lastname}' => $this->context->customer->lastname,
                        '{id_order}' => $order->id,
                        '{order_name}' => $order->getUniqReference(),
                    ];
                    Mail::Send(
                        (int) $order->id_lang,
                        'voucher',
                        Context::getContext()->getTranslator()->trans(
                            'New voucher for your order %s',
                            [$order->reference],
                            'Emails.Subject',
                            $orderLanguage->locale
                        ),
                        $params,
                        $this->context->customer->email,
                        $this->context->customer->firstname . ' ' . $this->context->customer->lastname,
                        null, null, null, null, _PS_MAIL_DIR_, false, (int) $order->id_shop
                    );
                }

                $values['tax_incl'] = $order->total_products_wt - $total_reduction_value_ti;
                $values['tax_excl'] = $order->total_products - $total_reduction_value_tex;
                if (1 == $voucher->free_shipping) {
                    $values['tax_incl'] += $order->total_shipping_tax_incl;
                    $values['tax_excl'] += $order->total_shipping_tax_excl;
                }
            }
            $total_reduction_value_ti += $values['tax_incl'];
            $total_reduction_value_tex += $values['tax_excl'];

            $order->addCartRule($cartRule->id, $cartRule->name, $values, 0, $cartRule->free_shipping);

            if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && !in_array($cartRule->id, $cart_rule_used)) {
                $cart_rule_used[] = $cartRule->id;

                // Create a new instance of Cart Rule without id_lang, in order to update its quantity
                $cart_rule_to_update = new CartRule((int) $cartRule->id);
                $cart_rule_to_update->quantity = max(0, $cart_rule_to_update->quantity - 1);
                $cart_rule_to_update->update();
            }

            $cart_rules_list[] = [
                'voucher_name' => $cartRule->name,
                'voucher_reduction' => ($values['tax_incl'] != 0.00 ? '-' : '') . (Product::getTaxCalculationMethod() == PS_TAX_EXC
                    ? Tools::getContextLocale($this->context)->formatPrice($values['tax_excl'], $this->context->currency->iso_code)
                    : Tools::getContextLocale($this->context)->formatPrice($values['tax_incl'], $this->context->currency->iso_code)
                ),
            ];
        }

        return $cart_rules_list;
    }
}
