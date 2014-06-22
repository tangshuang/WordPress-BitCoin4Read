<?php

require_once(dirname(__FILE__)."/../../../wp-load.php");

/*
https://blockchain.info/zh-cn/api/api_receive
value The value of the payment received in satoshi. Divide by 100000000 to get the value in BTC.
input_address The bitcoin address that received the transaction.
confirmations The number of confirmations of this transaction.
{Custom Parameters} Any parameters included in the callback URL will be passed back to the callback URL in the notification.
transaction_hash The transaction hash.
input_transaction_hash The original paying in hash before forwarding.
destination_address The destination bitcoin address. Check this matches your address.
*/

//Commented out to test, uncomment when live
if ($_GET['test'] == true) {
	echo 'Ignoring Test Callback';
	return;
}

$value_in_satoshi = $_GET['value'];
$amount = $value_in_satoshi / 100000000;
$input_address = $_GET['input_address'];
$confirmations = $_GET['confirmations'];
$transaction_hash = $_GET['transaction_hash'];
$input_transaction_hash = $_GET['input_transaction_hash'];
$destination_address = $_GET['destination_address'];
$secret = $_GET['secret'];
$trade_id = urldecode($_GET['trade_id']);

if($destination_address != BITCOIN4READ_ADDRESS){
	echo 'Incorrect Receiving Address';
	return;
}

$trade_info = get_option($trade_id);
$trade_info = is_array($trade_info) && !empty($trade_info) ? $trade_info : array();

date_default_timezone_set("PRC");
$trade_info['paid_time'] = date('Y-m-d H:i:s');
$trade_info['transaction_hash'] = $transaction_hash;
$trade_info['input_transaction_hash'] = $input_transaction_hash;
$trade_info['paid_amount'] = $amount;
$trade_info['input_address'] = $input_address;
$trade_info['confirmations'] = $confirmations;

if(isset($trade_info['secret']) && $secret != $trade_info['secret']){
	echo 'Invalid Secret';
	return;
}else{
	$trade_info['secret'] = $secret;
}

update_option($trade_id,$trade_info);

if($confirmations >= 6){
	echo "*ok*";
}else{
	echo "Waiting for confirmations";
}