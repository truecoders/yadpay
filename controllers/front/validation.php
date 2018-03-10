<?php

class YadpayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
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

        $mailVars =    array(
            '{YAD_DESCRIPTION}' => Configuration::get('YAD_DESCRIPTION'),
            '{yadpay_html}' => str_replace("\n", '<br />', Configuration::get('yadpay_description')));

        $this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_CHEQUE'), $total, $this->module->displayName, null, $mailVars, (int)$currency->id, false, $customer->secure_key);

        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }
}
