<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/
?>


window.operation_popup = new class {

	init({eventsource, recovery_phase, data, scripts_with_warning, actionid}) {
		this.recovery_phase = recovery_phase;
		this.eventsource = eventsource;
		this.overlay = overlays_stack.getById('operations');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.actionid = actionid;
		this.row_index = data.row_index;
		this.data = data;
		this.scripts_with_warning = scripts_with_warning;

		if (document.getElementById('operation-condition-list')) {
			this.condition_count = (document.getElementById('operation-condition-list').rows.length - 2);
		}

		this._loadViews();
		this._processTypeOfCalculation();

		if (this.data.opconditions.length > 0) {
			this.data.opconditions.map((row, index) => {
				this._createOperationConditionsRow(row, index);
			})
		}
	}

	_loadViews() {
		this._customMessageFields();
		this.#loadHostTags(this.data.optag);
		this._removeAllFields();
		const operation_type = document.getElementById('operation-type-select').value;
		this.#toggleScriptWarningIcon(operation_type);
		this._changeView(operation_type);

		document.getElementById('operation-type-select').addEventListener('change', (e) => {
			this.#toggleScriptWarningIcon(e.target.value);
			this._removeAllFields();
			this._changeView(e.target.value);
			this._processTypeOfCalculation();
		});

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('operation-condition-list-footer')) {
				this._openConditionsPopup(e.target);
			}
			else if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
				this._processTypeOfCalculation();
			}
			else if (e.target.classList.contains('element-table-add')) {
				const tags_table = this.form.querySelector('#tags-table');
				const form_rows = tags_table.querySelectorAll('.form_row')
				let row_index = 0;

				if (form_rows.length !== 0) {
					const last_row = form_rows[form_rows.length - 1];

					row_index = parseInt(last_row.getAttribute('data-id')) + 1;
				}

				this.#addHostTags([{tag: '', value:'', row_index: row_index}]);
			}
			else if (e.target.classList.contains('element-table-remove')) {
				e.target.closest('tr').remove();
			}
		});
	}

	/**
	 * Show/hides warning icon for script operation type.
	 *
	 * @param {string} operation_type  Type of operation selected.
	 */
	#toggleScriptWarningIcon(operation_type) {
		if (this.scripts_with_warning.length > 0) {
			const has_warning = this.scripts_with_warning.includes(operation_type);

			this.form.querySelector('.js-script-warning-icon').style.display = has_warning ? '' : 'none';
		}
	}

	/**
	 * Adds empty row if no host tags are initially present and adds a row index for each host tag.
	 *
	 * @param {array} optags  Operation host tags.
	 */
	#loadHostTags(optags) {
		if (optags.length === 0) {
			optags.push({tag: '', value:'', row_index: 0});
		}
		else {
			optags.map((optag, index) => {
				optag.row_index = index;
			});
		}

		this.#addHostTags(optags);
	}

	/**
	 * Adds rows for "Add host tags" and "Remove host tags" options based on row template.
	 *
	 * @param {array} optags  Operation host tags.
	 */
	#addHostTags(optags) {
		const tags_table = this.form.querySelector('#tags-table');
		const template = new Template(this.form.querySelector('#operation-host-tags-row-tmpl').innerHTML);

		optags.forEach((optag) => {
			tags_table.rows[tags_table.rows.length - 1].insertAdjacentHTML('beforebegin', template.evaluate(optag));

			$(`#operation_optag_${optag.row_index}_tag, #operation_optag_${optag.row_index}_value`).textareaFlexible();
		});
	}

	_changeView(operation_type) {
		let type = parseInt(operation_type.replace(/\D/g, ''));

		if ((/\b(scriptid)\b/g).test(operation_type)) {
			type = <?= OPERATION_TYPE_COMMAND ?>;
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

			case <?= OPERATION_TYPE_HOST_TAGS_ADD ?>:
			case <?= OPERATION_TYPE_HOST_TAGS_REMOVE ?>:
				this.#hostTagsFields();
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
			'operation-message-custom', 'operation-opmessage-subject', 'operation_opmessage_message',
			'operation-message-subject-label', 'operation_opmessage_default_msg'
		];

		this._enableFormFields(fields);
		this._customMessageFields();
	}

	_allInvolvedFieldsUpdate() {
		const fields = [
			'operation-message-custom-label', 'operation-message-custom', 'operation-opmessage-subject',
			'operation_opmessage_message', 'operation_opmessage_default_msg', 'operation-message-mediatype-default'
		];

		this._enableFormFields(fields);
		this._customMessageFields();
	}

	_hostGroupFields() {
		document.getElementById('operation-attr-hostgroups').style.display='';
		document.getElementById('operation-attr-hostgroups-label').style.display='';

		this._enableFormFields(['operation-attr-hostgroups']);
		this._updateHostGroupMs();
		this.hostgroup_ms.on('change', () => {
			this._updateHostGroupMs();
		});
	}

	_updateHostGroupMs() {
		this.hostgroup_ms = $('#operation_opgroup__groupid');

		this.hostgroup_ms.multiSelect('setDisabledEntries',
			[... this.form.querySelectorAll('[name^="operation[opgroup]["]')].map((input) => input.value)
		);
	}

	/**
	 * Shows or hides the host tags fields.
	 */
	#hostTagsFields() {
		this.form.querySelector('#operation-host-tags').style.display = '';
		this._enableFormFields(['operation-host-tags']);
	}

	_templateFields() {
		document.getElementById('operation-attr-templates').style.display='';
		document.getElementById('operation-attr-templates-label').style.display='';

		this._enableFormFields(['operation-attr-templates']);
		this._updateTemplateMs();

		this.template_ms.on('change', () => {
			this._updateTemplateMs();
		});
	}

	_updateTemplateMs() {
		this.template_ms = $('#operation_optemplate__templateid');

		this.template_ms.multiSelect('setDisabledEntries',
			[... this.form.querySelectorAll('[name^="operation[optemplate]["]')].map((input) => input.value)
		);
	}

	_removeAllFields() {
		for (const field of this.form.getElementsByClassName('form-field')) {
			if (field.id === 'operation-type') {
				continue;
			}
			field.style.display = 'none';
			field.getElementsByTagName('input')
		}

		for (const label of this.form.getElementsByTagName('label')) {
			if (label.id === 'operation-type-label') {
				continue;
			}
			label.style.display = 'none';
		}

		for (const input of this.form.querySelectorAll('input, textarea')) {
			if (['operation_eventsource', 'operation_recovery', 'submit'].includes(input.id)) {
				continue;
			}
			if (input.name === 'operation[operationtype]') {
				continue;
			}
			input.setAttribute('disabled', true);
			input.style.display = 'none';
		}
	}

	_sendMessageFields() {
		document.getElementById('operation_opmessage_default_msg').dispatchEvent(new Event('change'));

		switch (this.eventsource) {
			case <?= EVENT_SOURCE_TRIGGERS ?>:
				this.fields = [
					'operation-message-user-groups', 'operation-condition-table', 'operation-evaltype-label',
					'operation-condition-list-label', 'operation-condition-list', 'step-from', 'operation-step-range',
					'operation-step-duration', 'operation-message-notice', 'operation-message-notice', 'operation-type',
					'operation-message-users', 'operation-evaltype', 'operation-message-mediatype-only',
					'operation-message-custom', 'operation_esc_period', 'operation-message-custom-label',
					'operation_opmessage_default_msg', 'operation-condition-row', 'operation_opmessage_usr__userid_ms',
					'operation-condition-evaltype-formula', 'user-groups-label', 'operation_opmessage_grp__usrgrpid_ms'
				];

				this._customMessageFields();
				break;

			case <?= EVENT_SOURCE_INTERNAL ?>:
			case <?= EVENT_SOURCE_SERVICE?>:
				this.fields = [
					'step-from', 'operation-step-range', 'operation-step-duration', 'operation-message-notice',
					'operation-message-user-groups', 'operation-message-notice', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-type',
					'operation_opmessage_grp__usrgrpid_ms', 'operation_opmessage_usr__userid_ms'
				];

				this._customMessageFields();
				break;

			case <?= EVENT_SOURCE_DISCOVERY ?>:
			case <?= EVENT_SOURCE_AUTOREGISTRATION ?>:
				this.fields = [
					'operation-message-notice', 'operation-message-user-groups', 'operation-message-users',
					'operation-message-mediatype-only', 'operation-message-custom', 'operation_esc_period',
					'operation-message-custom-label', 'operation_opmessage_default_msg', 'operation-message-notice',
					'operation-type', 'operation_opmessage_grp__usrgrpid_ms', 'operation_opmessage_usr__userid_ms'
				];

				this._customMessageFields();
				break;
		}

		this._enableFormFields(this.fields);
		this._updateUserGroupMs();
		this._updateUserMs()

		this.usergroup_ms.on('change', () => {
			this._updateUserGroupMs();
		});

		this.user_ms.on('change', () => {
			this._updateUserMs();
		});
	}

	_updateUserGroupMs() {
		this.usergroup_ms = $('#operation_opmessage_grp__usrgrpid');

		this.usergroup_ms.multiSelect('setDisabledEntries',
			[... this.form.querySelectorAll('[name^="operation[opmessage_grp]["]')].map((input) => input.value)
		);
	}

	_updateUserMs() {
		this.user_ms = $('#operation_opmessage_usr__userid');

		this.user_ms.multiSelect('setDisabledEntries',
			[... this.form.querySelectorAll('[name^="operation[opmessage_usr]["]')].map((input) => input.value)
		);
	}

	_enableFormFields(fields = []) {
		for (const field of this.form.getElementsByClassName('form-field')) {
			if (fields.includes(field.id)) {
				field.style.display = '';

				for (const input of field.querySelectorAll('input, textarea')) {
					input.removeAttribute('disabled');
					input.style.display = '';
				}
				for (const label of field.querySelectorAll('label')) {
					label.style.display = '';
				}
			}
		}

		for (const label of this.form.getElementsByTagName('label')) {
			if (fields.includes(label.id.replace('-label', ''))) {
				label.style.display = '';
			}
			if (fields.includes(label.htmlFor)) {
				label.style.display = '';
			}
		}
	}

	_hostInventoryFields() {
		const fields = ['operation-attr-inventory'];
		this._enableFormFields(fields);
	}

	_addScriptFields() {
		let fields;
		switch (this.eventsource) {
			case <?= EVENT_SOURCE_TRIGGERS ?>:
				fields = [
					'step-from', 'operation-step-range', 'operation-step-duration', 'operation-command-targets-label',
					'operation-command-checkbox', 'operation-command-chst-label', 'operation_opcommand_host_ms',
					'operation-opcommand-hst-label', 'operation-opcommand-grp', 'operation-command-targets',
					'operation-condition-table', 'operation-condition-list-label', 'operation-condition-list',
					'operation_opcommand_hst__hostidch', 'operation_opcommand_hst__hostid_current_host',
					'operation_opcommand_hostgroup_ms'
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
					'operation-command-targets-label', 'operation-command-checkbox', 'operation-command-chst',
					'operation-command-chst-label', 'operation-opcommand-hst-label', 'operation-opcommand-grp',
					'operation-command-targets', 'operation-condition-table', 'operation-condition-list-label',
					'operation-condition-list', 'operation_opcommand_hst__hostidch', 'operation_opcommand_host_ms',
					'operation_opcommand_hostgroup_ms'
				];
		}

		this._enableFormFields(fields);

		this._updateOperationHostMs();
		this._updateOperationHostGroupMs();

		this.host_ms.on('change', () => {
			this._updateOperationHostMs();
		});

		this.hostgroup_ms.on('change', () => {
			this._updateOperationHostGroupMs();
		});
	}

	_updateOperationHostMs() {
		this.host_ms = $('#operation_opcommand_hst__hostid');

		this.host_ms.multiSelect('setDisabledEntries',
			[... this.form.querySelectorAll('[name^="operation[opcommand_hst]["]')].map((input) => input.value)
		);
	}

	_updateOperationHostGroupMs() {
		this.hostgroup_ms = $('#operation_opcommand_grp__groupid');

		this.hostgroup_ms.multiSelect('setDisabledEntries',
			[... this.form.querySelectorAll('[name^="operation[opcommand_grp]["]')].map((input) => input.value)
		);
	}

	_createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = value;

		return input;
	}

	_openConditionsPopup(trigger_element) {
		this._processTypeOfCalculation();
		let row_index = 0;

		while (document.querySelector(`#operation-condition-list [data-id="${row_index}"]`) !== null) {
			row_index++;
		}

		const parameters = {
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

	/**
	 * Check if row with the same conditiontype and value already exists.
	 */
	_checkConditionRow(input) {
		const result = [];
		[...document.getElementById('operation-condition-list').getElementsByTagName('tr')].map(element => {
			const table_row = element.getElementsByTagName('td')[2];

			if (table_row !== undefined) {
				const conditiontype = table_row.getElementsByTagName('input')[0].value;
				const value = table_row.getElementsByTagName('input')[2].value;

				result.push(input.conditiontype === conditiontype && input.value === value);

				if (input.row_index == element.dataset.id) {
					input.row_index++;
				}
			}

			result.push(false);
		});

		return result;
	}

	_createOperationConditionsRow(input, row_index) {
		const has_row = this._checkConditionRow(input);

		const result = [has_row.some(element => element === true)]
		if (result[0] === true) {
			return;
		}
		else {
			if (input.conditiontype == <?= ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
				if (input.value == 1) {
					input.name = <?= json_encode(_('Event is acknowledged')) ?> + ' ';
				}
				else if (input.value == 0) {
					input.name = <?= json_encode(_('Event is not acknowledged')) ?> + ' ';
				}
			}

			input.label = num2letter(row_index);
			input.row_index = row_index;
			const template = new Template(document.getElementById('operation-condition-row-tmpl').innerHTML);

			document
				.querySelector('#operation-condition-list tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(input));
		}

		this._processTypeOfCalculation();
	}

	submit() {
		this.form.append(this._createHiddenInput('row_index', this.row_index));
		this.form.append(this._createHiddenInput('actionid', this.actionid));

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'action.operation.check');

		const fields = getFormFields(this.form);

		if (fields.operation.esc_period != null) {
			fields.operation.esc_period = fields.operation.esc_period.trim();
		}

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
		let row_count;
		document.getElementById('operation-condition-list')
			? row_count = document.getElementById('operation-condition-list').rows.length - 2
			: row_count = 0;

		document.querySelector('#operation-evaltype').style.display = row_count > 1 ? '' : 'none';
		document.querySelector('#operation-evaltype-label').style.display = row_count > 1 ? '' : 'none';
		document.querySelector('#operation-condition-row').style.display = row_count > 1 ? '' : 'none';

		const labels = document.querySelectorAll('#operation-condition-list .label');
		const conditions = [];

		[...labels].forEach(function (label) {
			conditions.push({
				id: label.dataset.formulaid,
				type: label.dataset.conditiontype
			});
		});

		document.getElementById('operation-condition-evaltype-formula')
			.innerHTML = getConditionFormula(conditions, + document.querySelector('#operation-evaltype').value);

		document.querySelector('#operation-evaltype').onchange = function() {
			const labels = document.querySelectorAll('#operation-condition-list .label');
			const conditions = [];

			[...labels].forEach(function (label) {
				conditions.push({
					id: label.dataset.formulaid,
					type: label.dataset.conditiontype
				});
			});

			document.getElementById('operation-condition-evaltype-formula')
				.innerHTML = getConditionFormula(conditions, + document.querySelector('#operation-evaltype').value);
		}
	}

	_customMessageFields() {
		const default_msg = document.querySelector('#operation_opmessage_default_msg');
		const message_fields = [
			'operation-message-subject-label', 'operation-opmessage-subject', 'operation-message-label',
			'operation_opmessage_message', 'operation-message-subject', 'operation-message'
		];

		default_msg.onchange = function() {
			if (document.querySelector('#operation_opmessage_default_msg').checked) {
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
