<?php declare(strict_types = 0);
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
?>


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

		document.querySelector('#operation-type-select').onchange = () => {
			const operation_type = document.getElementById('operation-type-select').value;

			this._removeAllFields();
			this._changeView(operation_type)
		}

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
		let type = parseInt(operation_type.replace(/\D/g, ''));

		if ((/\b(scriptid)\b/g).test(operation_type)){
			type = <?= 	OPERATION_TYPE_COMMAND ?>;
		}

		switch (type) {
			case <?= OPERATION_TYPE_MESSAGE ?>:
				this._sendMessageFields();
				break;

			case <?= OPERATION_TYPE_GROUP_ADD ?>:
			case <?= OPERATION_TYPE_GROUP_REMOVE ?>:
				this._hostGroupFields();
				break;

			case <?= OPERATION_TYPE_TEMPLATE_ADD ?>:
			case <?= OPERATION_TYPE_TEMPLATE_REMOVE ?>:
				this._templateFields();
				break;

			case <?= OPERATION_TYPE_HOST_INVENTORY ?>:
				this._hostInventoryFields();
				break;

			case <?= OPERATION_TYPE_RECOVERY_MESSAGE ?>:
				this._allInvolvedRecoveryFields();
				break;

			case <?= OPERATION_TYPE_UPDATE_MESSAGE ?>:
				this._allInvolvedFieldsUpdate();
				break;

			case <?= OPERATION_TYPE_HOST_ADD ?>:
			case <?= OPERATION_TYPE_HOST_REMOVE ?>:
			case <?= OPERATION_TYPE_HOST_ENABLE ?>:
			case <?= OPERATION_TYPE_HOST_DISABLE ?>:
				break;

			case <?= OPERATION_TYPE_UPDATE_MESSAGE ?>:
				this._allInvolvedFieldsUpdate();
				break;

			case <?= OPERATION_TYPE_COMMAND ?>:
				this._addScriptFields();
				break;

			default:
				this._sendMessageFields();
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
		document.getElementById('operation-attr-hostgroups').style.display='';
		document.getElementById('operation-attr-hostgroups-label').style.display='';

		const $hostgroup_ms = $('#operation_opgroup__groupid');

		$hostgroup_ms.on('change', () => {
			$hostgroup_ms.multiSelect('setDisabledEntries',
				[... this.form.querySelectorAll('[name^="operation[opgroup]["]')].map((input) => input.value)
			);
		});
	}

	_templateFields() {
		document.getElementById('operation-attr-templates').style.display='';
		document.getElementById('operation-attr-templates-label').style.display='';

		const $template_ms = $('#operation_optemplate__templateid');

		$template_ms.on('change', () => {
			$template_ms.multiSelect('setDisabledEntries',
				[... this.form.querySelectorAll('[name^="operation[optemplate]["]')].map((input) => input.value)
			);
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
		document.getElementById('operation_opmessage_default_msg').dispatchEvent(new Event('change'));

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
					'operation-message-body'
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

		const $host_ms = $('#operation_opcommand_hst__hostid');

		$host_ms.on('change', () => {
			$host_ms.multiSelect('setDisabledEntries',
				[... this.form.querySelectorAll('[name^="operation[opcommand_hst]["]')].map((input) => input.value)
			);
		});

		const $hostgroup_ms = $('#operation_opcommand_grp__groupid');

		$hostgroup_ms.on('change', () => {
			$hostgroup_ms.multiSelect('setDisabledEntries',
				[... this.form.querySelectorAll('[name^="operation[opcommand_grp]["]')].map((input) => input.value)
			);
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

		let cond_table_rows = document.getElementById('operation-condition-list').getElementsByTagName('tr');
		cond_table_rows[cond_table_rows.length - 1].before(row);
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
				cell.append(<?= json_encode(_('Event is acknowledged')) ?> + ' ');
			}
			else if (input.value == 0) {
				cell.append(<?= json_encode(_('Event is not acknowledged')) ?> + ' ');
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
		document.querySelector('#operation-evaltype').style.display = this.row_count > 1 ? '' : 'none';
		document.querySelector('#operation-evaltype-label').style.display = this.row_count > 1 ? '' : 'none';
		document.querySelector('#operation-condition-row').style.display = this.row_count > 1 ? '' : 'none';

		const labels = document.querySelectorAll('#operation-condition-list .label');
		let conditions = [];
		[...labels].forEach(function (label) {

			conditions.push({
				id: label.getAttribute('data-formulaid'),
				type: label.getAttribute('data-conditiontype')
			});
		});

		document.getElementById('operation-condition-evaltype-formula')
			.innerHTML = getConditionFormula(conditions, + document.querySelector('#operation-evaltype').value);

		document.querySelector('#operation-evaltype').onchange = function() {
			const labels = document.querySelectorAll('#operation-condition-list .label');
			let conditions = [];
			[...labels].forEach(function (label) {

				conditions.push({
					id: label.getAttribute('data-formulaid'),
					type: label.getAttribute('data-conditiontype')
				});
			});

			document.getElementById('operation-condition-evaltype-formula')
				.innerHTML = getConditionFormula(conditions, + document.querySelector('#operation-evaltype').value);
		}
	}

	_customMessageFields() {
		let default_msg = document.querySelector('#operation_opmessage_default_msg')

		let message_fields = [
			'operation-message-subject-label', 'operation-message-subject', 'operation-message-label',
			'operation-message-body'
		]

		default_msg.onchange = function() {
			if(document.querySelector('#operation_opmessage_default_msg').checked) {
				message_fields.forEach((field) => {
					document.getElementById(field).style.display='';
					document.getElementById(field).removeAttribute('disabled');
				});

				document.querySelector('#operation_opmessage_default_msg').value = 0;
			}
			else {
				message_fields.forEach((field) => {
					document.getElementById(field).style.display='none';
				});

				document.querySelector('#operation_opmessage_default_msg').value = 1;
			}
		}
		default_msg.dispatchEvent(new Event('change'));
	}
}
