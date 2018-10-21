<?php
/**
 * zibal online gateway for PrestaPay 
 *
 * @website		zibal.ir
 * @copyright	(c) 2018 - Zibal Team
 * @since		21 Oct 2018
 */

if( !defined('_PS_VERSION_'))
    exit;

class Prestapay_zibal extends PrestapayPlugin
{
    /**
     * Initialize plugin
     *
     * @return	void
     */
    public function init()
    {
        $this->name 		= 'zibal';
        $this->version 		= '1.0';
        $this->displayName 	= $this->l('zibal.ir','zibal');
        $this->description 	= $this->l('درگاه پرداخت انلاین زیبال','zibal');
        $this->authorName 	= 'zibal.ir';
        $this->authorUrl 	= 'http://zibal.ir';
        $this->adminIcon 	= 'icon-clock-o';

        $this->webservice   = 'https://gateway.zibal.ir/';
        $this->ws_test_api  = 'zibal';

        // gate , ...
        $this->category = array(
            'gateway'
        );

        if ( $this->is_ps17() ) {
            $this->hooks    = array(
                'paymentOptions'
            );
        } else {
            $this->hooks    = array(
                'displayPayment'
            );
        }

        $this->configs  = array(
            'PSFPAY_GATE_ZIBAL_MERCHANT' 	    => '',
            'PSFPAY_GATE_ZIBAL_ACTIVE' 	    => 0,
            'PSFPAY_GATE_ZIBAL_DIRECT' 	=> 0,
            'PSFPAY_GATE_ZIBAL_TITLE'       => $this->l('پرداخت با درگاه پرداخت زیبال','zibal'),
            'PSFPAY_GATE_ZIBAL_TEXT_CONFIRM_PAYMENT' => '',
        );
    }

    /*
     |--------------------------------------------------------------------------
     | Configure
     |--------------------------------------------------------------------------
     */

    /**
     * Configure page for plugin
     *
     * @return	string
     */
    public function configure()
    {
        $output = '';

        $fields = array(
            'PSFPAY_GATE_ZIBAL_MERCHANT',
            'PSFPAY_GATE_ZIBAL_ACTIVE',
            'PSFPAY_GATE_ZIBAL_DIRECT',
            'PSFPAY_GATE_ZIBAL_TITLE',
            'PSFPAY_GATE_ZIBAL_TEXT_CONFIRM_PAYMENT',
        );

        $soption = array(
            array(
                'id' => 'active_on',
                'value' => 1,
                'label' => $this->l('Enabled','zibal')
            ),
            array(
                'id' => 'active_off',
                'value' => 0,
                'label' => $this->l('Disabled','zibal')
            )
        );

        $result = $this->is_currency();
        if ( $result === true ) {
            if ( Tools::isSubmit('submit'.$this->module->name) )
            {
                foreach ($this->configs as $key => $defaultValue)
                    Configuration::updateValue($key, Tools::getValue($key,$defaultValue), true);

                $output .= $this->displayConfirmation($this->l('تنظیمات با موفقیت به روز شد !','zibal'));
            }
        } else {
            $output .= $result;
            Configuration::updateValue('PSFPAY_GATE_ZIBAL_ACTIVE', 0);
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('تنظیمات درگاه زیبال','zibal'),
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('وضعیت','zibal'),
                    'name' => 'PSFPAY_GATE_ZIBAL_ACTIVE',
                    'values' => $soption,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('عنوان درگاه','zibal'),
                    'name' => 'PSFPAY_GATE_ZIBAL_TITLE'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('کد مرچنت','zibal'),
                    'name' => 'PSFPAY_GATE_ZIBAL_MERCHANT'
                ),
                array(
                    'type' => 'textarea',
                    'name' => 'PSFPAY_GATE_ZIBAL_TEXT_CONFIRM_PAYMENT',
                    'label' => $this->l('پیام موفق آمیز بودن پرداخت و ثبت سفارش','zibal'),
                    'desc' => $this->l('شناسه سفارش: {id_order} ، شماره پیگیری سفارش: {reference_order} ، شماره پیگیری پرداخت: {reference_payment}','zibal'),
                    'autoload_rte' 	=> true
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('درگاه مستقیم','zibal'),
                    'name' => 'PSFPAY_GATE_ZIBAL_DIRECT',
                    'desc' => $this->l('در صورتیکه درگاه مستقیم برای شما فعال باشد این گزینه کارایی دارد','zibal'),
                    'values' => $soption,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save','zibal'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $form = new PrestapayForm($this->module);
        $form->setFieldsByArray($fields);

        return	$output.$form->generateForm( $fields_form ).$this->getAds();
    }

    /*
     |--------------------------------------------------------------------------
     | methode payment
     |--------------------------------------------------------------------------
     */
    public function preparePayment($options = array()) {

        $direct = Configuration::get('PSFPAY_GATE_ZIBAL_DIRECT');


        $api        = Configuration::get('PSFPAY_GATE_ZIBAL_MERCHANT');
        $redirect   = $this->getValidationLink($this->name);

        $amount     = (isset($options['amount'])) ? $options['amount'] : $this->getOrderTotal();

        $result     = $this->postToZibal( 'request', [
            'merchant'       => $api,
            'amount'    => $amount,
            'callbackUrl'  => $redirect,
            'mobile'    => $options['mobile'],
            'description' => $options['description'],
            'reseller'=> 'faraket',
        ]);

        // Display the result
        if  ( $result->result==100 )
        {
            $url  = $this->webservice.'start/'.$result->trackId;
            $url.= ($direct=='1')?'/direct':'/';
            return [
                'authority'     => $result->trackId,
                'redirect_link' => $url
            ];

        } else {

            if ( isset($result->message ) )
                $errorMessage =  $result->message;
            else
                $errorMessage =  $this->_getError($result->result);

            return [
                'errorMessage'  => [$errorMessage]
            ];
        }

    }

    public function verifyPayment($options = array()) {

        $status = Tools::getValue('success');
        if ( $status == 1 ) {

            $trackId   = (int)$_POST['trackId'];

            $activeTestMode = Configuration::get('PSFPAY_GATE_ZIBAL_DIRECT');
            $api            = ($activeTestMode ? $this->ws_test_api : Configuration::get('PSFPAY_GATE_ZIBAL_MERCHANT'));

            $result = $this->postToZibal( 'verify',  [
                'merchant'       => $api,
                'trackId'    => $trackId
            ]);

            // Display the result
            if  ( $result->result == 100 ) {
                return [
                    'authority' => $trackId,
                    'reference' => $result->refNumber
                ];
            }

            if ( isset($result->message ) )
                $errorMessage =  $result->message;
            else
                $errorMessage =  $this->_getError($result->result,'validation');

            return [
                'errorMessage'  => [$errorMessage]
            ];

        } else {
            return [
                'errorMessage'  => [$this->l('عملیات پرداخت انجام نشده است.','zibal')]
            ];
        }

    }

    public function getAuthority(){
        return (int)$_POST['trackId'];
    }

    /*
     |--------------------------------------------------------------------------
     | methode plugin
     |--------------------------------------------------------------------------
     */
    public function _getError( $result, $type = 'payment')
    {
        return 'نا مشخص';
    }

    private function postToZibal($path, $parameters)
    {
        $url = $this->webservice.$path;
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        curl_close($ch);
        return json_decode($response);
    }

 public function getAds(){
        return $this->renderPluginTemplate('ads.tpl');
    }

}

