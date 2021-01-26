<?php defined('ABSPATH')||exit();?>
<div id="<?php echo $strHandlerID;?>_instalment">

	<label>
		<span>信用卡分期</span>

		<select name="<?php echo $strHandlerID;?>_instalment">
			<option value="0">不分期</option>
			<?php foreach($arrInstalmentAllow as $key=>$value):?>
			<option value="<?php echo $key;?>"><?php echo $value;?></option>
			<?php endforeach;?>
		</select>
	</label>

</div>