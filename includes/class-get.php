<?php
/*<cloudwp />*/

/*
 * get
 * 所有的 get
 */
namespace cloudwp\TapPay;

if(!defined('ABSPATH'))exit();

class Get{

	function __construct(){}

	public static function ClassName($stdClass){
		$stdReflect=new \ReflectionClass($stdClass);
		return $stdReflect->getShortName();
	}

	public static function ParentOrder($order){

		$stdOrder			=is_numeric($order)?wc_get_order($order):$order;
		$strOrderType	=Subscription::OrderType($stdOrder);

		if($strOrderType=='parent'){
			return $stdOrder;

		}elseif($strOrderType=='subscription'){
			$stdSubscription=wcs_get_subscription($stdOrder);
			return $stdSubscription->get_parent();

		}else{
			$arrSubscription=wcs_get_subscriptions_for_renewal_order($stdOrder);
			if(count($arrSubscription)===0){
				return false;
			}else{
				return current($arrSubscription);
			}
		}

		return $intParentID;
	}

	public static function ParentOrderToken($order){

		$stdToken=false;
		$stdOrder=is_numeric($order)?wc_get_order($order):$order;

		if($stdOrder){
			$intOrderID=Get::OrderID($stdOrder);

			$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
			if(!$strResponse)return false;

			$stdResponse=Extend::DecodeJSON($strResponse);

			if(!is_a($stdResponse, 'stdClass')||!property_exists($stdResponse, 'card_secret'))return;

			$strToken='';
			if(property_exists($stdResponse->card_secret, 'card_key')){
				$strToken=$stdResponse->card_secret->card_key;
			}
			$strToken.=':::'.$stdResponse->card_secret->card_token;

			$stdToken=Get::TokenByString($strToken, $stdOrder->get_customer_id());
		}

		return $stdToken;
	}

	public static function DefaultToken($stdRenewalOrder, $arrTokens, $intUserID){

		$stdDefaultToken=false;

		$stdSubscription=Subscription::OrderFromRenewal($stdRenewalOrder);
		if($stdSubscription){

			$stdParentOrder=$stdSubscription->get_parent(); // 讀取 parent order

			//$intOrderID=Get::OrderID($stdParentOrder);

			/*
			 * renewal order 扣款前
			 * 預設第一組 ( default ) token 為 parent order 中記錄的 token
			 */
			/*
			$strResponse=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
			$stdResponse=Extend::DecodeJSON($strResponse);

			if(is_object($stdResponse)&&isset($stdResponse->card_secret)){
			*/
				/*
				 * 2020.06.05
				 * pay by token 的回傳沒有 card_key
				 * 所以 TokenByString 要判斷 card_key 是否存在
				 */
				// $strToken=$stdResponse->card_secret->card_key.':::'.$stdResponse->card_secret->card_token;
				/*
				$strToken='';
				if(property_exists($stdResponse->card_secret, 'card_key')){
					$strToken=$stdResponse->card_secret->card_key;
				}
				$strToken.=':::'.$stdResponse->card_secret->card_token;

				$stdDefaultToken=Get::TokenByString($strToken, $intUserID);
			}
			*/
			$stdDefaultToken=Get::ParentOrderToken($stdParentOrder);
		}

		/*
		 * 若無預設的 token 則從 payment token 中查詢
		 */
		if(!$stdDefaultToken)$stdDefaultToken=\WC_Payment_Tokens::get_customer_default_token($intUserID);

		return apply_filters(
			Handler::ID.'_default-token', 
			$stdDefaultToken===false?current($arrTokens):$stdDefaultToken, 
			$stdRenewalOrder, 
			$arrTokens, 
			$intUserID);
	}

