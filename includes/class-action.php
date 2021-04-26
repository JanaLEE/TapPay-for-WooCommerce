<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

if(!defined('ABSPATH'))exit();

class Action{

	function __construct(){}

	public static function CheckReturn($strResponse, $arrPostData, $stdOrder, $stdClass){

		$strOrderType=Subscription::OrderType($stdOrder); // 當訂單是 renewal order 時不作任何更動
		if($strOrderType=='renewal')return $strResponse; // simple|subscription|renewal|parent

		if(isset($arrPostData['card_key'])&&isset($arrPostData['card_token'])){ // pay by token

			$stdResponse=Extend::DecodeJSON($strResponse);

			/*
			 * pay by token: TapPay 不會回傳 card_secret ( key 與 token )
			 * 所以要將 $arrPostData 中的 card_key、card_token 加到 $strResponse 後
			 * 後續如果 subscription 需要扣款或改變卡號才有依據
			 */
			if(!property_exists($stdResponse, 'card_secret')){
				$stdResponse->card_secret=new \stdClass;
				$stdResponse->card_secret->card_token=$arrPostData['card_key'];
				$stdResponse->card_secret->card_token=$arrPostData['card_token'];
			}

			$strResponse=json_encode($stdResponse);
		}

		return $strResponse;
	}

	public static function AddCard($stdClass, $arrPostData){

		$intError			=0;
		$strResult		=urldecode($arrPostData[Handler::ID.'_result']);
		$stdResult		=Extend::DecodeJSON($strResult);
		$stdCardInfo	=$stdResult->card;
		$intUserID		=get_current_user_id();

		//$strRedirectURL=apply_filters(Handler::ID.'_bind-card', wc_get_endpoint_url('payment-methods'));

		if($intUserID){
			$stdUser	=new \WP_User($intUserID);
			$strEmail	=$stdUser->user_email;

		}else{
			$strEmail	=get_option('admin_email', false);
		}

		if(!isset($stdCardInfo)||!isset($stdCardInfo->prime)){
			$intError=1;
		}elseif(!$stdClass->partner_key){
			$intError=7;
		}

		/*
		 * Get::PaymentVariables
		 * - $strSandBox
		 * - $strDetails
		 * - $strOrderNumber
		 * - $strMerchantID
		 * - $arrCardHolderIndex
		 * - $arrCardHolder
		 */
		$arrVariables=Get::PaymentVariables($stdClass);
		extract($arrVariables);
		if(!$strMerchantID)$intError=8;

		if($intError===0){

			foreach($arrCardHolderIndex as $key=>$value){
				switch($key){
					case 'phone_number':
						if(empty($arrCardHolder[$key])){
							$arrCardHolder[$key]=Get::UserBillingPhone($intUserID);
						}
						break;
					case 'name':
						if(empty($arrCardHolder[$key])){
							$arrCardHolder[$key]=Get::UserBillingName($intUserID);
						}
						break;
					case 'email':
						if(empty($arrCardHolder[$key])){
							$arrCardHolder[$key]=Get::UserBillingEmail($intUserID);
						}
						break;
				}
			}

			$arrCardHolder=array(
				'phone_number'	=>$arrCardHolder['phone_number'], // billing_phone
				'name'					=>$arrCardHolder['name'], // 姓名
				'email'					=>$arrCardHolder['email'], 

				'zip_code'			=>'', // 以下均為 optional
				'address'				=>'', 
				'national_id'		=>'', 
				'member_id'			=>'', 
			);

			$arrPostData=array(
				'prime'					=>$stdCardInfo->prime, 
				'partner_key'		=>$stdClass->partner_key, 
				'merchant_id'		=>$strMerchantID, 

				// 'merchant_group_id'=>'', // 不可與 merchant_id 同時使用 ( optional )

				'currency'			=>'TWD', 
				'cardholder'		=>$arrCardHolder, 

				//'cardholder_verify'=>'', // ( optional )
			);

			/*===if 3D secure===*/
			if($stdClass->three_domain==='yes'){
				$arrPostData['three_domain_secure']=true;

				$arrPostData['result_url']=array(
					'frontend_redirect_url'	=>$strSiteURL.'/cwtpfw/3d-secure/checkout?order_id=0', 
					'backend_notify_url'		=>$strSiteURL.'/cwtpfw/3d-secure/notify?order_id=0', 
				);
			}

			if($stdClass->sandbox=='yes'){
				$strURL='https://sandbox.tappaysdk.com/tpc/card/bind';
			}else{
				$strURL='https://prod.tappaysdk.com/tpc/card/bind';
			}

			$arrResult=Admin::RemotePost($strURL, $arrPostData, $stdClass);

			if($arrResult['error']===false){

				$stdResult=$arrResult['data'];

				if($stdResult->status!='0'){
					wc_add_notice($stdResult->msg, 'error');
					return;

				}else{
					Admin::SaveCard($stdResult, $intUserID);
				}

				do_action(Handler::ID.'_after-add-card', $intUserID, $stdResult, $stdClass);

				$stdResult->card_secret=''; // 返回 json 時，移除 card_secret 內容 ( card_token, card_key )

				$strRedirectURL=apply_filters(Handler::ID.'_bind-card-redirect', wc_get_endpoint_url('payment-methods'), 'success', $arrResult);

				return array(
					'data'			=>$stdResult, 
					'result'		=>'success', 
					'redirect'	=>$strRedirectURL, // filter: cwtpfw_bind-card-redirect
				);

			}else{
				wc_add_notice($arrResult['message'], 'error');

				/*
				 * 2020.10.24
				 * array key ( result 與 redirect ) 一定要存在
				 * result: <empty string>|success|failure
				 * redirect: <empty string>|url
				 */
				$strRedirectURL=apply_filters(Handler::ID.'_bind-card-redirect', wc_get_endpoint_url('payment-methods'), 'error', $arrResult); // v1.2 2020.10.24
				return ['result'=>'', 'redirect'=>$strRedirectURL];
			}

		}else{
			$strError=Get::ErrorMessage($intError, false);

			$arrResult=array( // v1.2 2020.10.24
				'error'		=>$intError, 
				'message'	=>$strError, 
			);

			wc_add_notice($strError, 'error');

			/*
			 * 2020.10.24
			 * array key ( result 與 redirect ) 一定要存在
			 * result: <empty string>|success|failure
			 * redirect: <empty string>|url
			 */
			$strRedirectURL=apply_filters(Handler::ID.'_bind-card-redirect', wc_get_endpoint_url('payment-methods'), 'error', $arrResult); // v1.2 2020.10.24
			return ['result'=>'', 'redirect'=>$strRedirectURL];
		}
	}

