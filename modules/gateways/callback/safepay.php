<?php
/**
 * WHMCS Safepay Payment Callback File
 *
 * Verifying that the payment gateway module is active,
 * Validating an Invoice ID, Checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');
// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
$invoiceId = filter_input(INPUT_GET, "invoiceid");
$transaction = filter_input(INPUT_GET, "transaction");
$tracker = filter_input(INPUT_GET, "tracker");
$amount = filter_input(INPUT_GET, "amount");

if ($gatewayParams['testMode'] == 'on') {
    $environment = "sandbox";
} else {
    $environment = "production";
}

$success = verifyTransaction($tracker, $amount, $environment);

if ($success === true) {

	/**
	 * Validate Callback Invoice ID.
	 *
	 * Checks invoice ID is a valid invoice number. Note it will count an
	 * invoice in any status as valid.
	 *
	 * Performs a die upon encountering an invalid Invoice ID.
	 *
	 * Returns a normalised invoice ID.
	 *
	 * @param int $invoiceId Invoice ID
	 * @param string $gatewayName Gateway Name
	 */
	$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

	 /**
     * Check Callback Transaction ID.
     *
     * Performs a check for any existing transactions with the same given
     * transaction number.
     *
     * Performs a die upon encountering a duplicate.
     */
    checkCbTransID($tracker);

    # Successful
    # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
    addInvoicePayment($invoiceid, $tracker, $amount, 0, $gatewayParams["name"]);
    logTransaction($gatewayParams["name"], $_GET, "Successful"); # Save to Gateway Log: name, data array, status
} else {
    # Unsuccessful
    # Save to Gateway Log: name, data array, status
    logTransaction($gatewayParams["name"], $_GET, "Unsuccessful-".$error . ". Please check Safepay dashboard for Payment id: ".$_GET['tracker']);
}

header("Location: ".$gatewayParams['systemurl']."/viewinvoice.php?id=" . $invoiceid);

function verifyTransaction($tracker = "track_", $amount, $environment)
{

	if($environment == 'sandbox') {
		$url = "https://sandbox.api.getsafepay.com/order/v1/".$tracker;
	} else {
		$url = "https://api.getsafepay.com/order/v1/".$tracker;
	}

    $ch =  curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	$result = curl_exec($ch);
	if (curl_errno($ch)) { 
	   return false;
	}

	curl_close($ch);
	$result_array = json_decode($result);
	if(empty($result_array->status->errors)) {
		$state = $result_array->data->state;
		$amt = $result_array->data->amount;
		if($state === "TRACKER_ENDED" && floatval($amount) == $amt ) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}