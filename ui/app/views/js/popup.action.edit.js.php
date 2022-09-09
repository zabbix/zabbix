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
		this.eventsource = eventsource;
		this.row_count = document.getElementById('conditionTable').rows.length-2;

		this._initActionButtons();
		this._processTypeOfCalculation();
	}

	_initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-create')) {
				this._openConditionPopup();
			}
			else if (e.target.classList.contains('condition-remove')) {
				e.target.closest('tr').remove();
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
		// todo: check if condition with the same value already exists in the table.

		// check if identical condition already exists in table
		const hasRows = [...document.getElementById('conditionTable').getElementsByTagName('tr')].map(it => {
			const table_row = it.getElementsByTagName('td')[1];
			if (table_row !== undefined) {
				return (table_row.innerHTML === this._createNameCell(input).getElementsByTagName('td')[0].innerHTML);
			}
		});

		const hasRow = [hasRows.some(it => it === true)]
		if (hasRow[0] === true) return;

		row.append(this._createLabelCell(input));
		row.append(this._createNameCell(input));
		row.append(this._createRemoveCell());

		this.table = document.getElementById('conditionTable');
		this.row_count = this.table.rows.length -1;
	}

	_createLabelCell(input) {
		// todo E.S. : FIX LABEL WHEN DELETE ROW AND ADD A NEW ONE!!
		const cell = document.createElement('td');

		this.label = num2letter(document.getElementById('conditionTable').rows.length -2);
		cell.setAttribute('class', 'label');
		cell.setAttribute('data-formulaid', this.label);
		cell.setAttribute('data-conditiontype', input.conditiontype);
		cell.append(this.label);
		return cell;
	}

	_createNameCell(input) {
		const cell = document.createElement('tr');
		const span = document.createElement('td');
		const value = document.createElement('em');
		const value2 = document.createElement('em');

		cell.appendChild(this._createHiddenInput('formulaid',this.label));
		cell.appendChild(this._createHiddenInput('conditiontype',input.conditiontype));
		cell.appendChild(this._createHiddenInput('operator',input.operator));
		cell.appendChild(this._createHiddenInput('value',input.value));
		if (input.value2 !== '') {
			cell.appendChild(this._createHiddenInput('value2',input.value2));
		}

		if (input.conditiontype == <?= CONDITION_TYPE_EVENT_TAG_VALUE ?>) {
			value2.textContent = input.value2;

			span.append('Value of tag ');
			span.append(value2)
			span.append(' ' + this.condition_operators[input.operator] + ' ');
			value.textContent = input.value;
			span.append(value);
		}
		else if (input.conditiontype == <?= CONDITION_TYPE_SUPPRESSED ?>) {
			if (input.operator == <?= CONDITION_OPERATOR_YES ?>) {
				span.append('Problem is suppressed');
			}
			else {
				span.append('Problem is not suppressed');
			}
		}
		else if (input.conditiontype == <?= CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
			if (input.value) {
				span.append('Event is acknowledged');
			}
			else {
				span.append('Event is not acknowledged');
			}
		}
		else {
			value.textContent = input.name;

			span.append(this.condition_types[input.conditiontype] + ' ' + this.condition_operators[input.operator] + ' ');
			span.append(value);
		}
		cell.append(span);

		return cell;
	}

	_createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `conditions_${this.row_count}_${name}`;
		input.name = `conditions[${this.row_count}][${name}]`;
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

	submit() {
		const fields = getFormFields(this.form);
		fields.name = fields.name.trim();
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

		jQuery('#formula').toggle(this.show_formula).removeAttr("readonly");
		jQuery('#expression').toggle(!this.show_formula);
		jQuery('#label-evaltype').toggle(this.row_count > 1);
		jQuery('#evaltype-formfield').toggle(this.row_count > 1);

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
