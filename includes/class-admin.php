<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

if(!defined('ABSPATH'))exit();

class Admin{

	const TestAmount=5;

	function __construct(){}

	/*
	 * v1.2 2020.06.06
	 */
	public static function SaveOrder($intOrderID, $stdPost, $intUpdate){

		// $intOrderID: subscription id

		if(array_key_exists(Handler::ID.'_token', $_POST)){
			$strResponse	='';
			$intTokenID		=$_POST[Handler::ID.'_token'];
			$stdToken			=\WC_Payment_Tokens::get($intTokenID);
			$arrTokenData	=$stdToken->get_data();

			$stdParentOrder=Get::ParentOrder($intOrderID);
			$intOrderID=Get::OrderID($stdParentOrder);

			$strPostMeta	=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true); // 要找回 parent order id
			$stdResponse	=Extend::DecodeJSON($strPostMeta);

			$arrCardSecret=explode(':::', $arrTokenData['token']);

			if(is_a($stdResponse, 'stdClass')){

				if(property_exists($stdResponse, 'card_secret')&&is_a($stdResponse->card_secret, 'stdClass')){

					if(property_exists($stdResponse->card_secret, 'card_key')){
						$stdResponse->card_secret->card_key		=$arrCardSecret[0];
					}

					if(property_exists($stdResponse->card_secret, 'card_token')){
						$stdResponse->card_secret->card_token	=$arrCardSecret[1];
					}

					$strResponse=json_encode($stdResponse);
				}

				if($strResponse)update_post_meta($intOrderID, '_'.Handler::ID.'-return', $strResponse);
			}
		}
	}

	/*
	 * v1.2 2020.06.06
	 */
	public static function CardTokenField($stdOrder){

		$strOrderType=Subscription::OrderType($stdOrder);
		if($strOrderType!='subscription')return; // 限 subscription order 顯示 card token

		$strPaymentMethod=$stdOrder->get_payment_method();
		if($strPaymentMethod!=Handler::ID)return;

		$intUserID=$stdOrder->get_customer_id();
		$arrTokens=\WC_Payment_Tokens::get_customer_tokens($intUserID, Handler::ID);

		$stdParentOrder=Get::ParentOrder($stdOrder);

		$stdDefaultToken=Get::ParentOrderToken($stdParentOrder);

		if(!is_array($arrTokens))$arrTokens=array();

		$arrVariables=array(
			'stdDefaultToken'	=>$stdDefaultToken, 
			'arrTokens'				=>$arrTokens, 
		);

		echo Get::Template('card-token-field', 'admin', $arrVariables);

	}

	public static function RemotePost($strURL, $arrPostData, $stdClass){

		$intError			=false;
		$strMessage		=false;
		$strResponse	='';
		$stdResponse	=false;

		if($stdClass->post_method=='remote'){
			$strBody=json_encode($arrPostData);

			$arrRequest=array(
				'httpversion'	=>'1.1', 
				'timeout'			=>30, 
				'headers'			=>array(
					'content-type'	=>'application/json;', 
					'x-api-key'			=>$stdClass->partner_key), 
				'body'						=>$strBody);

			$response=wp_remote_post($strURL, $arrRequest);

			if(is_wp_error($response)){
				$intError		=true;
				$strMessage	=$response->get_error_message();

			}else{
				$strResponse=$response['body'];
			}

		}elseif($stdClass->post_method=='curl'){

			$arrResponse=Admin::CurlPost($strURL, $arrPostData, $stdClass);
			if($arrResponse['error']){
				$intError		=true;
				$strMessage	=$arrResponse['message'];
			}else{
				$strResponse=$arrResponse['response'];
			}

		}

		$stdResponse=Extend::DecodeJSON($strResponse);

		$arrResult=array(
			'error'		=>$intError, 
			'message'	=>$strMessage, 
			'json'		=>$strResponse, 
			'data'		=>$stdResponse, 
		);

		return $arrResult;

	}

	public static function CurlPost($strURL, $arrPostData, $stdClass){

		$arrResponse=array(
			'error'		=>false, 
			'message'	=>'', 
			'response'=>false, 
		);

		$ch=curl_init();

		curl_setopt($ch, CURLOPT_URL, $strURL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arrPostData));

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json', 
			'x-api-key: '.$stdClass->partner_key));

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$arrResponse['response']=curl_exec($ch);

		if(curl_errno($ch)){
			/*
			$intError		=true;
			$strMessage	=curl_error($ch);
			*/
			$arrResponse['error']		=true;
			$arrResponse['message']	=curl_error($ch);

		}

		curl_close($ch);

		return $arrResponse;

	}

	public static function AddMetaBox(){

		if(!isset($_GET['post']))return;

		$intPostID	=$_GET['post'];
		$stdOrder		=wc_get_order($intPostID);

		if(!$stdOrder)return;

		$strPaymentID=$stdOrder->get_payment_method();

		if(!$stdOrder||$strPaymentID!=Handler::ID)return;

		add_meta_box(Handler::ID.'_query-status', 'TapPay 扣款狀態', __CLASS__.'::Metabox', 'shop_order', 'side', 'default');

		/*
		 * 訂單一定要是 renewal order 才可手動觸發扣款
		 */
		if(!class_exists('WC_Subscriptions_Order'))return;
		$stdSubscription=Subscription::OrderFromRenewal($stdOrder);
		if(!$stdSubscription)return;

		add_meta_box(Handler::ID.'_manual-renew', 'TapPay 手動扣款', __CLASS__.'::Metabox', 'shop_order', 'side', 'default');

	}

	public static function MetaBox($stdPost, $arrParam){

		//$intPostID	=$_GET['post'];
		$intPostID	=$stdPost->ID;
		$stdOrder		=wc_get_order($intPostID);

		if($arrParam['id']==Handler::ID.'_manual-renew'){
			Admin::ManualPayment($stdOrder, $intPostID);

		}elseif($arrParam['id']==Handler::ID.'_query-status'){
			Admin::QueryStatusField($stdOrder, $intPostID);
		}
	}

	/*
	 * v1.2 2020.06.06
	 */
	public static function QueryStatus(){

		$intOrderID		=$_POST['order_id'];
		$stdOrder			=wc_get_order($intOrderID);
		$strOrderNote	='TapPay 查詢交易';

		$arrStatus=array(
			'0'=>'授權', 
			'1'=>'請款', 
			'3'=>'退款', 
			'4'=>'待付款', 
			'5'=>'取消', 
			'6'=>'取消退款', 
		);

		$stdClass			=new WC_Gateway_TapPay();
		$strPostMeta	=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);

		if($stdOrder&&$strPostMeta){
			$stdResult=Extend::DecodeJSON($strPostMeta);

			if(strpos($stdResult->order_number, $intOrderID.'00')===0){

				$stdData=Basic::CheckTradeID($stdResult->rec_trade_id, $stdClass, true); // true for full information

				if(is_a($stdData, 'stdClass')&&property_exists($stdData, 'trade_history')){

					$arrTradeHistory	=$stdData->trade_history;
					$strCurrency			=Get::Currency($stdData->currency);

					if($stdData->status=='0'){
						$strOrderNote.='成功';
					}else{
						$strOrderNote.='失敗: '.$stdData->msg;
					}
					$strOrderNote.="\r\n";

					if(is_array($arrTradeHistory)){

						/*
						 * action
						 * -	0 授權
						 * -	1 請款
						 * -	3 退款
						 * -	4 待付款
						 * -	5 取消
						 * -	6 取消退款
						 */

						$strOrderNote.='交易編號: '.$stdData->rec_trade_id."\r\n";
						$strOrderNote.='幣別: '.$strCurrency."\r\n";

						foreach($arrTradeHistory as $key=>$value){

							$intIndex=$key+1;
							if($intIndex>1){
								$strOrderNote.="\r\n";
							}

							$strOrderNote.='交易紀錄['.$intIndex.']'."\r\n";

							$intTimestamp=(int)$value->millis/1000;
							$strDate=date('Y-m-d H:i:s', $intTimestamp+3600*8);

							$strOrderNote.='- 交易時間: '.$strDate."\r\n";
							$strOrderNote.='- 交易狀態: ';

							$strStatus=array_key_exists($value->action, $arrStatus)?$arrStatus[$value->action]:'未知狀態['.$value->action.']';

							$strOrderNote.=$strStatus."\r\n".'- 交易金額: '.$value->amount;
						}
					}

				}else{
					$strOrderNote.='無任何資料[1]';
				}

			}else{
				$strOrderNote.='無交易 ID[1]';
			}

			$stdOrder->add_order_note(trim($strOrderNote));

		}


		$arrResult=array('result'=>$strOrderNote);
		wp_send_json($arrResult);
	}

	public static function QueryStatusField($stdOrder, $intPostID){
		echo '<a href="#" data-id="'.$intPostID.'" class="button button-primary" onclick="CWTAPPAY.QueryStatus(this); return false;">查詢 TapPay 扣款狀態</a>';
	}

	public static function ManualPayment($stdOrder, $intPostID){

		$stdSubscription=Subscription::OrderFromRenewal($stdOrder);

		$strStatus=$stdSubscription->get_status();

		if($strStatus=='on-hold'){
			echo '<a href="#" data-id="'.$intPostID.'" class="button button-primary" onclick="CWTAPPAY.ManualRenew(this); return false;">手動扣款</a>';

		}else{
			$strStatus=$stdOrder->get_status();

			if(in_array($strStatus, array('pending', 'on-hold'))){
				echo '<a href="#" data-id="'.$intPostID.'" class="button button-primary" onclick="CWTAPPAY.ManualRenew(this); return false;">手動扣款</a>';

			}else{
				echo '<p>此訂單無法手動扣款</p>';
			}
		}
	}

	public static function SiteURL(){
		$strSiteURL=get_site_url();
		preg_match('@^(https?:\/\/)([^\/]+)@', $strSiteURL, $match);
		if(count($match)>0){
			$strSiteURL=$match[1].$match[2];
		}
		return $strSiteURL;
	}

	public static function AdditionalUpdateMessages($arrData, $stdResponse){
		if(!isset($arrData['upgrade_notice'])||empty($arrData['upgrade_notice']))return;
		echo '<span id="'.Handler::ID.'_update-message">'.$arrData['upgrade_notice'].'</span>';
	}

	public static function AfterSuccessPayment($order, $stdResult, $stdClass, $strAdditionText=' '){

		$strExpiryDate='';

		if(isset($stdResult->card_info->expiry_date)){
			$arrExpiryDate=Get::ExpiryDate($stdResult->card_info->expiry_date);
			$strExpiryDate='
卡片到期時間: '.$arrExpiryDate['year'].' 年 '.$arrExpiryDate['month'].' 月';
		}

		$strDealTime=date('Y-m-d H:i:s', intval(preg_replace('@[^0-9]@', '', $stdResult->transaction_time_millis)/1000)+8*3600);
		$strCardType=Get::CardType($stdResult->card_info->type);

		$order->payment_complete($stdResult->rec_trade_id);

		/*
		 * $stdResult->auth_code 銀行授權碼若是空的，則代表該訂單在 TapPay 後台為 pending
		 * 通常是因為訂閱商品產生 renewal order -> Pay by token，但扣款當下 3D 驗證無法使用
		 */

		/*
		 * 執行第二次以上的 payment_url 不會回傳 $stdResult->auth_code
		 */
		$order->add_order_note('TapPay'.$strAdditionText.'刷卡成功
銀行端訂單編號: '.$stdResult->bank_transaction_id.'
付款金額: '.$stdResult->amount.'
卡片前六碼: '.$stdResult->card_info->bin_code.'
卡片後四碼: '.$stdResult->card_info->last_four.'
發卡銀行: '.$stdResult->card_info->issuer.'
卡片種類: '.$strCardType.'
發卡行國家: '.$stdResult->card_info->country.$strExpiryDate.'
發卡行國家碼: '.$stdResult->acquirer.'
交易時間: '.$strDealTime);

		do_action(Handler::ID.'_after-success-payment', $order, $stdResult, $stdClass); // 訂單可能是 free-trial，取得 token 後需退款

		/* 
		 * status[1]
		 * apply_filters('woocommerce_payment_complete_order_status', 'processing|completed', $intOrderID, $stdOrder)
		 *  ↓
		 * $stdOrder->add_order_note
		 *  ↓
		 * status[2]
		 * $stdOrder->update_status
		 */
		if($stdClass->status_change!=$order->get_status())$order->update_status($stdClass->status_change);

	}

	public static function UserTokens($intUserID){

		$arrResult=array();

		$arrTokens=\WC_Payment_Tokens::get_customer_tokens($intUserID, Handler::ID);

		foreach($arrTokens as $key=>$value){
			$arrData=$value->get_data();
			$arrResult[$key]=array(
				'token'					=>$arrData['token'], 
				'is_default'		=>$arrData['is_default'], 
				'type'					=>$arrData['type'], 
				'last4'					=>$arrData['last4'], 
				'expiry_year'		=>$arrData['expiry_year'], 
				'expiry_month'	=>$arrData['expiry_month'], 
				'card_type'			=>$arrData['card_type'], 
			);
		}

		return $arrResult;
	}

	public static function MaybeSaveCard($stdResult, $intUserID, $intRemember){
		if(isset($_POST['wc-'.Handler::ID.'-payment-token'])&&$_POST['wc-'.Handler::ID.'-payment-token']!='new')return;
		if(!$intUserID||!class_exists('WC_Payment_Token_CC')||$intRemember!='true')return;
		self::SaveCard($stdResult, $intUserID);
	}

	public static function SaveCard($stdResult, $intUserID){

		if(!is_a($stdResult, 'stdClass')||!property_exists($stdResult, 'card_secret')||!is_a($stdResult->card_secret, 'stdClass'))return;
		if(!property_exists($stdResult->card_secret, 'card_key')||!property_exists($stdResult->card_secret, 'card_token'))return;

		$strToken		=$stdResult->card_secret->card_key.':::'.$stdResult->card_secret->card_token;

		$strCardType		=Get::CardName($stdResult->card_info->type);
		$arrExpiryDate	=Get::ExpiryDate($stdResult->card_info->expiry_date);
		$stdToken				=new \WC_Payment_Token_CC();

		$stdToken->set_token($strToken);
		$stdToken->set_gateway_id(Handler::ID);
		$stdToken->set_card_type($strCardType);
		$stdToken->set_last4($stdResult->card_info->last_four);
		$stdToken->set_expiry_year($arrExpiryDate['year']);
		$stdToken->set_expiry_month($arrExpiryDate['month']);
		$stdToken->set_user_id($intUserID);

		$stdToken->save();
	}

	public static function CancelAuthorized($intOrderID, $stdClass, $stdPostMeta, $stdOrder=false){

		$stdClass->type='refund';

		if($stdOrder){
			$strAmount=$stdOrder->get_total();
			$strAmount=apply_filters(Handler::ID.'_refund-amount', $strAmount, $stdOrder, $stdClass);

		}else{
			$strAmount=self::TestAmount;
		}

		$arrVariables=Get::PaymentVariables($stdClass, $stdOrder, $intOrderID);
		extract($arrVariables);

		if($strSandBox=='yes'){
			$strURL='https://sandbox.tappaysdk.com/tpc/transaction/refund';
		}else{
			$strURL='https://prod.tappaysdk.com/tpc/transaction/refund';
		}

		$arrPostData=array(
			'partner_key'		=>$stdClass->partner_key, 
			'amount'				=>$strAmount, 
			'rec_trade_id'	=>$stdPostMeta->rec_trade_id);

		$strResult=CheckoutProcess::DoPayment($arrPostData, $strURL, $stdClass->partner_key, $stdClass, $stdOrder);

		$stdResult=Extend::DecodeJSON($strResult);

		if($stdOrder){
			if($stdResult->status=='0'){
				if(strlen($stdResult->is_captured)===0){
					$stdResult->is_captured='未請款';
				}else{
					$stdResult->is_captured='已請款';
				}

				$stdOrder->add_order_note('
					TapPay 退刷成功
					退刷金額: '.$stdResult->refund_amount.'
					請款狀態: '.$stdResult->is_captured);

				// self::RestoreStock($intOrderID); // 2020.12.14 有訂單編號的退刷不一定要恢復庫存，例如 free trial、zero amount

			}else{
				$stdOrder->add_order_note(
					'TapPay 退刷失敗
					錯誤代號: '.$stdResult->status.'
					錯誤訊息: '.$stdResult->msg);

				update_post_meta($intOrderID, '_'.Handler::ID.'_refund-lock', '0');

			}
		}

		return $strResult;
	}

	public static function RestoreStock($intOrderID){

		$stdOrder=wc_get_order($intOrderID);
		if(!$stdOrder)return;

		if((!get_option('woocommerce_manage_stock')=='yes'&&count($stdOrder->get_items())===0)||$stdOrder->get_payment_method()!=Handler::ID)return;

		$intIncreased=get_post_meta($intOrderID, '_'.Handler::ID.'_restore-stock', true);
		if($intIncreased)return;

		foreach($stdOrder->get_items() as $value){

			if($value['product_id']>0){

				$stdProduct=$stdOrder->get_product_from_item($value);

				if($stdProduct&&$stdProduct->exists()&&$stdProduct->managing_stock()){

					$intStock			=apply_filters('woocommerce_order_item_quantity', $value['qty'], $stdOrder, $value);
					$intOldStock	=$stdProduct->get_stock_quantity();

					/*===The WC_Product::increase_stock function is deprecated since version 3.0. Replace with wc_update_product_stock.===*/
					if(WC()->version<'3'){
						$intNewStock=$stdProduct->increase_stock($intStock);
					}else{
						$arrData=$value->get_data();

						$intStockChange=apply_filters('woocommerce_restore_order_stock_quantity', $arrData['quantity'], $arrData['id']); // parameters: item quantity, item id
						$intNewStock=wc_update_product_stock($stdProduct, $intStockChange, 'increase');
					}

					$strItemName=$stdProduct->get_sku()?$stdProduct->get_sku():$stdProduct->get_id();

					$stdOrder->add_order_note(sprintf(__('Item #%1$s %2$s stock increased from %3$s to %4$s.', 'woocommerce'), $strItemName, $value['name'], $intOldStock, $intNewStock));

					/*===The WC_Order::send_stock_notifications function is deprecated since version 3.0.===*/
					if(WC()->version<'3'){
						$stdOrder->send_stock_notifications($stdProduct, $intNewStock, $value['qty']);
					}
				}
			}
		}

		update_post_meta($intOrderID, '_'.Handler::ID.'_restore-stock', 1);
		update_post_meta($intOrderID, '_order_stock_reduced', 'no');

	}

	public static function AddAdminScripts(){
		wp_register_script(Handler::ID.'-admin-script', CWTAPPAY_URL.'js/cw-tappay_admin.js?t='.time(), array('jquery'));
		wp_enqueue_script(Handler::ID.'-admin-script');

		wp_localize_script(	
			Handler::ID.'-admin-script', 
			'CWTAPPAY_vars', 
			array(
				'handler_id'	=>Handler::ID, 
				'ajaxurl'			=>admin_url('admin-ajax.php')));
	}

	public static function AddAdminStyles(){
		wp_register_style(Handler::ID.'-admin-style', CWTAPPAY_URL.'css/cw-tappay_admin.css');
		wp_enqueue_style(Handler::ID.'-admin-style');
	}
}