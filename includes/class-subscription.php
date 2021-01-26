<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

class Subscription{

	function __construct(){}

	public static function IsRenewal($intOrderID){

		/*
		 * 判斷 $intOrderID 是否為 renewal order
		 * 如果回傳空的陣列，代表不是 renewal order
		 * 若是 renewal order 則回傳 [8374] => WC_Subscription Object
		 * 8374 是 subscription id
		 */
		if(class_exists('WC_Subscriptions_Order')){
			$arrSubscription=wcs_get_subscriptions_for_renewal_order($intOrderID);
			if(!empty($arrSubscription))return true;
		}
		return false;
	}

	public static function OrderType($order){
		$strType='simple';
		$stdOrder=is_numeric($order)?wc_get_order($order):$order;

		if(class_exists('WC_Subscriptions_Order')){
			if(is_a($stdOrder, 'WC_Subscription')){
				$strType='subscription';

			}else{
				$arrMetaData=$stdOrder->get_meta_data();
				foreach($arrMetaData as $key=>$value){
					$arrData=$value->get_data();
					if(isset($arrData['key'])&&$arrData['key']=='_subscription_renewal'){
						$strType='renewal';
						break;
					}
				}

				if($strType=='simple'){
					if(!empty(wcs_get_subscriptions_for_order($stdOrder)))$strType='parent';
				}
			}
		}

		return $strType;
	}

	public static function Init(){
		if(class_exists('WC_Subscriptions_Order')){
			add_filter('woocommerce_scheduled_subscription_payment_'.Handler::ID,	__CLASS__.'::RenewalOrderPayment', 10, 2);

			//add_filter(Handler::ID.'_remember', __CLASS__.'::RememberCard', 999, 3);

			/*===Free trial payment===*/
			add_action(Handler::ID.'_after-success-payment',	__CLASS__.'::SuccessFreeTrialPayment', 10, 3);
			add_filter(Handler::ID.'_refund-amount',					__CLASS__.'::RefundFreeTrialAmount', 999, 3);
			add_filter(Handler::ID.'_check-payment-data',			__CLASS__.'::MaybeFreeTrialPayment', 10, 4);
			/*===Free trial payment===*/
		}
	}

	/*
	public static function RememberCard($intRemember, $intOrderID, $stdClass){
		error_log(print_r($intOrderID, true));
		throw new \Exception(111);
		return $intRemember;
	}
	*/

	public static function RefundFreeTrialAmount($strAmount, $stdOrder, $stdClass){ // 2020.12.13 Free trial 授權後要執行退款

		$intFreeTrial=Subscription::FreeTrialProduct($stdOrder); // intFreeTrial 等於 true 的情況下金額一定是 0

		if($intFreeTrial){
			$strAmount=Admin::TestAmount;
		}
		
		return $strAmount;
	}

	public static function MaybeFreeTrialPayment($arrPostData, $strType, $stdOrder, $stdClass){

		$intFreeTrial=Subscription::FreeTrialProduct($stdOrder); // intFreeTrial 等於 true 的情況下金額一定是 0

		if($intFreeTrial){
			$arrPostData['amount']=Admin::TestAmount;
			//if($strType=='prime')$arrPostData['remember']='true';
		}

		return $arrPostData;
	}

	public static function SuccessFreeTrialPayment($stdOrder, $stdResult, $stdClass){

		$intOrderID=Get::OrderID($stdOrder);
		if(!$intOrderID)return;

		$intFreeTrial=Subscription::FreeTrialProduct($stdOrder); // intFreeTrial 等於 true 的情況下金額一定是 0				

		if($intFreeTrial){

			$intUserID=$stdOrder->get_user_id();

			$strResult=Extend::CancelAuthorized($intOrderID, $stdOrder, $stdClass);
			$stdResult=Extend::DecodeJSON($strResult);

			if($stdResult->status=='0'){
				
			}else{
				if(!isset($_SERVER['WTSERVER'])||$_SERVER['WTSERVER']!='windows'){
					ini_set('log_errors', 'On');
					ini_set('display_errors', 'Off');
					ini_set('error_log', dirname(__FILE__).'/error_log.log');
				}
				error_log(print_r('TapPay debug by JanaLEE[2]', true));
				error_log(print_r($intOrderID, true));
				error_log(print_r($_POST, true));
				error_log(print_r($stdResult, true));
			}
		}
	}

