<?php

/*
Plugin Name: BITCOIN4READ
Plugin URI: http://www.utubon.com
Description: 基于比特币的，快捷的网站付费阅读功能插件
Version:  1.0
Author: 否子戈
Author URI: http://www.utubon.com
*/


define('BITCOIN4READ_PLUGIN_NAME',__FILE__);
define('BITCOIN4READ_ADDRESS','1GZztBA2JowMEypMjxQtSx1iwLwdGeUaZD');// 付款到的地址
define('BITCOIN4READ_CONFIRMATION',4);// 确认数到达这个值时，认为可以给用户看内容

add_action('get_header','bitcoin4read_set_cookie',-999999);
function bitcoin4read_set_cookie(){
	global $wp_query;
	$has_bitcoin4read = false;
	if($wp_query->posts){
		$count = count($wp_query->posts);
		for($i=0;$i<$count;$i++){
			if(preg_match('/\[bitcoin4read([^\]]+)?\]/',$wp_query->posts[$i]->post_content)){
				$has_bitcoin4read = true;
				break;
			}
		}
	}
	if(!$has_bitcoin4read)return;// 如果不存在比特付就不需要往下执行
	$cookie = $_COOKIE['bitcoin4read_user_id'];
	if(!$cookie){
		$user_id = get_current_user_id();
		if(!$user_id){
			$user_id = dechex(rand(100,999).time());
		}
		setcookie('bitcoin4read_user_id',$user_id,time()+(10*365*24*60*60));
		if(!$_GET['bitcoin4read'] && !$cookie){
			wp_redirect(add_query_arg('bitcoin4read',$user_id));
			exit;
		}
	}
}

function add_bitcoin4download_shortcode($atts){
	// 基于WP2PCS，通过path传递附件路径，并通过浏览器下载
	extract(shortcode_atts(array(
		'trade_id' => '',
		'path' => '',
		'title' => '',
		'target' => ''
	),$atts));
	global $post;
	$title = $title ? $title : $post->post_title;
	$target_attr = $target ? " target='$target'" : '';
	if($trade_id){
		// 将download path保存到数据库中
		$trade_info = get_option($trade_id);
		$trade_info = is_array($trade_info) && !empty($trade_info) ? $trade_info : array();
		if(!$trade_info['download'] || $trade_info['download'] != $path){
			$trade_info['download'] = $path;
			update_option($trade_id,$trade_info);			
		}
		$link = plugins_url('download.php',BITCOIN4READ_PLUGIN_NAME).'?id='.$trade_id;
		return "<a href='$link'{$target_attr}><img src='".plugins_url('bitcoin4download.jpg',BITCOIN4READ_PLUGIN_NAME)."' title='$title' alt='$title' /></a>";
	}
}

