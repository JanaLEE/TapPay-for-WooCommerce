<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

class Extend{

	function __construct(){

		/*===信用卡 3D 驗證===*/
		add_action('init',							__NAMESPACE__.'\\Basic::AddRewriteRules');
		add_filter('query_vars',				__NAMESPACE__.'\\Basic::AddQueryVars');
		add_action('template_redirect',	__NAMESPACE__.'\\Basic::RewriteTemplates');
		/*===信用卡 3D 驗證===*/

		add_action('wp_ajax_'.Handler::ID.'_ManualRenew', __NAMESPACE__.'\\Subscription::ManualRenew');
		add_action('wp_ajax_'.Handler::ID.'_QueryStatus', __NAMESPACE__.'\\Admin::QueryStatus');

		add_action('woocommerce_checkout_update_order_meta', function($intOrderID, $arrData){
			/*===避免因為 post 出錯而重新生出訂單===*/
			WC()->session->set('order_awaiting_payment', $intOrderID);
		}, 1, 2);

		/*===Subscription===*/
		add_action('init', __NAMESPACE__.'\\Subscription::Init');
		/*===Subscription===*/

		add_filter(Handler::ID.'_posted-order-id',	__NAMESPACE__.'\\Get::PostedOrderID', 10, 3);
		//add_filter(Handler::ID.'_details',				__NAMESPACE__.'\\Get::Details', 10, 3);
		add_filter(Handler::ID.'_amount',						__NAMESPACE__.'\\Get::CartAmount', 10, 3);

		add_filter(Handler::ID.'_billing-phone', __CLASS__.'::CheckMobileNumber', 99, 2);

		add_filter(Handler::ID.'_update-tappay-return', __NAMESPACE__.'\\Action::CheckReturn', 999, 4);

		add_action('woocommerce_before_checkout_process',		__NAMESPACE__.'\\CheckoutProcess::CheckAuthorized');

		//add_action('woocommerce_checkout_order_processed',	__NAMESPACE__.'\\CheckoutProcess::CheckPrimeData', 11, 3);

		add_action('woocommerce_checkout_order_processed',	__CLASS__.'::CheckData', 11, 3);
		add_action('woocommerce_checkout_order_processed',	__CLASS__.'::ReadyToPay', 12, 3);

		/*===退刷、取消授權===*/
		/*
		add_action('woocommerce_order_status_cancelled', __CLASS__.'::CancelAuthorized');
		add_action('woocommerce_order_status_refunded', __CLASS__.'::CancelAuthorized');
		*/
		add_action('woocommerce_order_status_changed',	__CLASS__.'::MaybeNeedRefund', 10, 4);

		add_filter(Handler::ID.'_payment-complete',			__CLASS__.'::PaymentCompleteStatus');
		add_filter(Handler::ID.'_allow-refund',					__CLASS__.'::RefundStatus');
		add_filter(Handler::ID.'_refund-amount',				__CLASS__.'::RefundAmount', 999, 3);

		add_action(Handler::ID.'_after-success-payment', __CLASS__.'::MaybeZeroAmount', 999, 3); // 2020.12.13 訂單可能因為優惠券全額抵用，授權後要執行退款
		add_filter(Handler::ID.'_refund-amount', __CLASS__.'::RefundZeroAmount', 999, 3);

		//add_filter(Handler::ID.'_check-payment-data',		__CLASS__.'::CheckFraudID', 999, 4); /*===check fraud id===*/
		/*===退刷、取消授權===*/

		/*===TapPay bind card api 2020.04.01===*/
		add_action('wp', __NAMESPACE__.'\\Action::RemoveCard', 1);
		add_action(Handler::ID.'_after-payment-fields', __CLASS__.'::CardHolderInfo');
		/*===TapPay bind card api 2020.04.01===*/

		if(is_admin()){
			/*===New version of Plugin update checker===*/
			require CWTAPPAY_DIR.'/plugin-update-checker/plugin-update-checker.php';
			\Puc_v4_Factory::buildUpdateChecker(
				'https://auth.woocloud.io/update/tappay/info.json', 
				CWTAPPAY_File,
				'cw-tappay'
			);
			/*===New version of Plugin update checker===*/

			add_filter('plugin_action_links_'.CWTAPPAY_BaseName, __NAMESPACE__.'\\Basic::PluginActionLinks');
			add_action('in_plugin_update_message-'.basename(CWTAPPAY_DIR).'/'.basename(CWTAPPAY_File), __NAMESPACE__.'\\Admin::AdditionalUpdateMessages', 20, 2);

			add_action('admin_enqueue_scripts', __NAMESPACE__.'\\Admin::AddAdminScripts');
			add_action('admin_enqueue_scripts', __NAMESPACE__.'\\Admin::AddAdminStyles');

			add_action('add_meta_boxes', __NAMESPACE__.'\\Admin::AddMetaBox');

			add_action('woocommerce_admin_order_data_after_billing_address', __NAMESPACE__.'\\Admin::CardTokenField');

			add_action('save_post_shop_subscription', __NAMESPACE__.'\\Admin::SaveOrder', 999, 3);

		}else{
			add_action('wp_enqueue_scripts', __NAMESPACE__.'\\Basic::AddScripts');
			add_action('wp_enqueue_scripts', __NAMESPACE__.'\\Basic::AddStyles');

			add_action('woocommerce_cart_calculate_fees', __NAMESPACE__.'\\Basic::CalculateFee', 9999);

			//add_action(Handler::ID.'_after-payment-field', __NAMESPACE__.'\\Basic::AfterPaymentField');
		}
	}

