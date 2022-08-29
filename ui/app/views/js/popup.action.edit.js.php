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

window.action_edit_popup = new class {

	init({condition_operators, condition_types, conditions, actionid, eventsource}) {
		this.overlay = overlays_stack.getById('action-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.condition_operators = condition_operators;
		this.condition_types = condition_types;
		this.conditions = conditions;
		this.actionid = actionid;
		this.eventsource = eventsource
		this.row_num = 0;

		this._initActionButtons();
		this._createExistingConditionRow(conditions);
		this._processTypeOfCalculation();
	}

	_initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-create')) {
				this._openConditionPopup();
			}
			else if (e.target.classList.contains('js-operation-details')) {
				this._openOperationPopup('0', '0', this.actionid);
			}
			else if (e.target.classList.contains('js-recovery-operations-create')) {
				this._openOperationPopup();
			}
			else if (e.target.classList.contains('js-update-operations-create')) {
				this._openOperationPopup();
			}
			else if (e.target.classList.contains('element-table-remove')) {
				this.row_count--;
				this._processTypeOfCalculation();
			}
		});
	}

	_openConditionPopup() {
		const parameters = {
			type: <?= ZBX_POPUP_CONDITION_TYPE_ACTION ?>,
			source: this.eventsource,
			actionid: this.actionid
		};

		const overlay =  PopUp('popup.condition.edit', parameters, {
			dialogueid: 'condition',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			console.log(e.detail.name)
				this.row = document.createElement('tr');
				this._createRow(this.row, e.detail);
				$('#conditionTable tr:last').before(this.row);
				this._processTypeOfCalculation();
			});
	}

	_openOperationPopup(eventsource, recovery_phase, actionid) {
		const parameters = {
			// trigger_element: trigger_element,
			eventsource: eventsource,
			recovery_phase: recovery_phase,
			actionid: actionid
		};

		return PopUp('popup.operations', parameters, {
			dialogueid: 'condition',
			dialogue_class: 'modal-popup-medium'
		});

	}

	_createRow(row, input) {
		row.append(this._createLabelCell(input));
		row.append(this._createNameCell(input));
		row.append(this._createRemoveCell());

		this.table = document.getElementById('conditionTable');
		this.row_count = this.table.rows.length -1;
	}

	_createLabelCell(input) {
		const cell = document.createElement('td');

		this.label = num2letter(this.row_num)
		cell.setAttribute('class', 'label')
		cell.setAttribute('data-formulaid', this.label)
		cell.setAttribute('data-conditiontype', input.conditiontype)
		cell.append(this.label);
		this.row_num ++;
		return cell;
	}

	_createNameCell(input) {
		const cell = document.createElement('tr');
		const condition_cell = document.createElement('td');
		const operator_cell = document.createElement('td');
		const value_cell = document.createElement('em');
		const value2_cell = document.createElement('em');

		cell.appendChild(this._createHiddenInput('conditiontype',input.conditiontype));
		cell.appendChild(this._createHiddenInput('operator',input.operator));
		cell.appendChild(this._createHiddenInput('value',input.value));
		if (input.value2 !== '') {
			cell.appendChild(this._createHiddenInput('value2',input.value2));
		}
		cell.appendChild(this._createHiddenInput('formulaid',this.label));

		if (input.conditiontype == <?= CONDITION_TYPE_EVENT_TAG_VALUE ?>) {
			condition_cell.textContent = ('Value of tag ');
			operator_cell.textContent = (this.condition_operators[input.operator] + ' ')
			value_cell.textContent = (input.value);
			value2_cell.textContent = (input.value2);

			cell.append(condition_cell);
			cell.append(value2_cell);
			cell.append(operator_cell);
			cell.append(value_cell);
		}
		else if (input.conditiontype == <?= CONDITION_TYPE_SUPPRESSED ?>) {
			if (input.operator == <?= CONDITION_OPERATOR_YES ?>) {
				cell.append('Problem is suppressed');
			}
			else {
				cell.append('Problem is not suppressed');
			}
		}
		else if (input.conditiontype == <?= CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
			if (input.value) {
				cell.append('Event is acknowledged');
			}
			else {
				cell.append('Event is not acknowledged');
			}
		}
		else {
			condition_cell.textContent = (
				this.condition_types[input.conditiontype] + " " +
				this.condition_operators[input.operator]
			);
			value_cell.textContent = input.name;

			cell.append(condition_cell);
			cell.append(operator_cell);
			cell.append(value_cell);
		}

		return cell;
	}

	_createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `conditions_${this.row_num-1}_${name}`;
		input.name = `conditions[${this.row_num-1}][${name}]`;
		input.value =value;

		return input;
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

	_createExistingConditionRow(conditions) {
		conditions.forEach(condition => {
				const row = document.createElement('tr');
				const cell = document.createElement('td');

				this.label = condition.formulaid;
				cell.append(this.label)
				cell.setAttribute('class', 'label')
				cell.setAttribute('data-formulaid', this.label)
				cell.setAttribute('data-conditiontype', condition.conditiontype)
				row.append(cell)
				this.row_num ++;
				row.append(this._createNameCell(condition));
				row.append(this._createRemoveCell());

				$('#conditionTable tr:last').before(row);
			}
		)
		const table = document.getElementById('conditionTable');
		this.row_count = table.rows.length -2;
	}

	submit() {
		const fields = getFormFields(this.form);
		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', this.actionid !== '' ? 'action.update' : 'action.create');

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
		this.actionid = '';
		const actionid = document.getElementById('actionid');
		actionid.parentNode.removeChild(actionid);
		const title = ('New action');

		const buttons = [
			{
				title:  t('Add'),
				class: '',
				keepOpen: true,
				isSubmit: true,
				action: () => this.submit()
			},
			{
				title: t('Cancel'),
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

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData({g_actionid: [this.actionid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {
					detail: {
						success: response.success
					}
				}));
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	_processTypeOfCalculation() {
		this.show_formula = (jQuery('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

		jQuery('#label-evaltype').toggle(this.row_count > 1);
		jQuery('#evaltype-formfield').toggle(this.row_count > 1);
		jQuery('#formula').toggle(this.show_formula).removeAttr("readonly");

		const labels = jQuery('#conditionTable .label');

		var conditions = [];
		labels.each(function(index, label) {
			var label = jQuery(label);

			conditions.push({
				id: label.data('formulaid'),
				type: label.data('conditiontype')
			});
		});

		jQuery('#expression').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));

		jQuery('#evaltype').change(function() {

			this.show_formula = (jQuery(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);
			jQuery('#formula').toggle(this.show_formula).removeAttr("readonly");
			jQuery('#expression').toggle(!this.show_formula);

			const labels = jQuery('#conditionTable .label');
			var conditions = [];

			labels.each(function(index, label) {
				var label = jQuery(label);

				conditions.push({
					id: label.data('formulaid'),
					type: label.data('conditiontype')
				});
			});

			jQuery('#expression').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));
		});
	}
}
