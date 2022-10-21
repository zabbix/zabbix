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
		this._initTemplates();

		if (data?.opconditions) {
			data?.opconditions.map(row => this._createOperationConditionsRow(row, 0))
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
				this._processTypeOfCalculation();
			}
			else if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
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
		this._enableFormFields(['operation-attr-hostgroups']);

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
		this._enableFormFields(['operation-attr-templates']);

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

	_addUserGroup(values) {
		values.forEach((value) => {
			document
				.querySelector('#operation-message-user-groups-table tbody')
				.insertAdjacentHTML('beforeend', this.usrgrp_template.evaluate(value))
		});
	}

	_addUser(values) {
		values.forEach((value) => {
			if (value.userid) {
				value.id = value.userid;
			}
			document
				.querySelector('#operation-message-user-table tbody')
				.insertAdjacentHTML('beforeend', this.usr_template.evaluate(value))
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
		this._processTypeOfCalculation();

		let parameters;
		let row_index = 0;

		while (document.querySelector(`#operation-condition-list [data-id="${row_index}"]`) !== null) {
			row_index++;
		}

		parameters = {
			type: <?= ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION ?>,
			source: this.eventsource,
			row_index: row_index
		};

		const overlay = PopUp('popup.condition.operations', parameters, {
			dialogueid: 'operation-condition',
			dialogue_class: 'modal-popup-medium',
			trigger_element: trigger_element
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			this._createOperationConditionsRow(e.detail, row_index);
		});
	}

	_createOperationConditionsRow(input, row_index) {
		if (input.conditiontype == <?= CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
			if (input.value == 1) {
				input.name = <?= json_encode(_('Event is acknowledged')) ?> + ' '
			}
			else if (input.value == 0) {
				input.name = <?= json_encode(_('Event is not acknowledged')) ?> + ' '
			}
		}

		input.label = num2letter(row_index);
		input.row_index = row_index;

		document
			.querySelector('#operation-condition-list tbody')
			.insertAdjacentHTML('beforeend', this.op_condition_template.evaluate(input))

		this._processTypeOfCalculation();
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
		let row_count
		document.getElementById('operation-condition-list')
			? row_count = document.getElementById('operation-condition-list').rows.length -2
			: row_count = 0;

		document.querySelector('#operation-evaltype').style.display = row_count > 1 ? '' : 'none';
		document.querySelector('#operation-evaltype-label').style.display = row_count > 1 ? '' : 'none';
		document.querySelector('#operation-condition-row').style.display = row_count > 1 ? '' : 'none';

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

	_initTemplates() {
		this.op_condition_template = new Template(`
			<tr data-id="#{row_index}">
				<td>
					<span  class="label" data-conditiontype="#{conditiontype}" data-formulaid= "#{label}" >#{label}</span>
				</td>
				<td>
					<span>#{name}</span>
				</td>
				<td class="<?= ZBX_STYLE_NOWRAP ?>">
					<input type="hidden" name="operation[opconditions][#{row_index}][conditiontype]" value="#{conditiontype}" />
					<input type="hidden" name="operation[opconditions][#{row_index}][operator]" value="#{operator}" />
					<input type="hidden" name="operation[opconditions][#{row_index}][value]" value="#{value}" />
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>
		`);

		this.usrgrp_template = new Template(`
			<tr data-id="#{usrgrpid}">
				<td>
					<span>#{name}</span>
				</td>
				<td class="<?= ZBX_STYLE_NOWRAP ?>">
					<input name="operation[opmessage_grp][][usrgrpid]" type="hidden" value="#{usrgrpid}" />
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>
		`);

		this.usr_template =  new Template(`
			<tr data-id="#{id}">
				<td>
					<span>#{name}</span>
				</td>
				<td class="<?= ZBX_STYLE_NOWRAP ?>">
					<input name="operation[opmessage_usr][][userid]" type="hidden" value="#{id}" />
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>
		`);
	}
}
