<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use \Transbank\Webpay\Configuration;
use \Transbank\Webpay\Webpay;

/**
 * WC_Gateway_Webpay_Plus class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Webpay_Plus extends WC_Payment_Gateway {
    var $log;

    public function __construct() {
        $this->log = new LogHandler();
        $this->log->logInfo('constructor del plugin');

        $this->id = 'webpay_plus'; // the unique ID for this gateway
        $this->icon = 'https://www.transbank.cl/public/img/Logo_Webpay3-01-50x50.png';  // the link to the image displayed next to the method’s title on the checkout page — this is optional and doesn’t need to be set.
        $this->method_title = 'Webpay Plus'; // the title of the payment method for the admin page
        $this->has_fields = false; // This should be false for our simple gateway, but can be set to true if you create a direct payment gateway that will have fields, such as credit card fields.

        $this->title = 'Webpay Plus'; // Title in the checkout button
        $this->description = 'Permite el pago con tarjetas de cr&eacute;dito o redcompra a trav&eacute;s de Webpay Plus';

        // We’ll have to initialize the form fields and settings.
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_receipt_' . $this->id, array($this, 'create_transaction')); // register function habdle place order page where we will create transaction an present payment button
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_ipn_response')); // register function to handle callback_url
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = apply_filters('transbank_webpay_form_fields', array (
            'enabled' => array (
                'title' => __('Activar/Desactivar', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Activar pagos con Webpay Plus', 'woocommerce'),
                'default' => 'yes'
            ),
            'integration_type' => array (
                'title' => __('Ambiente', 'woocommerce'),
                'type' => 'select',
                'options' => array (
                    'INTEGRACION' => 'Integraci&oacute;n',
                    'PRODUCCION' => 'Producci&oacute;n'
                ),
                'default' => __('INTEGRACION', 'woocommerce')
            ),
            'commerce_code' => array (
                'title' => __('C&oacute;digo de Comercio', 'woocommerce'),
                'type' => 'text',
                'default' => ''
            )
        ));
    }

    public function process_payment($order_id) {
        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function create_transaction($order_id) {
        try {
            $this->log->logInfo('process_payment');
            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $this->log->logInfo('$amount = ' . $amount);
            $session_id = uniqid();
            $this->log->logInfo('$session_id = ' . $session_id);
            $buy_order = $order_id;
            $this->log->logInfo('$buy_order = ' . $buy_order);
            $return_url = home_url('/') . '?wc-api=WC_Gateway_Webpay_Plus';
            $this->log->logInfo('$return_url = ' . $return_url);
            $final_url = str_replace('_URL_',
                    add_query_arg( 'key', $order->get_order_key(), $order->get_checkout_order_received_url()), '_URL_');
            $this->log->logInfo('$final_url = ' . $final_url);
            $return_url = $return_url . '&orid=' . $order_id;
            $this->log->logInfo('$return_url = ' . $return_url);
            $final_url = $final_url . '&orid=' . $order_id;
            $this->log->logInfo('$final_url = ' . $final_url);

            $webpay_plus_transaction = $this->get_webpay_plus_transaction();
            $this->log->logInfo('Transaction allocated');
            $response = $webpay_plus_transaction->initTransaction($amount, $buy_order, $session_id, $return_url, $final_url);
            if (isset($response) && isset($response->url) && isset($response->token))  {
                $this->log->logInfo(json_encode($response));
                $form_data = '<form action="' . $response->url . '" method="POST" name="webpayForm">';
                $form_data .= '<input type="hidden" name="token_ws" value="' . $response->token . '">';
                $form_data .= '<input type="image" width="200" src="https://webcomercio.cl/wp-content/uploads/2018/06/BotonWebPay-01.png">';
                $form_data .= '</form>';
                echo $form_data;
            }
        } catch(Exception $e) {
            $this->log->logError($e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
            return;
        }
    }

    public function check_ipn_response() {
        $this->log->logInfo('check_ipn_response');
        @ob_clean();
        if (isset($_POST)) {
            header('HTTP/1.1 200 OK');
            $token_ws = isset($_POST["token_ws"]) ? $_POST["token_ws"] : null;
            $this->log->logInfo('$token_ws : ' . $token_ws);
            $wc_order = new WC_Order($order_id);

            if ($token_ws != null) {
                // commit transaction
                $webpay_plus_transaction = $this->get_webpay_plus_transaction();

                try {
                    $response = $webpay_plus_transaction->getTransactionResult($token_ws);
                    if (is_object($response) && isset($response->detailOutput)) {
                        $output = $response->detailOutput;
                        if ($output->responseCode == 0) {
                            $order_id = $_GET['orid'];
                            $this->log->logInfo('$order_id : ' . $order_id);
                            
                            $wc_order->add_order_note(__('Pago exitoso con Webpay Plus', 'woocommerce'));
                            $wc_order->add_order_note(__(json_encode($response), 'woocommerce'));
                            $wc_order->update_status('processing');
                            wc_reduce_stock_levels($order_id);
                            $this->redirect($response->urlRedirection, array("token_ws" => $token_ws));
                            die;
                        } else {
                            $this->log->logError('Codigo de repuesta invalido en el pago');
                        }
                    } else {
                        $this->log->logError('Respuesta incorrecta desde el servicio de webpay. No se pudo confirmar tu compra');
                    }
                } catch (Exception $e) {
                    $this->log->logError('Ocurrio un error al intentar confirmar su orden: ' . $this->log->logError($e->getMessage()));
                }
            } else {
                $this->log->logError('No se encuentra token_ws en la respuesta');
            }
        } else {
            $this->log->logError('Ocurrio un error al procesar su Compra');
        }

        wc_add_notice(__('Ocurrio al procesar tu pago', 'woothemes'), 'error');
        // delete the order-pay from url query because it show a page with bad styles
        header('Location: ' . preg_replace('/order-pay=[0-9]+/', '', $wc_order->get_checkout_payment_url(true))); 
        die();
    }

    function get_webpay_plus_transaction() {
        try {
            $config = Configuration::forTestingWebpayPlusNormal();
            $transaction = (new Webpay($config))->getNormalTransaction();
            return $transaction;
        } catch (Exception $e) {
            $this->log->logError($e->getMessage());
            wc_add_notice('Ocurrio un error creando la transacción: ' . $this->log->logError($e->getMessage()), 'error');

        }
    }

    public function redirect($url, $data) {
        $form_data = '<form action="' . $url . '" method="POST" name="webpayForm">';

        foreach ($data as $name => $value) {
            $form_data .= '<input type="hidden" name="' . htmlentities($name) . '" value="' . htmlentities($value) . '">';
        }

        $form_data .= '</form><script language="JavaScript">document.webpayForm.submit();</script>';
        $this->log->logInfo($form_data);
        echo $form_data;
    }
}