	public static function RemoveCard(){
		global $wp;

		$strError=false;

		if(isset($wp->query_vars['delete-payment-method'])){

			if(function_exists('wc_nocache_headers')){
				wc_nocache_headers();
			}

			$intTokenID	=absint($wp->query_vars['delete-payment-method']);
			$stdToken		=\WC_Payment_Tokens::get($intTokenID);

			if(is_null($stdToken)
				||get_current_user_id()!==$stdToken->get_user_id()
				||!isset($_REQUEST['_wpnonce'])
				||false===wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'delete-payment-method-'.$intTokenID)){

			}else{
				$strCardKey		='';
				$strCardToken	='';
				$arrData			=$stdToken->get_data();

				if($arrData['gateway_id']!=Handler::ID)return; // 非 TapPay

				preg_match('@^(.*?):::(.*?)$@', $arrData['token'], $match);

				if(count($match)===0){
					$strError='Card key|token 有誤';

				}else{
					$strCardKey		=$match[1];
					$strCardToken	=$match[2];
				}

				if($strError===false){
					$stdClass=new WC_Gateway_TapPay();

					$strURL='https://prod.tappaysdk.com/tpc/card/bind';
					if($stdClass->sandbox=='yes')$strURL='https://sandbox.tappaysdk.com/tpc/card/bind';

					$arrPostData=array(
						'partner_key'	=>$stdClass->partner_key, 
						'card_key'		=>$stdClass, 
						'card_token'	=>$strCardToken, 
					);

					$arrResult=Admin::RemotePost($strURL, $arrPostData, $stdClass);

					if($arrResult['error']===false){
						

					}else{
						wc_add_notice($strError, 'error');
						wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
						exit();
					}

				}else{
					wc_add_notice($strError, 'error');
					wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
					exit();
				}
			}
		}
	}
}