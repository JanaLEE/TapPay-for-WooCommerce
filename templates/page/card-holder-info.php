<?php defined('ABSPATH')||exit();?>
<div id="<?php echo $strHandlerID;?>_card-holder-info">

	<fieldset>
		<label for="<?php echo $strHandlerID;?>_phone_number">
			<span>持卡人手機</span>
		</label>
		<input
			type="text"
			id="<?php echo $strHandlerID;?>_phone_number"
			name="<?php echo $strHandlerID;?>_phone_number"
			value="<?php echo apply_filters($strHandlerID.'_card-holder-phone-number', NULL);?>"
			placeHolder="09 開頭或包含加號之 E.164 格式 ( +886 )" />
	</fieldset>

	<fieldset>
		<label for="<?php echo $strHandlerID;?>_name">
			<span>持卡人姓名</span>
		</label>
		<input
			type="text"
			id="<?php echo $strHandlerID;?>_name"
			name="<?php echo $strHandlerID;?>_name"
			value="<?php echo apply_filters($strHandlerID.'_card-holder-name', NULL);?>"
			placeHolder="請輸入持卡人姓名" />
	</fieldset>

	<fieldset>
		<label for="<?php echo $strHandlerID;?>_email">
			<span>電子郵件</span>
		</label>
		<input
			type="email"
			id="<?php echo $strHandlerID;?>_email"
			name="<?php echo $strHandlerID;?>_email"
			value="<?php echo apply_filters($strHandlerID.'_card-holder-email', NULL);?>"
			placeHolder="請輸入電子郵件" />
	</fieldset>

</div>