	public static function TokenByString($strToken, $intUserID){
		global $wpdb;

		/*
		 * 2020.06.05
		 * pay by token 的回傳沒有 card_key
		 * 所以 TokenByString 要判斷 card_key 是否存在
		 */
		if(strpos($strToken, ':::')===0){
			$dbResult=$wpdb->get_row("SELECT token_id FROM ".$wpdb->prefix."woocommerce_payment_tokens WHERE token LIKE '%".$strToken."' AND user_id='".$intUserID."'");
		}else{
			$dbResult=$wpdb->get_row("SELECT token_id FROM ".$wpdb->prefix."woocommerce_payment_tokens WHERE token='".$strToken."' AND user_id='".$intUserID."'");
		}

		if(is_object($dbResult)&&isset($dbResult->token_id))$intTokenID=$dbResult->token_id;

		return \WC_Payment_Tokens::get($intTokenID);
	}

	public static function PaymentField($stdClass){

		wp_enqueue_script('wc-credit-card-form');

		$strSavePaymentMethod=isset($stdClass->default_save_payment_method)?$stdClass->default_save_payment_method:'no';
		$intChecked=false;
		if('yes'==$strSavePaymentMethod)$intChecked=true;

		$intChecked=apply_filters(Handler::ID.'_save-payment-method', $intChecked);

		$strChecked='';
		if($intChecked)$strChecked=' checked="checked"';

		$intShowSavedCard=$stdClass->supports('tokenization')&&is_checkout()&&$stdClass->allow_save_card==='yes';

		$arrVariables=array(
			'stdClass'					=>$stdClass, 
			'strChecked'				=>$strChecked, 
			'intShowSavedCard'	=>$intShowSavedCard, 
			'intFields'					=>$stdClass->fields, 
			'strSaveCardText'		=>$stdClass->save_payment_method_checkbox_text);

		if($stdClass->description)echo wpautop(wp_kses_post($stdClass->description));

		if($intShowSavedCard){
			$stdClass->tokenization_script();
			$stdClass->saved_payment_methods();
		}

		echo self::Template('payment-field', 'page', $arrVariables);
	}

	/*
	public static function Template($strTemplateName, $arrVariables=array(), $strFolderName='/'){

		if($strFolderName!='/')$strFolderName=$strFolderName.'/';
		$strFilePath=CWTAPPAY_DIR.'/template/'.$strFolderName.$strTemplateName.'.php';

		if(!file_exists($strFilePath))return NULL;

		$arrVariables['strHandlerID']=Handler::ID;

		$arrVariables=apply_filters(Handler::ID.'_get-template_'.$strTemplateName, $arrVariables, $strTemplateName, $strFolderName);

		extract($arrVariables);

		ob_start();
		include $strFilePath;
		$strHTML=ob_get_clean();

		return $strHTML;
	}
	*/

	public static function Template($strTemplateName, $strFolderName='', $arrVariables=array()){

		$strTemplateRoot	=CWTAPPAY_DIR.'/templates/';
		$strTemplateRoot	=apply_filters(Handler::ID.'_template-root', $strTemplateRoot, $strTemplateName, $strFolderName);
		$strFilePath			=$strTemplateRoot.$strFolderName.'/'.$strTemplateName.'.php';

		$strFilePath=str_replace(
			array('//'), 
			array('/'), 
			$strFilePath);

		if(!file_exists($strFilePath))return NULL;

		if(!isset($arrVariables['strHandlerID']))$arrVariables['strHandlerID']=Handler::ID;

		$arrVariables=apply_filters(Handler::ID.'_template-variables', $arrVariables, $strTemplateName, $strFolderName);

		extract($arrVariables);

		ob_start();
		include $strFilePath;
		$strHTML=ob_get_clean();

		$strHTML=preg_replace('@>\s+<@', '><', $strHTML);
		/*<>*/

		return $strHTML;
	}


