<!-- Trigger Actions-->
<script type="text/x-jquery-tmpl" id="opmsgUsrgrpRowTPL">
<tr id="#{row}#{usrgrpid}">
	<td>
		<input name="#{field}[opmessage_grp][#{usrgrpid}][usrgrpid]" type="hidden" value="#{usrgrpid}" />
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeRow('#{row}#{usrgrpid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsgUserRowTPL">
<tr id="#{row}#{id}">
	<td>
		<input name="#{field}[opmessage_usr][#{id}][userid]" type="hidden" value="#{id}" />
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeRow('#{row}#{id}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdGroupRowTPL">
<tr id="#{row}#{groupid}">
	<td>
		<input name="#{field}[opcommand_grp][#{groupid}][groupid]" type="hidden" value="#{groupid}" />
		<input name="#{field}[opcommand_grp][#{groupid}][name]" type="hidden" value="#{name}" />
		#{objectCaption}
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeRow('#{row}#{groupid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opCmdHostRowTPL">
<tr id="#{row}#{hostid}">
	<td>
		<input name="#{field}[opcommand_hst][#{hostid}][hostid]" type="hidden" value="#{hostid}" />
		<input name="#{field}[opcommand_hst][#{hostid}][name]" type="hidden" value="#{name}" />
		#{objectCaption}
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeRow('#{row}#{hostid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opcmdEditFormTPL">
<li>
	<div class="<?= ZBX_STYLE_TABLE_FORMS_TD_LEFT ?>"></div>
	<div class="<?= ZBX_STYLE_TABLE_FORMS_TD_RIGHT ?>">
		<div id="opcmdEditForm" class="<?= ZBX_STYLE_TABLE_FORMS_SEPARATOR ?>" style="min-width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px;">
			<table style="width: 100%;">
				<tbody>
				<tr>
					<?= (new CCol([
							_('Target'),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							new CComboBox('opCmdTarget', null, null, [
								'current' => _('Current host'),
								'host' => _('Host'),
								'hostGroup' => _('Host group')
							]),
							(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
							new CVar('opCmdId', '#{opcmdid}')
						]))->toString()
					?>
				</tr>
				<tr>
					<?= (new CCol(
							new CHorList([
								(new CButton('save', '#{operationName}'))->addClass(ZBX_STYLE_BTN_LINK),
								(new CButton('cancel', _('Cancel')))->addClass(ZBX_STYLE_BTN_LINK)
							])
						))
							->setColSpan(3)
							->toString()
					?>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
</li>
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
					if (list.parentId == 'opmsgUserListFooter') {
						value.field = 'new_operation';
						value.row = 'opmsgUserRow_';
					}
					else {
						value.field = 'new_recovery_operation';
						value.row = 'recOpmsgUserRow_';
					}

					if (jQuery('#' + value.row + value.id).length) {
						continue;
					}

					tpl = new Template(jQuery('#opmsgUserRowTPL').html());
					container = jQuery('#' + list.parentId);
					container.before(tpl.evaluate(value));
					break;

				case 'usrgrpid':
					if (list.parentId == 'opmsgUsrgrpListFooter') {
						value.field = 'new_operation';
						value.row = 'opmsgUsrgrpRow_';
					}
					else {
						value.field = 'new_recovery_operation';
						value.row = 'recOpmsgUsrgrpRow_';
					}

					if (jQuery('#' + value.row + value.id).length) {
						continue;
					}

					tpl = new Template(jQuery('#opmsgUsrgrpRowTPL').html());
					container = jQuery('#' + list.parentId);
					container.before(tpl.evaluate(value));
					break;

				case 'groupid':
					if (list.parentId == 'opCmdListFooter') {
						value.field = 'new_operation';
						value.row = 'opCmdGroupRow_';
					}
					else {
						value.field = 'new_recovery_operation';
						value.row = 'recOpCmdGroupRow_';
					}

					tpl = new Template(jQuery('#opCmdGroupRowTPL').html());

					value.objectCaption = <?= CJs::encodeJson(_('Host group').NAME_DELIMITER) ?>;

					container = jQuery('#' + list.parentId);
					if (jQuery('#' + value.row + value.groupid).length == 0) {
						container.before(tpl.evaluate(value));
					}
					break;

				case 'hostid':
					if (list.parentId == 'opCmdListFooter') {
						value.field = 'new_operation';
						value.row = 'opCmdHostRow_';
					}
					else {
						value.field = 'new_recovery_operation';
						value.row = 'recOpCmdHostRow_';
					}

					tpl = new Template(jQuery('#opCmdHostRowTPL').html());

					if (value.hostid.toString() != '0') {
						value.objectCaption = <?= CJs::encodeJson(_('Host').NAME_DELIMITER) ?>;
					}
					else {
						value.name = <?= CJs::encodeJson(_('Current host')) ?>;
					}

					container = jQuery('#' + list.parentId);
					if (jQuery('#' + value.row + value.hostid).length == 0) {
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

	function removeOperation(index, type) {
		if (type == <?= ACTION_OPERATION ?>) {
			var row = jQuery('#operations_' + index);
		}
		else {
			var row = jQuery('#recovery_operations_' + index);
		}

		var rowParent = row.parent();

		row.find('*').remove();
		row.remove();
	}

	function removeOperationCondition(index) {
		jQuery('#opconditions_' + index).find('*').remove();
		jQuery('#opconditions_' + index).remove();

		processOperationTypeOfCalculation();
	}

	function removeRow(id) {
		jQuery('#' + id).remove();
	}

	function showOpCmdForm(opCmdId, type) {
		var objectTPL = {
				opcmdid: 'new',
				objectid: 0,
				name: '',
				target: 'current',
				operationName: <?= CJs::encodeJson(_('Add')) ?>
			},
			tpl,
			parentId;

		if (jQuery('#opcmdEditForm').length > 0) {
			closeOpCmdForm();
		}

		if (type == <?= ACTION_OPERATION ?>) {
			parentId = 'opCmdList';
		}
		else {
			parentId = 'recOpCmdList';
		}

		tpl = new Template(jQuery('#opcmdEditFormTPL').html());
		jQuery('#' + parentId).closest('li').after(tpl.evaluate(objectTPL));

		// actions
		jQuery('#opcmdEditForm')
			.find('#pCmdTargetSelect')
			.toggle(objectTPL.target != 'current').end()
			.find('button[name="save"]').click(function() {
				saveOpCmdForm(type)
			}).end()
			.find('button[name="cancel"]').click(closeOpCmdForm).end()
			.find('select[name="opCmdTarget"]').val(objectTPL.target).change(changeOpCmdTarget);
	}

	function saveOpCmdForm(type) {
		var objectForm = jQuery('#opcmdEditForm'),
			object = {},
			parentId;

		object.target = jQuery(objectForm).find('select[name="opCmdTarget"]').val();

		if (type == <?= ACTION_OPERATION ?>) {
			parentId = 'opCmdListFooter';
		}
		else {
			parentId = 'recOpCmdListFooter';
		}

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
							}],
							parentId: parentId
						});
					}
				}
			}
		}

		// host
		else if (object.target == 'host') {
			var values = jQuery('#opCmdTargetObject').multiSelect('getData');

			if (object.target != 'current' && empty(values)) {
				alert(<?= CJs::encodeJson(_('You did not specify host for operation.')) ?>);

				return true;
			}
			else {
				for (var key in values) {
					var data = values[key];

					if (!empty(data.id)) {
						addPopupValues({
							object: 'hostid',
							values: [{
								target: object.target,
								hostid: data.id,
								name: data.name
							}],
							parentId: parentId
						});
					}
				}
			}
		}

		// current
		else {
			addPopupValues({
				object: 'hostid',
				values: [{
					hostid: 0,
					name: ''
				}],
				parentId: parentId
			});
		}

		closeOpCmdForm();
	}

	function changeOpCmdTarget() {
		var opCmdTarget = jQuery('#opcmdEditForm select[name="opCmdTarget"]'),
			opCmdTargetVal = opCmdTarget.val();

		if (jQuery('#opCmdTargetObject').length > 0) {
			jQuery('.multiselect-wrapper').remove();
		}

		// multiselect
		if (opCmdTargetVal != 'current') {
			var opCmdTargetObject = jQuery('<div>', {
				id: 'opCmdTargetObject',
				'class': 'multiselect'
			});

			opCmdTarget.parent().append(opCmdTargetObject);

			var srctbl = (opCmdTargetVal == 'host') ? 'hosts' : 'host_groups',
				srcfld1 = (opCmdTargetVal == 'host') ? 'hostid' : 'groupid';

			jQuery(opCmdTargetObject).multiSelectHelper({
				id: 'opCmdTargetObject',
				objectName: (opCmdTargetVal == 'host') ? 'hosts' : 'hostGroup',
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
		jQuery('#opcmdEditForm').closest('li').remove();
	}

	function showOpTypeForm(type) {
		var current_op_type,
			optype_fieldids = {},
			fieldId,
			f;

		if (type == <?= ACTION_OPERATION ?>) {
			var opcommand_type = jQuery('#new_operation_opcommand_type'),
				opcommand_script = '#new_operation_opcommand_script',
				opcommand_execute_on = '#new_operation_opcommand_execute_on',
				opcommand_port = '#new_operation_opcommand_port',
				opcommand_command = '#new_operation_opcommand_command',
				opcommand_command_ipmi = '#new_operation_opcommand_command_ipmi',
				opcommand_authtype = '#new_operation_opcommand_authtype',
				opcommand_username = '#new_operation_opcommand_username';
		}
		else {
			var opcommand_type = jQuery('#new_recovery_operation_opcommand_type'),
				opcommand_script = '#new_recovery_operation_opcommand_script',
				opcommand_execute_on = '#new_recovery_operation_opcommand_execute_on',
				opcommand_port = '#new_recovery_operation_opcommand_port',
				opcommand_command = '#new_recovery_operation_opcommand_command',
				opcommand_command_ipmi = '#new_recovery_operation_opcommand_command_ipmi',
				opcommand_authtype = '#new_recovery_operation_opcommand_authtype',
				opcommand_username = '#new_recovery_operation_opcommand_username';
		}

		if (opcommand_type.length == 0) {
			return;
		}

		current_op_type = opcommand_type.val();

		optype_fieldids[opcommand_script] = [ZBX_SCRIPT_TYPES.userscript];
		optype_fieldids[opcommand_execute_on] = [ZBX_SCRIPT_TYPES.script];
		optype_fieldids[opcommand_port] = [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet];
		optype_fieldids[opcommand_command] = [ZBX_SCRIPT_TYPES.script, ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet];
		optype_fieldids[opcommand_command_ipmi] = [ZBX_SCRIPT_TYPES.ipmi];
		optype_fieldids[opcommand_authtype] = [ZBX_SCRIPT_TYPES.ssh];
		optype_fieldids[opcommand_username] = [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet];

		for (fieldId in optype_fieldids) {
			var show = false;

			for (f = 0; f < optype_fieldids[fieldId].length; f++) {
				if (current_op_type == optype_fieldids[fieldId][f]) {
					show = true;
				}
			}

			jQuery(fieldId)
				.closest('li')
				.toggle(show)
				.find(':input')
				.prop('disabled', !show);
		}

		showOpTypeAuth(type);
	}

	function showOpTypeAuth(type) {
		var show_password = false,
			show_publickey = false;

		if (type == <?= ACTION_OPERATION ?>) {
			var current_op_type = parseInt(jQuery('#new_operation_opcommand_type').val(), 10),
				opcommand_authtype = 'new_operation_opcommand_authtype',
				opcommand_password = 'new_operation_opcommand_password',
				opcommand_publickey = 'new_operation_opcommand_publickey',
				opcommand_privatekey = 'new_operation_opcommand_privatekey',
				opcommand_passphrase = 'new_operation_opcommand_passphrase';
		}
		else {
			var current_op_type = parseInt(jQuery('#new_recovery_operation_opcommand_type').val(), 10),
				opcommand_authtype = 'new_recovery_operation_opcommand_authtype',
				opcommand_password = 'new_recovery_operation_opcommand_password',
				opcommand_publickey = 'new_recovery_operation_opcommand_publickey',
				opcommand_privatekey = 'new_recovery_operation_opcommand_privatekey',
				opcommand_passphrase = 'new_recovery_operation_opcommand_passphrase';
		}

		if (current_op_type === <?= ZBX_SCRIPT_TYPE_SSH ?>) {
			var current_op_type_auth = parseInt(jQuery('#' + opcommand_authtype).val(), 10);

			show_password = (current_op_type_auth === <?= ITEM_AUTHTYPE_PASSWORD ?>);
			show_publickey = !show_password;
		}
		else if (current_op_type === <?= ZBX_SCRIPT_TYPE_TELNET ?>) {
			show_password = true;
		}

		jQuery('#' + opcommand_password)
			.closest('li')
			.toggle(show_password)
			.find(':input')
			.prop('disabled', !show_password);
		jQuery('#' + opcommand_publickey + ', #' + opcommand_privatekey + ', #' + opcommand_passphrase)
			.closest('li')
			.toggle(show_publickey)
			.find(':input')
			.prop('disabled', !show_publickey);
	}

	function processTypeOfCalculation() {
		var show_formula = (jQuery('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>),
			labels = jQuery('#conditionTable .label');

		jQuery('#evaltype').closest('li').toggle(labels.length > 1);
		jQuery('#conditionLabel').toggle(!show_formula);
		jQuery('#formula').toggle(show_formula);

		if (labels.length > 1) {
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
	}

	function processOperationTypeOfCalculation() {
		var labels = jQuery('#operationConditionTable .label');

		jQuery('#operationEvaltype').closest('li').toggle(labels.length > 1);

		if (labels.length > 1) {
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
	}

	jQuery(document).ready(function() {
		jQuery('#new_operation_opmessage_default_msg').on('change', function() {
			var default_message = jQuery(this).is(':checked');

			jQuery('#new_operation_opmessage_subject, #new_operation_opmessage_message')
				.closest('li')
				.toggle(!default_message);
		});

		jQuery('#new_recovery_operation_opmessage_default_msg').on('change', function() {
			var default_message = jQuery(this).is(':checked');

			jQuery('#new_recovery_operation_opmessage_subject, #new_recovery_operation_opmessage_message')
				.closest('li')
				.toggle(!default_message);
		});

		jQuery('#new_operation_opmessage_default_msg, #new_recovery_operation_opmessage_default_msg').trigger('change');

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#actionid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});

			// Remove operations IDs.
			var operationid_RegExp = /operations\[\d+\]\[operationid\]/;
			jQuery('input[name^=operations]').each(function() {
				// Intentional usage of JS Prototype.
				if ($(this).getAttribute('name').match(operationid_RegExp)) {
					$(this).remove();
				}
			});

			// Remove recovery operations IDs
			var recovery_operationid_RegExp = /recovery_operations\[\d+\]\[operationid\]/;
			jQuery('input[name^=recovery_operations]').each(function() {
				// Intentional usage of JS Prototype.
				if ($(this).getAttribute('name').match(recovery_operationid_RegExp)) {
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
		showOpTypeForm(<?= ACTION_OPERATION ?>);
		showOpTypeForm(<?= ACTION_RECOVERY_OPERATION ?>);

		jQuery('#select_operation_opcommand_script').click(function() {
			PopUp('popup.php?srctbl=scripts&srcfld1=scriptid&srcfld2=name&dstfrm=action.edit'
				+ '&dstfld1=new_operation_opcommand_scriptid&dstfld2=new_operation_opcommand_script'
			);
		});

		jQuery('#select_recovery_operation_opcommand_script').click(function() {
			PopUp('popup.php?srctbl=scripts&srcfld1=scriptid&srcfld2=name&dstfrm=action.edit'
				+ '&dstfld1=new_recovery_operation_opcommand_scriptid&dstfld2=new_recovery_operation_opcommand_script'
			);
		});

		processTypeOfCalculation();
		processOperationTypeOfCalculation();
	});
</script>
