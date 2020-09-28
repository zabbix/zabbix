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

<script type="text/x-jquery-tmpl" id="operation-popup-tmpl">
	<?= (new CPartial('popup.operations'))->getOutput() ?>
</script>

<!-- Trigger Actions-->
<script type="text/x-jquery-tmpl" id="opmsg-usrgrp-row-tmpl">
<tr data-id="#{usrgrpid}">
	<td>
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<input name="operation[opmessage_grp][][usrgrpid]" type="hidden" value="#{usrgrpid}" />
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="$(this).closest('tr').remove();">
			<?= _('Remove') ?>
		</button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="opmsg-user-row-tmpl">
<tr data-id="#{id}">
	<td>
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<input name="operation[opmessage_usr][][userid]" type="hidden" value="#{id}" />
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="$(this).closest('tr').remove();">
			<?= _('Remove') ?>
		</button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="operation-condition-row-tmpl">
<tr data-id="#{num}">
	<td>
		<span>#{formulaid}</span>
	</td>
	<td>
		<span>#{name}</span>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<input type="hidden" name="operation[opconditions][#{num}][conditiontype]" value="#{conditiontype}" />
		<input type="hidden" name="operation[opconditions][#{num}][operator]" value="#{operator}" />
		<input type="hidden" name="operation[opconditions][#{num}][value]" value="#{value}" />
		<button data-action="remove" type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues({object: objectid, parentId: sourceid, values}) {
		if (sourceid === 'operation-message-user-groups-footer') {
			for (let usergroup of values) {
				if (!operation_popup.view.operation_message.$usergroups.find(`tr[data-id="${usergroup[objectid]}"]`).length) {
					operation_popup.view.operation_message.addUserGroup(usergroup);
				}
			}
		}
		else if (sourceid === 'operation-message-users-footer') {
			objectid = 'id';
			for (let user of values) {
				if (!operation_popup.view.operation_message.$users.find(`tr[data-id="${user[objectid]}"]`).length) {
					operation_popup.view.operation_message.addUser(user);
				}
			}
		}
		else if (sourceid === 'operation-command-target-hosts') {
			operation_popup.view.operation_command.$targets_hosts_ms.multiSelect('addData', values);
		}
		else if (sourceid === 'operation-command-target-groups') {
			operation_popup.view.operation_command.$targets_groups_ms.multiSelect('addData', values);
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

	/**
	 * @param {object} props                    Meanwhile whole state is kept into DOM, few props needs to be passed in
	 *                                          component hierarchy. These are kept in props object. Default props must
	 *                                          be provided here.
	 * @param {object} props['eventsource']
	 * @param {object} props['operation_type']
	 * @param {object} props['command_type']
	 */
	function OperationView(props) {
		this.props = props;

		this.$obj = $($('#operation-popup-tmpl').html());
		this.$wrapper = this.$obj.find('>ul');

		this.operation_type = new OperationViewType(this.$obj.find('>ul>li[id^="operation-type"]'));
		this.$current_focus = this.operation_type.$select;

		this.operation_type.onchange = (operation_type) => {
			this.props.operation_type = operation_type;
			this.render();
			this.onupdate();
			this.operation_type.$select.focus();
		};

		this.operation_steps = new OperationViewSteps(this.$obj.find('>ul>li[id^="operation-step"]'));
		this.operation_message = new OperationViewMessage(this.$obj.find('>ul>li[id^="operation-message"]'));

		this.operation_command = new OperationViewCommand(this.$obj.find('>ul>li[id^="operation-command"]'));
		this.operation_command.onchange = (command_type) => {
			this.props.command_type = command_type;
			this.render();
			this.onupdate();
			this.operation_command.$type_select.focus();
		};

		this.operation_attr = new OperationViewAttr(this.$obj.find('>ul>li[id^="operation-attr"]'));
		this.operation_condition = new OperationViewCondition(this.$obj.find('>ul>li[id^="operation-condition"]'));
	}

	/**
	 * Is called when re-rendering has happened (when props are changed).
	 */
	OperationView.prototype.onupdate = function() {};

	/**
	 * Detaches all instance nodes.
	 */
	OperationView.prototype.detach = function() {
		this.operation_steps.detach();
		this.operation_message.detach();
		this.operation_command.detach();
		this.operation_attr.detach();
		this.operation_condition.detach();
	};

	/**
	 * Main rendering function call.
	 */
	OperationView.prototype.render = function() {
		this.detach();

		this.operation_type.attach(this.$wrapper);
		this.operation_steps.attach(this.$wrapper);

		if (this.props.operation_type == operation_details.OPERATION_TYPE_MESSAGE
				|| this.props.operation_type == operation_details.OPERATION_TYPE_ACK_MESSAGE
				|| this.props.operation_type == operation_details.OPERATION_TYPE_RECOVERY_MESSAGE) {
			this.operation_message.attach(this.$wrapper, this.props);
		}
		else if (this.props.operation_type == operation_details.OPERATION_TYPE_COMMAND) {
			this.operation_command.attach(this.$wrapper, this.props);
		}

		this.operation_attr.attach(this.$wrapper, this.props);
		this.operation_condition.attach(this.$wrapper);
	};

	/**
	 * Sets config for each of views. If config for particular view is "null", then the view is disabled permanently.
	 * Each of config fields must contain default values as they will be set into view.
	 *
	 * @param {object}      conf
	 * @param {object|null} conf['operation_type']       See OperationViewType.setConfig doc-block.
	 * @param {object|null} conf['operation_steps']      See OperationViewSteps.setConfig doc-block.
	 * @param {object|null} conf['operation_message']    See OperationViewMessage.setConfig doc-block.
	 * @param {object|null} conf['operation_command']    See OperationViewCommand.setConfig doc-block.
	 * @param {object|null} conf['operation_attr']       See OperationViewAttr.setConfig doc-block.
	 * @param {object|null} conf['operation_condition']  See OperationViewCondition.setConfig doc-block.
	 */
	OperationView.prototype.setConfig = function(conf) {
		if (conf.operation_steps === null) {
			this.operation_steps.attach = this.operation_steps.detach;
		}
		else {
			this.operation_steps.setConfig(conf.operation_steps);
		}

		if (conf.operation_message === null) {
			this.operation_message.attach = this.operation_message.detach;
		}
		else {
			this.operation_message.setConfig(conf.operation_message);
		}

		if (conf.operation_command === null) {
			this.operation_command.attach = this.operation_command.detach;
		}
		else {
			this.props.command_type = conf.operation_command.type;
			this.operation_command.setConfig(conf.operation_command);
		}

		if (conf.operation_attr === null) {
			this.operation_attr.attach = this.operation_attr.detach;
		}
		else {
			this.operation_attr.setConfig(conf.operation_attr);
		}

		if (conf.operation_condition === null) {
			this.operation_condition.attach = this.operation_condition.detach;
		}
		else {
			this.operation_condition.setConfig(conf.operation_condition);
		}

		if (conf.operation_type === null) {
			this.operation_type.attach = this.operation_type.detach;
		}
		else {
			this.props.operation_type = conf.operation_type.selected;
			this.operation_type.setConfig(conf.operation_type);
		}
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewMessage($obj) {
		this.$obj = $obj;
		this.$notice = $obj.siblings('#operation-message-notice');

		this.$custom = $obj.siblings('#operation-message-custom');
		this.$custom.find('input[type="checkbox"]')
			.on('change', ({target}) => this.showCustomMessage(target.checked));

		this.$subject = $obj.siblings('#operation-message-subject');
		this.$body = $obj.siblings('#operation-message-body');
		this.$mediatype_only = $obj.siblings('#operation-message-mediatype-only');
		this.$mediatype_default = $obj.siblings('#operation-message-mediatype-default');

		this.$usergroups = $obj.siblings('#operation-message-user-groups');
		this.$usergroups.find('#operation-message-user-groups-footer button')
			.on('click', ({target}) => this.showUserGroupPopup(target));

		this.$users = $obj.siblings('#operation-message-users');
		this.$users.find('#operation-message-users-footer button')
			.on('click', ({target}) => this.showUserPopup(target));

		this.tmpl_usergroup_row = new Template(jQuery('#opmsg-usrgrp-row-tmpl').html());
		this.tmpl_user_row = new Template(jQuery('#opmsg-user-row-tmpl').html());
	}

	/**
	 * @param {Node} return_focus
	 */
	OperationViewMessage.prototype.showUserPopup = function(return_focus) {
		PopUp('popup.generic', {
			'srctbl': 'users',
			'srcfld1': 'userid',
			'srcfld2': 'fullname',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-users-footer',
			'multiselect': '1'
		}, null, return_focus);
	};

	/**
	 * @param {Node} return_focus
	 */
	OperationViewMessage.prototype.showUserGroupPopup = function(return_focus) {
		PopUp('popup.generic', {
			'srctbl': 'usrgrp',
			'srcfld1': 'usrgrpid',
			'srcfld2': 'name',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-user-groups-footer',
			'multiselect': '1'
		}, null, return_focus);
	};

	/**
	 * @param {bool} show_message  If true, show custom message form, else hide it.
	 */
	OperationViewMessage.prototype.showCustomMessage = function(show_message) {
		if (show_message) {
			this.$custom.find('input[type="checkbox"]').prop('checked', true);
			this.$subject.show();
			this.$body.show();
		}
		else {
			this.$custom.find('input[type="checkbox"]').prop('checked', false);
			this.$subject.hide();
			this.$body.hide();
		}
	};

	/**
	 * Adds user group row-item.
	 *
	 * @param {object} usergroup
	 * @param {string} usergroup['usrgrpid']
	 * @param {string} usergroup['name']
	 */
	OperationViewMessage.prototype.addUserGroup = function(usergroup) {
		this.$usergroups
			.find('#operation-message-user-groups-footer')
			.before(this.tmpl_usergroup_row.evaluate(usergroup));
	};

	/**
	 * Adds user row-item.
	 *
	 * @param {object} user
	 * @param {string} user['id']
	 * @param {string} user['name']
	 */
	OperationViewMessage.prototype.addUser = function(user) {
		this.$users
			.find('#operation-message-users-footer')
			.before(this.tmpl_user_row.evaluate(user));
	};

	/**
	 * @param {object}  conf
	 * @param {string}  conf['subject']
	 * @param {string}  conf['body']
	 * @param {bool}    conf['custom_message']
	 * @param {array}   conf['mediatypes']                   Options of all available mediatypes.
	 * @param {string}  conf['mediatypes'][]['mediatypeid']
	 * @param {string}  conf['mediatypes'][]['name']
	 * @param {string}  conf['mediatypeid']                  Currently selected mediatype.
	 * @param {array}   conf['usergroups']                   Currently selected user groups.
	 * @param {string}  conf['usergroups'][]['usrgrpid']
	 * @param {string}  conf['usergroups'][]['name']
	 * @param {array}   conf['users']                        Currently selected users.
	 * @param {string}  conf['users'][]['id']
	 * @param {string}  conf['users'][]['name']
	 */
	OperationViewMessage.prototype.setConfig = function(conf) {
		this.$subject.find('input').val(conf.subject);
		this.$body.find('textarea').val(conf.body);
		this.showCustomMessage(conf.custom_message);
		conf.usergroups.forEach(usergroup => this.addUserGroup(usergroup));
		conf.users.forEach(user => this.addUser(user));

		const $mediatype_default_select = this.$mediatype_default.find('z-select');
		$mediatype_default_select.get(0).addOption({value: 0, label: `- ${t('All')} -`});

		const $mediatype_only_select = this.$mediatype_only.find('z-select');
		$mediatype_only_select.get(0).addOption({value: 0, label: `- ${t('All')} -`});

		conf.mediatypes.forEach(({mediatypeid, name, status}) => {
			$mediatype_default_select.get(0).addOption({
				value: mediatypeid,
				label: name,
				class_name: (status == operation_details.MEDIA_TYPE_DISABLED) ? operation_details.ZBX_STYLE_RED : null
			});

			$mediatype_only_select.get(0).addOption({
				value: mediatypeid,
				label: name,
				class_name: (status == operation_details.MEDIA_TYPE_DISABLED) ? operation_details.ZBX_STYLE_RED : null
			});
		});

		$mediatype_default_select.val(conf.mediatypeid);
		$mediatype_only_select.val(conf.mediatypeid);
	};

	/**
	 * Renders according to current instance configuration.
	 *
	 * @param {jQuery} $wrapper
	 * @param {object} props
	 * @param {string} props['operation_type']
	 * @param {string} props['recovery_phase']
	 */
	OperationViewMessage.prototype.attach = function($wrapper, props) {
		this.detach();

		if (props.operation_type == operation_details.OPERATION_TYPE_MESSAGE) {
			this.$notice.appendTo($wrapper);
			this.$usergroups.appendTo($wrapper);
			this.$users.appendTo($wrapper);
			this.$mediatype_only.appendTo($wrapper);
		}
		else if (props.operation_type == operation_details.OPERATION_TYPE_ACK_MESSAGE) {
			this.$mediatype_default.appendTo($wrapper);
		}
		else if (props.recovery_phase == operation_details.ACTION_OPERATION
				|| props.recovery_phase == operation_details.ACTION_ACKNOWLEDGE_OPERATION) {
			this.$mediatype_only.appendTo($wrapper);
		}

		this.$custom.appendTo($wrapper);
		this.$subject.appendTo($wrapper);
		this.$body.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewMessage.prototype.detach = function() {
		this.$notice.detach();
		this.$usergroups.detach();
		this.$users.detach();
		this.$mediatype_only.detach();
		this.$mediatype_default.detach();
		this.$custom.detach();
		this.$subject.detach();
		this.$body.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCommandTypeCustomScript($obj) {
		this.$script_target = $obj.siblings('#operation-command-script-target');
		this.$cmd = $obj.siblings('#operation-command-cmd');
		this.$cmd_input = this.$cmd.find('textarea');
	}

	/**
	 * @param {object} conf  See OperationViewCommand.setConfig doc-block.
	 */
	OperationViewCommandTypeCustomScript.prototype.setConfig = function(conf) {
		this.$cmd_input.val(conf.command);
		this.$script_target.find(`[value="${conf.execute_on}"]`).prop('checked', true);
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewCommandTypeCustomScript.prototype.attach = function($wrapper) {
		this.$script_target.appendTo($wrapper);
		this.$cmd.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCommandTypeCustomScript.prototype.detach = function() {
		this.$script_target.detach();
		this.$cmd.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCommandTypeGlobalScript($obj) {
		this.$global_script = $obj.siblings('#operation-command-global-script');
		this.$global_script_name = this.$global_script.find('[name="operation[opcommand][script]"]')
		this.$global_script_id = this.$global_script.find('[name="operation[opcommand][scriptid]"]')

		this.$global_script_select = this.$global_script.find('button');
		this.$global_script_select.on('click', ({target}) => this.showScriptsPopup(target));
	}

	/**
	 * @param {Node} return_focus
	 */
	OperationViewCommandTypeGlobalScript.prototype.showScriptsPopup = function(return_focus) {
		PopUp('popup.generic', {
			srctbl: 'scripts',
			srcfld1: 'scriptid',
			srcfld2: 'name',
			dstfrm: 'popup.operation',
			dstfld1: 'operation_opcommand_scriptid',
			dstfld2: 'operation_opcommand_script'
		}, null, return_focus);
	};

	/**
	 * @param {object} conf  See OperationViewCommand.setConfig doc-block.
	 */
	OperationViewCommandTypeGlobalScript.prototype.setConfig = function(conf) {
		this.$global_script_name.val(conf.global_script.name);
		this.$global_script_id.val(conf.global_script.scriptid);
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewCommandTypeGlobalScript.prototype.attach = function($wrapper) {
		this.$global_script.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCommandTypeGlobalScript.prototype.detach = function() {
		this.$global_script.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCommandTypeIPMI($obj) {
		this.$cmd_ipmi = $obj.siblings('#operation-command-cmd-ipmi');
		this.$cmd_ipmi_input = this.$cmd_ipmi.find('input');
	}

	/**
	 * @param {object} conf  See OperationViewCommand.setConfig doc-block.
	 */
	OperationViewCommandTypeIPMI.prototype.setConfig = function(conf) {
		this.$cmd_ipmi_input.val(conf.command);
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewCommandTypeIPMI.prototype.attach = function($wrapper) {
		this.$cmd_ipmi.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCommandTypeIPMI.prototype.detach = function() {
		this.$cmd_ipmi.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCommandTypeSSH($obj) {
		this.$authtype = $obj.siblings('#operation-command-authtype');
		this.$username = $obj.siblings('#operation-command-username');
		this.$pubkey = $obj.siblings('#operation-command-pubkey');
		this.$privatekey = $obj.siblings('#operation-command-privatekey');
		this.$password = $obj.siblings('#operation-command-password');
		this.$passphrase = $obj.siblings('#operation-command-passphrase');
		this.$port = $obj.siblings('#operation-command-port');
		this.$cmd = $obj.siblings('#operation-command-cmd');

		this.$authtype_select = this.$authtype.find('z-select');
		this.$privatekey_input = this.$privatekey.find('input');
		this.$publickey_input = this.$pubkey.find('input');
		this.$password_input = this.$password.find('input');
		this.$passphrase_input = this.$passphrase.find('input');

		this.$authtype_select.on('change', ({target}) => {
			if (target.value == operation_details.ITEM_AUTHTYPE_PUBLICKEY) {
				this.viewAuthTypePublicKey();
			}
			else {
				this.viewAuthTypePassword();
			}
		});
	}

	/**
	 * Sets instance view by toggling node style-display.
	 */
	OperationViewCommandTypeSSH.prototype.viewAuthTypePublicKey = function() {
		this.$password.hide();
		this.$password_input.prop('disabled', true);

		this.$passphrase.show();
		this.$passphrase_input.prop('disabled', false);

		this.$privatekey.show();
		this.$privatekey_input.prop('disabled', false);

		this.$pubkey.show();
		this.$publickey_input.prop('disabled', false);
	};

	/**
	 * Sets instance view by toggling node style-display.
	 */
	OperationViewCommandTypeSSH.prototype.viewAuthTypePassword = function() {
		this.$password.show();
		this.$password_input.prop('disabled', false);

		this.$passphrase.hide();
		this.$passphrase_input.prop('disabled', true);

		this.$privatekey.hide();
		this.$privatekey_input.prop('disabled', true);

		this.$pubkey.hide();
		this.$publickey_input.prop('disabled', true);
	};

	/**
	 * @param {object} conf  See OperationViewCommand.setConfig doc-block.
	 */
	OperationViewCommandTypeSSH.prototype.setConfig = function(conf) {
		this.$authtype_select.val(conf.authtype);
		this.$authtype_select.trigger('change');

		this.$privatekey_input.val(conf.privatekey);
		this.$publickey_input.val(conf.publickey);
		this.$passphrase_input.val(conf.password);
		this.$password_input.val(conf.password);
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewCommandTypeSSH.prototype.attach = function($wrapper) {
		this.$authtype.appendTo($wrapper);
		this.$username.appendTo($wrapper);
		this.$pubkey.appendTo($wrapper);
		this.$privatekey.appendTo($wrapper);
		this.$password.appendTo($wrapper);
		this.$passphrase.appendTo($wrapper);
		this.$port.appendTo($wrapper);
		this.$cmd.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCommandTypeSSH.prototype.detach = function() {
		this.$authtype.detach();
		this.$username.detach();
		this.$pubkey.detach();
		this.$privatekey.detach();
		this.$password.detach();
		this.$passphrase.detach();
		this.$port.detach();
		this.$cmd.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCommandTypeTelnet($obj) {
		this.$username = $obj.siblings('#operation-command-username');
		this.$password = $obj.siblings('#operation-command-password');
		this.$port = $obj.siblings('#operation-command-port');
		this.$cmd = $obj.siblings('#operation-command-cmd');

		this.$username_input = this.$username.find('input');
		this.$password_input = this.$password.find('input');
		this.$port_input = this.$port.find('input');
		this.$cmd_input = this.$cmd.find('input');
	}

	/**
	 * @param {object} conf  See OperationViewCommand.setConfig doc-block.
	 */
	OperationViewCommandTypeTelnet.prototype.setConfig = function(conf) {
		this.$username_input.val(conf.username);
		this.$password_input.val(conf.password);
		this.$port_input.val(conf.port);
		this.$cmd_input.val(conf.command);
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewCommandTypeTelnet.prototype.attach = function($wrapper) {
		this.$username.appendTo($wrapper);
		this.$password.appendTo($wrapper);
		this.$port.appendTo($wrapper);
		this.$cmd.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCommandTypeTelnet.prototype.detach = function() {
		this.$username.detach();
		this.$password.detach();
		this.$port.detach();
		this.$cmd.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCommand($obj) {
		this.$obj = $obj;

		this.type_custom_script = new OperationViewCommandTypeCustomScript($obj);
		this.type_global_script = new OperationViewCommandTypeGlobalScript($obj);
		this.type_ipmi = new OperationViewCommandTypeIPMI($obj);
		this.type_ssh = new OperationViewCommandTypeSSH($obj);
		this.type_telnet = new OperationViewCommandTypeTelnet($obj);

		this.$type = $obj.siblings('#operation-command-type');
		this.$type_select = $obj.find('z-select[name="operation[opcommand][type]"]');

		this.$targets = $obj.siblings('#operation-command-targets');
		this.$targets_current = this.$targets.find('#operation-command-chst');

		this.$targets_hosts_ms = this.$targets.find('#operation_opcommand_hst__hostid');

		const ms_hosts_url = new Curl('jsrpc.php', false);
		ms_hosts_url.setArgument('method', 'multiselect.get');
		ms_hosts_url.setArgument('object_name', 'hosts');
		ms_hosts_url.setArgument('editable', '1');
		ms_hosts_url.setArgument('type', operation_details.PAGE_TYPE_TEXT_RETURN_JSON);

		this.$targets_hosts_ms.multiSelect({
			url: ms_hosts_url.getUrl(),
			name: 'operation[opcommand_hst][][hostid]',
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'hosts',
					srcfld1: 'hostid',
					dstfrm: 'action.edit',
					dstfld1: 'operation-command-target-hosts',
					editable: '1'
				}
			}
		});

		const ms_groups_url = new Curl('jsrpc.php', false);
		ms_groups_url.setArgument('method', 'multiselect.get');
		ms_groups_url.setArgument('object_name', 'hostGroup');
		ms_groups_url.setArgument('editable', '1');
		ms_groups_url.setArgument('type', operation_details.PAGE_TYPE_TEXT_RETURN_JSON);

		this.$targets_groups_ms = this.$targets.find('#operation_opcommand_grp__groupid');
		this.$targets_groups_ms.multiSelect({
			url: ms_groups_url.getUrl(),
			name: 'operation[opcommand_grp][][groupid]',
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'action.edit',
					dstfld1: 'operation-command-target-groups',
					editable: '1'
				}
			}
		});

		this.$type_select.on('change', ({target}) => this.onchange(target.value));
	}

	/**
	 * @param {string} value
	 */
	OperationViewCommand.prototype.onchange = function(value) {};

	/**
	 * @param {object} conf
	 * @param {string} conf['authtype']
	 * @param {string} conf['command']
	 * @param {bool}   conf['current_host']
	 * @param {string} conf['execute_on']
	 * @param {object} conf['global_script']
	 * @param {string} conf['global_script']['scriptid']
	 * @param {string} conf['global_script']['name']
	 * @param {array}  conf['groups']
	 * @param {array}  conf['hosts']
	 * @param {string} conf['password']
	 * @param {string} conf['port']
	 * @param {string} conf['privatekey']
	 * @param {string} conf['publickey']
	 * @param {string} conf['type']
	 * @param {string} conf['username']
	 */
	OperationViewCommand.prototype.setConfig = function(conf) {
		this.$targets_current.prop('checked', conf.current_host);

		this.$targets_hosts_ms.multiSelect('clean');
		this.$targets_hosts_ms.multiSelect('addData', conf.hosts);

		this.$targets_groups_ms.multiSelect('clean');
		this.$targets_groups_ms.multiSelect('addData', conf.groups);

		this.$type_select.val(conf.type);

		this.type_custom_script.setConfig(conf);
		this.type_global_script.setConfig(conf);
		this.type_ipmi.setConfig(conf);
		this.type_ssh.setConfig(conf);
		this.type_telnet.setConfig(conf);
	};

	/**
	 * Attaches nodes for chosen command type.
	 *
	 * @param {jQuery} $wrapper
	 * @param {object} props
	 * @param {object} props['command_type']
	 */
	OperationViewCommand.prototype.attach = function($wrapper, {command_type}) {
		this.detach();

		this.$targets.appendTo($wrapper);
		this.$type.appendTo($wrapper);

		if (command_type == operation_details.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) {
			this.type_custom_script.attach($wrapper);
		}
		else if (command_type == operation_details.ZBX_SCRIPT_TYPE_IPMI) {
			this.type_ipmi.attach($wrapper);
		}
		else if (command_type == operation_details.ZBX_SCRIPT_TYPE_SSH) {
			this.type_ssh.attach($wrapper);
		}
		else if (command_type == operation_details.ZBX_SCRIPT_TYPE_TELNET) {
			this.type_telnet.attach($wrapper);
		}
		else if (command_type == operation_details.ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT) {
			this.type_global_script.attach($wrapper);
		}
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCommand.prototype.detach = function() {
		this.$targets.detach();
		this.$type.detach();
		this.type_custom_script.detach();
		this.type_global_script.detach();
		this.type_ipmi.detach();
		this.type_ssh.detach();
		this.type_telnet.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewType($obj) {
		this.$obj = $obj;
		this.$select = this.$obj.find('z-select');
		this.$select.on('change', ({target}) => this.onchange(target.value));
	}

	/**
	 * @param {string} value
	 */
	OperationViewType.prototype.onchange = function(value) {};

	/**
	 * @param {object} conf
	 * @param {array}  conf['options']             List of available options.
	 * @param {string} conf['options'][]['value']
	 * @param {string} conf['options'][]['name']
	 * @param {string} conf['selected']            The selected option value.
	 */
	OperationViewType.prototype.setConfig = function(conf) {
		const {options, selected} = conf;
		if (options.length == 1) {
			const $hidden_input = $('<input />', {type: 'hidden', name: this.$select.attr('name'), value: selected});
			this.$select.replaceWith([options[0].name, $hidden_input]);
		}
		else {
			options.forEach(({value, name}) => this.$select.get(0).addOption({value, label: name}));
			this.$select.val(selected);
		}
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewType.prototype.attach = function($wrapper) {
		this.$obj.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewType.prototype.detach = function() {
		this.$obj.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewAttr($obj) {
		this.$obj = $obj;

		this.$hostgroups = $obj.siblings('#operation-attr-hostgroups');
		this.$hostgroups_ms = this.$hostgroups.find('#operation_opgroup__groupid');

		this.$inventory = $obj.siblings('#operation-attr-inventory');

		const ms_groups_url = new Curl('jsrpc.php', false);
		ms_groups_url.setArgument('method', 'multiselect.get');
		ms_groups_url.setArgument('object_name', 'hostGroup');
		ms_groups_url.setArgument('editable', '1');
		ms_groups_url.setArgument('type', operation_details.PAGE_TYPE_TEXT_RETURN_JSON);

		this.$hostgroups_ms.multiSelect({
			url: ms_groups_url.getUrl(),
			name: 'operation[opgroup][][groupid]',
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'action.edit',
					dstfld1: 'operation_opgroup__groupid',
					editable: '1'
				}
			}
		});

		this.$templates = $obj.siblings('#operation-attr-templates');
		this.$templates_ms = this.$templates.find('#operation_optemplate__templateid');

		const ms_templates_url = new Curl('jsrpc.php', false);
		ms_templates_url.setArgument('method', 'multiselect.get');
		ms_templates_url.setArgument('object_name', 'templates');
		ms_templates_url.setArgument('editable', '1');
		ms_templates_url.setArgument('type', operation_details.PAGE_TYPE_TEXT_RETURN_JSON);

		this.$templates_ms.multiSelect({
			url: ms_templates_url.getUrl(),
			name: 'operation[optemplate][][templateid]',
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'templates',
					srcfld1: 'hostid',
					dstfrm: 'action.edit',
					dstfld1: 'operation_optemplate__templateid',
					editable: '1'
				}
			}
		});
	}

	/**
	 * @param {object} conf
	 * @param {array}  conf['hostgroups']
	 * @param {string} conf['hostgroups'][]['id']
	 * @param {string} conf['hostgroups'][]['name']
	 * @param {string} conf['inventory_mode']
	 * @param {array}  conf['templates']
	 * @param {string} conf['templates'][]['id']
	 * @param {string} conf['templates'][]['name']
	 */
	OperationViewAttr.prototype.setConfig = function(conf) {
		this.$inventory.find(`[value="${conf.inventory_mode}"]`).prop('checked', true);

		this.$hostgroups_ms.multiSelect('clean');
		this.$hostgroups_ms.multiSelect('addData', conf.hostgroups);

		this.$templates_ms.multiSelect('clean');
		this.$templates_ms.multiSelect('addData', conf.templates);
	};

	/**
	 * @param {jQuery} $wrapper
	 * @param {object} props
	 * @param {string} props['operation_type']
	 */
	OperationViewAttr.prototype.attach = function($wrapper, props) {
		if (props.operation_type == operation_details.OPERATION_TYPE_TEMPLATE_REMOVE
				|| props.operation_type == operation_details.OPERATION_TYPE_TEMPLATE_ADD) {
			this.$templates.appendTo($wrapper);
		}
		else if (props.operation_type == operation_details.OPERATION_TYPE_GROUP_REMOVE
				|| props.operation_type == operation_details.OPERATION_TYPE_GROUP_ADD) {
			this.$hostgroups.appendTo($wrapper);
		}
		else if (props.operation_type == operation_details.OPERATION_TYPE_HOST_INVENTORY) {
			this.$inventory.appendTo($wrapper);
		}
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewAttr.prototype.detach = function() {
		this.$hostgroups.detach();
		this.$templates.detach();
		this.$inventory.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewCondition($obj) {
		this.conditions = [];
		this.tmpl_condition_row = new Template(jQuery('#operation-condition-row-tmpl').html());

		this.$evaltype = $obj.siblings('#operation-condition-evaltype');

		this.$evaltype_formula = this.$evaltype.find('#operation-condition-evaltype-formula');
		this.$evaltype_select = this.$evaltype.find('z-select');

		this.$list = $obj.siblings('#operation-condition-list');

		this.$list.find('#operation-condition-list-footer button')
			.on('click', ({target}) => this.showConditionsPopup(target));

		this.$evaltype_select.on('change', ({target}) => {
			const conditions_fmt = this.conditions.map(({formulaid: id, conditiontype: type}) => ({id, type}));
			const formula = getConditionFormula(conditions_fmt, parseInt(target.value))
			this.$evaltype_formula.html(formula);
		});
	}

	/**
	 * @param {Overlay} overlay
	 */
	OperationViewCondition.prototype.onConditionPopupSubmit = function(overlay) {
		overlay.setLoading();

		const condition_form = new FormData(document.forms['popup.condition']);
		overlay.xhr = this.validateNewCondition(condition_form);
		overlay.xhr
			.fail(({statusText}) => {
				overlay.$dialogue.$body.find('output.msg-bad').remove();
				overlay.$dialogue.$body.prepend(makeMessageBox('bad', statusText));
				overlay.unsetLoading();
			})
			.then((res) => {
				if (res.errors) {
					overlay.$dialogue.$body.find('output.msg-bad').remove();
					overlay.$dialogue.$body.prepend(res.errors);

					return overlay.unsetLoading();
				}

				const {inputs: {conditiontype, operator, value}, name} = res;
				const condition = {conditiontype, operator, value, name};

				this.addCondition(condition);
				this.renderConditions();

				overlayDialogueDestroy(overlay.dialogueid);
			});
	};

	/**
	 * @param {FormData} condition_form
	 *
	 * @return {JQueryXHR}
	 */
	OperationViewCondition.prototype.validateNewCondition = function(condition_form) {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'popup.condition.actions');
		url.setArgument('validate', 1);

		return $.ajax({
			url: url.getUrl(),
			data: condition_form,
			processData: false,
			contentType: false,
			dataType: 'json',
			method: 'POST'
		});
	};

	/**
	 * @param {Node} return_focus
	 */
	OperationViewCondition.prototype.showConditionsPopup = function(return_focus) {
		PopUp('popup.condition.operations', {
			'type': operation_details.ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION,
			'source': operation_details.EVENT_SOURCE_TRIGGERS
		}, null, return_focus);
	};

	/**
	 * Re-renders current list of conditions.
	 */
	OperationViewCondition.prototype.renderConditions = function() {
		this.$list.find('tr[data-id]').remove();

		this.conditions.forEach((condition, num) => {
			condition.formulaid = num2letter(num);
			condition.num = num;
			const $condition = $(this.tmpl_condition_row.evaluate(condition));

			$condition.find('[data-action="remove"]').on('click', () => {
				this.conditions.splice(num, 1);
				this.renderConditions();
			});

			this.$list
				.find('#operation-condition-list-footer')
				.before($condition);
		});

		if (this.conditions.length < 2) {
			this.$evaltype.hide();
		}
		else {
			this.$evaltype_select.trigger('change');
			this.$evaltype.show();
		}
	};

	/**
	 * Adds a conditional to current list of conditions only if condition by this name does not exist in current list.
	 *
	 * @param {object} condition
	 * @param {string} condition['conditiontype']
	 * @param {string} condition['formulaid']
	 * @param {string} condition['name']
	 * @param {string} condition['operator']
	 * @param {string} condition['value']
	 */
	OperationViewCondition.prototype.addCondition = function(condition) {
		let exists = this.conditions.map((cond) => cond.name).includes(condition.name);
		if (!exists) {
			this.conditions.push(condition);
		}
	};

	/**
	 * @param {object} conf
	 * @param {array}  conf['conditions']
	 * @param {string} conf['conditions'][]['conditiontype']
	 * @param {string} conf['conditions'][]['formulaid']
	 * @param {string} conf['conditions'][]['name']
	 * @param {string} conf['conditions'][]['operator']
	 * @param {string} conf['conditions'][]['value']
	 * @param {string} conf['evaltype']
	 */
	OperationViewCondition.prototype.setConfig = function(conf) {
		conf.conditions.forEach((condition) => this.addCondition(condition));
		this.renderConditions();

		this.$evaltype_select.val(conf.evaltype);
		this.$evaltype_select.trigger('change');
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewCondition.prototype.attach = function($wrapper) {
		this.$evaltype.appendTo($wrapper);
		this.$list.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewCondition.prototype.detach = function() {
		this.$evaltype.detach();
		this.$list.detach();
	};

	/**
	 * @param {jQuery} $obj  JQuery collection to hydrate.
	 */
	function OperationViewSteps($obj) {
		this.$obj = $obj;
		this.$from = $obj.find('#operation_esc_step_from');
		this.$to = $obj.find('#operation_esc_step_to');
		this.$duration = $obj.find('#operation_esc_period');
	}

	/**
	 * Sets config for each of views.
	 *
	 * @param {object} conf
	 * @param {string} conf['from']
	 * @param {string} conf['to']
	 * @param {string} conf['duration']
	 */
	OperationViewSteps.prototype.setConfig = function(conf) {
		this.$from.val(conf.from);
		this.$to.val(conf.to);
		this.$duration.val(conf.duration);
	};

	/**
	 * @param {jQuery} $wrapper
	 */
	OperationViewSteps.prototype.attach = function($wrapper) {
		this.$obj.appendTo($wrapper);
	};

	/**
	 * Detaches all instance nodes.
	 */
	OperationViewSteps.prototype.detach = function() {
		this.$obj.detach();
	};

	/**
	 * @param {Node}   return_focus  The node a popup returns focus to when it closes.
	 * @param {number} eventsource
	 * @param {number} recovery_phase
	 * @param {number} actionid
	 */
	function OperationPopup(return_focus, eventsource, recovery_phase, actionid) {
		this.return_focus = return_focus;
		this.eventsource = eventsource;
		this.recovery_phase = recovery_phase;
		this.actionid = actionid;

		this.overlay = overlayDialogue({
			class: 'modal-popup modal-popup-medium',
			title: t('Operation details')
		});

		const props = {
			recovery_phase,
			operation_type: operation_details.OPERATION_TYPE_MESSAGE,
			command_type: operation_details.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		};

		this.view = new OperationView(props);
		this.view.onupdate = () => this.overlay.centerDialog();
	}

	/**
	 * Validates operation form.
	 *
	 * @param {FormData} operation_form
	 *
	 * @return {JQueryXHR}
	 */
	OperationPopup.prototype.validate = function(operation_form) {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'action.operation.validate');
		url.setArgument('actionid', this.actionid);

		return $.ajax({
			url: url.getUrl(),
			processData: false,
			contentType: false,
			data: operation_form,
			dataType: 'json',
			method: 'POST'
		});
	};

	/**
	 * @return {FormData}
	 */
	OperationPopup.prototype.getFormData = function() {
		const form_data = new FormData(this.overlay.$dialogue.$body.find('form').get(0));

		if (this.operation_num !== null) {
			form_data.append('operation[id]', this.operation_num);
		}

		form_data.append('operation[eventsource]', this.eventsource);
		form_data.append('operation[recovery]', this.recovery_phase);

		form_data.append('operation[operationtype]', form_data.get('operationtype'));
		form_data.delete('operationtype');

		return form_data;
	};

	/**
	 * Validates popup form, displays error if found, else submits main page.
	 */
	OperationPopup.prototype.onsubmit = function() {
		this.overlay.setLoading();

		const form_data = this.getFormData();

		this.overlay.xhr = this.validate(form_data);
		this.overlay.xhr
			.fail(({statusText}) => {
				this.overlay.$dialogue.$body.find('output.msg-bad').remove();
				this.overlay.$dialogue.$body.prepend(makeMessageBox('bad', statusText));
				this.overlay.unsetLoading();
			})
			.done((res) => {
				if (res.errors) {
					this.overlay.$dialogue.$body.find('output.msg-bad').remove();
					this.overlay.$dialogue.$body.prepend(res.errors);

					return this.overlay.unsetLoading();
				}
				// We keep overlay opened and in loading state during the full page reload.
				this.submit(form_data);
			});
	};

	/**
	 * @param {object}      res
	 * @param {object}      res['popup_config']
	 * @param {string|null} res['debug_data']
	 */
	OperationPopup.prototype.onload = function(res) {
		if (res.errors) {
			return this.overlay.setProperties({content: res.errors});
		}

		const buttons = [{
			title: this.operation_num === null ? t('Add') : t('Update'),
			class: '',
			keepOpen: true,
			isSubmit: true,
			action: () => this.onsubmit()
		}, {
			title: t('Cancel'),
			class: 'btn-alt',
			cancel: true,
			action: () => this.return_focus.focus()
		}];

		this.view.setConfig(res.popup_config);
		this.view.render();
		this.overlay.setProperties({content: this.view.$obj, debug: res.debug, buttons});
		this.overlay.containFocus();
		this.view.$obj.find(':focusable:first').focus();
	};

	/**
	 * Loads popup configuration from server, then displays configured view.
	 *
	 * @param {object} operation  (optional) The raw operation object.
	 */
	OperationPopup.prototype.load = function(operation) {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'action.operation.get');
		url.setArgument('eventsource', this.eventsource);
		url.setArgument('recovery', this.recovery_phase);

		if (operation) {
			this.operation_num = operation.id;
		}
		else {
			this.operation_num = null;
		}

		this.overlay.xhr = $.post(url.getUrl(), {operation});
		this.overlay.xhr
			.done(res => this.onload(res))
			.fail(({statusText}) => this.overlay.setProperties({content: makeMessageBox('bad', statusText)}));
	};

	/**
	 * Merges given operation form with page form and submits page.
	 *
	 * @param {FormData} operation_form
	 */
	OperationPopup.prototype.submit = function(operation_form) {
		let recovery_prefix = '';
		if (this.recovery_phase == operation_details.ACTION_RECOVERY_OPERATION) {
			recovery_prefix = 'recovery_';
		}
		else if (this.recovery_phase == operation_details.ACTION_ACKNOWLEDGE_OPERATION) {
			recovery_prefix = 'ack_';
		}

		const form = document.forms['action.edit'];
		const input = document.createElement('input');
		input.setAttribute('type', 'hidden');
		input.setAttribute('name', `add_${recovery_prefix}operation`);
		input.setAttribute('value', '1');
		form.appendChild(input);

		operation_form.forEach((value, name) => {
			const input = document.createElement('input');
			input.setAttribute('type', 'hidden');
			input.setAttribute('name', `new_${recovery_prefix}${name}`);
			input.setAttribute('value', value);
			form.appendChild(input);
		});

		form.submit();
	};

	window.operation_details = {
		/**
		 * Opens operation details popup.
		 *
		 * @param {Node}   target          Popup opener the focus will be returned to.
		 * @param {number} actionid        Current actionid.
		 * @param {string} eventsource     One of: EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
		 *                                 EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL.
		 * @param {string} recovery_phase  One of: ACTION_OPERATION, ACTION_RECOVERY_OPERATION,
		 *                                 ACTION_ACKNOWLEDGE_OPERATION.
		 * @param {object} operation       (optional) Current operation object.
		 */
		open(target, actionid, eventsource, recovery_phase, operation) {
			const operation_popup = new OperationPopup(target, eventsource, recovery_phase, actionid);
			operation_popup.load(operation);

			/*
			 * This is used to workaround hardcoded js function calls in popup html response.
			 * See: "addPopupValues", "validateConditionPopup".
			 */
			window.operation_popup = operation_popup;
		}
	}

	window.operation_details.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT             = <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>;
	window.operation_details.ZBX_SCRIPT_TYPE_IPMI                      = <?= ZBX_SCRIPT_TYPE_IPMI ?>;
	window.operation_details.ZBX_SCRIPT_TYPE_SSH                       = <?= ZBX_SCRIPT_TYPE_SSH ?>;
	window.operation_details.ZBX_SCRIPT_TYPE_TELNET                    = <?= ZBX_SCRIPT_TYPE_TELNET ?>;
	window.operation_details.ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT             = <?= ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT ?>;
	window.operation_details.OPERATION_TYPE_ACK_MESSAGE                = <?= OPERATION_TYPE_ACK_MESSAGE ?>;
	window.operation_details.OPERATION_TYPE_RECOVERY_MESSAGE           = <?= OPERATION_TYPE_RECOVERY_MESSAGE ?>;
	window.operation_details.OPERATION_TYPE_HOST_INVENTORY             = <?= OPERATION_TYPE_HOST_INVENTORY ?>;
	window.operation_details.OPERATION_TYPE_TEMPLATE_REMOVE            = <?= OPERATION_TYPE_TEMPLATE_REMOVE ?>;
	window.operation_details.OPERATION_TYPE_TEMPLATE_ADD               = <?= OPERATION_TYPE_TEMPLATE_ADD ?>;
	window.operation_details.OPERATION_TYPE_GROUP_REMOVE               = <?= OPERATION_TYPE_GROUP_REMOVE ?>;
	window.operation_details.OPERATION_TYPE_GROUP_ADD                  = <?= OPERATION_TYPE_GROUP_ADD ?>;
	window.operation_details.OPERATION_TYPE_COMMAND                    = <?= OPERATION_TYPE_COMMAND ?>;
	window.operation_details.OPERATION_TYPE_MESSAGE                    = <?= OPERATION_TYPE_MESSAGE ?>;
	window.operation_details.ACTION_ACKNOWLEDGE_OPERATION              = <?= ACTION_ACKNOWLEDGE_OPERATION ?>;
	window.operation_details.ACTION_RECOVERY_OPERATION                 = <?= ACTION_RECOVERY_OPERATION ?>;
	window.operation_details.ACTION_OPERATION                          = <?= ACTION_OPERATION ?>;
	window.operation_details.ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION = <?= ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION ?>;
	window.operation_details.ITEM_AUTHTYPE_PUBLICKEY                   = <?= ITEM_AUTHTYPE_PUBLICKEY ?>;
	window.operation_details.EVENT_SOURCE_TRIGGERS                     = <?= EVENT_SOURCE_TRIGGERS ?>;
	window.operation_details.PAGE_TYPE_TEXT_RETURN_JSON                = <?= PAGE_TYPE_TEXT_RETURN_JSON ?>;
	window.operation_details.MEDIA_TYPE_DISABLED                       = <?= MEDIA_TYPE_STATUS_DISABLED ?>;
	window.operation_details.ZBX_STYLE_RED                             = '<?= ZBX_STYLE_RED ?>';
</script>