function add_bitcoin4read_shortcode($atts,$content = null){
	extract(shortcode_atts(array(
		'price' => '',
		'title' => '',
		'message' => '',
		'day' => '' // 有效天数，为空则没有有效期，永远有效
	),$atts));
	
	global $post;
	static $post_id = 0;// 固定参数
	// 下面这个判断主要用在文章列表中，文章列表中如果文章的id增加了，那么$post_id也要变化
	if($post_id != $post->ID){
		$post_id = $post->ID;
	}
	// 创建一些参数
	$user_id = $_COOKIE['bitcoin4read_user_id'];
	if(!$user_id){
		$user_id = get_current_user_id();
		if(!$user_id)return '你的浏览器不支持COOKIE。';
	}
	$trade_id = "BITCOIN4READ:$post_id:$user_id";
	$trade_info = get_option($trade_id);
	$trade_info = is_array($trade_info) && !empty($trade_info) ? $trade_info : array();
	// 配置一个秘密的私钥
	$secret = $trade_info['secret'];
	if(!$secret){
		$secret = wp_create_nonce().dechex(rand(10,99).time());
		$trade_info['secret'] = $secret;
		update_option($trade_id,$trade_info);
	}
	// 插件的最低传输数量为0.0005B
	if($price < 0.0005){
		$price = 0.0005;
	}
	// 如果当前用户是管理员，就直接看内容吧
	if(current_user_can('edit_theme_options')){
		add_shortcode('bitcoin4download','add_bitcoin4download_shortcode');
		$content = str_replace('[bitcoin4download','[bitcoin4download trade_id="'.$trade_id.'"',$content);
		do_action('can_display_content_for_current_user',$content);
		return do_shortcode($content);
		// 目前只支持一个bitcoin4read里面只有一个bitcoin4download
	}
	// 获取打款到的地址
	$input_address = $trade_info['input_address'];
	if(!$input_address){
		// 注意：下面的内容，只有在不满足上面的条件时才执行
		$my_address = BITCOIN4READ_ADDRESS;
		$my_callback_url = plugins_url('notify_url.php',BITCOIN4READ_PLUGIN_NAME).'?trade_id='.$trade_id.'&secret='.$secret;
		$call_url = "https://blockchain.info/api/receive?method=create&address=$my_address&callback=".urlencode($my_callback_url);
		$response = file_get_contents($call_url);
		$object = json_decode($response);
		$input_address = $object->input_address;
		$trade_info['input_address'] = $input_address;
		$trade_info['callback'] = $my_callback_url;
		update_option($trade_id,$trade_info);
	}
	// 如果还没有支付时间被记录，就通过远程api检查是否已经支付了，如果已经支付了
	if($trade_info['input_address'] && $trade_info['confirmations'] < BITCOIN4READ_CONFIRMATION){
		$unspent = file_get_contents("https://blockchain.info/unspent?active=$input_address");
		$unspent = json_decode($unspent);
		// 已经支付
		if($unspent->unspent_outputs){
			$unspent = $unspent->unspent_outputs;// array oder acs by time
			$last_confirm = $unspent[count($unspent)-1];
			date_default_timezone_set("PRC");
			$trade_info['paid_time'] = date('Y-m-d H:i:s');
			$trade_info['input_transaction_hash'] = $last_confirm->tx_hash;
			$trade_info['paid_amount'] = $last_confirm->value / 100000000;
			$trade_info['confirmations'] = $last_confirm->confirmations;
			$trade_info['post_id'] = $post_id;
			$trade_info['user_id'] = $user_id;
			$trade_info['trade_title'] = $title;
			update_option($trade_id,$trade_info);
		}
	}
	// 二维码和按钮信息
	$qcode_src = "https://blockchain.info/qr?data=$input_address&size=200";
	$message_text = "<img src='".plugins_url('bitcoin4read.jpg',BITCOIN4READ_PLUGIN_NAME)."' class='bitcoin4read-button' data-src='$qcode_src' data-price='$price' data-address='$input_address' />";
	// 如果这部分内容已经付费过了，而且付费成功了
	if($trade_info['paid_time'] && $trade_info['confirmations'] >= BITCOIN4READ_CONFIRMATION){// 如果已经付过款
		// 如果支付的币数少于本文应该付的个数
		if($trade_info['paid_amount'] < $price){
			$return .= '<span class="bitcoin4read-notice-area">您只支付了'.$trade_info['paid_amount'].'个币，而实际需要支付{$price}个币，请继续付费</span>';
			$return .= $message_text;
		}
		// 如果没有规定时间，那么就没有时间期限；或当天在这个规定的时间内
		elseif($day === '' || ($day && (time()-strtotime($trade_info['paid_time'])) < $day*24*60*60)){
			add_shortcode('bitcoin4download','add_bitcoin4download_shortcode');
			$content = str_replace('[bitcoin4download','[bitcoin4download trade_id="'.$trade_id.'"',$content);
			do_action('can_display_content_after_paid',$content);
			$return .= do_shortcode($content);
		}
		// 如果超过了规定的时间
		else{
			$return .= '<span class="bitcoin4read-notice-area">您的消费记录已经超过有效期，请重新付费</span>';
			$return .= $message_text;
		}
	}
	// 如果用户虽然付过币，但尚未确认，不直接显示给他看
	elseif($trade_info['paid_time'] && $trade_info['confirmations'] < BITCOIN4READ_CONFIRMATION){
		$return .= '<span class="bitcoin4read-notice-area">比特币支付成功，已通过'.$trade_info['confirmations'].'次确认，等待剩下的确认中……</span>';
	}
	// 如果这部分内容还没有付费过
	else{
		$return .= $message_text;
	}
	return $return;
}
add_shortcode('bitcoin4read','add_bitcoin4read_shortcode');

