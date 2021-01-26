<?php defined('ABSPATH')||exit();?>
<div class="edit_address" style="clear:left;">

	<p class="form-field">
		<label>TapPay 付款資訊</label>
		<select name="<?php echo $strHandlerID;?>_token">
			<?php
			$arrData=array();

			if(is_a($stdDefaultToken, 'WC_Payment_Token_CC')):

				$arrData			=$stdDefaultToken->get_data();
				$intDefaultID	=$arrData['id'];
				?>
			<option value="<?php echo $arrData['id'];?>">
				**** **** **** <?php echo $arrData['last4'];?>
			</option>
			<?php
			endif;

			foreach($arrTokens as $key=>$value):
				if($key==$intDefaultID)continue;
				$arrData=$value->get_data();
				?>
			<option value="<?php echo $key;?>">
				**** **** **** <?php echo $arrData['last4'];?>
			</option>
			<?php endforeach;?>
		</select>
	</p>

</div>