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


window.action_edit_popup = new class {

	init({condition_operators, condition_types, conditions, actionid, eventsource}) {
		this.overlay = overlays_stack.getById('action.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.condition_operators = condition_operators;
		this.condition_types = condition_types;
		this.actionid = actionid;
		this.eventsource = eventsource;

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'action.list');
		backurl.setArgument('eventsource', this.eventsource);
		this.overlay.backurl = backurl.getUrl();

		this._initActionButtons();
		this.#processTypeOfCalculation();

		// Add existing conditions in action edit popup.
		if (typeof(conditions) === 'object') {
			conditions = Object.values(conditions);
		}
		for (const condition of conditions) {
			this._createConditionsRow(condition);
		}

		// Reload operation table when esc_period is changed.
		const esc_period = document.querySelector('#esc_period');
		if (esc_period) {
			esc_period.addEventListener('change', () => {
				this.recovery = <?= ACTION_OPERATION ?>;
				this._loadOperationTable();
			});
		}

		this.form.style.display = '';
		this.overlay.recoverFocus();
	}

	_loadOperationTable(new_operation = {}) {
		if (this.recovery == <?= ACTION_RECOVERY_OPERATION ?>) {
			this.$operation_table = $('#recovery-operations-container');
		}
		else if (this.recovery == <?= ACTION_UPDATE_OPERATION ?>) {
			this.$operation_table = $('#update-operations-container');
		}
		else {
			this.$operation_table = $('#operations-container');
		}

		const fields = getFormFields(this.form);

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'popup.action.operations.list');
		curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);

		if (document.querySelector('#esc_period')) {
			let esc_period = (document.querySelector('#esc_period').value).trim();

			if (esc_period === '') {
				esc_period = 0;
			}

			curl.setArgument('esc_period', esc_period);
		}

		this.loaderStart();

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				'operations': fields.operations,
				'recovery_operations': fields.recovery_operations,
				'update_operations': fields.update_operations,
				'new_operation': new_operation,
				'eventsource': this.eventsource,
				'actionid': this.actionid
			})
		})
			.then((response) => response.json())
			.then((response) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				if (typeof response === 'object' && 'error' in response) {
					const message_box = makeMessageBox('bad', response.error.messages, response.error.title)[0];
					this.form.parentNode.insertBefore(message_box, this.form);
				}
				else {
					this.$operation_table.empty();
					if (response.messages.length > 0) {
						const message_box = makeMessageBox('bad', response.messages)[0];
						this.form.parentNode.insertBefore(message_box, this.form);
					}

					this.$operation_table.append(response.body);
				}
			})
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
			.finally(
				this.$preloader.remove()
			);
	}

	loaderStart() {
		this.$preloader = $('<span>', {class: 'is-loading'});
		this.$operation_table.append(this.$preloader);
	}

	_initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-create')) {
				this._openConditionPopup();
			}
			else if (e.target.classList.contains('js-operation-details')) {
				this._openOperationPopup(this.eventsource, <?= ACTION_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('js-recovery-operations-create')) {
				this._openOperationPopup(this.eventsource, <?= ACTION_RECOVERY_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('js-update-operations-create')) {
				this._openOperationPopup(this.eventsource, <?= ACTION_UPDATE_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('js-edit-operation')) {
				this._openEditOperationPopup(e, JSON.parse(e.target.dataset.operation));
			}
			else if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-remove-condition')) {
				e.target.closest('tr').remove();
				this.#processTypeOfCalculation();
			}
		});
	}

	_openEditOperationPopup(e, operation_data) {
		let row_index = 0;
		if (operation_data !== null) {
			row_index = parseInt(operation_data.operationid);
		}

		this.parameters = {
			eventsource: this.eventsource,
			recovery: operation_data.data.recovery,
			actionid: this.actionid,
			data: operation_data.data,
			row_index: row_index
		};

		const overlay = PopUp('popup.action.operation.edit', this.parameters, {
			dialogueid: 'operations',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('operation.submit', (e) => {
			this.recovery = e.detail.operation.recovery;
			this._loadOperationTable(e.detail);
		});
	}

	_openOperationPopup(eventsource, recovery_phase, actionid) {
		this.recovery = parseInt(recovery_phase);
		const parameters = {
			eventsource: eventsource,
			recovery: recovery_phase,
			actionid: actionid
		};

		const overlay = PopUp('popup.action.operation.edit', parameters, {
			dialogueid: 'operations',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('operation.submit', (e) => {
			this.recovery = e.detail.operation.recovery;
			this._loadOperationTable(e.detail);
		});
	}

	_openConditionPopup() {
		this.#processTypeOfCalculation();
		let row_index = 0;

		while (document.querySelector(`#conditionTable [data-row_index="${row_index}"]`) !== null) {
			row_index++;
		}

		const parameters = {
			type: <?= ZBX_POPUP_CONDITION_TYPE_ACTION ?>,
			source: this.eventsource,
			actionid: this.actionid,
			row_index: row_index
		};

		const overlay = PopUp('popup.condition.edit', parameters, {
			dialogueid: 'action-condition',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			this._createConditionsRow(e.detail);
		});
	}

	_createConditionsRow(input) {
		let template;

		if (is_array(input.value)) {
			input.value.forEach((value, index) => {
				const element = {...input, name: input.name[index], value: input.value[index]};
				const has_row = this._checkConditionRow(element);

				const result = [has_row.some(element => element === true)]
				if (result[0] === true) {
					return;
				}
				else {
					element.condition_name = this.condition_types[element.conditiontype] + ' ' +
						this.condition_operators[element.operator] + ' ';
					element.data = element.name;
					element.label = num2letter(element.row_index);
					input.row_index++;
					template = new Template(document.getElementById('condition-row-tmpl').innerHTML)

					document
						.querySelector('#conditionTable tbody')
						.insertAdjacentHTML('beforeend', template.evaluate(element));
				}
				this.#processTypeOfCalculation();
			})
		}
		else {
			const has_row = this._checkConditionRow(input);
			const result = [has_row.some(element => element === true)];

			if (result[0] === true) {
				return;
			}
			else {
				input.label = num2letter(input.row_index);

				switch(parseInt(input.conditiontype)) {
					case <?= ZBX_CONDITION_TYPE_SUPPRESSED ?>:
						input.condition_name = input.operator == <?= CONDITION_OPERATOR_YES ?>
							? <?= json_encode(_('Problem is suppressed')) ?>
							: <?= json_encode(_('Problem is not suppressed')) ?>;

						template = new Template(document.getElementById('condition-suppressed-row-tmpl').innerHTML);
						break;

					case <?= ZBX_CONDITION_TYPE_EVENT_TAG_VALUE ?>:
						input.operator_name = this.condition_operators[input.operator];

						template = new Template(document.getElementById('condition-tag-value-row-tmpl').innerHTML);
						break;

					default:
						input.condition_name = this.condition_types[input.conditiontype] + ' ' +
							this.condition_operators[input.operator] + ' ';
						input.data = input.name;

						template = new Template(document.getElementById('condition-row-tmpl').innerHTML);
				}
				document
					.querySelector('#conditionTable tbody')
					.insertAdjacentHTML('beforeend', template.evaluate(input));
			}
			this.#processTypeOfCalculation();
		}
	}

	/**
	 * Check if row with the same conditiontype and value already exists.
	 */
	_checkConditionRow(input) {
		const result = [];
		[...document.getElementById('conditionTable').getElementsByTagName('tr')].map(element => {
			const table_row = element.getElementsByTagName('td')[2];

			if (table_row !== undefined) {
				const conditiontype = table_row.getElementsByTagName('input')[0].value;
				const value = table_row.getElementsByTagName('input')[2].value;
				const value2 = table_row.getElementsByTagName('input')[3].value
					? table_row.getElementsByTagName('input')[3].value
					: null;

				if (conditiontype == <?= ZBX_CONDITION_TYPE_SUPPRESSED ?>) {
					result.push(input.conditiontype === conditiontype);
				}
				else {
					if (input.value2 !== '') {
						result.push(
							input.conditiontype === conditiontype && input.value === value && input.value2 === value2
						)
					}
					else {
						result.push(input.conditiontype === conditiontype && input.value === value)
					}
				}

				if (input.row_index == element.dataset.row_index) {
					input.row_index++;
				}
			}

			result.push(false);
		});

		return result;
	}

	submit() {
		const fields = getFormFields(this.form);
		fields.name = fields.name.trim();

		if (fields.esc_period != null ) {
			fields.esc_period = fields.esc_period.trim();
		}

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.actionid !== 0 ? 'action.update' : 'action.create');

		this._post(curl.getUrl(), fields);
	}

	_post(url, data) {
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
				uncheckTableRows('action_' + this.eventsource, response.keepids ?? []);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
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

	clone() {
		this.actionid = 0;
		const title = <?= json_encode(_('New action')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: '',
				keepOpen: true,
				isSubmit: true,
				action: () => this.submit()
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: 'btn-alt',
				cancel: true,
				action: () => ''
			}
		];

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'action.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('action')) ?>);

		this._post(curl.getUrl(), {actionids: [this.actionid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	#processTypeOfCalculation() {
		this.show_formula = document.querySelector('#evaltype').value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
		const row_count = document.getElementById('conditionTable').rows.length - 2;

		document.querySelector('#formula').style.display = this.show_formula ? '' : 'none';
		document.querySelector('#formula').removeAttribute('readonly');
		document.querySelector('#expression').style.display = this.show_formula ? 'none' : '';
		document.querySelector('#label-evaltype').style.display = row_count > 1 ? '' : 'none';
		document.querySelector('#evaltype-formfield').style.display = row_count > 1 ? '' : 'none';

		const labels = document.querySelectorAll('#conditionTable .label');
		const conditions = [];

		[...labels].forEach(function (label) {
			conditions.push({
				id: label.dataset.formulaid,
				type: label.dataset.conditiontype
			});
		});

		document.getElementById('expression')
			.innerHTML = getConditionFormula(conditions, + document.querySelector('#evaltype').value);

		document.querySelector('#evaltype').onchange = function() {
			this.show_formula = document.querySelector('#evaltype').value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;

			document.querySelector('#expression').style.display = this.show_formula ? 'none' : '';
			document.querySelector('#formula').style.display = this.show_formula ? '' : 'none';
			document.querySelector('#formula').removeAttribute('readonly');

			const labels = document.querySelectorAll('#conditionTable .label');
			const conditions = [];

			[...labels].forEach(function (label) {
				conditions.push({
					id: label.dataset.formulaid,
					type: label.dataset.conditiontype
				});
			});

			document.getElementById('expression')
				.innerHTML = getConditionFormula(conditions, + document.querySelector('#evaltype').value);
		};
	}
}
