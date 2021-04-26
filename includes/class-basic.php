<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

if(!defined('ABSPATH'))exit();

class Basic{

	function __construct(){}

	public static function CalculateFee($stdCart){
		if(is_cart())return;
		$strPaymentMethod=isset($_POST['payment_method'])?$_POST['payment_method']:WC()->session->get('chosen_payment_method');

		if($strPaymentMethod==Handler::ID){
			$stdClass=new WC_Gateway_TapPay();

			$intAddAdditionalFee=true;
			if($stdClass->free_additional_fee>0&&WC()->cart->subtotal>$stdClass->free_additional_fee){
				$intAddAdditionalFee=false;
			}

			if($intAddAdditionalFee&&(int)$stdClass->additional_fee>0){
				$stdCart->add_fee($stdClass->additional_fee_title, $stdClass->additional_fee, true, 'standard');
			}
		}
	}

	public static function AddRewriteRules(){

		add_rewrite_rule(
			'^cwtpfw/([^\/]+)/?(.*)/?', 
			'index.php?type='.Handler::ID.'&action=$matches[1]&endtype=$matches[2]', 
			'top');

		flush_rewrite_rules();

	}

	public static function AddQueryVars($query_vars){
		if(!in_array('type', $query_vars))		$query_vars[]='type';
		if(!in_array('action', $query_vars))	$query_vars[]='action';
		if(!in_array('endtype', $query_vars))	$query_vars[]='endtype';

		return $query_vars;
	}

	public static function QueryVar($strVarType){

		$arrVars=array();

		$strQueryVar=get_query_var($strVarType);

		if(!$strQueryVar){ // for plain permalink

			preg_match('@^\/([^\.]+)@', $_SERVER['REQUEST_URI'], $match);

			if(!empty($match)&&isset($match[1])&&strpos($match[1], Handler::ID.'/')===0){
				$arrVars=explode('/', $match[1]);
			}
		}

		if(!empty($arrVars)){
			switch($strVarType){
				case 'type':
					return $arrVars[0];
					break;
				case 'action':
					return $arrVars[1];
					break;
				case 'endtype':
					return $arrVars[2];
					break;
				default:
					get_query_var($strVarType);
			}
		}else{
			return $strQueryVar;
		}

	}

	public static function RewriteTemplates(){
		/*
		$strType		=get_query_var('type');
		$strAction	=get_query_var('action');
		$strEndType	=get_query_var('endtype');
		*/

		$strType		=Basic::QueryVar('type');
		$strAction	=Basic::QueryVar('action');
		$strEndType	=Basic::QueryVar('endtype');

		$intUserID=get_current_user_id();

		if($strType==Handler::ID&&$strAction=='3d-secure'){

			switch($strEndType){
				case 'checkout':

					$intAuth=true;

					$strReturnURL		=false;
					$strErrorMessage=false;

					$intOrderID=isset($_GET['order_id'])?$_GET['order_id']:0;
					$stdOrder=wc_get_order($intOrderID);

					if($stdOrder){

						if($_GET['status']=='0'){

							$stdClass=new WC_Gateway_TapPay();
							$intTradeID=Basic::CheckTradeID($_GET['rec_trade_id'], $stdClass);

							$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
							$stdResponse=Extend::DecodeJSON($strResponse);

							if($intTradeID){
								
								$strReturnURL=$stdClass->get_return_url($stdOrder);

								if(isset($_GET['auth_code'])){
									$stdResponse->auth_code=$_GET['auth_code'];
								}

								CheckoutProcess::PaymentComplete($intOrderID, $stdOrder, $stdClass);
								Admin::AfterSuccessPayment($stdOrder, $stdResponse, $stdClass);

							}else{
								//$order->add_order_note('TapPay 3D 驗證失敗');
								$strErrorMessage='TapPay 3D 驗證失敗';
							}

						}else{
							$strErrorMessage='TapPay 結帳發生錯誤，錯誤代號: '.$_GET['status'];
						}

						if(!$strReturnURL)$strReturnURL=WC()->cart->get_checkout_url(); // 無 $strReturnURL，將畫面導回結帳頁

					}else{ // 2020.04.01 Bind card 不會有 order id
						$strRedirectURL=apply_filters(Handler::ID.'_bind-card', wc_get_endpoint_url('payment-methods'));
					}

					if($strErrorMessage)wc_add_notice($strErrorMessage);

					wp_redirect($strReturnURL);

					break;

				case 'notify':
					error_log(print_r('notify', true));
					error_log(print_r($_GET, true));
					error_log(print_r($_POST, true));
					break;
				default:;
			}
			exit(date('Y', time()).' TapPay Pro for WooCommerce by cloudwp.');
		}
	}

