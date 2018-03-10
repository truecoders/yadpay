<?php
/*
*  @author 
*  @copyright
*  @license 
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Yadpay extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public $yadWallet;
    public $yadRedirectUrl;
    public $yadClientId;
    public $yadSecret;
    public $yadDescription;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'yadpay';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->author = 'Truecoders';
        $this->controllers = array('payment', 'validation', 'paying');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('YAD_WALLET', 'YAD_REDIRECT_URL', 'YAD_CLIENT_ID', 'YAD_SECRET', 'YAD_DESCRIPTION'));

        if (isset($config['YAD_WALLET'])) {
            $this->yadWallet = $config['YAD_WALLET'];
        }
        if (isset($config['YAD_REDIRECT_URL'])) {
            $this->yadRedirectUrl = $config['YAD_REDIRECT_URL'];
        }
        if (isset($config['YAD_CLIENT_ID'])) {
            $this->yadClientId = $config['YAD_CLIENT_ID'];
        }
        if (isset($config['YAD_SECRET'])) {
            $this->yadSecret = $config['YAD_SECRET'];
        }
        if (isset($config['YAD_DESCRIPTION'])) {
            $this->yadDescription = $config['YAD_DESCRIPTION'];
        }


        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Оплата Картами и Яндекс.Деньгами', array(), 'Modules.Yadpay.Admin');
        $this->description = $this->trans('Этим модулем можно платить с помощью Яндекс.Денег и банковскими картами', array(), 'Modules.Yadpay.Admin');
        $this->confirmUninstall = $this->trans('Точно?', array(), 'Modules.Yadpay.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);

        if ((!isset($this->yadWallet) || !isset($this->yadRedirectUrl) || !isset($this->yadClientId) || !isset($this->yadSecret) || empty($this->yadWallet) || empty($this->yadRedirectUrl) || empty($this->yadClientId) || empty($this->yadSecret))) {
            $this->warning = $this->trans('Заполните поля в модуле', array(), 'Modules.Yadpay.Admin');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('Не установлена валюта', array(), 'Modules.Yadpay.Admin');
        }

        $this->extra_mail_vars = array(
                                    '{YAD_WALLET}' => Configuration::get('YAD_WALLET'),
                                    '{yadpay_html}' => Tools::nl2br(Configuration::get('YAD_DESCRIPTION'))
                                );
    }

    public function install()
    {
        return parent::install()
        && $this->registerHook('paymentOptions')
        && $this->registerHook('paymentReturn')
        && $this->createConfig();
    }

    public function uninstall()
    {
        return Configuration::deleteByName('YAD_WALLET')
            && Configuration::deleteByName('YAD_REDIRECT_URL')
            && Configuration::deleteByName('YAD_CLIENT_ID')
            && Configuration::deleteByName('YAD_SECRET')
            && Configuration::deleteByName('YAD_DESCRIPTION')
            && parent::uninstall()
        ;
    }


    public function createConfig()
    {
        $validationPage = $this->context->link->getModuleLink($this->name, 'validation', array(), true);

        Configuration::updateValue('YAD_WALLET', 'Типа того: 410012022000000');
        Configuration::updateValue('YAD_REDIRECT_URL', $validationPage);
        Configuration::updateValue('YAD_CLIENT_ID', NULL);
        Configuration::updateValue('YAD_SECRET', NULL);
        Configuration::updateValue('YAD_DESCRIPTION', 'Вы перейдете на сайт Яндекса для оплаты, а затем будет создан заказ');
        return true;
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('YAD_WALLET')) {
                $this->_postErrors[] = $this->trans('Кошелек нужно ввести', array(),'Modules.Yadpay.Admin');
            }
            elseif (!Tools::getValue('YAD_CLIENT_ID')) {
                $this->_postErrors[] = $this->trans('Необходимо ID приложения', array(), 'Modules.Yadpay.Admin');
            }
            elseif (!Tools::getValue('YAD_SECRET')) {
                $this->_postErrors[] = $this->trans('Введите SECRET', array(), 'Modules.Yadpay.Admin');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('YAD_WALLET', Tools::getValue('YAD_WALLET'));
            Configuration::updateValue('YAD_CLIENT_ID', Tools::getValue('YAD_CLIENT_ID'));
            Configuration::updateValue('YAD_SECRET', Tools::getValue('YAD_SECRET'));
            Configuration::updateValue('YAD_DESCRIPTION', Tools::getValue('YAD_DESCRIPTION'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Обновлено', array(), 'Admin.Notifications.Success'));
    }

    private function _displayYadpay()
    {
        return $this->display(__FILE__, './views/templates/hook/info.tpl');
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->_displayYadpay();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOptionYad = new PaymentOption();
        $newOptionCard = new PaymentOption();

        $cart = $this->context->cart;

        $newOptionCard->setModuleName($this->name)
                ->setCallToActionText($this->trans('Оплата Банковскими картами', array(), 'Modules.Yadpay.Admin'))
                ->setAction($this->context->link->getModuleLink($this->name, 'paying', array('payby'=>'card'), true))
                ->setAdditionalInformation($this->fetch('module:yadpay/views/templates/front/payment_infos.tpl'));

        $newOptionYad->setModuleName($this->name)
                ->setCallToActionText($this->trans('Оплата Яндекс.Деньгами', array(), 'Modules.Yadpay.Admin'))
                ->setAction($this->context->link->getModuleLink($this->name, 'paying', array('payby'=> 'yad'), true))
                ->setAdditionalInformation($this->fetch('module:yadpay/views/templates/front/payment_infos.tpl'));

        return [$newOptionYad,$newOptionCard];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (in_array($state, array(Configuration::get('PS_OS_CHEQUE'), Configuration::get('PS_OS_OUTOFSTOCK'), Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')))) {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'shop_name' => $this->context->shop->name,
                'yadWallet' => $this->yadWallet,
                'yadDescription' => Tools::nl2br($this->yadDescription),
                'status' => 'ok',
                'id_order' => $params['order']->id
            ));
            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $this->smarty->assign('reference', $params['order']->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }
        return $this->fetch('module:ps_checkpayment/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Настройки модуля', array(), 'Modules.Yadpay.Admin'),
                    'icon' => 'settings',
                    'desc' => $this->trans('Для работы с модулем нужно <a href="https://money.yandex.ru/new" target="_blank">открыть кошелек</a> на Яндексе и <a href="https://sp-money.yandex.ru/myservices/new.xml" target="_blank">зарегистрировать приложение</a> на сайте Яндекс.Денег', array(), 'Modules.Checkpayment.Admin'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Номер кошелька', array(), 'Modules.Yadpay.Admin'),
                        'name' => 'YAD_WALLET',
                        'required' => true,
                        'desc' => $this->trans('На этот кошелек придет оплата', array(), 'Modules.Checkpayment.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Redirect URL', array(), 'Modules.Yadpay.Admin'),
                        'name' => 'YAD_REDIRECT_URL',
                        'readonly' => true,
                        'required' => true,
                        'desc' => $this->trans('Эту ссылку нужно вставить в приложение Яндекса', array(), 'Modules.Checkpayment.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Id приложения', array(), 'Modules.Yadpay.Admin'),
                        'name' => 'YAD_CLIENT_ID',
                        'required' => true,
                        'desc' => $this->trans('Id приложения с сайта Яндекса', array(), 'Modules.Checkpayment.Admin'),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Секретное слово', array(), 'Modules.Yadpay.Admin'),
                        'name' => 'YAD_SECRET',
                        'required' => true,
                        'desc' => $this->trans('ID и секретное слово вы получите после <a href="https://sp-money.yandex.ru/myservices/new.xml" target="_blank">регистрации приложения</a> на сайте Яндекс.Денег', array(), 'Modules.Checkpayment.Admin'),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Описание', array(), 'Modules.Yadpay.Admin'),
                        'desc' => $this->trans('Описание на странице оформления', array(), 'Modules.Checkpayment.Admin'),
                        'name' => 'YAD_DESCRIPTION',
                        'required' => false
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Сохранить', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = array();

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'YAD_WALLET' => Tools::getValue('YAD_WALLET', Configuration::get('YAD_WALLET')),
            'YAD_REDIRECT_URL' => Tools::getValue('YAD_REDIRECT_URL', Configuration::get('YAD_REDIRECT_URL')),
            'YAD_CLIENT_ID' => Tools::getValue('YAD_CLIENT_ID', Configuration::get('YAD_CLIENT_ID')),
            'YAD_SECRET' => Tools::getValue('YAD_SECRET', Configuration::get('YAD_SECRET')),
            'YAD_DESCRIPTION' => Tools::getValue('YAD_DESCRIPTION', Configuration::get('YAD_DESCRIPTION')),
        );
    }

    public function getTemplateVars()
    {
        $cart = $this->context->cart;
        $total = $this->trans(
            '%amount% (tax incl.)',
            array(
                '%amount%' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            ),
            'Modules.Yadpay.Admin'
        );

        $checkOrder = Configuration::get('YAD_WALLET');
        if (!$checkOrder) {
            $checkOrder = '___________';
        }

        $checkDescription = Tools::nl2br(Configuration::get('YAD_DESCRIPTION'));
        if (!$checkDescription) {
            $checkDescription = '___________';
        }

        return [
            'checkTotal' => $total,
            'checkOrder' => $checkOrder,
            'checkDescription' => $checkDescription,
        ];
    }
}