	/*===check fraud id===*/
	/*
	public static function CheckFraudID($arrPostData, $strType, $stdOrder, $stdClass){

		// 需檢查是否有開啟偽卡偵測
		if($strType=='token'){
			if(count($_POST)>0&&array_key_exists(Handler::ID.'_fraud_id', $_POST)){
				$arrPostData['fraud_id']=$_POST[Handler::ID.'_fraud_id'];
			}
		}

		return $arrPostData;
	}
	*/

	public static function RefundAmount($strAmount, $stdOrder, $stdClass){

		$strPaymentMethod=$stdOrder->get_payment_method();

		if($strPaymentMethod==Handler::ID){

			/*
			 * 直接改變訂單狀態的自動退款，$_POST['refund_amount'] 為空，但 $_POST['refunded_amount'] 有正確的退款金額
			 */
			if(isset($_POST['refund_amount'])&&(int)$_POST['refund_amount']>0){ // 訂單頁按下部分退款按鈕
				$strAmount=$_POST['refund_amount'];
			}
		}

		return $strAmount;
	}

	public static function PaymentCompleteStatus($arrStatus){
		$arrRefund=array(
			'wc-cancelled', 
			'wc-refunded', 
			'wc-failed', 
		);

		foreach($arrRefund as $value){
			unset($arrStatus[$value]);
		}

		$arrNewStatus=$arrStatus;
		$arrStatus=array();

		foreach($arrNewStatus as $key=>$value){ // remove wc-
			$strKey=preg_replace('@^wc-@', '', $key);
			$arrStatus[$strKey]=$value;
		}

		return $arrStatus;
	}

	public static function RefundStatus($arrStatus){
		$arrNoRefund=array(
			'wc-processing', 
			'wc-completed');
		foreach($arrNoRefund as $value){
			unset($arrStatus[$value]);
		}
		return $arrStatus;
	}

	public static function MaybeNeedRefund($intOrderID, $strStatusFrom, $strStatusTo, $stdOrder){

		/*
		 * 使用輸入退款金額功能
		 * 當退款總金額等於訂單金額時，訂單會先退款，接著自動轉為「已退費」
		 * 因為金額已全退，所以要判斷 $_POST 來源，避免再次執行 CancelAuthorized
		 * 
		 * $_POST: Array(
		 *	[action] => woocommerce_refund_line_items
		 *	[order_id] => 100
		 *	[refund_amount] => 4097
		 *	[refunded_amount] => 100 // 先前已退款的金額
		 *	[refund_reason] => 
		 *	[line_item_qtys] => {}
		 *	[line_item_totals] => {\"3375\":0,\"3376\":0,\"3377\":0,\"3378\":0,\"3379\":0} // order item id
		 *	[line_item_tax_totals] => {}
		 *	[api_refund] => true
		 *	[restock_refunded_items] => true
		 *	[security] => 64xxxxxxeb // nonce
		 * )
		 */
		if(array_key_exists('action', $_POST)&&$_POST['action']=='woocommerce_refund_line_items')return;

		$strPaymentMethod=$stdOrder->get_payment_method();
		if($strPaymentMethod!=Handler::ID)return;

		$stdClass=new WC_Gateway_TapPay;

		if(in_array('wc-'.$strStatusTo, $stdClass->auto_refund)){

			$intRestoreStock=true;
			$intOrderTotal=$stdOrder->get_total();

			if((int)$intOrderTotal>0){

				$strResult=Extend::CancelAuthorized($intOrderID, $stdOrder, $stdClass);

				/*
				 * 2020.12.14
				 * 有訂單編號的退刷不一定要恢復庫存
				 * 例如 free trial、zero amount
				 */
				$stdResult=Extend::DecodeJSON($strResult);
				if($stdResult->status!='0'){
					$intRestoreStock=false;
				}
			}

			$strStockReduced=get_post_meta($intOrderID, '_order_stock_reduced', true); // 檢查資料庫 post_meta 的值，yes|no
			if('no'===$strStockReduced)$intRestoreStock=false;

			if($intRestoreStock){
				Admin::RestoreStock($intOrderID);
			}
		}
	}

