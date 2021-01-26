<?php defined('ABSPATH')||exit();?>
<div id="<?php echo $strHandlerID;?>_payment-data">

	<fieldset id="wc-<?php echo esc_attr($strHandlerID);?>-cc-form" class='wc-credit-card-form wc-payment-form'>

		<?php if($intFields==1):?>

		<!--Tappay iframe-->
		<div id="<?php echo $strHandlerID;?>_iframe"></div>
		<!--Tappay iframe-->

		<?php else:?>
		<div class="tpfield" id="card-number"></div>
		<div class="tpfield" id="card-expiration-date"></div>
		<div class="tpfield" id="card-ccv"></div>

		<?php endif;?>

		<?php if($intShowSavedCard):?>
		<!--Tappay save card-->
		<label for="<?php echo $strHandlerID;?>_remember">
			<input type="checkbox" id="<?php echo $strHandlerID;?>_remember" name="<?php echo $strHandlerID;?>_remember"<?php echo $strChecked;?> value="1" />
			<span>
				<svg x="0px" y="0px" width="23px" height="23px" viewBox="0 0 23 23">
					<polyline points="16.609,8.252 10.113,14.749 6.391,11.026 "/>
					<circle cx="11.5" cy="11.5" r="10"/>
				</svg>
				<?php echo apply_filters($strHandlerID.'_save_payment_method_checkbox_text', $strSaveCardText);?>
			</span>
		</label>
		<!--Tappay save card-->

		<?php
		/*
		 * 2019.09.23
		 * 有些網站當 <label for="cwtpfw_remember"> 與 <input id="cwtpfw_remember" /> 不存在時
		 * 造成 TapPay SDK 發生錯誤 ( 無法載入輸入卡號的 iframe )
		 */
		else:
		?>
		<label for="<?php echo $strHandlerID;?>_remember" style="display:none;">
			<input type="checkbox" id="<?php echo $strHandlerID;?>_remember" name="<?php echo $strHandlerID;?>_remember" value="1" disabled="disabled" />
		</label>

		<?php endif;?>

	</fieldset>

	<?php do_action($strHandlerID.'_after-payment-fields');?>

</div>

<?php
do_action($strHandlerID.'_after-payment-data');