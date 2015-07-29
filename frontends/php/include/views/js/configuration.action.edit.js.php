<!-- Discovery Actions-->
<script type="text/x-jquery-tmpl" id="opGroupRowTPL">
<tr id="opGroupRow_#{groupid}">
	<td>
		<input name="new_operation[opgroup][#{groupid}][groupid]" type="hidden" value="#{groupid}" />
		<span style="font-size: 1.1em; font-weight: bold;"> #{name} </span>
	</td>
	<td>
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpGroupRow('#{groupid}');"><?= _('Remove') ?></button>
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
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpTemplateRow('#{templateid}');"><?= _('Remove') ?></button>
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
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpmsgUsrgrpRow('#{usrgrpid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsgUserRowTPL">
<tr id="opmsgUserRow_#{userid}">
	<td>
		<input name="new_operation[opmessage_usr][#{userid}][userid]" type="hidden" value="#{userid}" />
		<span>#{fullname}</span>
	</td>
	<td>
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpmsgUserRow('#{userid}');"><?= _('Remove') ?></button>
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
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpCmdRow('#{groupid}', 'groupid');"><?= _('Remove') ?></button>
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
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpCmdRow('#{hostid}', 'hostid');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opcmdEditFormTPL">
<div id="opcmdEditForm">
	<table class="<?= ZBX_STYLE_TABLE_FORMS_SEPARATOR ?>" style="min-width: 310px;">
		<tbody>
		<tr>
			<td><?= _('Target') ?></td>
			<td>
				<select name="opCmdTarget" class="input select">
					<option value="current"><?= CHtml::encode(_('Current host')) ?></option>
					<option value="host"><?= CHtml::encode(_('Host')) ?></option>
					<option value="hostGroup"><?= CHtml::encode(_('Host group')) ?></option>
				</select>
			</td>
			<td style="padding-left: 0;">
				<div id="opCmdTargetSelect" class="inlineblock">
					<input name="opCmdId" type="hidden" value="#{opcmdid}" />
				</div>
			</td>
		</tr>
		<tr>
			<td colspan="3">
				<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="save">#{operationName}</button>
				&nbsp;
				<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="cancel"><?= CHtml::encode(_('Cancel')) ?></button>
			</td>
		</tr>
		</tbody>
	</table>
</div>
</script>

<!-- Script -->
<script type="text/x-jquery-tmpl" id="operationTypesTPL">
<tr id="operationTypeScriptElements" class="hidden">
	<td><?= CHtml::encode(_('Execute on')) ?></td>
	<td>
		<div class="<?= ZBX_STYLE_TABLE_FORMS_SEPARATOR ?>" id="uniqList">
			<div>
				<input type="radio" id="execute_on_agent" name="execute_on" value="0" class="input radio">
				<label for="execute_on_agent"><?= CHtml::encode(_('Zabbix agent')) ?></label>
			</div>
			<div>
				<input type="radio" id="execute_on_server" name="execute_on" value="1" class="input radio">
				<label for="execute_on_server"><?= CHtml::encode(_('Zabbix server')) ?></label>
			</div>
		</div>
	</td>
</tr>
</script>

<script type="text/javascript">
	var ZBX_SCRIPT_TYPES = {
		script: <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>,
		ipmi: <?= ZBX_SCRIPT_TYPE_IPMI ?>,
		telnet: <?= ZBX_SCRIPT_TYPE_TELNET ?>,
		ssh: <?= ZBX_SCRIPT_TYPE_SSH ?>,
		userscript: <?= ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT ?>
	};

	/**
	 * @see init.js add.popup event
	 */
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

					value.objectCaption = <?= CJs::encodeJson(_('Host group').NAME_DELIMITER) ?>;

					container = jQuery('#opCmdListFooter');
					if (jQuery('#opCmdGroupRow_' + value.groupid).length == 0) {
						container.before(tpl.evaluate(value));
					}
					break;

				case 'hostid':
					tpl = new Template(jQuery('#opCmdHostRowTPL').html());

					if (value.hostid.toString() != '0') {
						value.objectCaption = <?= CJs::encodeJson(_('Host').NAME_DELIMITER) ?>;
					}
					else {
						value.name = <?= CJs::encodeJson(_('Current host')) ?>;
					}

					container = jQuery('#opCmdListFooter');
					if (jQuery('#opCmdHostRow_' + value.hostid).length == 0) {
						container.before(tpl.evaluate(value));
					}
					break;
			}
		}
	}

	function removeCondition(index) {
		jQuery('#conditions_' + index).find('*').remove();
		jQuery('#conditions_' + index).remove();

		processTypeOfCalculation();
	}

	function removeOperation(index) {
		var row = jQuery('#operations_' + index);
		var rowParent = row.parent();

		row.find('*').remove();
		row.remove();
	}

	function removeOperationCondition(index) {
		jQuery('#opconditions_' + index).find('*').remove();
		jQuery('#opconditions_' + index).remove();

		processOperationTypeOfCalculation();
	}

	function removeOpmsgUsrgrpRow(usrgrpid) {
		var row = jQuery('#opmsgUsrgrpRow_' + usrgrpid);
		var rowParent = row.parent();

		row.remove();
	}

	function removeOpmsgUserRow(userid) {
		var row = jQuery('#opmsgUserRow_' + userid);
		var rowParent = row.parent();

		row.remove();
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

		objectTPL.opcmdid = 'new';
		objectTPL.objectid = 0;
		objectTPL.name = '';
		objectTPL.target = 'current';
		objectTPL.operationName = <?= CJs::encodeJson(_('Add')) ?>;

		tpl = new Template(jQuery('#opcmdEditFormTPL').html());
		jQuery('#opCmdList').after(tpl.evaluate(objectTPL));

		// actions
		jQuery('#opcmdEditForm')
			.find('#opCmdTargetSelect')
			.toggle(objectTPL.target != 'current').end()
			.find('button[name="save"]').click(saveOpCmdForm).end()
			.find('button[name="cancel"]').click(closeOpCmdForm).end()
			.find('select[name="opCmdTarget"]').val(objectTPL.target).change(changeOpCmdTarget);
	}

	function saveOpCmdForm() {
		var objectForm = jQuery('#opcmdEditForm'),
			object = {};

		object.target = jQuery(objectForm).find('select[name="opCmdTarget"]').val();

		// host group
		if (object.target == 'hostGroup') {
			var values = jQuery('#opCmdTargetObject').multiSelect('getData');

			object.opcommand_grpid = jQuery(objectForm).find('input[name="opCmdId"]').val();

			if (empty(values)) {
				alert(<?= CJs::encodeJson(_('You did not specify host group for operation.')) ?>);

				return true;
			}
			else {
				if (object.opcommand_grpid == 'new') {
					object['opcommand_grpid'] = null;
				}
				for (var key in values) {
					var data = values[key];

					if (!empty(data.id)) {
						addPopupValues({
							object: 'groupid',
							values: [{
								target: object.target,
								opcommand_grpid: object.opcommand_grpid,
								groupid: data.id,
								name: data.name
							}]
						});
					}
				}
			}
		}

		// host
		else if (object.target == 'host') {
			var values = jQuery('#opCmdTargetObject').multiSelect('getData');

			object.opcommand_hstid = jQuery(objectForm).find('input[name="opCmdId"]').val();

			if (object.target != 'current' && empty(values)) {
				alert(<?= CJs::encodeJson(_('You did not specify host for operation.')) ?>);

				return true;
			}
			else {
				if (object.opcommand_hstid == 'new') {
					object['opcommand_grpid'] = null;
				}
				for (var key in values) {
					var data = values[key];

					if (!empty(data.id)) {
						addPopupValues({
							object: 'hostid',
							values: [{
								target: object.target,
								opcommand_hstid: object.opcommand_hstid,
								hostid: data.id,
								name: data.name
							}]
						});
					}
				}
			}
		}

		// current
		else {
			object.object = 'hostid';
			object.opcommand_hstid = jQuery(objectForm).find('input[name="opCmdId"]').val();
			object.hostid = 0;
			object.name = '';

			if (object.opcommand_hstid == 'new') {
				delete(object['opcommand_hstid']);
			}

			addPopupValues({object: object.object, values: [object]});
		}

		jQuery(objectForm).remove();
	}

	function changeOpCmdTarget() {
		var opCmdTarget = jQuery('#opcmdEditForm select[name="opCmdTarget"]').val();

		jQuery('#opCmdTargetSelect').toggle(opCmdTarget != 'current');

		// multiselect
		if (opCmdTarget != 'current') {
			jQuery('.multiselect-wrapper').remove();

			var opCmdTargetObject = jQuery('<div>', {
				id: 'opCmdTargetObject',
				'class': 'multiselect'
			});

			jQuery('#opCmdTargetSelect').append(opCmdTargetObject);

			var srctbl = (opCmdTarget == 'host') ? 'hosts' : 'host_groups',
				srcfld1 = (opCmdTarget == 'host') ? 'hostid' : 'groupid';

			jQuery(opCmdTargetObject).multiSelectHelper({
				id: 'opCmdTargetObject',
				objectName: (opCmdTarget == 'host') ? 'hosts' : 'hostGroup',
				name: 'opCmdTargetObjectName[]',
				objectOptions: {
					editable: true
				},
				popup: {
					parameters: 'srctbl=' + srctbl + '&dstfrm=action.edit&dstfld1=opCmdTargetObject&srcfld1=' +
						srcfld1 + '&writeonly=1&multiselect=1',
					width: 450,
					height: 450
				}
			});
		}
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

		if (currentOpTypeAuth === <?= ITEM_AUTHTYPE_PASSWORD ?>) {
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

	function processTypeOfCalculation() {
		if(jQuery('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
			jQuery('#conditionLabel').hide();
			jQuery('#formula').show();
		}
		else {
			jQuery('#conditionLabel').show();
			jQuery('#formula').hide();
		}

		var labels = jQuery('#conditionTable .label');

		if (labels.length > 1) {
			jQuery('#conditionRow').css('display', '');

			var conditions = [];
			labels.each(function(index, label) {
				label = jQuery(label);

				conditions.push({
					id: label.data('formulaid'),
					type: label.data('conditiontype')
				});
			});

			jQuery('#conditionLabel').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));
		}
		else {
			jQuery('#conditionRow').css('display', 'none');
		}
	}

	function processOperationTypeOfCalculation() {
		var labels = jQuery('#operationConditionTable .label');

		if (labels.length > 1) {
			jQuery('#operationConditionRow').css('display', '');

			var conditions = [];
			labels.each(function(index, label) {
				label = jQuery(label);

				conditions.push({
					id: label.data('formulaid'),
					type: label.data('conditiontype')
				});
			});

			jQuery('#operationConditionLabel').html(getConditionFormula(conditions, +jQuery('#operationEvaltype').val()));
		}
		else {
			jQuery('#operationConditionRow').css('display', 'none');
		}
	}

	function addDiscoveryTemplates() {
		var values = jQuery('#discoveryTemplates').multiSelect('getData');

		for (var key in values) {
			var data = values[key];

			if (!empty(data.id)) {
				addPopupValues({
					object: 'dsc_templateid',
					values: [{
						templateid: data.id,
						name: data.name
					}]
				});
			}
		}

		jQuery('#discoveryTemplates').multiSelect('clean');
	}

	function addDiscoveryHostGroup() {
		var values = jQuery('#discoveryHostGroup').multiSelect('getData');

		for (var key in values) {
			var data = values[key];

			if (!empty(data.id)) {
				addPopupValues({
					object: 'dsc_groupid',
					values: [{
						groupid: data.id,
						name: data.name
					}]
				});
			}
		}

		jQuery('#discoveryHostGroup').multiSelect('clean');
	}

	jQuery(document).ready(function() {
		// clone button
		jQuery('#clone').click(function() {
			jQuery('#actionid, #delete, #clone').remove();
			jQuery('#update').button('option', 'label', <?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});

			var operationIdNameRegex = /operations\[\d+\]\[operationid\]/;
			jQuery('input[name^=operations]').each(function() {
				if ($(this).getAttribute('name').match(operationIdNameRegex)) {
					$(this).remove();
				}
			});

			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});

		jQuery('#esc_period').change(function() {
			jQuery('form[name="action.edit"]').submit();
		});

		// new operation form command type
		showOpTypeForm();

		jQuery('#select_opcommand_script').click(function() {
			PopUp('popup.php?srctbl=scripts&srcfld1=scriptid&srcfld2=name&dstfrm=action.edit&dstfld1=new_operation_opcommand_scriptid&dstfld2=new_operation_opcommand_script');
		});

		processTypeOfCalculation();
		processOperationTypeOfCalculation();
	});
</script>
