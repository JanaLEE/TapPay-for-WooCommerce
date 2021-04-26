<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

if(!defined('ABSPATH'))exit();

class CheckoutProcess{
	function __construct(){}

	public static function CheckPrimeData($intOrderID, $order){

		$intError=0;

		if($intError===0){
			$strResult=urldecode($_POST[Handler::ID.'_result']);
			$stdResult=Extend::DecodeJSON($strResult);

			if(!isset($stdResult->status)){
				$intError=2;

			}else{
				if($stdResult->status=='0'){
					$arrData=(array)$stdResult;
					$arrData['orderid']		=apply_filters(Handler::ID.'_checkout_order_id', $intOrderID, $order);
					$arrData['remember']	=isset($_POST[Handler::ID.'_remember'])?$_POST[Handler::ID.'_remember']:0;

					update_post_meta($intOrderID, '_'.Handler::ID.'_post-data', $arrData);

				}else{

					throw new \Exception('[1]TapPay: '.$stdResult->msg);

				}
			}
		}

		if($intError>0)Get::ErrorMessage($intError);
	}

	public static function PayByToken($intOrderID, $order, $stdClass, $intPaymentToken){

		$intUserID=$order->get_user_id();

		$arrTokens=\WC_Payment_Tokens::get_customer_tokens($intUserID, Handler::ID);

		if(!$arrTokens||count($arrTokens)===0){
			Get::ErrorMessage(4); // 無已記錄的 token 資料
			return;
		}

		$stdToken=$arrTokens[$intPaymentToken];
		$arrData=$stdToken->get_data();

		preg_match('@^(.*?):::(.*?)$@', $arrData['token'], $match);

		if(count($match)===0){
			Get::ErrorMessage(4); // 無已記錄的 token 資料
			return;
		}

		$strCardKey		=$match[1];
		$strCardToken	=$match[2];

		$strAmount=(int)$order->get_total();
		$strAmount=apply_filters(Handler::ID.'_amount', $strAmount, $order, $stdClass);

		$arrVariables=Get::PaymentVariables($stdClass, $order, $intOrderID);
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
			'amount'				=>$strAmount, 
			'currency'			=>'TWD', 
			'order_number'	=>$strOrderNumber, 
			//'instalment'		=>3, // 1.3 instalment
			'details'				=>$strDetails, 
		);

		if($stdClass->three_domain==='yes'){ // 3D 驗證必要欄位

			$strSiteURL=Admin::SiteURL();

			$arrPostData['three_domain_secure']=true;
			$arrPostData['result_url']=array(
				'frontend_redirect_url'	=>$strSiteURL.'/cwtpfw/3d-secure/checkout?order_id='.$intOrderID, 
				'backend_notify_url'		=>$strSiteURL.'/cwtpfw/3d-secure/notify?order_id='.$intOrderID);
		}

		$arrPostData=apply_filters(Handler::ID.'_check-payment-data', $arrPostData, 'token', $order, $stdClass);

		//return self::DoPayment($arrPostData, $strURL, $arrPostData['partner_key'], $order);
		$strResponse=self::DoPayment($arrPostData, $strURL, $arrPostData['partner_key'], $stdClass, $order);

		$stdResponse=Extend::DecodeJSON($strResponse);

		//do_action(Handler::ID.'_after-success-payment', $order, $stdResponse);
		if($stdResponse->status=='0'){

			if($order){
				$intUserID	=$order->get_user_id();
				$intOrderID	=Get::OrderID($order);

				if(isset($stdResponse->payment_url))update_post_meta($intOrderID, '_'.Handler::ID.'-3dsecure-url', $stdResponse->payment_url);

				if($stdClass->three_domain==='yes'){ // 3D 驗證必要欄位
					
				}else{
					//do_action(Handler::ID.'_after-success-payment', $order, $stdResponse); // 訂單可能是 free-trial，取得 token 後需退款
				}

			}

		}else{
			if(strpos($stdResponse->msg, 'cURL error 28:')===0||strpos($stdResponse->msg, 'cURL Error:')===0)$stdResponse->msg='連線逾時，請再試一次';
			if($order)throw new \Exception('[2]TapPay: '.$stdResponse->msg);
		}

