<!-- email fields -->
<script type="text/x-jquery-tmpl" id="<?php echo MEDIA_TYPE_EMAIL; ?>-fields">
	<li class="formrow">
		<div class="dt right">
			<label for="smtp_server"><?php echo CHtml::encode(_('SMTP server')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="localhost" name="smtp_server" id="smtp_server" class="input text" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="smtp_helo"><?php echo CHtml::encode(_('SMTP helo')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="localhost" name="smtp_helo" id="smtp_helo" class="input text" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="smtp_email"><?php echo CHtml::encode(_('SMTP email')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="zabbix@localhost" name="smtp_email" id="smtp_email" class="input text" />
		</div>
	</li>
</script>

<!-- script fields -->
<script type="text/x-jquery-tmpl" id="<?php echo MEDIA_TYPE_EXEC; ?>-fields">
	<li class="formrow">
		<div class="dt right">
			<label for="exec_path"><?php echo CHtml::encode(_('Script name')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="" name="exec_path" id="exec_path" class="input text" />
		</div>
	</li>
</script>

<!-- SMS fields -->
<script type="text/x-jquery-tmpl" id="<?php echo MEDIA_TYPE_SMS; ?>-fields">
	<li class="formrow">
		<div class="dt right">
			<label for="gsm_modem"><?php echo CHtml::encode(_('GSM modem')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="/dev/ttyS0" name="gsm_modem" id="gsm_modem" class="input text" />
		</div>
	</li>
</script>

<!-- Jabber fields -->
<script type="text/x-jquery-tmpl" id="<?php echo MEDIA_TYPE_JABBER; ?>-fields">
	<li class="formrow">
		<div class="dt right">
			<label for="username"><?php echo CHtml::encode(_('Jabber identifier')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="user@server" name="username" id="username" class="input text" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="password"><?php echo CHtml::encode(_('Password')); ?></label>
		</div>
		<div class="dd">
			<input type="password" maxlength="255" size="<?php echo ZBX_TEXTBOX_SMALL_SIZE; ?>" value="" name="password" id="password" class="input password">
		</div>
	</li>
</script>

<!-- Ez Texting fields -->
<script type="text/x-jquery-tmpl" id="<?php echo MEDIA_TYPE_EZ_TEXTING; ?>-fields">
	<li class="formrow">
		<div class="dt right">
			<label for="username"><?php echo CHtml::encode(_('Username')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="username" name="username" id="username" class="input text" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="password"><?php echo CHtml::encode(_('Password')); ?></label>
		</div>
		<div class="dd">
			<input type="password" maxlength="255" size="<?php echo ZBX_TEXTBOX_SMALL_SIZE; ?>" value="" name="password" id="password" class="input password">
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="exec_path"><?php echo CHtml::encode(_('Message text limit')); ?></label>
		</div>
		<div class="dd">
			<select size="1" name="exec_path" id="exec_path" class="input select">
				<option selected="selected" value="<?php echo EZ_TEXTING_LIMIT_USA; ?>"><?php echo CHtml::encode(_('USA (160 characters)')); ?></option>
				<option value="<?php echo EZ_TEXTING_LIMIT_CANADA; ?>"><?php echo CHtml::encode(_('Canada (136 characters)')); ?></option>
			</select>
		</div>
	</li>
</script>

<!-- Remedy Service fields -->
<script type="text/x-jquery-tmpl" id="<?php echo MEDIA_TYPE_REMEDY; ?>-fields">
	<li class="formrow">
		<div class="dt right">
			<label for="smtp_server"><?php echo CHtml::encode(_('Remedy Service URL')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="localhost" name="smtp_server" id="smtp_server" class="input text" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="username"><?php echo CHtml::encode(_('Username')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="" name="username" id="username" class="input text" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="password"><?php echo CHtml::encode(_('Password')); ?></label>
		</div>
		<div class="dd">
			<input type="password" maxlength="255" size="<?php echo ZBX_TEXTBOX_SMALL_SIZE; ?>" value="" name="password" id="password" class="input password">
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="smtp_helo"><?php echo CHtml::encode(_('Proxy')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="" name="smtp_helo" id="smtp_helo" class="input text" placeholder="http://[username[:password]@]proxy.example.com[:port]" />
		</div>
	</li>
	<li class="formrow">
		<div class="dt right">
			<label for="exec_path"><?php echo CHtml::encode(_('Company name')); ?></label>
		</div>
		<div class="dd">
			<input type="text" maxlength="255" size="<?php echo ZBX_TEXTBOX_STANDARD_SIZE; ?>" value="" name="exec_path" id="exec_path" class="input text" />
		</div>
	</li>
</script>

<script type="text/javascript">
	function getMediaTypeFields(fieldList) {
		var i = 0,
			j = 0,
			cnt = fieldList.length - 1,
			fields = new Array();

		fieldList.each(function() {
			if (i > 1 && i < cnt) {
				fields[j++] = this;
			}
			i++;
		});

		return fields;
	}

	function removeMediaTypeFields(fieldList) {
		var i = 0,
			cnt = fieldList.length - 1;

		fieldList.each(function() {
			if (i > 1 && i < cnt) {
				$(this).remove();
			}
			i++;
		});
	}

	jQuery(document).ready(function($) {
		var type = $('#type'),
			selectedType = type.val(),
			fieldList = $('.formlist').children(),
			originalFields = getMediaTypeFields(fieldList);

		type.change(function() {
			var fieldList = $('.formlist').children(),
				destinationTemplate = '';

			// when at some point we switch back to original fields, replace template with original data we had
			if (type.val() == selectedType) {
				$(originalFields).each(function(index, value) {
					destinationTemplate += value.outerHTML;
				});
			}
			else {
				destinationTemplate = $('#'+type.val()+'-fields').html();
			}

			removeMediaTypeFields(fieldList);

			var tpl = new Template(destinationTemplate);

			$(fieldList[1]).after(tpl.evaluate());
		});
	});

</script>
