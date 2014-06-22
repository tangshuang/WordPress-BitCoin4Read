<?php

add_action('admin_menu','register_bitcoin4read_manage_menu');
function register_bitcoin4read_manage_menu(){
	add_submenu_page('plugins.php','BITCOIN4READ','BITCOIN4READ','remove_users','bitcoin4read-manage','bitcoin4read_manage_page');
}

function bitcoin4read_manage_page(){
	global $wpdb;
	echo '<h2>WP2PCS所有已购买服务确认</h2>';

?>
<style>
.opt-trade{text-decoration:none;}
</style>
<?php

	if(isset($_GET['action'])){
		$user_id = @$_GET['user_id'];
		$post_id = @$_GET['post_id'];
		$trade_id = @$_GET['trade_id'];
		// 删除某个账单
		if($_GET['action'] == 'delete_payment'){
			delete_option($trade_id);
			echo '<script>window.location.href="'.remove_query_arg(array('action','trade_id')).'"</script>';
			return;
		}
		// 添加某个账单表单
		elseif($_GET['action'] == 'add_payment'){
			?>
			<form method="post" action="<?php echo add_query_arg('action','add_payment_record'); ?>">
			<h3>添加账单：</h3>
			<p><textarea name="add_payment" style="width:80%;height:260px;"><?php echo "trade_id=BITCOIN4READ:\nuser_id=\npost_id=\ntrade_title=\ntrade_url=".home_url('?p=')."\nprice=\ntrade_day=\npaid_time=".date('Y-m-d H:i:s')."\ntrade_status=SUCCESS\ntrade_no=\ndownload="; ?></textarea></p>
			<p><button type="submit" class="button-primary">提交</button></p>
			</form>
			<?php
			return;
		}
		// 执行提交过来的账单
		elseif($_GET['action'] == 'add_payment_record'){
			$post_info = str_replace("\r\n",'&',$_POST['add_payment']);
			parse_str($post_info,$trade_info);
			$trade_id = 'BITCOIN4READ:'.$trade_info['post_id'].':'.$trade_info['user_id'];
			if($trade_info['trade_id'] && $trade_id != $trade_info['trade_id']){
				delete_option($trade_id);
				$trade_id = $trade_info['trade_id'];
			}
			update_option($trade_id,$trade_info);
			echo '<script>window.location.href="'.remove_query_arg(array('action','user_id','post_id','trade_id')).'"</script>';
			return;
		}
		// 修改账单
		elseif($_GET['action'] == 'edit_payment'){
			$trade_info = get_option($trade_id);
			?>
			<form method="post" action="<?php echo add_query_arg('action','add_payment_record'); ?>">
				<h3>修改账单 <?php echo $trade_id; ?></h3>
				<p><textarea name="add_payment" style="width:80%;height:260px;"><?php
					foreach($trade_info as $key => $value){
						echo "$key=$value\n";
					}
				?></textarea></p>
				<p><?php echo plugins_url('download.php',BITCOIN4READ_PLUGIN_NAME).'?id='.$trade_id; ?></p>
				<p><button type="submit" class="button-primary">提交</button></p>
			</form>
			<?php
			return;
		}elseif($_GET['action'] == 'show_payment'){
			$trade_info = get_option($trade_id);
			echo "<pre>$trade_info</pre>";
			return;
		}
	}

	echo '<a href="'.add_query_arg('action','add_payment').'" class="button-primary">添加新账单</a>';
	$payments = $wpdb->get_results("SELECT * FROM $wpdb->options WHERE option_name LIKE 'BITCOIN4READ:%' AND option_value LIKE '%paid_time%';");

	if(!empty($payments))foreach($payments as $payment){
		$trade_id = $payment->option_name;
		$trade_info = unserialize($payment->option_value);
		$user_id = $trade_info['user_id'];
			$user_data = get_userdata($user_id);
			if($user_data){
				$user_name = $user_data->user_login;
				$user_email = $user_data->user_email;
				$user_qq = $user_data->qq_number;
			}
		$post_id = $trade_info['post_id'];
		if(!$trade_info['confirmations'])continue;// 不显示没有付账的账单
		echo '<div>';
		echo "<p>
			$trade_id &nbsp;&nbsp;&nbsp;&nbsp; 
			<a href='".get_permalink($trade_info['post_id'])."' target='_blank'>".($trade_info['trade_title']?$trade_info['trade_title']:get_the_title($trade_info['post_id']))."</a> &nbsp;&nbsp;&nbsp;&nbsp; 
			".($trade_info['confirmations'] < BITCOIN4READ_CONFIRMATION ? '待确认' : '已成功')." &nbsp;&nbsp;&nbsp;&nbsp; 
			".($trade_info['trade_day'] ? "有效期{$trade_info['trade_day']}天" : '永久有效')." &nbsp;&nbsp;&nbsp;&nbsp; ";
		if($user_data)echo "
			$user_id &nbsp;&nbsp;&nbsp;&nbsp; 
			$user_name &nbsp;&nbsp;&nbsp;&nbsp; 
			$user_email &nbsp;&nbsp;&nbsp;&nbsp; ";
		echo "<a href='".add_query_arg(array(
					'trade_id' => $trade_id,
					'user_id' => $user_id,
					'post_id' => $post_id,
					'action' => 'edit_payment'
				))."' class='opt-trade' title='修改账单'>+</a>
			<a href='".add_query_arg(array(
					'trade_id' => $trade_id,
					'action' => 'delete_payment'
				))."' onclick='if(!confirm(\"删除账单不可撤销，确认删除？\"))return false;' class='opt-trade' title='删除账单'>×</a>
			</p>";
		echo '</div>';
	}
}