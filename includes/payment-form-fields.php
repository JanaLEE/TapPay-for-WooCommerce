<?php
if(!isset($arrCompleteStatus)||!is_array($arrCompleteStatus))	$arrCompleteStatus=array();
if(!isset($arrRefundStatus)||!is_array($arrRefundStatus))			$arrRefundStatus=array();

$arrFormFileds=array(
	'enabled'=>array(
		'title'		=>__('Enable/Disable', 'woocommerce'),
		'type'		=>'checkbox',
		'label'		=>'啟用',
		'default'	=>'yes'), 

	'sandbox'=>array(
		'title'				=>'測試模式',
		'type'				=>'checkbox',
		'label'				=>'啟用', 
		'description'	=>'',
		'default'			=>'no',
		'desc_tip'		=>false), 

	'title'=>array(
		'title'				=>'Tap Pay 標題',
		'type'				=>'text',
		'description'	=>'顯示在結帳頁面的 Tap Pay 標題文字',
		'default'			=>'Tap Pay',
		'desc_tip'		=>false),

	'order_button_text'=>array(
		'title'				=>'結帳按鈕文字',
		'type'				=>'text',
		'description'	=>'',
		'default'			=>'Tap Pay',
		'desc_tip'		=>false),

	'description'=>array(
		'title'				=>'Tap Pay 說明',
		'type'				=>'textarea',
		'description'	=>'選取 Tap Pay 結帳時顯示的說明文字',
		'default'			=>'線上刷卡',
		'css'					=>'width: 450px; resize:none;',
		'desc_tip'		=>false),

	'status_change'	=>array(
		'title'				=>'訂單狀態',
		'type'				=>'select',
		'description'	=>'付款後的訂單狀態',
		'options'			=>$arrCompleteStatus, 
		'default'			=>'processing',
		'class'				=>'wc-enhanced-select',
		'desc_tip'		=>false),

	'partner_key' 	=>array(
		'title'				=>'Partner Key',
		'type'				=>'text',
		'description'	=>'Tap Pay 的 Partner Key，於<a href="https://portal.tappaysdk.com/information" target="_blank">此頁面</a>中查詢', 
		'default'			=>''), 

	'app_id'				=>array(
		'title'				=>'App ID',
		'type'				=>'text',
		'description'	=>'Tap Pay 的 APP ID，於<a href="https://portal.tappaysdk.com/myapps" target="_blank">此頁面</a>中查詢', 
		'default'			=>''), 

	'sandbox_merchant_id'	=>array(
		'title'				=>'Sandbox Merchant ID',
		'type'				=>'text',
		'description'	=>'Tap Pay 測試環境的 Merchant ID，於<a href="https://portal.tappaysdk.com/merchant/sandbox/" target="_blank">此頁面</a>中查詢', 
		'default'			=>''), 

	'sandbox_app_key'	=>array(
		'title'				=>'Sandbox App Key',
		'type'				=>'text',
		'description'	=>'Tap Pay 測試金鑰，於<a href="https://portal.tappaysdk.com/myapps" target="_blank">此頁面</a>中查詢', 
		'default'			=>''), 

	'production_merchant_id'	=>array(
		'title'				=>'Production Merchant ID',
		'type'				=>'text',
		'description'	=>'Tap Pay 正式環境的 Merchant ID，於<a href="https://portal.tappaysdk.com/merchant/prod/" target="_blank">此頁面</a>中查詢', 
		'default'			=>''), 

	'production_app_key'	=>array(
		'title'				=>'Production App Key',
		'type'				=>'text',
		'description'	=>'Tap Pay 正式金鑰，於<a href="https://portal.tappaysdk.com/myapps" target="_blank">此頁面</a>中查詢', 
		'default'			=>''), 

	'allow_save_card'=>array(
		'title'				=>'儲存信用卡資料',
		'type'				=>'checkbox',
		'label'				=>'啟用', 
		'description'	=>'當選用 Tap Pay 結帳時允許消費者儲存信用卡資料，方便於下次結帳時使用',
		'default'			=>'yes',
		'desc_tip'		=>false), 

	'default_save_payment_method'	=>array(
		'title'				=>'預設儲存信用卡資料',
		'type'				=>'checkbox',
		'label'				=>'啟用', 
		'description'	=>'當開啟「儲存信用卡資料」時，此選項為預設是否勾選儲存信用卡',
		'default'			=>'no',
		'desc_tip'		=>false), 

	'additional_fee'=>array(
		'title'				=>'刷卡額外費用',
		'type'				=>'text',
		'description'	=>'當使用者選擇 TapPay 結帳時顯示額外費用，0 為不顯示額外費用 ( 此欄位可以是負數 )',
		'default'			=>'0',
		'desc_tip'		=>false),

	'additional_fee_title'=>array(
		'title'				=>'刷卡額外費用標題',
		'type'				=>'text',
		'default'			=>'刷卡手續費',
		'desc_tip'		=>false),

	'free_additional_fee'=>array(
		'title'				=>'免額外費用門檻',
		'type'				=>'text',
		'description'	=>'訂單金額大於此費用則免額刷卡費用，0 為無免額外費用門檻',
		'default'			=>'0',
		'desc_tip'		=>false),

	'save_payment_method_checkbox_text'	=>array(
		'title'				=>'存入帳號文字',
		'type'				=>'text',
		'description'	=>'', 
		'default'			=>'記錄卡號', 
		'desc_tip'		=>false), 

	'post_method'	=>array(
		'title'				=>'TapPay 連線方式',
		'type'				=>'select',
		'description'	=>'若刷卡線連逾時，請換另一種連線方式',
		'options'			=>array(
			'remote'	=>'預設模式', 
			'curl'		=>'伺服器模式', 
		), 
		'default'			=>'remote',
		'class'				=>'wc-enhanced-select',
		'desc_tip'		=>false),

	'three_domain'=>array(
		'title'		=>'3D 驗證',
		'type'		=>'checkbox',
		'label'		=>'啟用',
		'default'	=>'no'), 

	'try_next'=>array(
		'title'				=>'使用其他信用卡扣款',
		'type'				=>'checkbox',
		'label'				=>'啟用', 
		'description'	=>'當訂閱制的新訂單以預設的信用卡扣款失敗時，嘗試用該使用者其他已記錄的信用卡繼續扣款', 
		'default'			=>'no'), 

	'auto_refund'=>array(
		'title'				=>'允許自動退款的訂單狀態',
		'type'				=>'multiselect',
		'description'	=>'留空則停用自動退費',
		'options'			=>$arrRefundStatus,
		'class'				=>'wc-enhanced-select',
		'default'			=>array(), 
		'desc_tip'		=>false),

	/*
	'instalment'	=>array(
		'title'				=>'分期合作銀行',
		'type'				=>'select',
		'description'	=>'',
		'options'			=>array(
			'no-instalment'=>'請選擇', 
			'taishin'	=>'台新銀行', 
			'nccc'		=>'聯合信用卡中心', 
			'neweb'		=>'藍新 ( 智付通 )', 
			'cathay'	=>'國泰世華銀行'),
		'default'			=>'no-instalment',
		'class'				=>'wc-enhanced-select',
		'desc_tip'		=>false),

	'instalment_allow'=>array(
		'title'				=>'信用卡允許分期期數',
		'type'				=>'multiselect',
		'description'	=>'請先詢問簽約銀行可提供的分期期數，避免無法完成交易',
		'options'			=>array(
			'3'		=>'3 期', 
			'6'		=>'6 期', 
			'12'	=>'12 期', 
			'24'	=>'24 期', 
			'36'	=>'36 期', 
			'48'	=>'48 期'),
		'class'				=>'wc-enhanced-select',
		'desc_tip'		=>false),
		*/

	'fields'=>array(
		'title'				=>'欄位',
		'type'				=>'select',
		'description'	=>'結帳頁顯示的信用卡欄位',
		'options'			=>array(
			'1'		=>'一欄', 
			'3'		=>'三欄'), 
		'default'			=>'3', 
		'class'				=>'wc-enhanced-select',
		'desc_tip'		=>false),

	'messages_WRONG_CARD_FORMAT'	=>array(
		'title'				=>'錯誤訊息自訂欄位[1]',
		'type'				=>'text',
		'default'			=>'信用卡部分資料輸入不齊全',
		'description'	=>'WRONG CARD FORMAT ( 通常是缺少輸入資料 )'), 

	'messages_CARD_ERROR'	=>array(
		'title'				=>'錯誤訊息自訂欄位[2]',
		'type'				=>'text',
		'default'			=>'信用卡資料輸入錯誤',
		'description'	=>'CARD ERROR ( 通常是信用卡資料輸入有誤 )'), 

	'messages_EXPIRED_CARD'	=>array(
		'title'				=>'錯誤訊息自訂欄位[3]',
		'type'				=>'text',
		'default'			=>'卡片已過期',
		'description'	=>'EXPIRED CARD'), 
);

return $arrFormFileds;