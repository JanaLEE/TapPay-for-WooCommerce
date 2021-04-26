<?php
/*
 * Plugin Name:	Tap Pay Pro
 * Description:	Tap Pay for WooCommerce Pro by cloudwp.
 * Author:			JanaLEE
 * Author URI:	https://cloudwp.pro/
 * Text Domain: cw-tappay
 * Domain Path: /languages/
 * Version:			1.2.1
 * Tested up to:					4.9.7
 * WC requires at least:	2.6
 * WC tested up to:				3.5.5
 * Plugin Slug: cw-tappay
 * Published:		not yet
 */
/*<cloudwp />*/
namespace cloudwp\TapPay;

if(!defined('ABSPATH'))exit;

define('CWTAPPAY_File', __FILE__);
define('CWTAPPAY_BaseName', plugin_basename(CWTAPPAY_File));
define('CWTAPPAY_DIR', dirname(CWTAPPAY_File));
define('CWTAPPAY_URL', plugin_dir_url(CWTAPPAY_File));

class Handler{

	//const ID='cw-tappay';
	const ID='cwtpfw';

	function __construct(){
		include_once CWTAPPAY_DIR.'/includes/class-action.php';
		include_once CWTAPPAY_DIR.'/includes/class-admin.php';
		include_once CWTAPPAY_DIR.'/includes/class-basic.php';
		include_once CWTAPPAY_DIR.'/includes/class-get.php';
		include_once CWTAPPAY_DIR.'/includes/class-checkout-process.php';
		include_once CWTAPPAY_DIR.'/includes/class-subscription.php';
		include_once CWTAPPAY_DIR.'/includes/class-extend.php';

		add_action('plugins_loaded', __CLASS__.'::LoadClasses');
		add_action('woocommerce_api_'.strtolower(Get::ClassName($this)), __CLASS__.'::HandleCallback');
		add_filter('woocommerce_payment_gateways', __CLASS__.'::AddGateways');

		new Extend();
	}

	public static function LoadClasses(){
		if(class_exists('WC_Payment_Gateway')){
			include_once CWTAPPAY_DIR.'/includes/class-payment-gateway.php';
		}
	}

	public static function AddGateways($methods){
		$methods[]=__NAMESPACE__.'\WC_Gateway_TapPay';
		return $methods;
	}

	public static function HandleCallback(){}
}

new Handler;