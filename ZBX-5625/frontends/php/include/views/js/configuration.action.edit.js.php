<!-- Discovery Actions-->
<script type="text/x-jquery-tmpl" id="opGroupRowTPL">
<tr id="opGroupRow_#{groupid}">
<td>
	<input name="new_operation[opgroup][#{groupid}][groupid]" type="hidden" value="#{groupid}" />
	<span style="font-size: 1.1em; font-weight: bold;"> #{name} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeOpGroupRow(#{groupid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opTemplateRowTPL">
<tr id="opTemplateRow_#{templateid}">
<td>
	<input name="new_operation[optemplate][#{templateid}][templateid]" type="hidden" value="#{templateid}" />
	<span style="font-size: 1.1em; font-weight: bold;"> #{name} </span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeOpTemplateRow(#{templateid});" />
</td>
</tr>
</script>

<!-- Trigger Actions-->
<script type="text/x-jquery-tmpl" id="opmsgUsrgrpRowTPL">
<tr id="opmsgUsrgrpRow_#{usrgrpid}">
<td>
	<input name="new_operation[opmessage_grp][#{usrgrpid}][usrgrpid]" type="hidden" value="#{usrgrpid}" />
	<span>#{name}</span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeOpmsgUsrgrpRow(#{usrgrpid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsgUserRowTPL">
<tr id="opmsgUserRow_#{userid}">
<td>
	<input name="new_operation[opmessage_usr][#{userid}][userid]" type="hidden" value="#{userid}" />
	<span>#{alias}</span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeOpmsgUserRow(#{userid});" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdGroupRowTPL">
<tr id="opCmdGroupRow_#{groupid}">
<td>
	<input name="new_operation[opcommand_grp][#{groupid}][groupid]" type="hidden" value="#{groupid}" />
	<input name="new_operation[opcommand_grp][#{groupid}][name]" type="hidden" value="#{name}" />
	#{objectCaption}
	<span>#{name}</span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeOpCmdRow(#{groupid}, 'groupid');" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdHostRowTPL">
<tr id="opCmdHostRow_#{hostid}">
<td>
	<input name="new_operation[opcommand_hst][#{hostid}][hostid]" type="hidden" value="#{hostid}" />
	<input name="new_operation[opcommand_hst][#{hostid}][name]" type="hidden" value="#{name}" />
	#{objectCaption}
	<span>#{name}</span>