add_action('wp_footer','bitcoin4read_print_dialog');
function bitcoin4read_print_dialog($content){
?>
<style>
.bitcoin4read-notice-area{display:block;font-size:1.2em;color:#E43733}
.bitcoin4read-button{cursor:pointer;}
.hidden{display:none;}

#show-bitcoin4read-bg{
	position:fixed;top:0;left:0;
	_position:absolute;
	_top:expression(documentElement.scrollTop);
	width:100%;height:100%;background:#fff;z-index:999999998;
	filter:alpha(Opacity=60);-moz-opacity:0.6;opacity:0.6;
}
#show-bitcoin4read{
	position:fixed;top:50%;left:50%;
	_position:absolute;
	_top:expression(documentElement.scrollTop + window.innerHeight/2);
	width:400px;height:280px;
	margin:-155px 0 0 -210px;
	padding:10px;
	padding-top:20px;
	text-align:center;
	background:#fff;border:#dddddd solid 5px;z-index:999999999;
}
#show-bitcoin4read-close{float:right;margin:-20px -5px 0 0;text-decoration:none;}
</style>
<script>window.jQuery || document.write('<script src="http://lib.sinaapp.com/js/jquery/1.7.2/jquery.min.js">\x3C/script>')</script>
<script>
jQuery(function($){
	$('body').prepend('<div id="show-bitcoin4read-bg" class="hidden"></div><div id="show-bitcoin4read" class="hidden"></div>');
	$('img.bitcoin4read-button').click(function(){
		var $this = $(this),qcode = $this.attr('data-src'),price = $this.attr('data-price'),address = $this.attr('data-address');
		if(address == ''){
			$('#show-bitcoin4read').html('<a href="javascript:void(0);" id="show-bitcoin4read-close">×</a><img src="https://blockchain.info/qr?data=<?php echo BITCOIN4READ_ADDRESS; ?>&size=200" style="width:200px;height:200px;" /> <br /> <span style="color:red;">没有生成比特币付款地址，请刷新后再试！</span> <br /> 通过上面的二维码直接向我们捐赠 <br /> <span style="font-size:0.8em;color:#F89115;">注意：发送的BTC绝对不能少于0.0005฿(=0.5m฿)，<a href="http://www.utubon.com/2666" style="color:#F89115;text-decoration:underline;" target="_blank">说明</a>。</span>');
		}
		else if(address == '<?php echo BITCOIN4READ_ADDRESS; ?>'){
			$('#show-bitcoin4read').html('<a href="javascript:void(0);" id="show-bitcoin4read-close">×</a><img src="https://blockchain.info/qr?data=<?php echo BITCOIN4READ_ADDRESS; ?>&size=200" style="width:200px;height:200px;" /> <br /> <?php echo BITCOIN4READ_ADDRESS; ?> <br />向我们捐赠');
		}
		else{
			$('#show-bitcoin4read').html('<a href="javascript:void(0);" id="show-bitcoin4read-close">×</a><img src="' + qcode + '" style="width:200px;height:200px;" /> <br /> ' + '请支付 <b style="color:#F79115">' + price + '฿(=' + price*1000 + 'm฿)</b> 到 <br />' + address + ' <br /> <span style="font-size:0.8em;color:#F89115;">注意：发送的BTC绝对不能少于0.0005฿(=0.5m฿)，<a href="http://www.utubon.com/2666" style="color:#F89115;text-decoration:underline;" target="_blank">说明</a>。</span>');
		}
		$('#show-bitcoin4read-bg,#show-bitcoin4read').show();
	});
	$('body').on('click','#show-bitcoin4read-close,#show-bitcoin4read-bg',function(e){
		$('#show-bitcoin4read-bg,#show-bitcoin4read').hide();
	});
});
</script><?php
}

include(dirname(__FILE__).'/payment-admin.php');