	public static function CheckTradeID($strTradeID, $stdClass, $intFullInfo=false){

		$intTradeID	=false;
		$stdData		=false;

		if($stdClass->sandbox=='yes'){
			$strURL='https://sandbox.tappaysdk.com/tpc/transaction/trade-history';
		}else{
			$strURL='https://prod.tappaysdk.com/tpc/transaction/trade-history';
		}

		$arrData=array(
			'partner_key'		=>$stdClass->partner_key, 
			'rec_trade_id'	=>$strTradeID, 
		);

		/*
		$arrResult=array(
			'error'		=>$intError, 
			'message'	=>$strMessage, 
			'json'		=>$strResponse, 
			'data'		=>$stdResponse, 
		);
		*/
		$arrResult=Admin::RemotePost($strURL, $arrData, $stdClass);

		if(isset($arrResult['data'])){
			$stdData=$arrResult['data'];

			/*
			 * $stdData
			 * stdClass Object(
			 * 	[status] => 0
			 * 	[msg] => Success
			 * 	[currency] => 901
			 * 	[rec_trade_id] => D20200520xxxxxx
			 * 	[trade_history] => Array(
			 * 		[0] => stdClass Object(
			 * 			[action] => 4 // 3D 驗證未完成，所以 action=4 ( pending )
			 * 			[millis] => 1589948696895
			 * 			[amount] => 1
			 * 			[success] => 1 // 1|0
			 * 			[is_pending] => // 當退款並未完成前會回應 true，已完成則會回傳 false
			 * 		)
			 * 	)
			 * )
			 */
			if(is_a($stdData, 'stdClass')&&property_exists($stdData, 'trade_history')&&is_array($stdData->trade_history)){
				foreach($stdData->trade_history as $key=>$value){
					/*
					 * action:
					 * - 0 授權 // authorized
					 * - 1 請款
					 * - 3 退款
					 * - 4 待付款 // pending payment
					 * - 5 取消
					 * - 6 取消退款
					 */
					if($value->action=='0'&&$value->success=='1'){
						$intTradeID=true;
						break;
					}
				}
			}
		}

		if($intFullInfo)return $stdData;
		return $intTradeID;

	}

	public static function PluginActionLinks($arrLinks){
		return $arrLinks;
	}

	public static function AddScripts(){

		global $wp_query;

		if(is_account_page()||is_checkout()){
			wp_enqueue_script(Handler::ID.'-sdk', 'https://js.tappaysdk.com/tpdirect/v5.5.1', array(), '1.2.1', true);

			$stdClass=new WC_Gateway_TapPay;

			$strSandBox=$stdClass->sandbox;
			if($strSandBox=='yes'){
				$strAppKey=$stdClass->sandbox_app_key;
			}else{
				$strAppKey=$stdClass->app_key;
			}

			wp_register_script(Handler::ID.'-script', CWTAPPAY_URL.'js/cw-tappay.js', array('jquery', 'jquery-payment'));
			wp_enqueue_script(Handler::ID.'-script');

			$stdPost=$wp_query->get_queried_object();
			$intPageID=$stdPost->ID;

			$arrPage=array(
				get_option('woocommerce_checkout_page_id')	=>'checkout', 
				get_option('woocommerce_myaccount_page_id')	=>'my-account');

			$strPage=array_key_exists($intPageID, $arrPage)?$arrPage[$intPageID]:false;

			$strCurrentPage=apply_filters(Handler::ID.'_current-page', $strPage);

			wp_localize_script(	
				Handler::ID.'-script', 
				'CWTAPPAY_vars', 
				array(
					'id'					=>$stdClass->id, 
					'ajaxurl'			=>admin_url('admin-ajax.php'), 
					'app_id'			=>$stdClass->app_id, 
					'app_key'			=>$strAppKey, 
					'environment'	=>$stdClass->environment, 
					'current'			=>$strCurrentPage, 
					'fields'			=>$stdClass->fields));
		}
	}

	public static function AddStyles(){
		if(is_account_page()||is_checkout()){
			wp_register_style(Handler::ID.'-style', CWTAPPAY_URL.'css/cw-tappay.css');
			wp_enqueue_style(Handler::ID.'-style');
		}
	}

	public static function CheckDate(){
		if(!headers_sent())header('Content-Type: text/html; charset=utf-8');

		$context=stream_context_create(
			array(
				'http'=>array(
					'method'			=>'GET', 
					'header'			=>"Connection: close\r\n", 
					'user_agent'	=>$_SERVER['HTTP_USER_AGENT'])));

		$strContentURL='https://auth.woocloud.io/TapPay/tappay.php?checkdate='.get_site_url();

		$arrResult=wp_remote_get($strContentURL);
		$strResult=wp_remote_retrieve_body($arrResult);
		$stdCheckDate=json_decode($strResult);

		return $stdCheckDate;
	}
}