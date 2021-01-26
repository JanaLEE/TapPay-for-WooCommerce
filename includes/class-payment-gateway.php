<?php
/*<cloudwp />*/
namespace cloudwp\TapPay;

class WC_Gateway_TapPay extends \WC_Payment_Gateway_CC{

	function __construct(){
		$this->id									=Handler::ID;
		$this->icon								='';
		$this->has_fields					=true; // 一定要設為 true 否則信用卡欄位無法顯示
		$this->method_title				='TapPay Pro';
		$this->method_description	='TapPay Pro';

		if(isset($_GET['section'])&&($_GET['section']==Handler::ID||$_GET['section']=='wc_shipping_'.Handler::ID)){
			$stdCheckDate=Basic::CheckDate();
			$this->method_description.=' 服務到期日: '.$stdCheckDate->msg;
			if($stdCheckDate->error!='0')return;
		}

		$this->title='TapPay Pro';

		$this->init_form_fields();
		$this->init_settings();

		/*===讀取所有 form fields 中的資料===*/
		Get::PaymentOptions($this);

		$arrSupports=array(
			'products', 
			'refunds', 
			'tokenization', 
			'add_payment_method', 
			'subscriptions', 
			'subscription_cancellation', 
			'subscription_suspension', 
			'subscription_reactivation', 
			'subscription_amount_changes', 
			'subscription_date_changes', 
			'subscription_payment_method_change', 

			'multiple_subscriptions', // 允許同一張訂單有多個訂閱商品
		);

		$arrSupports=apply_filters(Handler::ID.'_payment-supports', $arrSupports, $this);

		$this->supports=$arrSupports;

		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
	}

	public function init_form_fields(){

		$arrStatus=wc_get_order_statuses();

		$arrCompleteStatus	=apply_filters(Handler::ID.'_payment-complete', $arrStatus);
		$arrRefundStatus		=apply_filters(Handler::ID.'_allow-refund', $arrStatus);

		$this->form_fields=include('payment-form-fields.php');
	}

	public function payment_fields(){
		Get::PaymentField($this);
	}

	public function add_payment_method(){
		return Extend::AddCard($this);
	}

	public function process_refund($intOrderID, $amount=NULL, $reason=''){

		$order=wc_get_order($intOrderID);
		$strResult=Extend::CancelAuthorized($intOrderID, $order, $this);

		$stdResult=Extend::DecodeJSON($strResult);

		if(isset($stdResult->status)){
			if($stdResult->status=='0'){
				return true;

			}else{
				return new \WP_Error('error', 'TapPay 退款發生錯誤'."\r\n".$stdResult->msg."\r\n".'status: '.$stdResult->status);
			}
		}
	}

	public function process_payment($order_id){
		$order=wc_get_order($order_id);

		//$strReturnURL=CheckoutProcess::PaymentComplete($order_id, $order, $this); // 更換到 ReturnURL 執行 ( 如果沒有開啟 3D 驗證 )
		//$strReturnURL=$this->get_return_url($order);
		$strReturnURL=CheckoutProcess::ReturnURL($order_id, $order, $this);

		return array(
			'result'		=>'success',
			'redirect'	=>$strReturnURL);
	}

	public function get_icon(){
		Get::Icon();
	}
}