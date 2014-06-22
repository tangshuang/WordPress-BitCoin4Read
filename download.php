<?php
define('BITCOIN4READ_WP2PCS_DIR','/apps/utubon/www.utubon.com/bitcoin4read/');// 本插件的付费下载链接基于WP2PCS

require_once("../../../wp-load.php");

$user_id = $_COOKIE['bitcoin4read_user_id'];
$trade_id = $_GET['id'];
$trade_info = get_option($trade_id);

if(!$trade_info || empty($trade_info)){
	wp_die('尚未交易，如何下载？');
}

// 如果当前用户没有权限，才对付款与否进行判断
if(!current_user_can('edit_theme_options')){
	if($trade_info['user_id'] != $user_id){
		wp_die('这不是你的下载链接，请付费购买后下载');
		exit;
	}
	elseif(!isset($trade_info['paid_time']) || $trade_info['confirmations'] < BITCOIN4READ_CONFIRMATION){
		wp_die('该交易尚未成功，请付款后下载');
		exit;
	}
	elseif($trade_info['trade_day'] && time()-strtotime($trade_info['paid_time']) > $trade_info['trade_day']*24*60*60){
		wp_die('您的消费记录已经超过有效期，请重新付费');
		exit;
	}
}

$path = BITCOIN4READ_WP2PCS_DIR.$trade_info['download'];

global $baidupcs;
$result = $baidupcs->downloadStream($path);
$meta = json_decode($result,true);
if(isset($meta['error_msg'])){
	wp_die($meta['error_msg'].'<br />请稍后再试，或联系商家');
	exit;
}

flush();
header("Content-Type: application/octet-stream");
header('Content-Disposition:inline;filename="'.basename($path).'"');
header('Accept-Ranges: bytes');
echo $result;
exit;