	public static function Icon(){
		$icon='';
		$ext		=version_compare(WC()->version, '2.6', '>=')?'.svg':'.png';
		$style	=version_compare(WC()->version, '2.6', '>=')?' style="margin-left: 0.3em"':'';

		$icon.='<img src="'.\WC_HTTPS::force_https_url(WC()->plugin_url().'/assets/images/icons/credit-cards/visa'.$ext).'" alt="Visa" width="32"'.$style.' />';
		$icon.='<img src="'.\WC_HTTPS::force_https_url(WC()->plugin_url().'/assets/images/icons/credit-cards/mastercard'.$ext).'" alt="Mastercard" width="32"'.$style.' />';
		$icon.='<img src="'.\WC_HTTPS::force_https_url(WC()->plugin_url().'/assets/images/icons/credit-cards/jcb'.$ext).'" alt="JCB" width="32"'.$style.' />';
		$icon.='<img src="'.\WC_HTTPS::force_https_url(WC()->plugin_url().'/assets/images/icons/credit-cards/amex'.$ext).'" alt="Visa" width="32"'.$style.' />';

		return apply_filters('woocommerce_gateway_icon', $icon, Handler::ID);
	}

	public static function PaymentOptions(&$stdClass){
		foreach($stdClass->form_fields as $key=>$value){
			if($value['type']==='select'||$value['type']==='multiselect'){
				$stdClass->form_fields[$key]['options']=apply_filters('cw-tappay_form-field_'.$key, $stdClass->form_fields[$key]['options']);
			}

			if(strpos($key, 'messages_')===0){
				$strIndex=str_replace('messages_', '', $key);
				$stdClass->messages[$strIndex]=$stdClass->get_option($key);

			}else{
				$stdClass->{$key}=$stdClass->get_option($key);
			}

			if($value['type']=='multiselect'&&!is_array($stdClass->{$key}))$stdClass->{$key}=array();
		}

		if('yes'===$stdClass->sandbox){
			$stdClass->environment	='sandbox';
			$stdClass->merchant_id	=$stdClass->sandbox_merchant_id;
			$stdClass->app_key			=$stdClass->sandbox_app_key;

		}else{
			$stdClass->environment	='production';
			$stdClass->merchant_id	=$stdClass->production_merchant_id;
			$stdClass->app_key			=$stdClass->production_app_key;
		}
	}

	public static function PostURL(){
		return 'https://auth.woocloud.io/TapPay/tappay.php?type=tappaydata';
	}

	public static function ExpiryDate($strDate){ // YYYYMM
		preg_match('@^(\d{4})(\d{2})$@', $strDate, $match);

		$arrExpiryDate=array('year'=>'-', 'month'=>'-');

		if(count($match)>0){
			$arrExpiryDate=array(
				'year'	=>$match[1], 
				'month'	=>$match[2]);
		}

		return $arrExpiryDate;
	}

	public static function ErrorMessage($intError, $intThrowException=true){
		$arrErrorMessage=array(
			'1'=>'Result 資料有誤', 
			'2'=>'Decode 資料有誤', 
			'3'=>'缺少 input payment token 值', 
			'4'=>'無已記錄的 token 資料', 
			'5'=>'新增信用卡資料發生錯誤', 
			'6'=>'缺少 E-Mail', 
			'7'=>'缺少 partner key', 
			'8'=>'缺少 merchant id');

		if(!isset($arrErrorMessage[$intError])){
			$strErrorMessage='TapPay: ['.$intError.']未知錯誤';
		}else{
			$strErrorMessage='TapPay: '.$arrErrorMessage[$intError];
		}

		if($intThrowException){
			throw new \Exception($strErrorMessage);
		}else{
			return $strErrorMessage;
		}
	}

	public static function CardType($intCardType){
		switch($intCardType){
			case '1':
				$strCardType='VISA';
				break;
			case '2':
				$strCardType='MasterCard';
				break;
			case '3':
				$strCardType='JCB';
				break;
			case '4':
				$strCardType='Union Pay';
				break;
			case '5':
				$strCardType='American Express';
				break;
			default:
				$strCardType='Unknown';
		}

		return apply_filters(Handler::ID.'_get_card_type', $strCardType);
	}

