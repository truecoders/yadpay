<?php
// For payments from the Yandex.Money wallet
require_once __DIR__.'/../../lib/api.php';

// For payments from bank cards without authorization
require_once __DIR__.'/../../lib/external_payment.php';

use \YandexMoney\API;
use \YandexMoney\ExternalPayment;

class YadpayPayingModuleFrontController extends ModuleFrontController

{
    public function postProcess()
    {

        $wallet = $this->module->yadWallet;
        $client_id = $this->module->yadClientId;
        $redirect_uri = $this->module->yadRedirectUrl;
        $client_secret = $this->module->yadSecret;

        $cart = $this->context->cart;
        $cid = (int)$cart->id;
        $currency = $this->context->currency;
        $total = (int)Configuration::get('YAD_TEST') === 1 ? 2 : (float)$cart->getOrderTotal(true, Cart::BOTH);

        $rub_currency_id = Currency::getIdByIsoCode('RUB');
        if ($cart->id_currency != $rub_currency_id) {
            $from_currency = new Currency($cart->id_currency);
            $to_currency = new Currency($rub_currency_id);
            $total = Tools::convertPriceFull($total, $from_currency, $to_currency);
        }


        $shop_name = Configuration::get('PS_SHOP_NAME');
        $comment = 'Оплата заказа в '.$shop_name.' на сумму '.$total.' руб.';
        $message = 'Оплата корзины '.$cart->id.' в '.$shop_name.' на сумму '.$total.' руб.';
        $label = $shop_name.'/'.$cart->id;

        $this->module->sendToVk($shop_name.': '.'Началась оплата корзины №'. $cid .' на сумму ' . $total . ' руб.');

        if (Tools::getValue('error')) {
            $this->module->sendToVk($shop_name.': Ошибка оплаты -> '.Tools::getValue('error'));
            Tools::redirect('index.php?controller=order&step=3');
        }

        //Оплата деньгами
        if (Tools::getValue('by') == 'yad') {

            $scope = array(
                "payment.to-account(\"".$wallet."\",\"account\").limit(,".$total.")",
                "money-source(\"wallet\")"
            );
            
            $auth_url = API::buildObtainTokenUrl($client_id, $redirect_uri, $scope);
            Tools::redirect($auth_url);
        }

        //Получаем код от Яндекса и снимаем деньги
        if (Tools::getValue('code')) {
            $code = Tools::getValue('code');

            $response = API::getAccessToken($client_id, $code, $redirect_uri, $client_secret);

            if(property_exists($response, "error")) {
                $this->module->sendToVk($shop_name.': '.Tools::getValue('error'));
                Tools::redirect('index.php?controller=order&step=3');
            }
            $access_token = $response->access_token;

            if ($access_token) {
                $api = new API($access_token);

                $request_payment = $api->requestPayment(array(
                    "pattern_id" => "p2p",
                    "to" => $wallet,
                    "amount_due" => $total,
                    "comment" => $comment,
                    "message" => $message,
                    "label" => $label,
                ));

                $process_payment = $api->processPayment(array(
                    "request_id" => $request_payment->request_id,
                ));

                $this->module->sendToVk($shop_name.': '.'Оплачено я.деньгами ' . $total .' руб.');

                Tools::redirect($this->context->link->getModuleLink($this->module->name, 'validation', array(), true));

            }else{
                $this->module->sendToVk($shop_name.': '.'Ошибка access_token Я.Деньги');
                Tools::redirect('index.php?controller=order&step=3');
            }


        }

        //Оплата картой
        if (Tools::getValue('by') == 'card') {

            $res = ExternalPayment::getInstanceId($client_id);

            if ($res->status == 'success') {
                $instance_id = $res->instance_id;
                $external_payment = new ExternalPayment($instance_id);

                $payment_options = array(
                    "pattern_id" => "p2p",
                    "to" => $wallet,
                    "amount_due" => $total,
                    "comment" => trim($comment),
                    "message" => trim($message),
                    "label" => $label,
                );
                
                $response = $external_payment->request($payment_options);

                if ($response->status == "success") {

                    $request_id = $response->request_id;
                    $this->context->cookie->yadpay_encrypt_CRequestId
                        = urlencode($this->module->getCipher()->encrypt($request_id));
                    $this->context->cookie->yadpay_encrypt_CInstanceId
                        = urlencode($this->module->getCipher()->encrypt($instance_id));
                    $this->context->cookie->write();
                    
                    do {
                        $process_options = array(
                            'request_id' => $request_id,
                            'instance_id' => $instance_id,
                            'ext_auth_success_uri' => $this->context->link->getModuleLink($this->module->name, 'validation', array('cart_id'=> $cid), true),
                            'ext_auth_fail_uri' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'index.php?controller=order&step=3'
                        );
                        
                        $result = $external_payment->process($process_options);
                        if ($result->status == "in_progress") {
                            sleep($result->next_retry);
                        }
                    } while ($result->status == "in_progress");

                    if ($result->status == 'success') {
                        $this->module->sendToVk($shop_name.': '.'Оплачено Картой ' . $total .' руб. (без 3D secure)');
                        Tools::redirect($this->context->link->getModuleLink($this->module->name, 'validation', array('cart_id'=> $cid, true)));
                        
                    } elseif ($result->status == 'ext_auth_required') {
                        $url = sprintf("%s?%s", $result->acs_uri, http_build_query($result->acs_params));
                        Tools::redirect($url, '');
                        exit;
                    } elseif ($result->status == 'refused') {
                        $this->module->sendToVk($shop_name.' - Ошибка оплаты картой: ' . $result->error);
                        Tools::redirect('index.php?controller=order&step=3');
                    }
                } else {
                    $this->module->sendToVk($shop_name.' - Ошибка оплаты картой: '.$response->error);
                    Tools::redirect('index.php?controller=order&step=3');
                }

            } else {
                $this->module->sendToVk($shop_name.' - Ошибка оплаты картой: '. $res->error);
                Tools::redirect('index.php?controller=order&step=3');
            }
        }

        //Tools::redirect('index.php?controller=order&step=3');
    }
}
