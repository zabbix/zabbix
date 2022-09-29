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
	init({eventsource, recovery_phase}) {
		this.recovery_phase = recovery_phase;
		this.eventsource = eventsource;
		this.overlay = overlays_stack.getById('operations');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		if (document.getElementById('operation-condition-list')) {
			this.condition_count = (document.getElementById('operation-condition-list').rows.length - 2);
		}

		this._loadViews();
		this._processTypeOfCalculation();
	}

	_loadViews() {
		this._removeAllFields();
		this._sendMessageFields();

		jQuery('#operation-type-select').on('change', (e) => {
			// todo : rewrite - switch??!
			this._removeAllFields();

			const operation_type = document.getElementById('operation-type-select').value;

			if (operation_type == 'cmd[0]') {
				this._sendMessageFields();
			}
			else if (operation_type == 'cmd[2]' || operation_type == 'cmd[3]') {
				// todo : add hidden input - ?
			}
			else if (operation_type == 'cmd[4]' || operation_type == 'cmd[5]') {
				this._hostGroupFields();
			}
			else if (operation_type == 'cmd[6]' || operation_type == 'cmd[7]') {
				this._templateFields();
			}
			else if (operation_type == 'cmd[8]' || operation_type == 'cmd[9]') {
				// todo : add hidden input - ?
			}
			else if (operation_type == 'cmd[10]') {
				this._hostInventoryFields();
			}
			else if (operation_type == 'cmd[11]') {
				this._allInvolvedFields();
			}
			else if (operation_type == 'cmd[12]') {
				this._allInvolvedFieldsUpdate();
			}
			else {
				this._addScriptFields();
			}
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

	_allInvolvedFields() {
		const fields = [
			'operation-message-custom-label', 'operation-message-custom', 'operation-message-subject',
			'operation-message-body', 'opmessage'
		]

		this._enableFormFields(fields);
		this._addCustomMessageFields();
	}

	_allInvolvedFieldsUpdate() {
		const fields = [
			'operation-message-custom-label', 'operation-message-custom', 'operation-message-mediatype-default-label',
			'operation-message-mediatype-default'
		]

		this._enableFormFields(fields);
		this._addCustomMessageFields();
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
		}
	}

	_sendMessageFields() {
		switch (this.eventsource) {
			case <?= EVENT_SOURCE_TRIGGERS ?>:
			case <?= EVENT_SOURCE_INTERNAL ?>:
			case <?= EVENT_SOURCE_SERVICE?>:
				this.fields = [
					'step-from', 'operation-step-range', 'operation-step-duration', 'operation-message-notice',
					'operation-message-user-groups', 'operation-message-notice', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-type',
					'operation-message-subject', 'operation-message-body'
				]
				break;
			case <?= EVENT_SOURCE_DISCOVERY ?>:
			case <?= EVENT_SOURCE_AUTOREGISTRATION ?>:
				this.fields = [
					'operation-message-notice', 'operation-message-user-groups', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-type',
					'operation-message-subject', 'operation-message-body', 'operation-message-notice'
				]
				break;
		}

		this._enableFormFields(this.fields);
		this._addCustomMessageFields();
	}

	_enableFormFields(fields = []) {
		for (let field of this.form.getElementsByClassName('form-field')) {
			if (fields.includes(field.id)) {
				field.style.display = 'block';

				for (let input of field.querySelectorAll('input, textarea')) {
					input.removeAttribute('disabled')
				}
				for (let label of field.querySelectorAll('label')) {
					label.style.display = 'block';
				}
			}
		}

		for (let label of this.form.getElementsByTagName('label')) {
			if (fields.includes(label.id.replace('-label', ''))) {
				label.style.display = 'block';
			}
			if (fields.includes(label.htmlFor)) {
				label.style.display = 'block';
			}
		}
	}

	_hostInventoryFields() {
		const fields = ['operation-attr-inventory']
		this._enableFormFields(fields);
	}

	_addScriptFields() {
		const fields = ['step-from', 'operation-step-range', 'operation-step-duration',
			'operation-command-targets-label', 'operation-command-checkbox',
			'operation-command-chst', 'operation-command-chst-label',
			'operation-opcommand-hst-label', 'operation-opcommand-grp',

			'operation-opcommand-grp-label', 'operation-command-targets',
			'operation-command-targets-label', 'operation-condition-list-label', 'operation-condition-list',
			'operation-condition-table'
		];
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
						dstfld1: 'operation-command-target-hosts',
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
					dstfld1: 'operation-command-target-groups',
					editable: '1'
				}
			}
		});
	}

	_openUserGroupPopup(trigger_element) {
		const parameters = {
			'srctbl': 'usrgrp',
			'srcfld1': 'usrgrpid',
			'srcfld2': 'name',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-user-groups-footer',
			'multiselect': '1'
		}

		const overlay = PopUp('popup.generic', parameters, {
			dialogue_class: 'modal-popup-generic', trigger_element, dialogueid: 'usergroup-popup'
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

	_addUserGroup(values) {
		values.forEach((value, index) => {
			const row = document.createElement('tr');
			row.append(value.name)
			row.append(this._createRemoveCell())
			row.appendChild(this._createHiddenInput(`operation[opmessage_grp][${index}][usrgrpid]`,value.usrgrpid));

			document.getElementById('operation-message-user-groups-footer').before(row);
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

	_addUser(values) {
		// todo : fix bug
		values.forEach((value, index) => {
			const row = document.createElement('tr');
			row.append(value.name)
			row.append(this._createRemoveCell())
			row.append(this._createHiddenInput(`operation[opmessage_usr][][userid]`, value.id))

			document.getElementById('operation-message-users-footer').before(row);
		});
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
			// this._checkRow(e.detail)
			// todo : check if row already exists
			this._createRow(e.detail);
			this._processTypeOfCalculation();

		});
	}

	_checkRow(input) {
	// todo: add function to check if row already exists
	}

	_createRow(input) {
		const row = document.createElement('tr');

		row.append(this._createLabel(input));
		row.append(this._createName(input));
		row.append(this._createRemoveCell());

		row.appendChild(this._createHiddenInput('operation[condition][formulaid]', this.label));
		row.appendChild(this._createHiddenInput('operation[condition][conditiontype]', input.conditiontype));
		row.appendChild(this._createHiddenInput('operation[condition][operator]', input.operator));
		row.appendChild(this._createHiddenInput('operation[condition][value]', input.value));

		this.table = document.getElementById('operation-condition-list');
		this.row_count = this.table.rows.length -1;

		$('#operation-condition-list tr:last').before(row);
	}

	_createLabel(input) {
		// todo E.S. : FIX LABEL WHEN DELETE ROW AND ADD A NEW ONE!!
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
		let curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'action.operation.validate');
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

	_addCustomMessageFields() {
		// todo E.S. : rewrite jqueries:
		$('[id="operation-message-subject"],[id="operation-message-subject-label"]').hide();
		$('[id="operation-message-body"],[id="operation-message-label"]').hide();

		$('#operation_opmessage_default_msg')
			.change(function() {
				if($('#operation_opmessage_default_msg')[0].checked) {
					$('[id="operation-message-subject"],[id="operation-message-subject-label"]').show().attr('disabled', false);
					$('[id="operation-message-body"],[id="operation-message-label"]').show().attr('disabled', false);
				}
				else {
					$('[id="operation-message-subject"],[id="operation-message-subject-label"]').hide();
					$('[id="operation-message-body"],[id="operation-message-label"]').hide();
				}
			})
	}

	_processTypeOfCalculation() {
		// todo E.S.: rewrite jqueries.
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
}