	public static function CancelAuthorized($intOrderID, $stdOrder, $stdClass){

		$strPostMeta=get_post_meta($intOrderID, '_'.Handler::ID.'-return', true);
		if(!$strPostMeta)return; /*===必須要有 return 資料 ( 刷卡成功 ) 才能啟用退刷===*/

		$stdPostMeta	=Extend::DecodeJSON($strPostMeta);
		$strResult		=Admin::CancelAuthorized($intOrderID, $stdClass, $stdPostMeta, $stdOrder);

		do_action(Handler::ID.'_after-refund', $intOrderID, $stdClass);

		return $strResult;
	}

	public static function MaybeZeroAmount($stdOrder, $stdResult, $stdClass){ // 2020.12.13 訂單可能因為優惠券全額抵用，授權後要執行退款
		$intOrderID=Get::OrderID($stdOrder);
		if(!$intOrderID)return;

		$intZeroAmount=get_post_meta($intOrderID, '_'.Handler::ID.'-zero-amount', true);
		if(!$intZeroAmount)return;

		$strResult=Extend::CancelAuthorized($intOrderID, $stdOrder, $stdClass);
		$stdResult=Extend::DecodeJSON($strResult);
		if($stdResult->status=='0'){
			delete_post_meta($intOrderID, '_'.Handler::ID.'-zero-amount', 1); // 1: 刷卡金額 1 元
		}
	}

	public static function RefundZeroAmount($strAmount, $stdOrder, $stdClass){ // 2020.12.13 訂單可能因為優惠券全額抵用，授權後要執行退款
		$intOrderID=Get::OrderID($stdOrder);
		if(!$intOrderID)return;

		$intZeroAmount=get_post_meta($intOrderID, '_'.Handler::ID.'-zero-amount', true);
		if($intZeroAmount){
			$strAmount=$intZeroAmount;
		}
		
		return $strAmount;
	}

	public static function CheckData($intOrderID, $arrPostedData, $stdOrder){
		if($arrPostedData['payment_method']!=Handler::ID)return;

		if(isset($_POST['wc-'.Handler::ID.'-payment-token'])){
			if($_POST['wc-'.Handler::ID.'-payment-token']=='new'){
				if(!isset($_POST[Handler::ID.'_result'])||strlen(trim($_POST[Handler::ID.'_result']))===0){
					Get::ErrorMessage(1); // Result 資料有誤
					return;
				}
				CheckoutProcess::CheckPrimeData($intOrderID, $stdOrder);

			}else{
				/*
				$intUserID=get_current_user_id();
				if(!$intUserID){
					Get::ErrorMessage(4); // User ID 有誤
					return;
				}
				CheckoutProcess::CheckTokenData($intOrderID, $stdOrder, $intUserID);
				*/
			}

		}else{
			Get::ErrorMessage(3); // 缺少 input payment token 值
		}
	}

	public static function ReadyToPay($intOrderID, $arrPostedData, $stdOrder){

		if($arrPostedData['payment_method']!=Handler::ID)return false;

		if(isset($_POST['wc-'.Handler::ID.'-payment-token'])){

			$stdClass=new WC_Gateway_TapPay;

			$stdClass=apply_filters(Handler::ID.'_ready-to-pay', $stdClass, $intOrderID, $arrPostedData, $stdOrder);

			if($_POST['wc-'.Handler::ID.'-payment-token']=='new'){
				return CheckoutProcess::PayByPrime($intOrderID, $stdOrder, $stdClass);

			}else{
				return CheckoutProcess::PayByToken($intOrderID, $stdOrder, $stdClass, $_POST['wc-'.Handler::ID.'-payment-token']);
			}

		}else{
			Get::ErrorMessage(3); // 缺少 input payment token 值
		}
	}

	public static function AddCard($stdClass){
		$arrPostData=$_POST;
		return Action::AddCard($stdClass, $arrPostData);
	}

	public static function CheckMobileNumber($strMobileNumber, $stdOrder){
		$strMobileNumber=CheckoutProcess::CheckMobileNumber($strMobileNumber);
		return $strMobileNumber;
	}

	/*
	 * 2020.04.01 TapPay Bind Card API
	 */
	public static function CardHolderInfo(){
		$intCardHolder=apply_filters(Handler::ID.'_card-holder-info', is_wc_endpoint_url('add-payment-method'));
		if($intCardHolder){
			echo Get::Template('card-holder-info', 'page');
		}
	}

	public static function DecodeJSON($strData){
		/*
		 * Reference: https://gist.github.com/bcantoni/2162791
		 */
		if(version_compare(PHP_VERSION, '5.4.0', '>=')){
			return json_decode($strData, false, 512, JSON_BIGINT_AS_STRING);
		}else{
			return json_decode(preg_replace('@:\s?(\d{14,})@', ': "${1}"', $strData));
		}
	}
}