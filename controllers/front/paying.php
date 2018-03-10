<?php

class YadpayPayingModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        foreach($cart as $key => $value)
        {
        echo "[$key]", $value, "<hr>";
        }

        echo $total . '<hr>';
        echo $this->module->yadWallet  . '<hr>';
        echo $this->module->yadRedirectUrl . '<hr>';
        echo $this->module->yadClientId . '<hr>';
        echo $this->module->yadSecret . '<hr>';
        echo $customer->secure_key . '<hr>';
    }
}