	public static function CardName($intCardType){
		switch($intCardType){
			case '1':
				$strCardType='visa';
				break;
			case '2':
				$strCardType='mastercard';
				break;
			case '3':
				$strCardType='jcb';
				break;
			case '4':
				$strCardType='union pay';
				break;
			case '5':
				$strCardType='american express';
				break;
			default:
				$strCardType='unknown';
		}

		return apply_filters(Handler::ID.'_card-name', $strCardType);
	}

	public static function OrderPaymentMethod($order){
		if(WC()->version<'3'){
			$strPaymentMethod=$order->payment_method;
		}else{
			$strPaymentMethod=$order->get_payment_method();
		}
		return $strPaymentMethod;
	}

	public static function PaymentVariables($stdClass, $stdOrder=false, $intOrderID=false){
		$arrCardHolderIndex=array(
			'phone_number'	=>'', 
			'name'					=>'', 
			'email'					=>'', 
		);

		$strSandBox			=$stdClass->sandbox;
		$strDetails			='online order';
		$strDetails			=apply_filters(Handler::ID.'_details', $strDetails, $stdOrder, $stdClass);
		$strOrderNumber	=apply_filters(Handler::ID.'_posted-order-id', $intOrderID, $stdOrder, $stdClass);

		if($strSandBox=='yes'){
			$strMerchantID=$stdClass->sandbox_merchant_id;
		}else{
			$strMerchantID=$stdClass->production_merchant_id;
		}

		foreach($arrCardHolderIndex as $key=>$value){
			$arrCardHolder[$key]='';
			if(isset($_POST[Handler::ID.'_'.$key])){
				$arrCardHolder[$key]=$_POST[Handler::ID.'_'.$key];
			}
		}

		return array(
			'arrCardHolderIndex'=>$arrCardHolderIndex, 

			'arrCardHolder'		=>$arrCardHolder, 
			'strSandBox'			=>$strSandBox, 
			'strDetails'			=>$strDetails, 
			'strOrderNumber'	=>$strOrderNumber, 
			'strMerchantID'		=>$strMerchantID);
	}

	public static function CartAmount($strAmount, $order, $stdClass){
		return (int)$strAmount;
	}

	public static function CardHolder($order){

		$arrData=array(
			'phone_number'	=>self::OrderBillingPhone($order), 
			'name'					=>self::OrderBillingName($order), 
			'email'					=>self::OrderBillingEmail($order), 
			'zip_code'			=>self::OrderBillingPostcode($order), 
			'address'				=>self::OrderBillingAddress($order), 
			'national_id'		=>'');

		return apply_filters(Handler::ID.'_card-holder', $arrData, $order);
	}

