/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

window.operation_popup = new class {
	init({eventsource, recovery_phase, data, actionid}) {
		this.recovery_phase = recovery_phase;
		this.eventsource = eventsource;
		this.overlay = overlays_stack.getById('operations');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.actionid = actionid;

		if (document.getElementById('operation-condition-list')) {
			this.condition_count = (document.getElementById('operation-condition-list').rows.length - 2);
		}

		this._loadViews();
		this._processTypeOfCalculation();

		if (data?.opconditions) {
			data?.opconditions.map(row => this._createRow(row))
		}
		if (data?.opmessage_grp) {
			this._addUserGroup(data.opmessage_grp, data.opmessage_grp.length);
		}
		if (data?.opmessage_usr) {
			this._addUser(data.opmessage_usr, data.opmessage_usr.length);
		}
	}

	_loadViews() {
		this._customMessageFields();
		this._removeAllFields();
		const operation_type = document.getElementById('operation-type-select').value;
		this._changeView(operation_type)

		jQuery('#operation-type-select').on('change', () => {
			const operation_type = document.getElementById('operation-type-select').value;

			this._removeAllFields();
			this._changeView(operation_type)
		});

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('operation-message-user-groups-footer')) {
				this._openUserGroupPopup(e.target);
			}
			else if (e.target.classList.contains('operation-message-users-footer')) {
				this._openUserPopup(e.target);
			}
			else if (e.target.classList.contains('operation-condition-list-footer')) {
				this._openConditionsPopup(e.target);
			}
			else if (e.target.classList.contains('element-table-remove')) {
				this.row_count--;
				this._processTypeOfCalculation();
			}
		});
	}

	_changeView(operation_type) {
		switch (operation_type) {
			case 'cmd[0]':
				this._sendMessageFields();
				break;
			case 'cmd[4]':
			case 'cmd[5]':
				this._hostGroupFields();
				break;
			case 'cmd[6]':
			case 'cmd[7]':
				this._templateFields();
				break;
			case 'cmd[10]':
				this._hostInventoryFields();
				break;
			case 'cmd[11]':
				this._allInvolvedRecoveryFields();
				break;
			case 'cmd[12]':
				this._allInvolvedFieldsUpdate();
				break;
			case 'cmd[2]':
			case 'cmd[3]':
			case 'cmd[8]':
			case 'cmd[9]':
				break;
			default:
				this._addScriptFields();
				break;
		}
	}

	_allInvolvedRecoveryFields() {
		const fields = [
			'operation-message-custom-label', 'operation-message-custom', 'operation-message-subject',
			'operation-message-body', 'opmessage', 'operation-message-subject-label', 'operation_opmessage_default_msg'
		]

		this._enableFormFields(fields);
		this._customMessageFields();
	}

	_allInvolvedFieldsUpdate() {
		const fields = [
			'operation-message-custom-label', 'operation-message-custom', 'operation-message-subject',
			'operation-message-body', 'operation_opmessage_default_msg', 'operation-message-mediatype-default'
		]

		this._enableFormFields(fields);
		this._customMessageFields();
	}

	_hostGroupFields() {
		jQuery('#operation-attr-hostgroups').toggle(true);
		jQuery('#operation-attr-hostgroups-label').toggle(true);

		this.hostgroups_ms = jQuery('#operation_opgroup__groupid');

		const ms_groups_url = new Curl('jsrpc.php', false);
		ms_groups_url.setArgument('method', 'multiselect.get');
		ms_groups_url.setArgument('object_name', 'hostGroup');
		ms_groups_url.setArgument('editable', '1');
		ms_groups_url.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);

		this.hostgroups_ms.multiSelect({
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
	}

	_templateFields() {
		jQuery('#operation-attr-templates').toggle(true)
		jQuery('#operation-attr-templates-label').toggle(true)

		this.templates_ms = jQuery('#operation_optemplate__templateid');

		const ms_templates_url = new Curl('jsrpc.php', false);
		ms_templates_url.setArgument('method', 'multiselect.get');
		ms_templates_url.setArgument('object_name', 'templates');
		ms_templates_url.setArgument('editable', '1');
		ms_templates_url.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);

		this.templates_ms.multiSelect({
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

	_removeAllFields() {
		for (let field of this.form.getElementsByClassName('form-field')) {
			if (field.id === 'operation-type') {
				continue;
			}
			field.style.display = 'none';
			field.getElementsByTagName('input')
		}

		for (let label of this.form.getElementsByTagName('label')) {
			if (label.id === 'operation-type-label') {
				continue;
			}
			label.style.display = 'none';
		}

		for (let input of this.form.querySelectorAll('input, textarea')) {
			if (['operation_eventsource', 'operation_recovery', 'submit'].includes(input.id)) {
				continue;
			}
			if (input.name === 'operation[operationtype]') {
				continue;
			}
			input.setAttribute('disabled', true)
			input.style.display = 'none';
		}
	}

	_sendMessageFields() {
		$('#operation_opmessage_default_msg').trigger('change')

		switch (this.eventsource) {
			case <?= EVENT_SOURCE_TRIGGERS ?>:
				this.fields = [
					'operation-condition-table', 'operation-condition-list-label', 'operation-condition-list',
					'step-from', 'operation-step-range', 'operation-step-duration', 'operation-message-notice',
					'operation-message-user-groups', 'operation-message-notice', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-type',
					'operation-condition-row', 'operation-condition-evaltype-formula', 'operation-evaltype-label',
					'operation-evaltype'
				]
				this._customMessageFields();
				this._processTypeOfCalculation();
				break;
			case <?= EVENT_SOURCE_INTERNAL ?>:
			case <?= EVENT_SOURCE_SERVICE?>:
				this.fields = [
					'step-from', 'operation-step-range', 'operation-step-duration', 'operation-message-notice',
					'operation-message-user-groups', 'operation-message-notice', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-type',
					'operation-message-subject', 'operation-message-body'
				]
				this._customMessageFields();
				break;
			case <?= EVENT_SOURCE_DISCOVERY ?>:
			case <?= EVENT_SOURCE_AUTOREGISTRATION ?>:
				this.fields = [
					'operation-message-notice', 'operation-message-user-groups', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-type',
					'operation-message-notice'
				]
				this._customMessageFields();
				break;
		}

		this._enableFormFields(this.fields);
	}

	_enableFormFields(fields = []) {
		for (let field of this.form.getElementsByClassName('form-field')) {
			if (fields.includes(field.id)) {
				field.style.display = '';

				for (let input of field.querySelectorAll('input, textarea')) {
					input.removeAttribute('disabled');
					input.style.display = '';
				}
				for (let label of field.querySelectorAll('label')) {
					label.style.display = '';
				}
			}
		}

		for (let label of this.form.getElementsByTagName('label')) {
			if (fields.includes(label.id.replace('-label', ''))) {
				label.style.display = '';
			}
			if (fields.includes(label.htmlFor)) {
				label.style.display = '';
			}
		}
	}

	_hostInventoryFields() {
		const fields = ['operation-attr-inventory']
		this._enableFormFields(fields);
	}

	_addScriptFields() {
		let fields;
		switch (this.eventsource) {
			case <?= EVENT_SOURCE_TRIGGERS ?>:
				fields = [
					'step-from', 'operation-step-range', 'operation-step-duration', 'operation-command-targets-label',
					'operation-command-checkbox', 'operation-command-chst-label',
					'operation-opcommand-hst-label', 'operation-opcommand-grp', 'operation-command-targets',
					'operation-condition-table', 'operation-condition-list-label', 'operation-condition-list',
					'operation_opcommand_hst__hostidch', 'operation_opcommand_hst__hostid_current_host'
				];

				break;
			case <?= EVENT_SOURCE_SERVICE ?>:
				fields = [
					'step-from', 'operation-step-range', 'operation-step-duration',
					'operation-command-targets-label'
				];

				break;
			case <?= EVENT_SOURCE_DISCOVERY ?>:
			case <?= EVENT_SOURCE_AUTOREGISTRATION ?>:
				fields = [
					'operation-command-targets-label',
					'operation-command-checkbox', 'operation-command-chst', 'operation-command-chst-label',
					'operation-opcommand-hst-label', 'operation-opcommand-grp', 'operation-command-targets',
					'operation-condition-table', 'operation-condition-list-label', 'operation-condition-list',
					'operation_opcommand_hst__hostidch'
				]
		}

		this._enableFormFields(fields);

		this.targets_hosts_ms = jQuery('#operation_opcommand_hst__hostid');

		const ms_hosts_url = new Curl('jsrpc.php', false);
		ms_hosts_url.setArgument('method', 'multiselect.get');
		ms_hosts_url.setArgument('object_name', 'hosts');
		ms_hosts_url.setArgument('editable', '1');
		ms_hosts_url.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);

		this.targets_hosts_ms.multiSelect({
			url: ms_hosts_url.getUrl(),
			name: 'operation[opcommand_hst][][hostid]',
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'hosts',
					srcfld1: 'hostid',
					dstfrm: 'action.edit',
					dstfld1: 'operation_opcommand_hst__hostid',
					editable: '1'
				}
			}
		});

		const ms_groups_url = new Curl('jsrpc.php', false);
		ms_groups_url.setArgument('method', 'multiselect.get');
		ms_groups_url.setArgument('object_name', 'hostGroup');
		ms_groups_url.setArgument('editable', '1');
		ms_groups_url.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);

		this.targets_groups_ms = jQuery('#operation_opcommand_grp__groupid');
		this.targets_groups_ms.multiSelect({
			url: ms_groups_url.getUrl(),
			name: 'operation[opcommand_grp][][groupid]',
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'action.edit',
					dstfld1: 'operation_opcommand_grp__groupid',
					editable: '1'
				}
			}
		});
	}

	_openUserGroupPopup(target) {
		const parameters = {
			'srctbl': 'usrgrp',
			'srcfld1': 'usrgrpid',
			'srcfld2': 'name',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-user-groups-footer',
			'multiselect': '1'
		}

		const overlay = PopUp('popup.generic', parameters, {
			dialogue_class: 'modal-popup-generic', target, dialogueid: 'usergroup-popup'
		});

		window.addPopupValues = ({object: objectid, parentId: sourceid, values}) => {
			if (sourceid === 'operation-message-user-groups-footer') {
				overlay.$dialogue[0].dispatchEvent(new CustomEvent('submit-usergroups-popup', {detail:values}));
			}
		};

		overlay.$dialogue[0].addEventListener('submit-usergroups-popup', (e) => {
			this._addUserGroup(e.detail);
		})
	}

	_addUserGroup(values, row_count = 0) {
		values.forEach((value, index) => {
			const row = document.createElement('tr');
			row.append(value.name)
			row.append(this._createRemoveCell())
			row.appendChild(
				this._createHiddenInput(`operation[opmessage_grp][${index + row_count}][usrgrpid]`, value.usrgrpid)
			);

			document.getElementById('operation-message-user-groups-footer').before(row);
		});
	}

	_addUser(values, row_count = 0) {
		values.forEach((value, index) => {
			const row = document.createElement('tr');
			row.append(value.name)
			row.append(this._createRemoveCell())
			row.append(this._createHiddenInput(`operation[opmessage_usr][${index + row_count}][userid]`, value.id))

			document.getElementById('operation-message-users-footer').before(row);
		});
	}

	_createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = value;

		return input;
	}

	_openUserPopup(trigger_element) {
		const overlay = PopUp('popup.generic', {
			'srctbl': 'users',
			'srcfld1': 'userid',
			'srcfld2': 'fullname',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-users-footer',
			'multiselect': '1'
		}, {dialogue_class: 'modal-popup-generic', trigger_element});

		window.addPopupValues = ({object: objectid, parentId: sourceid, values}) => {
			if (sourceid === 'operation-message-users-footer') {
				overlay.$dialogue[0].dispatchEvent(new CustomEvent('submit-users-popup', {detail: values}));
			}
		}

		overlay.$dialogue[0].addEventListener('submit-users-popup', (e) => {
			this._addUser(e.detail);
		})
	}

	_openConditionsPopup(trigger_element) {
		const parameters = {
			'type': <?= ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION ?>,
			'source': this.eventsource
		};

		const overlay = PopUp('popup.condition.operations', parameters, {
			dialogue_class: 'modal-popup-medium',
			trigger_element: trigger_element,
			dialogueid: 'operation-condition'
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			this._createRow(e.detail);
			this._processTypeOfCalculation();
		});
	}

	_createRow(input) {
		const row = document.createElement('tr');

		row.append(this._createLabel(input));
		row.append(this._createName(input));
		row.append(this._createRemoveCell());

		this.table = document.getElementById('operation-condition-list');
		this.row_count = this.table.rows.length -1;

		row.appendChild(this._createHiddenInput(
			`operation[opconditions][${this.row_count-1}][conditiontype]`, input.conditiontype)
		);
		row.appendChild(this._createHiddenInput(`operation[opconditions][${this.row_count-1}][operator]`, input.operator));
		row.appendChild(this._createHiddenInput(`operation[opconditions][${this.row_count-1}][value]`, input.value));

		$('#operation-condition-list tr:last').before(row);
	}

	_createLabel(input) {
		const cell = document.createElement('td');

		this.label = num2letter(document.getElementById('operation-condition-list').rows.length -2);
		cell.setAttribute('class', 'label');
		cell.setAttribute('data-formulaid', this.label);
		cell.setAttribute('data-conditiontype', input.conditiontype);
		cell.append(this.label);
		return cell;
	}

	_createName(input) {
		const cell = document.createElement('td');
		if (input.conditiontype == <?= CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
			if (input.value == 1) {
				cell.append('Event is acknowledged');
			} else if (input.value == 0) {
				cell.append('Event is not acknowledged');
			}
		}
		return cell;
	}

	_createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.textContent = <?= json_encode(_('Remove')) ?>;
		btn.addEventListener('click', () => btn.closest('tr').remove());

		cell.appendChild(btn);
		return cell;
	}

	submit() {
		const actionid = this._createHiddenInput('actionid', this.actionid)
		this.form.append(actionid);

		let curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'action.operation.check');
		const fields = getFormFields(this.form);

		this._post(curl.getUrl(), fields);
	}

	_post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('operation.submit', {detail: response}));
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];
				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	_processTypeOfCalculation() {
		jQuery('#operation-evaltype').toggle(this.row_count > 1);
		jQuery('#operation-evaltype-label').toggle(this.row_count > 1);
		jQuery('#operation-condition-row').toggle(this.row_count > 1);

		const labels = jQuery('#operation-condition-list .label');
		var conditions = [];
		labels.each(function(index, label) {
			var label = jQuery(label);

			conditions.push({
				id: label.data('formulaid'),
				type: label.data('conditiontype')
			});
		});
		jQuery('#operation-condition-evaltype-formula').html(getConditionFormula(conditions, +jQuery('#operation-evaltype').val()));

		jQuery('#operation-evaltype').change(function() {
			const labels = jQuery('#operation-condition-list .label');
			var conditions = [];

			labels.each(function(index, label) {
				var label = jQuery(label);

				conditions.push({
					id: label.data('formulaid'),
					type: label.data('conditiontype')
				});
			});

			jQuery('#operation-condition-evaltype-formula').html(getConditionFormula(conditions, +jQuery('#operation-evaltype').val()));
		});
	}

	_customMessageFields() {
		$('#operation_opmessage_default_msg')
			.change(function() {
				if($('#operation_opmessage_default_msg')[0].checked) {
					$('[id="operation-message-subject"],[id="operation-message-subject-label"]').show().attr('disabled', false);
					$('[id="operation-message-body"],[id="operation-message-label"]').show().attr('disabled', false);
					$('#operation_opmessage_default_msg').val(0);
				}
				else {
					$('[id="operation-message-subject"],[id="operation-message-subject-label"]').hide();
					$('[id="operation-message-body"],[id="operation-message-label"]').hide();
					$('#operation_opmessage_default_msg').val(1);
				}
			})
			.trigger('change');
	}
}