	public static function FreeTrialProduct($order){ // 只針對訂閱商品

		$intFreeTrial		=false;
		$intOrderTotal	=$order->get_total();

		if((float)$intOrderTotal==0){ // 訂單總計為 0，可能是 free trial 或是折價券全額抵用

			$arrOrderItems=$order->get_items();

			foreach($arrOrderItems as $key=>$value){ // value: stdOrderItem

				/*
				 * 2020.12.13
				 * woocommerce-subscriptions/includes/class-wc-subscriptions-product.php
				 * \WC_Subscriptions_Product::get_trial_length($value->get_product())
				 * 返回 free trial 的天數，無 free trial 則回傳 0
				 */
				$intFreeTrial=\WC_Subscriptions_Product::get_trial_length($value->get_product());
				if((int)$intFreeTrial>0)break;
			}
		}

		return $intFreeTrial;
	}

	public static function ManualRenew(){

		$intStatus=true;

		$stdRenewalOrder=wc_get_order($_POST['order_id']);
		if(!$stdRenewalOrder)$intStatus=false;

		if($intStatus){
			$intRenewalTotal=$stdRenewalOrder->get_total();
			Subscription::RenewalOrderPayment($intRenewalTotal, $stdRenewalOrder);
		}

		wp_send_json(array('a'=>'b'));
	}

	/*
	 * 1.2
	 * Return: Subscription or false
	 */
	public static function OrderFromRenewal($stdOrder){

		if(!class_exists('WC_Subscriptions_Order'))return false;

		$arrSubscription=wcs_get_subscriptions_for_order($stdOrder, array('order_type'=>array('renewal')));
		if(is_array($arrSubscription)&&count($arrSubscription)>0)return current($arrSubscription);
		return false;
	}