	public static function Currency($intCode){
		$arrCurrency=array(
			'784'=>'AED', 
			'971'=>'AFN', 
			'8'=>'ALL', 
			'51'=>'AMD', 
			'532'=>'ANG', 
			'973'=>'AOA', 
			'32'=>'ARS', 
			'36'=>'AUD', 
			'533'=>'AWG', 
			'944'=>'AZN', 
			'977'=>'BAM', 
			'52'=>'BBD', 
			'50'=>'BDT', 
			'975'=>'BGN', 
			'48'=>'BHD', 
			'108'=>'BIF', 
			'60'=>'BMD', 
			'96'=>'BND', 
			'68'=>'BOB', 
			'984'=>'BOV', 
			'986'=>'BRL', 
			'44'=>'BSD', 
			'64'=>'BTN', 
			'72'=>'BWP', 
			'933'=>'BYN', 
			'84'=>'BZD', 
			'124'=>'CAD', 
			'976'=>'CDF', 
			'947'=>'CHE', 
			'756'=>'CHF', 
			'948'=>'CHW', 
			'990'=>'CLF', 
			'152'=>'CLP', 
			'156'=>'CNY', 
			'170'=>'COP', 
			'970'=>'COU', 
			'188'=>'CRC', 
			'931'=>'CUC', 
			'192'=>'CUP', 
			'132'=>'CVE', 
			'203'=>'CZK', 
			'262'=>'DJF', 
			'208'=>'DKK', 
			'214'=>'DOP', 
			'12'=>'DZD', 
			'818'=>'EGP', 
			'232'=>'ERN', 
			'230'=>'ETB', 
			'978'=>'EUR', 
			'242'=>'FJD', 
			'238'=>'FKP', 
			'826'=>'GBP', 
			'981'=>'GEL', 
			'936'=>'GHS', 
			'292'=>'GIP', 
			'270'=>'GMD', 
			'324'=>'GNF', 
			'320'=>'GTQ', 
			'328'=>'GYD', 
			'344'=>'HKD', 
			'340'=>'HNL', 
			'191'=>'HRK', 
			'332'=>'HTG', 
			'348'=>'HUF', 
			'360'=>'IDR', 
			'376'=>'ILS', 
			'356'=>'INR', 
			'368'=>'IQD', 
			'364'=>'IRR', 
			'352'=>'ISK', 
			'388'=>'JMD', 
			'400'=>'JOD', 
			'392'=>'JPY', 
			'404'=>'KES', 
			'417'=>'KGS', 
			'116'=>'KHR', 
			'174'=>'KMF', 
			'408'=>'KPW', 
			'410'=>'KRW', 
			'414'=>'KWD', 
			'136'=>'KYD', 
			'398'=>'KZT', 
			'418'=>'LAK', 
			'422'=>'LBP', 
			'144'=>'LKR', 
			'430'=>'LRD', 
			'426'=>'LSL', 
			'434'=>'LYD', 
			'504'=>'MAD', 
			'498'=>'MDL', 
			'969'=>'MGA', 
			'807'=>'MKD', 
			'104'=>'MMK', 
			'496'=>'MNT', 
			'446'=>'MOP', 
			'929'=>'MRU', 
			'480'=>'MUR', 
			'462'=>'MVR', 
			'454'=>'MWK', 
			'484'=>'MXN', 
			'979'=>'MXV', 
			'458'=>'MYR', 
			'943'=>'MZN', 
			'516'=>'NAD', 
			'566'=>'NGN', 
			'558'=>'NIO', 
			'578'=>'NOK', 
			'524'=>'NPR', 
			'554'=>'NZD', 
			'512'=>'OMR', 
			'590'=>'PAB', 
			'604'=>'PEN', 
			'598'=>'PGK', 
			'608'=>'PHP', 
			'586'=>'PKR', 
			'985'=>'PLN', 
			'600'=>'PYG', 
			'634'=>'QAR', 
			'946'=>'RON', 
			'941'=>'RSD', 
			'643'=>'RUB', 
			'646'=>'RWF', 
			'682'=>'SAR', 
			'90'=>'SBD', 
			'690'=>'SCR', 
			'938'=>'SDG', 
			'752'=>'SEK', 
			'702'=>'SGD', 
			'654'=>'SHP', 
			'694'=>'SLL', 
			'706'=>'SOS', 
			'968'=>'SRD', 
			'728'=>'SSP', 
			'930'=>'STN', 
			'760'=>'SYP', 
			'748'=>'SZL', 
			'764'=>'THB', 
			'972'=>'TJS', 
			'934'=>'TMT', 
			'788'=>'TND', 
			'776'=>'TOP', 
			'949'=>'TRY', 
			'780'=>'TTD', 
			'901'=>'TWD', 
			'834'=>'TZS', 
			'980'=>'UAH', 
			'800'=>'UGX', 
			'840'=>'USD', 
			'997'=>'USN', 
			'940'=>'UYI', 
			'858'=>'UYU', 
			'860'=>'UZS', 
			'928'=>'VES', 
			'704'=>'VND', 
			'548'=>'VUV', 
			'882'=>'WST', 
			'950'=>'XAF', 
			'961'=>'XAG', 
			'959'=>'XAU', 
			'955'=>'XBA', 
			'956'=>'XBB', 
			'957'=>'XBC', 
			'958'=>'XBD', 
			'951'=>'XCD', 
			'960'=>'XDR', 
			'966'=>'XFU', 
			'952'=>'XOF', 
			'964'=>'XPD', 
			'953'=>'XPF', 
			'962'=>'XPT', 
			'994'=>'XSU', 
			'963'=>'XTS', 
			'965'=>'XUA', 
			'999'=>'XXX', 
			'886'=>'YER', 
			'710'=>'ZAR', 
			'967'=>'ZMW', 
		);
		return array_key_exists($intCode, $arrCurrency)?$arrCurrency[$intCode]:'Unknown';
	}

