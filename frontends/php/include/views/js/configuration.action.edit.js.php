<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<!-- Trigger Actions-->
<script type="text/x-jquery-tmpl" id="opmsg-usrgrp-row-tmpl">
<tr id="#{row}#{usrgrpid}">
	<td>
		<input name="operation[opmessage_grp][#{usrgrpid}][usrgrpid]" type="hidden" value="#{usrgrpid}" />
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeRow('#{row}#{usrgrpid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsg-user-row-tmpl">
<tr id="#{row}#{id}">
	<td>
		<input name="operation[opmessage_usr][#{id}][userid]" type="hidden" value="#{id}" />
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeRow('#{row}#{id}');"><?= _('Remove') ?></button>
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
		var field_variables = {
			userid: {
				opmsgUserListFooter: {
					field: 'new_operation',
					row: 'opmsgUserRow_'
				},
				recOpmsgUserListFooter: {
					field: 'new_recovery_operation',
					row: 'recOpmsgUserRow_'
				},
				ackOpmsgUserListFooter: {
					field: 'new_ack_operation',
					row: 'ackOpmsgUserRow_'
				}
			},
			usrgrpid: {
				opmsgUsrgrpListFooter: {
					field: 'new_operation',
					row: 'opmsgUsrgrpRow_'
				},
				recOpmsgUsrgrpListFooter: {
					field: 'new_recovery_operation',
					row: 'recOpmsgUsrgrpRow_'
				},
				ackOpmsgUsrgrpListFooter: {
					field: 'new_ack_operation',
					row: 'ackOpmsgUsrgrpRow_'
				},
			}
		};

		for (var i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}

			var value = list.object in field_variables
				? jQuery.extend(list.values[i], field_variables[list.object][list.parentId])
				: null;

			switch (list.object) {
				case 'userid':
					if (jQuery('#' + value.row + value.id).length) {
						continue;
					}

					var tpl = new Template(jQuery('#opmsg-user-row-tmpl').html());
					var $container = jQuery('#' + list.parentId);
					$container.before(tpl.evaluate(value));
					break;

				case 'usrgrpid':
					if (jQuery('#' + value.row + value.id).length) {
						continue;
					}

					var tpl = new Template(jQuery('#opmsg-usrgrp-row-tmpl').html());
					var $container = jQuery('#' + list.parentId);
					$container.before(tpl.evaluate(value));
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
		else if (type == <?= ACTION_RECOVERY_OPERATION ?>) {
			var row = jQuery('#recovery_operations_' + index);
		}
		else {
			var row = jQuery('#ack_operations_' + index);
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

	function showOpTypeForm() {
		var current_op_type,
			optype_fieldids = {},
			$opcommand_type = jQuery('#operation_opcommand_type'),
			opcommand_script = '#operation_opcommand_script',
			opcommand_execute_on = '#operation_opcommand_execute_on',
			opcommand_port = '#operation_opcommand_port',
			opcommand_command = '#operation_opcommand_command',
			opcommand_command_ipmi = '#operation_opcommand_command_ipmi',
			opcommand_authtype = '#operation_opcommand_authtype',
			opcommand_username = '#operation_opcommand_username';

		if ($opcommand_type.length == 0) {
			return;
		}

		current_op_type = $opcommand_type.val();

		optype_fieldids[opcommand_script] = [ZBX_SCRIPT_TYPES.userscript];
		optype_fieldids[opcommand_execute_on] = [ZBX_SCRIPT_TYPES.script];
		optype_fieldids[opcommand_port] = [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet];
		optype_fieldids[opcommand_command] = [ZBX_SCRIPT_TYPES.script, ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet];
		optype_fieldids[opcommand_command_ipmi] = [ZBX_SCRIPT_TYPES.ipmi];
		optype_fieldids[opcommand_authtype] = [ZBX_SCRIPT_TYPES.ssh];
		optype_fieldids[opcommand_username] = [ZBX_SCRIPT_TYPES.ssh, ZBX_SCRIPT_TYPES.telnet];

		for (var fieldId in optype_fieldids) {
			var show = false;

			for (var f = 0; f < optype_fieldids[fieldId].length; f++) {
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

		showOpTypeAuth();
	}

	function showOpTypeAuth() {
		var show_password = false,
			show_publickey = false,
			current_op_type = parseInt(jQuery('#operation_opcommand_type').val(), 10);

		if (current_op_type === <?= ZBX_SCRIPT_TYPE_SSH ?>) {
			var current_op_type_auth = parseInt(jQuery('#operation_opcommand_authtype').val(), 10);

			show_password = (current_op_type_auth === <?= ITEM_AUTHTYPE_PASSWORD ?>);
			show_publickey = !show_password;
		}
		else if (current_op_type === <?= ZBX_SCRIPT_TYPE_TELNET ?>) {
			show_password = true;
		}

		jQuery('#operation_opcommand_password')
			.closest('li')
			.toggle(show_password)
			.find(':input')
			.prop('disabled', !show_password);
		jQuery('#operation_opcommand_publickey, #operation_opcommand_privatekey, #opcommand_passphrase')
			.closest('li')
			.toggle(show_publickey)
			.find(':input')
			.prop('disabled', !show_publickey);

		jQuery(window).trigger('resize');
	}

	function processTypeOfCalculation() {
		var show_formula = (jQuery('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>),
			$labels = jQuery('#conditionTable .label');

		jQuery('#evaltype').closest('li').toggle($labels.length > 1);
		jQuery('#conditionLabel').toggle(!show_formula);
		jQuery('#formula').toggle(show_formula);

		if ($labels.length > 1) {
			var conditions = [];

			$labels.each(function(index, label) {
				$label = jQuery(label);

				conditions.push({
					id: $label.data('formulaid'),
					type: $label.data('conditiontype')
				});
			});

			jQuery('#conditionLabel').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));
		}
	}

	function processOperationTypeOfCalculation() {
		var $labels = jQuery('#operationConditionTable .label');

		jQuery('#operationEvaltype').closest('li').toggle($labels.length > 1);

		if ($labels.length > 1) {
			var conditions = [];

			$labels.each(function(index, label) {
				$label = jQuery(label);

				conditions.push({
					id: $label.data('formulaid'),
					type: $label.data('conditiontype')
				});
			});

			jQuery('#operationConditionLabel')
				.html(getConditionFormula(conditions, +jQuery('#operationEvaltype').val()));
		}
	}

	function resetOpmessage() {
		jQuery('#operation_opmessage_mediatypeid').val(0);
		jQuery('#operation_opmessage_default_msg').val(1);
		jQuery('#operation_opmessage_subject, #operation_opmessage_message').val('');
	}

	jQuery(document).ready(function() {
		var remove_operationid = function() {
			var operationid_RegExp = /^(operations|recovery_operations|ack_operations)\[\d+\]\[operationid\]$/;

			jQuery('input[name^=operations], input[name^=recovery_operations], input[name^=ack_operations]')
				.each(function() {
					if ($(this).attr('name').match(operationid_RegExp)) {
						$(this).remove();
					}
				});
		};

		jQuery('#add').click(remove_operationid);

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#actionid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= json_encode(_('Add')) ?>)
				.attr({id: 'add', name: 'add'})
				.click(remove_operationid);
			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});

		jQuery('#esc_period').change(function() {
			jQuery('form[name="action.edit"]').submit();
		});

		processTypeOfCalculation();
	});
</script>
