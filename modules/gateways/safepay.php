<?php

/**
 * WHMCS Safepay Payment Gateway Module
 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
/**
 * Define module related meta data.
 * @return array
 */
function safepay_MetaData()
{
    return array(
        'DisplayName' => 'Safepay',
        'APIVersion' => '1.0',
    );
}

/**
 * Define gateway configuration options.
 * @return array
 */
function safepay_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Safepay (Debit/Credit Cards)',
        ),
        'testMode' => (
        	'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
            'Default' => '0'
        ),
        'productionApiKey' => array(
            'FriendlyName' => 'Production API Key',
            'Type' => 'text',
            'Size' => '50'
        ),
        'sandboxApiKey' => array(
            'FriendlyName' => 'Sandbox API Key',
            'Type' => 'text',
            'Size' => '50'
        ),
    );
}

/**
 * Payment link.
 * Required by third party payment gateway modules only.
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 * @param array $params Payment Gateway Module Parameters
 * @return string
 */
function safepay_link($params)
{
	// Config Options
    if ($params['testMode'] == 'on') {
    	$environment = "sandbox";
    } else {
    	$environment = "production";
    }

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $currency = $params['currency'];
    $amount = floatval($params['amount']);
    ///Transaction_reference
    $transaction = $invoiceId . '_' .time();

    if (!in_array(strtoupper($currency), [ 'PKR', 'USD', 'AUD', 'GBP' ])) {
        return ("<b style='color:red;margin:2px;padding:2px;border:1px dotted;display: block;border-radius: 10px;font-size: 13px;'>Sorry, this version of the Safepay WHMCS plugin only accepts PKR, USD, AUD and GBP payments. <i>$currency</i> not yet supported.</b>");
    }

    $queryString = http_build_query(
        array(
        'invoiceid'=>$invoiceId,
        'transaction'=>$transaction,
        'amount'=>$amount
        )
    );
    $callbackUrl = $params['systemurl'] . '/modules/gateways/callback/safepay.php'. $queryString;

    $code = '
    	<script type="text/javascript" src="https://storage.googleapis.com/safepayobjects/api/safepay-checkout.min.js"></script>
    	<div id="safepay-btn-container"></div>
    	<script>
	        // load jQuery 1.12.3 if not loaded
	        (typeof $ === \'undefined\') && document.write("<scr" + "ipt type=\"text\/javascript\" '.
	        'src=\"https:\/\/code.jquery.com\/jquery-1.12.3.min.js\"><\/scr" + "ipt>");
        </script>
        <script>
        	var button_created = false;
        	var safepayProps = {
        		env: '.$environment.',
        		amount: '.$amount.',
        		currency: '.$currency.',
        		client: {
        			sandbox: '.$params['sandboxApiKey'].',
        			production: '.$params['productionApiKey'].'
        		},
        		payment: function(data, actions) {
        			return actions.payment.create({
        				amount: '.$amount.',
        				currency: '.$currency.'
        			})
        		},
        		onCheckout: function(data, actions) {
        			var redirectUrl = "'.$callbackUrl.'&tracker=" + data.tracker;
        			window.location.href = redirectUrl;
        		}
        	}

        	safepay.Button.render(safepayProps, "#safepay-btn-container");
        </script>';

    return $code;
}