		return $strResponse;

	}

	public static function PayByPrime($intOrderID, $order, $stdClass){

		$arrData=get_post_meta($intOrderID, '_'.Handler::ID.'_post-data', true);

		$intRemember=$arrData['remember']==='1'?'true':'false';

		if(class_exists('WC_Subscriptions_Cart')){ // 結帳的當下無法判斷是否為 subscription order，因此判斷購物車
			$intSubscription=\WC_Subscriptions_Cart::cart_contains_subscription();
			if($intSubscription)$intRemember='true';
		}

		//$intRemember=apply_filters(Handler::ID.'_remember', $intRemember, $intOrderID, $stdClass); // remember true|false 透過 cwtpfw_check-payment-data 處理

		$stdCardInfo		=$arrData['card'];
		$arrCardHolder	=Get::CardHolder($order);

		$strAmount=$order->get_total();
		$strAmount=apply_filters(Handler::ID.'_amount', $strAmount, $order, $stdClass);

		$arrVariables=Get::PaymentVariables($stdClass, $order, $arrData['orderid']);
		extract($arrVariables);

		if($strSandBox=='yes'){
			$strURL='https://sandbox.tappaysdk.com/tpc/payment/pay-by-prime';
		}else{
			$strURL='https://prod.tappaysdk.com/tpc/payment/pay-by-prime';
		}

		$arrPostData=array(
			'prime'					=>$stdCardInfo->prime, 
			'partner_key'		=>$stdClass->partner_key, 
			'merchant_id'		=>$strMerchantID, 
			'amount'				=>$strAmount, 
			'currency'			=>'TWD', 
			'order_number'	=>$strOrderNumber, 
			'details'				=>$strDetails, 
			'cardholder'		=>$arrCardHolder, 
			//'instalment'		=>3, // 1.3 instalment
			'remember'			=>$intRemember);

		if($stdClass->three_domain==='yes'){

			$strSiteURL=Admin::SiteURL();

			$arrPostData['three_domain_secure']=true;
			$arrPostData['result_url']=array(
				'frontend_redirect_url'	=>$strSiteURL.'/cwtpfw/3d-secure/checkout?order_id='.$intOrderID, 
				'backend_notify_url'		=>$strSiteURL.'/cwtpfw/3d-secure/notify?order_id='.$intOrderID);
		}

		$arrPostData=apply_filters(Handler::ID.'_check-payment-data', $arrPostData, 'prime', $order, $stdClass);

		//return self::DoPayment($arrPostData, $strURL, $arrPostData['partner_key'], $order);
		$strResponse=self::DoPayment($arrPostData, $strURL, $arrPostData['partner_key'], $stdClass, $order);
		$stdResponse=Extend::DecodeJSON($strResponse);

		if($stdResponse->status=='0'){

			if($order){
				$intUserID	=$order->get_user_id();
				$intOrderID	=Get::OrderID($order);

				if(isset($stdResponse->payment_url))update_post_meta($intOrderID, '_'.Handler::ID.'-3dsecure-url', $stdResponse->payment_url);

				Admin::MaybeSaveCard($stdResponse, $intUserID, $intRemember);

				if($stdClass->three_domain==='yes'){ // 3D 驗證必要欄位
					
				}else{
					//do_action(Handler::ID.'_after-success-payment', $order, $stdResponse); // 訂單可能是 free-trial，取得 token 後需退款
				}
			}

		}else{

			/*
			 * 2021.01.27
			 * 新增銀行回傳訊息到 Exception 中
			 * 用於前台顯示
			 */

			$strMessage=$stdResponse->msg;
			if(property_exists($stdResponse, 'bank_result_msg'))$strMessage.=' '.$stdResponse->bank_result_msg;

			if(strpos($strMessage, 'cURL error 28:')===0||strpos($strMessage, 'cURL Error:')===0)$strMessage='連線逾時，請再試一次';
			if($order)throw new \Exception('[3]TapPay: '.$strMessage, $stdResponse->status);

		}

		return $strResponse;
	}

	/*
	 * 2020.12.13
	 * 非 free trial 的訂單若使用折價券全額折抵，金額為 0
	 * TapPay 會出現 Invalid arguments : amount 的錯誤訊息
	 * 所以需要扣款 1 元並立即退款
	 *
	 * 不能用 Action::AddCard，因為 Action::AddCard 無法將 token 加入訂單編號
	 * 而且 Action::AddCard 還需要 post 持卡人姓名、手機、電子郵件等必填資料
	 */
	public static function DoPayment($arrPostData, $strURL, $strPartnerKey, $stdClass, $stdOrder=false){

		/*
		 * v1.1.2 註記 2020.03.30
		 * 先檢查 -return 避免造成重複扣款
		 */
		$arrResult	=array();
		$intOrderID	=Get::OrderID($stdOrder);

		/*
		 * 手動針對 renewal order 扣款不檢查 post meta 的 return
		 */
		//$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
		$strResponse=false;
		if(isset($_POST['type'])&&$_POST['type']=='manual-renew'){
			
		}else{
			$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
		}

		$strType='pay';
		if(property_exists($stdClass, 'type')){
			$strType=$stdClass->type;
		}

		/*
		 * v1.2.1 2021.03.08
		 * 先判斷先前是否付款成功，若付款成功才直接回傳 response
		 */
		$stdResponse=false;
		if($strResponse){
			$stdResponse=Extend::DecodeJSON($strResponse);
		}

		// 2020.04.08
		//if($strResponse)return $strResponse;
		if($strResponse&&is_a($stdResponse, 'stdClass')&&property_exists($stdResponse, 'status')&&$stdResponse->status=='0'){ // 付款資料已存在

			if($strType=='pay'){
				$stdResponse=Extend::DecodeJSON($strResponse);

			}elseif($strType=='refund'){
				$arrResult=Admin::RemotePost($strURL, $arrPostData, $stdClass);


				$stdResponse=$arrResult['data']; // 'data': json object
				$strResponse=$arrResult['json']; // 'json': json string
			}

		}else{

			/*
			 * 2020.12.13
			 * 
			 * 最後確認 post 參數中的 amount 值
			 * 如果是 0 ( free trial 的值已被 filter cwtpfw_check-payment-data 改為 5 元 )
			 * amount 要改為 1 並在授權後立刻退款
			 */
			if($strType=='pay'){
				if((int)$arrPostData['amount']===0){
					$arrPostData['amount']=1;
					update_post_meta($intOrderID, '_'.Handler::ID.'-zero-amount', $arrPostData['amount']); // 2020.12.13 訂單金額如果是 0 無法授權，此參數用來驗證授權 ( 1 元 ) 後是否立刻退款
				}
			}

			$arrResult=Admin::RemotePost($strURL, $arrPostData, $stdClass);

			if(array_key_exists('message', $arrResult)&&!empty($arrResult['message'])){
				return json_encode(
					array(
						'status'	=>'-999999', 
						'msg'			=>$arrResult['message'], 
					)
				);
			}

			$stdResponse=$arrResult['data']; // 'data': json object
			$strResponse=$arrResult['json']; // 'json': json string

		}

		if($stdResponse->status=='0'){
			if($stdOrder){

				if($strType=='refund'){
					update_post_meta($intOrderID, '_'.Handler::ID.'-refund', $strResponse);

				}else{

					/* 
					 * Parent order 的 post meta 會被複製一份到 subscription order 的 post meta
					 * 所以 subscription 也會有 '_cwtpfw-return'
					 * 如果是 pay by tokem，需要複製一份 card_secret 合併到 $strResponse
					 */
					$strResponse=apply_filters(Handler::ID.'_update-tappay-return', $strResponse, $arrPostData, $stdOrder, $stdClass);
					update_post_meta($intOrderID, '_'.Handler::ID.'-return', $strResponse);

					/*
					 * v1.1.2 註記 2020.03.30
					 * 有外掛造成 PHP Fatal error 後，導致 TapPay 雖已扣款但回傳無法紀錄
					 * 因此在 TapPay 一回傳馬上處理 order note 等 after success payment
					 */
					$stdResponse=Extend::DecodeJSON($strResponse);

					if($stdClass->three_domain==='yes'){ // 若有開啟 3D 驗證，則要等待 3D 驗證結束後才可執行 AfterSuccessPayment
					
					}else{

						/*
						 * 判斷 $intOrderID 是否為 renewal order
						 */
						$intRenewal=Subscription::IsRenewal($intOrderID);

						/*
						 * 若訂單不是 renewal order 時執行 AfterSuccessPayment
						 * 訂單如果是 renewal order，AfterSuccessPayment 交給 Subscription::DoRenewalPayment 執行
						 * 否則會出現兩次 order note
						 */
						if(!$intRenewal){
							/*
							 * 2020.05.14
							 * Parent order 過早執行 $order->update_status 會造成 subscrption 卡在 pending
							 */
							//Admin::AfterSuccessPayment($stdOrder, $stdResponse, $stdClass);
						}
					}
				}
			}
		}else{
			if($intOrderID){ // 2021.01.27 即使付款失敗也要記錄 $strResponse，以利後續的查詢與判斷
				update_post_meta($intOrderID, '_'.Handler::ID.'-return', $strResponse);
			}
		}

		return $strResponse;
	}

	public static function CheckMobileNumber($strPhoneNumber){

		/*===開頭為加號 ( + ) 的 E.164 格式===*/
		preg_match('@^\+?(886)?\s?0?(9\d{8})$@', $strPhoneNumber, $match);

		$strMobileNumber=false;

		if(isset($match[0])){
			$strMobileNumber='+886'.$match[2];

		}else{
			if(strpos($strPhoneNumber, '+')===false)$strPhoneNumber='+'.$strPhoneNumber;
			$strMobileNumber=$strPhoneNumber;
		}

		return $strMobileNumber;
	}

	public static function CheckAuthorized(){
		if($_POST['payment_method']!=Handler::ID)return;
		$stdResult=Basic::CheckDate();
		if($stdResult->error!='0'){
			throw new \Exception('TapPay 錯誤: '.$stdResult->msg);
		}
	}

	public static function ReturnURL($intOrderID, $order, $stdClass){

		$strSecureURL=get_post_meta($intOrderID, '_'.Handler::ID.'-3dsecure-url', true);

		if($strSecureURL&&$stdClass->three_domain==='yes'){

			/*
			 * 2020.04.29
			 * 若用 WooCommerce subscription API 建立訂閱訂單，當開啟 3D 驗證時
			 * 若不執行 empty cart，會造成 subscription 訂單建立後又立刻被刪除的狀況
			 * 而且會發生 Invalid argument supplied for foreach(), file: class-wc-subscriptions-switcher.php #722 #89 兩個 php warning
			 */
			if(WC()->cart)WC()->cart->empty_cart();

			$strReturnURL=$strSecureURL;

		}else{
			$strReturnURL=$stdClass->get_return_url($order);
			CheckoutProcess::PaymentComplete($intOrderID, $order, $stdClass, 1); // 1: $intException==true

			/*
			 * v1.1.2 註記 2020.03.30
			 * 有外掛造成 PHP Fatal error 後，導致 TapPay 雖已扣款但回傳無法紀錄
			 * 因此將 AfterSuccessPayment 改到 DoPayment 中
			 */
			/*
			$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
			$stdResponse=Extend::DecodeJSON($strResponse);
			Admin::AfterSuccessPayment($order, $stdResponse, $stdClass);
			*/

			/*
			 * 2020.05.14
			 * Parent order 過早執行 $order->update_status 會造成 subscrption 卡在 pending
			 */

			$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
			$stdResponse=Extend::DecodeJSON($strResponse);
			Admin::AfterSuccessPayment($order, $stdResponse, $stdClass);
		}

		$strSecureURL=apply_filters(Handler::ID.'_return-url', $strReturnURL, $intOrderID, $stdClass);

		return $strSecureURL;
	}

	public static function PaymentComplete($intOrderID, $order, $stdClass, $intException=false){

		$stdPostMeta=false;
		$strPostMeta=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);

		if($strPostMeta)$stdPostMeta=Extend::DecodeJSON($strPostMeta);

		if(!$stdPostMeta||(property_exists($stdPostMeta, 'status')&&$stdPostMeta->status!='0')){

			$strException='TapPay: 信用卡資料不齊全';
			if(property_exists($stdPostMeta, 'bank_result_msg')&&!empty($stdPostMeta->bank_result_msg))$strException=$stdPostMeta->bank_result_msg;

			if($intException){
				throw new \Exception($strException);

			}else{
				$stdOrder=wc_get_order($intOrderID);
				$stdOrder->add_order_note($strException);
				return;
			}

		}

		if(WC()->cart)WC()->cart->empty_cart();
		//if($stdClass->status_change!=$order->get_status())$order->update_status($stdClass->status_change);

		$intReduceStock=apply_filters(Handler::ID.'_reduce-stock', true, $intOrderID);

		if($intReduceStock){
			version_compare(WC_VERSION, '3.0.0', '<')?$order->reduce_order_stock():wc_reduce_stock_levels($intOrderID);
		}
	}

}