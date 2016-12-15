<!-- Trigger Actions-->
<script type="text/x-jquery-tmpl" id="opmsgUsrgrpRowTPL">
<tr id="opmsgUsrgrpRow_#{usrgrpid}">
	<td>
		<input name="new_operation[opmessage_grp][#{usrgrpid}][usrgrpid]" type="hidden" value="#{usrgrpid}" />
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpmsgUsrgrpRow('#{usrgrpid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsgUserRowTPL">
<tr id="opmsgUserRow_#{id}">
	<td>
		<input name="new_operation[opmessage_usr][#{id}][userid]" type="hidden" value="#{id}" />
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpmsgUserRow('#{id}');"><?= _('Remove') ?></button>
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
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
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
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeOpCmdRow('#{hostid}', 'hostid');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opcmdEditFormTPL">
<li>
	<div class="<?= ZBX_STYLE_TABLE_FORMS_TD_LEFT ?>"></div>
	<div class="<?= ZBX_STYLE_TABLE_FORMS_TD_RIGHT ?>">
		<div id="opcmdEditForm" class="<?= ZBX_STYLE_TABLE_FORMS_SEPARATOR ?>" style="min-width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px;">
			<?= (new CTable())
				->addRow([
					[
						_('Target'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						new CComboBox('opCmdTarget', null, null, [
							'current' => _('Current host'),
							'host' => _('Host'),
							'hostGroup' => _('Host group')
						]),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						new CVar('opCmdId', '#{opcmdid}')
					]
				])
				->addRow([
					new CHorList([
						(new CButton('save', '#{operationName}'))->addClass(ZBX_STYLE_BTN_LINK),
						(new CButton('cancel', _('Cancel')))->addClass(ZBX_STYLE_BTN_LINK)
					])
				])
				->setAttribute('style', 'width: 100%;')
				->toString()
			?>
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
					if (jQuery('#opmsgUserRow_' + value.id).length) {
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
		var objectTPL = {
				opcmdid: 'new',
				objectid: 0,
				name: '',
				target: 'current',
				operationName: <?= CJs::encodeJson(_('Add')) ?>
			},
			tpl;

		if (jQuery('#opcmdEditForm').length > 0) {
			closeOpCmdForm();
		}

		tpl = new Template(jQuery('#opcmdEditFormTPL').html());
		jQuery('#opCmdList').closest('li').after(tpl.evaluate(objectTPL));

		// actions
		jQuery('#opcmdEditForm')
			.find('#pCmdTargetSelect')
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
				'class': 'multiselect',
				css: {
					width: '<?= ZBX_TEXTAREA_MEDIUM_WIDTH ?>px'
				}
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

	function showOpTypeForm() {
		var currentOpType,
			opTypeFieldIds,
			fieldId,
			f;

		if (jQuery('#new_operation_opcommand_type').length == 0) {
			return;
		}

		currentOpType = jQuery('#new_operation_opcommand_type').val();

		opTypeFieldIds = {
			'#new_operation_opcommand_script': [ZBX_SCRIPT_TYPES.userscript],
			'#new_operation_opcommand_execute_on': [ZBX_SCRIPT_TYPES.script],
			'#new_operation_opcommand_port': [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
			'#new_operation_opcommand_command': [ZBX_SCRIPT_TYPES.script, ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
			'#new_operation_opcommand_command_ipmi': [ZBX_SCRIPT_TYPES.ipmi],
			'#new_operation_opcommand_authtype': [ZBX_SCRIPT_TYPES.ssh],
			'#new_operation_opcommand_username': [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet],
		};

		for (fieldId in opTypeFieldIds) {
			var show = false;

			for (f = 0; f < opTypeFieldIds[fieldId].length; f++) {
				if (currentOpType == opTypeFieldIds[fieldId][f]) {
					show = true;
				}
			}

			jQuery(fieldId)
				.closest('li')
				.toggle(show)
				.find(':input')
				.prop('disabled', !show);
		}

		showOpTypeAuth();
	}

	function showOpTypeAuth() {
		var currentOpType = parseInt(jQuery('#new_operation_opcommand_type').val(), 10),
			show_password = false,
			show_publickey = false;

		if (currentOpType === <?= ZBX_SCRIPT_TYPE_SSH ?>) {
			var currentOpTypeAuth = parseInt(jQuery('#new_operation_opcommand_authtype').val(), 10);

			show_password = (currentOpTypeAuth === <?= ITEM_AUTHTYPE_PASSWORD ?>);
			show_publickey = !show_password;
		}
		else if (currentOpType === <?= ZBX_SCRIPT_TYPE_TELNET ?>) {
			show_password = true;
		}

		jQuery('#new_operation_opcommand_password')
			.closest('li')
			.toggle(show_password)
			.find(':input')
			.prop('disabled', !show_password);
		jQuery('#new_operation_opcommand_publickey,#new_operation_opcommand_privatekey,#new_operation_opcommand_passphrase')
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

			jQuery('#new_operation_opmessage_subject').closest('li').toggle(!default_message);
			jQuery('#new_operation_opmessage_message').closest('li').toggle(!default_message);
		});

		jQuery('#new_operation_opmessage_default_msg').trigger('change');

		jQuery('#recovery_msg').on('change', function() {
			var recovery_msg = jQuery(this).is(':checked');

			jQuery('#r_shortdata').closest('li').toggle(recovery_msg);
			jQuery('#r_longdata').closest('li').toggle(recovery_msg);
		});

		jQuery('#recovery_msg').trigger('change');

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#actionid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
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