</td>
<td>
	<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeOpCmdRow(#{hostid}, 'hostid');" />
</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opcmdEditFormTPL">
<br/>
<div id="opcmdEditForm" class="objectgroup border_dotted ui-corner-all inlineblock">
<table class="formElementTable" style="min-width: 310px;"><tbody>
<tr>
	<td><?php echo _('Target'); ?></td>
	<td>
		<select name="opCmdTarget" class="input select">
			<option value="0"><?php echo CHtml::encode(_('Current host')); ?></option>
			<option value="1"><?php echo CHtml::encode(_('Host')); ?></option>
			<option value="2"><?php echo CHtml::encode(_('Host group')); ?></option>
		</select>
		<div id="opCmdTargetSelect" class="inlineblock">
			<input name="action" type="hidden" value="#{action}" />
			<input name="opCmdId" type="hidden" value="#{opcmdid}" />
			<input name="opCmdTargetObjectId" id="opCmdTargetObjectId" type="hidden" value="#{objectid}" />
			<input name="opCmdTargetObjectName" id="opCmdTargetObjectName" type="text" class="input text" value="#{name}" readonly="readonly" size="30"/>
			<input type="button" class="input link_menu" name="select" value="<?php echo CHtml::encode(_('Select')); ?>" />
		</div>
	</td>
</tr>
<tr>
	<td colspan="2">
		<input type="button" class="input link_menu" name="save" value="#{operationName}" />&nbsp;
		<input type="button" class="input link_menu" name="cancel" value="<?php echo CHtml::encode(_('Cancel')); ?>" />
	</td>
</tr>
</tbody></table>
</div>
</script>

<!-- Script -->
<script type="text/x-jquery-tmpl" id="operationTypesTPL">
<tr id="operationTypeScriptElements" class="hidden">
<td>
	<?php echo CHtml::encode(_('Execute on')); ?>
</td>
<td>
	<div class="objectgroup inlineblock border_dotted ui-corner-all" id="uniqList">
		<div>
			<input type="radio" id="execute_on_agent" name="execute_on" value="0" class="input radio">
			<label for="execute_on_agent"><?php echo CHtml::encode(_('Zabbix agent')); ?></label>
		</div>

		<div>
			<input type="radio" id="execute_on_server" name="execute_on" value="1" class="input radio">
			<label for="execute_on_server"><?php echo CHtml::encode(_('Zabbix server')); ?></label>
		</div>
	</div>
</td>
</tr>
</script>

<script type="text/javascript">
	function removeCondition(index) {
		jQuery('#conditions_' + index).find('*').remove();
		jQuery('#conditions_' + index).remove();

		processTypeOfCalculation();
	}

	function removeOperation(index) {
		jQuery('#operations_' + index).find('*').remove();
		jQuery('#operations_' + index).remove();
	}

	function removeOperationCondition(index) {
		jQuery('#opconditions_' + index).find('*').remove();
		jQuery('#opconditions_' + index).remove();
	}

	function addPopupValues(list) {
		var i,
			value,
			tpl,
			container,
			inlineContainers;

		for (i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}
			value = list.values[i];

			switch (list.object) {
				case 'userid':
					if (jQuery('#opmsgUserRow_' + value.userid).length) {
						continue;
					}

					tpl = new Template(jQuery('#opmsgUserRowTPL').html());
					container = jQuery('#opmsgUserListFooter');
					container.before(tpl.evaluate(value));
					break;
				case 'usrgrpid':
					if (jQuery('#opmsgUsrgrpRow_' + value.usrgrpid).length) {
						continue;
					}

					tpl = new Template(jQuery('#opmsgUsrgrpRowTPL').html());
					container = jQuery('#opmsgUsrgrpListFooter');
					container.before(tpl.evaluate(value));
					break;
				case 'dsc_groupid':
					if (jQuery('#opGroupRow_' + value.groupid).length) {
						continue;
					}

					tpl = new Template(jQuery('#opGroupRowTPL').html());
					container = jQuery('#opGroupListFooter');
					container.before(tpl.evaluate(value));
					break;
				case 'dsc_templateid':
					if (jQuery('#opTemplateRow_' + value.templateid).length) {
						continue;
					}

					tpl = new Template(jQuery('#opTemplateRowTPL').html());
					container = jQuery('#opTemplateListFooter');
					container.before(tpl.evaluate(value));
					break;
				case 'groupid':
					tpl = new Template(jQuery('#opCmdGroupRowTPL').html());

					value.objectCaption = <?php echo CJs::encodeJson(_('Host group').': '); ?>;

					container = jQuery('#opCmdListFooter');
					if (jQuery('#opCmdGroupRow_' + value.groupid).length == 0) {
						container.before(tpl.evaluate(value));
					}
					break;
				case 'hostid':
					tpl = new Template(jQuery('#opCmdHostRowTPL').html());

					if (value.hostid.toString() != '0') {
						value.objectCaption = <?php echo CJs::encodeJson(_('Host').': '); ?>;
					}
					else {
						value.name = <?php echo CJs::encodeJson(_('Current host')); ?>;
					}

					container = jQuery('#opCmdListFooter');
					if (jQuery('#opCmdHostRow_' + value.hostid).length == 0) {
						container.before(tpl.evaluate(value));
					}
					break;
			}

			// IE8 hack to fix inline-block container resizing
			if (jQuery.browser.msie && parseInt(jQuery.browser.version) == 8) {
				inlineContainers = container.parents('.inlineblock').filter(function() {
					return jQuery(this).css('display') == 'inline-block';
				});
				inlineContainers.last().addClass('ie8fix-inline').removeClass('ie8fix-inline');
			}
		}
	}

	function removeOpmsgUsrgrpRow(usrgrpid) {
		jQuery('#opmsgUsrgrpRow_' + usrgrpid).remove();
	}

	function removeOpmsgUserRow(userid) {
		jQuery('#opmsgUserRow_' + userid).remove();
	}

	function removeOpGroupRow(groupid) {
		jQuery('#opGroupRow_' + groupid).remove();
	}

	function removeOpTemplateRow(tplid) {
		jQuery('#opTemplateRow_' + tplid).remove();
	}

	function removeOpCmdRow(opCmdRowId, object) {
		if (object == 'groupid') {
			jQuery('#opCmdGroupRow_' + opCmdRowId).remove();
		}
		else {
			jQuery('#opCmdHostRow_' + opCmdRowId).remove();
		}
	}

	function showOpCmdForm(opCmdId) {
		var objectTPL = {},
			tpl;

		if (jQuery('#opcmdEditForm').length > 0 && !closeOpCmdForm()) {
			return true;
		}

		objectTPL.action = 'create';
		objectTPL.opcmdid = 'new';
		objectTPL.objectid = 0;
		objectTPL.name = '';
		objectTPL.target = 0;
		objectTPL.operationName = <?php echo CJs::encodeJson(_('Add')); ?>;

		tpl = new Template(jQuery('#opcmdEditFormTPL').html());
		jQuery('#opCmdList').after(tpl.evaluate(objectTPL));

		// actions
		jQuery('#opcmdEditForm')
			.find('#opCmdTargetSelect')
			.toggle((objectTPL.target != 0)).end()
			.find('input[name="save"]').click(saveOpCmdForm).end()
			.find('input[name="cancel"]').click(closeOpCmdForm).end()
			.find('input[name="select"]').click(selectOpCmdTarget).end()
			.find('select[name="opCmdTarget"]').val(objectTPL.target).change(changeOpCmdTarget);
	}

	function saveOpCmdForm() {
		var objectForm = jQuery('#opcmdEditForm'),
			object = {};

		object.action = jQuery(objectForm).find('input[name="action"]').val();
		object.target = jQuery(objectForm).find('select[name="opCmdTarget"]').val();

		if (object.target.toString() == '2') {
			object.object = 'groupid';
			object.opcommand_grpid = jQuery(objectForm).find('input[name="opCmdId"]').val();
			object.groupid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
			object.name = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();

			if (empty(object.name)) {
				alert(<?php echo CJs::encodeJson(_('You did not specify host group for operation.')); ?>);
				return true;
			}

			if (object.opcommand_grpid == 'new') {
				delete(object['opcommand_grpid']);
			}
		}
		else {
			object.object = 'hostid';
			object.opcommand_hstid = jQuery(objectForm).find('input[name="opCmdId"]').val();
			object.hostid = jQuery(objectForm).find('input[name="opCmdTargetObjectId"]').val();
			object.name = jQuery(objectForm).find('input[name="opCmdTargetObjectName"]').val();

			if (object.target.toString() != '0' && empty(object.name)) {
				alert(<?php echo CJs::encodeJson(_('You did not specify host for operation.')); ?>);
				return true;
			}

			if (object.opcommand_hstid == 'new') {
				delete(object['opcommand_hstid']);
			}
		}

		addPopupValues({'object': object.object, 'values': [object]});
		jQuery(objectForm).remove();
	}

	function selectOpCmdTarget() {
		var target = jQuery('#opcmdEditForm select[name="opCmdTarget"]').val();

		if (target.toString() == '2') {
			PopUp("popup.php?srctbl=host_group&srcfld1=groupid&srcfld2=name&dstfrm=action.edit&dstfld1=opCmdTargetObjectId&dstfld2=opCmdTargetObjectName&writeonly=1&noempty=1", 480, 480);
		}
		else {
			PopUp('popup.php?srctbl=hosts&srcfld1=hostid&srcfld2=name&dstfrm=action.edit&dstfld1=opCmdTargetObjectId&dstfld2=opCmdTargetObjectName&real_hosts=1&writeonly=1&noempty=1', 780, 480);
		}
	}

	function changeOpCmdTarget() {
		jQuery('#opcmdEditForm')
			.find('#opCmdTargetSelect').toggle((jQuery('#opcmdEditForm select[name="opCmdTarget"]').val() > 0)).end()
			.find('input[name="opCmdTargetObjectId"]').val(0).end()
			.find('input[name="opCmdTargetObjectName"]').val('').end();
	}

	function closeOpCmdForm() {
		jQuery('#opCmdDraft').attr('id', jQuery('#opCmdDraft').attr('origid'));
		jQuery('#opcmdEditForm').remove();
		return true;
	}

	function showOpTypeForm() {
		var currentOpType,
			opTypeFields,
			showFields = [],
			fieldClass,
			f;

		if (jQuery('#new_operation_opcommand_type').length == 0) {
			return;
		}

		currentOpType = jQuery('#new_operation_opcommand_type').val();

		opTypeFields = {
			class_opcommand_userscript: [ZBX_SCRIPT_TYPES.userscript],
			class_opcommand_execute_on: [ZBX_SCRIPT_TYPES.script],
			class_opcommand_port: [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
			class_opcommand_command: [ZBX_SCRIPT_TYPES.script, ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
			class_opcommand_command_ipmi: [ZBX_SCRIPT_TYPES.ipmi],
			class_authentication_method: [ZBX_SCRIPT_TYPES.ssh],
			class_authentication_username: [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
			class_authentication_publickey: [],
			class_authentication_privatekey: [],
			class_authentication_password: [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
			class_authentication_passphrase: [ZBX_SCRIPT_TYPES.ssh]
		};

		for (fieldClass in opTypeFields) {
			jQuery('#operationlist .' + fieldClass).toggleClass('hidden', true).find(':input').prop('disabled', true);

			for (f = 0; f < opTypeFields[fieldClass].length; f++) {
				if (currentOpType == opTypeFields[fieldClass][f]) {
					showFields.push(fieldClass);
				}
			}
		}

		for (f = 0; f < showFields.length; f++) {
			jQuery('#operationlist .' + showFields[f]).toggleClass('hidden', false).find(':input').prop('disabled', false);
		}

		if (jQuery.inArray('class_authentication_method', showFields) !== -1) {
			showOpTypeAuth();
		}
	}

	function showOpTypeAuth() {
		var currentOpTypeAuth = parseInt(jQuery('#new_operation_opcommand_authtype').val(), 10);

		if (currentOpTypeAuth === <?php echo ITEM_AUTHTYPE_PASSWORD; ?>) {
			jQuery('#operationlist .class_authentication_publickey').toggleClass('hidden', true);
			jQuery('#new_operation_opcommand_publickey').prop('disabled', true);
			jQuery('#operationlist .class_authentication_privatekey').toggleClass('hidden', true);
			jQuery('#new_operation_opcommand_privatekey').prop('disabled', true);
			jQuery('.class_authentication_password').toggleClass('hidden', false);
			jQuery('.class_authentication_passphrase').toggleClass('hidden', true);
			jQuery('#new_operation_opcommand_password').prop('disabled', false);
			jQuery('#new_operation_opcommand_passphrase').prop('disabled', true);
		}
		else {
			jQuery('#operationlist .class_authentication_publickey').toggleClass('hidden', false).prop('disabled', false);
			jQuery('#new_operation_opcommand_publickey').prop('disabled', false);
			jQuery('#operationlist .class_authentication_privatekey').toggleClass('hidden', false).prop('disabled', false);
			jQuery('#new_operation_opcommand_privatekey').prop('disabled', false);
			jQuery('.class_authentication_password').toggleClass('hidden', true);
			jQuery('.class_authentication_passphrase').toggleClass('hidden', false);
			jQuery('#new_operation_opcommand_password').prop('disabled', true);
			jQuery('#new_operation_opcommand_passphrase').prop('disabled', false);
		}
	}

	var ZBX_SCRIPT_TYPES = {};
	ZBX_SCRIPT_TYPES['script'] = <?php echo ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT; ?>;
	ZBX_SCRIPT_TYPES['ipmi'] = <?php echo ZBX_SCRIPT_TYPE_IPMI; ?>;
	ZBX_SCRIPT_TYPES['telnet'] = <?php echo ZBX_SCRIPT_TYPE_TELNET; ?>;
	ZBX_SCRIPT_TYPES['ssh'] = <?php echo ZBX_SCRIPT_TYPE_SSH; ?>;
	ZBX_SCRIPT_TYPES['userscript'] = <?php echo ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT; ?>;

	function processTypeOfCalculation() {
		var count = jQuery('#conditionTable tr').length - 1;
		if (count > 1) {
			jQuery('#conditionRow').css('display', 'block');

			var groupOperator = '',
				globalOperator = '',
				str = '';

			if (jQuery('#evaltype').val() == <?php echo ACTION_EVAL_TYPE_AND; ?>) {
				groupOperator = <?php echo CJs::encodeJson(_('and')); ?>;
				globalOperator = <?php echo CJs::encodeJson(_('and')); ?>;
			}
			else if (jQuery('#evaltype').val() == <?php echo ACTION_EVAL_TYPE_OR; ?>) {
				groupOperator = <?php echo CJs::encodeJson(_('or')); ?>;
				globalOperator = <?php echo CJs::encodeJson(_('or')); ?>;
			}
			else {
				groupOperator = <?php echo CJs::encodeJson(_('or')); ?>;
				globalOperator = <?php echo CJs::encodeJson(_('and')); ?>;
			}

			var conditionTypeHold = '';
			jQuery('#conditionTable tr').not('.header').each(function() {
				var conditionType = jQuery(this).find('.label').data('conditiontype');

				if (empty(str)) {
					str = ' (' + jQuery(this).find('.label').data('label');
					conditionTypeHold = conditionType;
				}
				else {
					if (conditionType != conditionTypeHold) {
						str += ') ' + globalOperator + ' (' + jQuery(this).find('.label').data('label');
						conditionTypeHold = conditionType;
					}
					else {
						str += ' ' + groupOperator + ' ' + jQuery(this).find('.label').data('label');
					}
				}
			});
			str += ')';

			jQuery('#conditionLabel').html(str);
		}
		else {
			jQuery('#conditionRow').css('display', 'none');
		}
	}

	jQuery(document).ready(function() {
		setTimeout(function() {jQuery('#name').focus()}, 10);

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#actionid, #delete, #clone').remove();
			jQuery('#cancel').addClass('ui-corner-left');
			jQuery('#name').focus();
		});

		// new operation form command type
		showOpTypeForm();

		jQuery('#select_opcommand_script').click(function() {
			PopUp('popup.php?srctbl=scripts&srcfld1=scriptid&srcfld2=name&dstfrm=action.edit&dstfld1=new_operation_opcommand_scriptid&dstfld2=new_operation_opcommand_script', 480, 720);
		})

		processTypeOfCalculation();
	});
</script>
