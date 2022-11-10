<?php declare(strict_types=0);
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


window.action_edit_popup = new class {
	init({condition_operators, condition_types, conditions, actionid, eventsource}) {
		this.overlay = overlays_stack.getById('action-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.condition_operators = condition_operators;
		this.condition_types = condition_types;
		this.actionid = actionid;
		this.eventsource = eventsource;
		this.row_count = document.getElementById('conditionTable').rows.length - 2;

		this._initActionButtons();
		this._processTypeOfCalculation();

		// Add existing conditions in action edit popup.
		if (typeof(conditions) === 'object') {
			conditions = Object.values(conditions)
		}
		for (const condition of conditions) {
			this._createConditionsRow(condition);
		}

		// Reload operation table when esc_period is changed.
		let esc_period = document.querySelector('#esc_period');
		if (esc_period) {
			esc_period.addEventListener('change', (e) => {
				this._loadOperationTable(e);
			});
		}
	}

	_loadOperationTable(e = null) {
		if (e.type === 'change'){
			this.recovery = <?= ACTION_OPERATION ?>
		}
		else if (e && e.type != 'change') {
			this.recovery = e.detail.operation.recovery;
		}

		if (this.recovery == <?= ACTION_RECOVERY_OPERATION ?>){
			this.$operation_table = $('#rec-operations-table-div');
		}
		else if (this.recovery == <?= ACTION_UPDATE_OPERATION ?>){
			this.$operation_table = $('#upd-operations-table-div');
		}
		else {
			this.$operation_table = $('#operations-table-div');
		}

		let new_operation = {};
		if (e) {
			new_operation = e.detail;
		}

		const fields = getFormFields(this.form);

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'popup.action.operation.get');
		curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);
		if (document.querySelector('#esc_period')) {
			let esc_period = (document.querySelector('#esc_period').value).trim();

			if (esc_period === '') {
				esc_period = 0;
			}

			curl.setArgument('esc_period', esc_period);
		}

		// todo : add loader somewhere
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
				if (typeof response === 'object' && 'error' in response) {
					const message_box = makeMessageBox('bad', response.error.messages, response.error.title);

					this.$operation_table.empty();
					this.$operation_table.append(message_box);
				}
				else {
					this.$operation_table.empty();
					this.$operation_table.append(response.body)
				}
			})
			.catch((exception) => {
				// todo : add actions for situation when exception
			})
			.finally(
				// todo : remove loader
			);
	}

	loaderStart() {
		// todo : add functionality to add loader
		// this.operation_table.setAttribute('class', 'is-loading');
	}

	loaderStop() {
		// todo : add functionality to remove loader
		// this.operation_table.removeAttribute('class');
		// this.$preloader.remove();
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
				this._openEditOperationPopup(e, JSON.parse(e.target.getAttribute('data_operation')));
			}
			else if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-remove-condition')) {
				e.target.closest('tr').remove();
				this._processTypeOfCalculation();
			}
		});
	}

	_openEditOperationPopup(e, operation_data) {
		let row = e.target.closest('tr');

		this.parameters = {
			eventsource: this.eventsource,
			recovery: operation_data.data.recovery,
			actionid: this.actionid,
			data: operation_data.data
		}

		const overlay = PopUp('popup.action.operation.edit', this.parameters, {
			dialogueid: 'operations',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('operation.submit', (e) => {
			row.remove();
			this._loadOperationTable(e);
		});
	}

	_openOperationPopup(eventsource, recovery_phase, actionid) {
		this.recovery = recovery_phase;
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
			this._loadOperationTable(e);
		});
	}

	_openConditionPopup() {
		this._processTypeOfCalculation();

		let parameters;
		let row_index = 0;

		while (document.querySelector(`#conditionTable [data-row_index="${row_index}"]`) !== null) {
			row_index++;
		}

		parameters = {
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
				let element = {...input, name: input.name[index], value: input.value[index]};
				let has_row = this._checkConditionRow(element);

				const result = [has_row.some(it => it === true)]
				if (result[0] === true) {
					return;
				}
				else {
					element.condition_name = this.condition_types[element.conditiontype] + ' ' +
						this.condition_operators[element.operator] + ' '
					element.data = element.name
					element.label = num2letter(element.row_index);
					input.row_index ++;
					template = new Template(document.getElementById('condition-row-tmpl').innerHTML)

					document
						.querySelector('#conditionTable tbody')
						.insertAdjacentHTML('beforeend', template.evaluate(element))
				}
				this._processTypeOfCalculation();
			})
		}
		else {
			let has_row = this._checkConditionRow(input);

			const result = [has_row.some(it => it === true)]
			if (result[0] === true) {
				return;
			}
			else {
				input.label = num2letter(input.row_index);

				switch(parseInt(input.conditiontype)) {
					case <?= CONDITION_TYPE_SUPPRESSED ?>:
						input.condition_name = input.operator == <?= CONDITION_OPERATOR_YES ?>
							? <?= json_encode(_('Problem is suppressed')) ?>
							: <?= json_encode(_('Problem is not suppressed')) ?>

						template = new Template(document.getElementById('condition-suppressed-row-tmpl').innerHTML);
						break;

					case <?= CONDITION_TYPE_EVENT_TAG_VALUE ?>:
						input.operator_name = this.condition_operators[input.operator]

						template = new Template(document.getElementById('condition-tag-value-row-tmpl').innerHTML);
						break;

					default:
						input.condition_name = this.condition_types[input.conditiontype] + ' ' +
							this.condition_operators[input.operator] + ' '
						input.data = input.name

						template = new Template(document.getElementById('condition-row-tmpl').innerHTML);
				}
				document
					.querySelector('#conditionTable tbody')
					.insertAdjacentHTML('beforeend', template.evaluate(input))
			}
			this._processTypeOfCalculation();
		}
	}

	/**
	 * Check if row with the same conditiontype and value already exists.
	 */
	_checkConditionRow(input) {
		let result = [];
		[...document.getElementById('conditionTable').getElementsByTagName('tr')].map(it => {
			const table_row = it.getElementsByTagName('td')[2];

			if (table_row !== undefined) {
				let conditiontype = table_row.getElementsByTagName('input')[0].value;
				let value = table_row.getElementsByTagName('input')[2].value;
				let value2 = table_row.getElementsByTagName('input')[3].value
					? table_row.getElementsByTagName('input')[3].value
					: null;

				if (conditiontype == <?= CONDITION_TYPE_SUPPRESSED ?>) {
					result.push(input.conditiontype === conditiontype);
				}
				else {
					result.push(
						input.conditiontype === conditiontype && input.value === value && input.value2 === value2
					);
				}

				if (input.row_index == it.getAttribute('data-row_index')) {
					input.row_index ++;
				}
			}

			result.push(false);
		});

		return result;
	}

	_getConditionName(input) {
		switch (parseInt(input.conditiontype)) {
			case <?= CONDITION_TYPE_SUPPRESSED ?> :
				if (input.operator == <?= CONDITION_OPERATOR_YES ?>) {
					this.condition_name = <?= json_encode(_('Problem is suppressed')) ?>;
				}
				else {
					this.condition_name = <?= json_encode(_('Problem is not suppressed')) ?>;
				}
				break;

			default:
				this.condition_name = this.condition_types[input.conditiontype] + ' ' +
					this.condition_operators[input.operator] + ' ' + input.name;
				break;
		}

		return this.condition_name
	}

	submit() {
		const fields = getFormFields(this.form);
		fields.name = fields.name.trim();

		const curl = new Curl('zabbix.php', false);
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

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
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
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'action.delete');
		curl.setArgument('eventsource', this.eventsource);


		this._post(curl.getUrl(), {actionids: [this.actionid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	_processTypeOfCalculation() {
		this.show_formula = document.querySelector('#evaltype').value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
		let row_count = document.getElementById('conditionTable').rows.length -2;

		document.querySelector('#formula').style.display = this.show_formula ? '' : 'none';
		document.querySelector('#formula').removeAttribute('readonly');
		document.querySelector('#expression').style.display = this.show_formula ? 'none' : '';
		document.querySelector('#label-evaltype').style.display = row_count > 1 ? '' : 'none';
		document.querySelector('#evaltype-formfield').style.display = row_count > 1 ? '' : 'none';

		const labels = document.querySelectorAll('#conditionTable .label');
		let conditions = [];
		[...labels].forEach(function (label) {

			conditions.push({
				id: label.getAttribute('data-formulaid'),
				type: label.getAttribute('data-conditiontype')
			});
		});

		document.getElementById('expression')
			.innerHTML = getConditionFormula(conditions, + document.querySelector('#evaltype').value);

		document.querySelector('#evaltype').onchange = function() {
			this.show_formula = +document.querySelector('#evaltype').value === <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;

			document.querySelector('#expression').style.display = this.show_formula ? 'none' : '';
			document.querySelector('#formula').style.display = this.show_formula ? '' : 'none';
			document.querySelector('#formula').removeAttribute('readonly');

			const labels = document.querySelectorAll('#conditionTable .label');
			let conditions = [];
			[...labels].forEach(function (label) {

				conditions.push({
					id: label.getAttribute('data-formulaid'),
					type: label.getAttribute('data-conditiontype')
				});
			});

			document.getElementById('expression')
				.innerHTML = getConditionFormula(conditions, + document.querySelector('#evaltype').value);
		};
	}
}
