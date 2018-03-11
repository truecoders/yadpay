<?php

class YadpayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'yadpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->trans('Оплата не доступна', array(), 'Modules.Yadpay.Shop'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $mailVars = array();

        if ($total) {
            $this->module->validateOrder((int)$cart->id, Configuration::get('YAD_STATEMENT_ID'), $total, $this->module->displayName, null, $mailVars, (int)$currency->id, false, $customer->secure_key);
            $this->module->sendToVk(Configuration::get('PS_SHOP_NAME').': '.'Оплачено Картой ' . $total .' руб. (3D secure). Корзина №'.$cart->id);

            Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id
                .'&id_module='.(int)$this->module->id
                .'&id_order='.$this->module->currentOrder
                .'&key='.$customer->secure_key);

        }else{

            $ord = new Order((int)Order::getOrderByCartId(Tools::getValue('cart_id')));

            Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)Tools::getValue('cart_id')
                .'&id_module='.(int)$this->module->id
                .'&id_order='.$ord->id
                .'&key='.$customer->secure_key);

        }

        /*if (Tools::getValue('cart_id')) {

            if ($total) {
                $this->module->validateOrder((int)$cart->id, Configuration::get('YAD_STATEMENT_ID'), $total, $this->module->displayName, null, $mailVars, (int)$currency->id, false, $customer->secure_key);
                $this->module->sendToVk(Configuration::get('PS_SHOP_NAME').': '.'Оплачено Картой ' . $total .' руб. (3D secure). Корзина №'.$cart->id);

                Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id
                    .'&id_module='.(int)$this->module->id
                    .'&id_order='.$this->module->currentOrder
                    .'&key='.$customer->secure_key);

            }else{

                $ord = new Order((int)Order::getOrderByCartId(Tools::getValue('cart_id')));

                Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)Tools::getValue('cart_id')
                    .'&id_module='.(int)$this->module->id
                    .'&id_order='.$ord->id
                    .'&key='.$customer->secure_key);
            }

        }else{

            $this->module->validateOrder((int)$cart->id, Configuration::get('YAD_STATEMENT_ID'), $total, $this->module->displayName, null, $mailVars, (int)$currency->id, false, $customer->secure_key);

            Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id
                .'&id_module='.(int)$this->module->id
                .'&id_order='.$this->module->currentOrder
                .'&key='.$customer->secure_key);
        }*/
    }
}