	public static function RenewalOrderPayment($intRenewalTotal, $stdRenewalOrder){

		/*
		 * 2019.05.09
		 * v1.2.1*
		 * 新增判斷訂閱訂單是否為可扣款狀態 ( on-hold )
		 */

		$stdSubscription=Subscription::OrderFromRenewal($stdRenewalOrder);

		$stdClass		=new WC_Gateway_TapPay;
		$intUserID	=$stdRenewalOrder->get_user_id();
		$arrTokens	=\WC_Payment_Tokens::get_customer_tokens($intUserID, Handler::ID);

		if(!$arrTokens||count($arrTokens)===0){

			if(!isset($_SERVER['WTSERVER'])||$_SERVER['WTSERVER']!='windows'){
				ini_set('log_errors', 'On');
				ini_set('display_errors', 'Off');
				ini_set('error_log', dirname(__FILE__).'/error_log.log');
			}

			error_log(print_r('$stdRenewalOrder->get_id()[1]', true));
			error_log(print_r($stdRenewalOrder->get_id(), true));

			error_log(print_r('$arrTokens[1]', true));
			error_log(print_r($arrTokens, true));

			// 無已記錄的 token 資料
			$stdRenewalOrder->add_order_note('TapPay 訂閱扣款失敗
無已記錄的 token 資料[2]');
			return;
		}

		$stdDefaultToken=Get::DefaultToken($stdRenewalOrder, $arrTokens, $intUserID);

		$arrData=$stdDefaultToken->get_data();
		$arrTokensData[]=$arrData; // 將預設 token 放在陣列第一個

		foreach($arrTokens as $key=>$value){
			$arrTokenData=$value->get_data();
			if($arrData==$arrTokenData)continue; // 避開已存在的 token data

			$arrTokensData[]=$arrTokenData;
		}

		return self::DoRenewalPayment($arrTokensData, $intRenewalTotal, $stdRenewalOrder, $stdClass);
	}

	public static function DoRenewalPayment($arrTokensData, $intRenewalTotal, $stdRenewalOrder, $stdClass){

		$intKey=key($arrTokensData);
		$arrData=current($arrTokensData);
		preg_match('@^(.*?):::(.*?)$@', $arrData['token'], $match);

		if(count($match)===0){
			// 無已記錄的 token 資料
			$stdRenewalOrder->add_order_note('TapPay 訂閱扣款失敗
無已記錄的 token 資料[1]');
			return;
		}

		$strCardKey		=$match[1];
		$strCardToken	=$match[2];

		$intRenewalTotal=(int)$stdRenewalOrder->get_total();
		$intRenewalTotal=apply_filters(Handler::ID.'_amount', $intRenewalTotal, $stdRenewalOrder, $stdClass);

		$intOrderID=Get::OrderID($stdRenewalOrder);

		/*
		 * 2020.05.22
		 * Subscription 的 post meta 中的 _cwtpfw-return 會在生成 renewal order 時一併被複製到 renewal order 的 post meta
		 * 造成 TapPay 外掛誤判此訂單已有付款，所以在每次的 renewal order 扣款時都要先清除 _cwtpfw-return
		 */
		$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
		if($strResponse){
			update_post_meta($intOrderID, '_'.Handler::ID.'-return', NULL);
		}

		$arrVariables=Get::PaymentVariables($stdClass, $stdRenewalOrder, $intOrderID);
		extract($arrVariables);

		if($strSandBox=='yes'){
			$strURL='https://sandbox.tappaysdk.com/tpc/payment/pay-by-token';
		}else{
			$strURL='https://prod.tappaysdk.com/tpc/payment/pay-by-token';
		}

		$arrPostData=array(
			'card_key'			=>$strCardKey, 
			'card_token'		=>$strCardToken, 
			'partner_key'		=>$stdClass->partner_key, 
			'merchant_id'		=>$strMerchantID, 
			'amount'				=>$intRenewalTotal, 
			'currency'			=>'TWD', 
			'order_number'	=>$strOrderNumber, 
			'details'				=>$strDetails);

		// 2020.04.29 3D 驗證目前無法用在 renewal order
		/*
		if($stdClass->three_domain==='yes'){
			$strSiteURL=Admin::SiteURL();
			$arrPostData['three_domain_secure']=true;
			$arrPostData['result_url']=array(
				'frontend_redirect_url'	=>$strSiteURL.'/cwtpfw/3d-secure/checkout?order_id='.$intOrderID, 
				'backend_notify_url'		=>$strSiteURL.'/cwtpfw/3d-secure/notify?order_id='.$intOrderID);
		}
		*/

		$strResponse=CheckoutProcess::DoPayment($arrPostData, $strURL, $arrPostData['partner_key'], $stdClass, $stdRenewalOrder);
		$stdResponse=Extend::DecodeJSON($strResponse);

		if($stdResponse->status=='0'){
			$intOrderID=Get::OrderID($stdRenewalOrder);
			CheckoutProcess::PaymentComplete($intOrderID, $stdRenewalOrder, $stdClass);
			Admin::AfterSuccessPayment($stdRenewalOrder, $stdResponse, $stdClass, '訂閱扣款');

		}else{

			$strLastFour=$arrData['last4'];

			$strOrderNote='TapPay 訂閱扣款失敗
卡片末四碼: '.$strLastFour.'
錯誤代碼: '.$stdResponse->status.'
錯誤訊息: '.$stdResponse->msg;

			if(isset($stdResponse->rec_trade_id))			$strOrderNote.="\r\n".'交易編號: '.$stdResponse->rec_trade_id;
			if(isset($stdResponse->bank_result_code))	$strOrderNote.="\r\n".'銀行回傳代碼: '.$stdResponse->bank_result_code;
			if(isset($stdResponse->bank_result_msg))	$strOrderNote.="\r\n".'銀行回傳訊息: '.$stdResponse->bank_result_msg;

			$stdRenewalOrder->add_order_note($strOrderNote);

			if('yes'===$stdClass->try_next){
				unset($arrTokensData[$intKey]);

				if(count($arrTokensData)>0){
					self::DoRenewalPayment($arrTokensData, $intRenewalTotal, $stdRenewalOrder, $stdClass);
				}else{
					$stdRenewalOrder->add_order_note('TapPay 訂閱扣款失敗
無已記錄的 token 資料[3]');
				}
			}
		}
	}
}