	/*===2020.04.01===*/
	public static function UserBillingName($intUserID){
		$strBillingName=get_user_meta($intUserID, 'billing_last_name', true).get_user_meta($intUserID, 'billing_first_name', true);
		return apply_filters(Handler::ID.'_billing-name', $strBillingName, $intUserID);
	}

	/*===2020.04.01===*/
	public static function UserBillingPhone($intUserID){
		$strBillingPhone=get_user_meta($intUserID, 'billing_phone', true);
		return apply_filters(Handler::ID.'_billing-phone', $strBillingPhone, $intUserID);
	}

	/*===2020.04.01===*/
	public static function UserBillingEmail($intUserID){
		$strBillingEmail=get_user_meta($intUserID, 'billing_email', true);
		return apply_filters(Handler::ID.'_billing-email', $strBillingEmail, $intUserID);
	}

	public static function OrderBillingPostcode($order){

		if(!$order)return false;

		if(WC()->version<'3'){
			$strBillingPostCode=$order->billing_postcode;
		}else{
			$strBillingPostCode=$order->get_billing_postcode();
		}
		return apply_filters(Handler::ID.'_billing-postcode', $strBillingPostCode, $order);
	}

	public static function OrderBillingAddress($order){

		if(!$order)return false;

		if(WC()->version<'3'){
			$strBillingAddress=$order->billing_state.$order->billing_city.$order->billing_address_1.$order->billing_address_2;
		}else{
			$strBillingAddress=$order->get_billing_state().$order->get_billing_city().$order->get_billing_address_1().$order->get_billing_address_2();
		}
		return apply_filters(Handler::ID.'_billing-address', $strBillingAddress, $order);
	}

	public static function OrderBillingName($order){

		if(!$order)return false;

		if(WC()->version<'3'){
			$strBillingName=$order->billing_last_name.$order->billing_first_name;
		}else{
			$strBillingName=$order->get_billing_last_name().$order->get_billing_first_name();
		}
		return apply_filters(Handler::ID.'_billing-name', $strBillingName, $order);
	}

	public static function OrderBillingPhone($order){

		if(!$order)return false;

		if(WC()->version<'3'){
			$strBillingPhone=$order->billing_phone;
		}else{
			$strBillingPhone=$order->get_billing_phone();
		}
		return apply_filters(Handler::ID.'_billing-phone', $strBillingPhone, $order);
	}

	public static function OrderBillingEmail($order){

		if(!$order)return false;

		if(WC()->version<'3'){
			$strMailAddress=$order->billing_email;
		}else{
			$strMailAddress=$order->get_billing_email();
		}
		return apply_filters(Handler::ID.'_mail-address', $strMailAddress, $order);
	}

	public static function OrderID($order){

		if(!$order)return false;

		if(WC()->version<'3'){
			$intOrderID=$order->id;
		}else{
			$intOrderID=$order->get_id();
		}
		return $intOrderID;
	}

	public static function PostedOrderID($intOrderID=false, $order=false, $stdClass=false){

		// 2020.04.01 若不指定 $intOrderID 則返回 false
		if(empty($intOrderID)||$intOrderID===false)return $intOrderID;

		$intTime=time();
		$intOrderID=$intOrderID.'00'.date('is', $intTime);
		return $intOrderID;
	}
}