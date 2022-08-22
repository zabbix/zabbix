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
		this.createExistingConditionRow(conditions);
		this.processTypeOfCalculation();

		this.dialogue.addEventListener('condition.dialogue.submit', (e) => {
		// todo: add multiselect title, not value
			this.row = document.createElement('tr');
			this.createRow(this.row, e.detail.inputs);
			this.processTypeOfCalculation();
			$('#conditionTable tr:last').before(this.row);
		});
	}

	_initActionButtons() {
		document.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-condition-create')) {
				this.openConditionPopup();
			}
			else if (e.target.classList.contains('js-operation-details')) {
				this.openOperationPopup('0', '0', this.actionid);
			}
			else if (e.target.classList.contains('js-recovery-operations-create')) {
				this.openOperationPopup();
			}
			else if (e.target.classList.contains('js-update-operations-create')) {
				this.openOperationPopup();
			}
			else if (e.target.classList.contains('js-action-clone')) {
				this._clone();
			}
			else if (e.target.classList.contains('js-action-delete')) {
				this._delete();
			}
			else if (e.target.classList.contains('element-table-remove')) {
				this.row_count--;
				this.processTypeOfCalculation();
			}
		});
	}

	openConditionPopup() {
		const parameters = {
			type: <?= ZBX_POPUP_CONDITION_TYPE_ACTION ?>,
			source: this.eventsource
		};

		return PopUp('popup.condition.actions', parameters, {
			dialogueid: 'condition',
			dialogue_class: 'modal-popup-medium'
		});
	}

	openOperationPopup(eventsource, recovery_phase, actionid) {
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

	createRow(row, input) {
		row.append(this.createLabelCell());
		row.append(this.createNameCell(input));
		row.append(this.createRemoveCell());

		const table = document.getElementById('conditionTable');
		this.row_count = table.rows.length -1;
	}

	createLabelCell() {
		const cell = document.createElement('td');

		this.label = num2letter(this.row_num)
		cell.append(this.label);
		this.row_num ++;
		return cell;
	}

	createNameCell(input) {
		const cell = document.createElement('tr');
		const condition_cell = document.createElement('td');
		const value_cell = document.createElement('em');

		cell.appendChild(this.createHiddenInput('conditiontype',input.conditiontype));
		cell.appendChild(this.createHiddenInput('operator',input.operator));
		cell.appendChild(this.createHiddenInput('value',input.value));
		if (input.value2 !== '') {
			cell.appendChild(this.createHiddenInput('value2',input.value2));
		}
		cell.appendChild(this.createHiddenInput('formulaid',this.label));

		condition_cell.textContent = (
			this.condition_types[input.conditiontype] + " " +
			this.condition_operators[input.operator] + " "
		);
		value_cell.textContent = input.value;

		cell.append(condition_cell);
		cell.append(value_cell);

		return cell;
	}

	createHiddenInput(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `conditions_${this.row_num-1}_${name}`;
		input.name = `conditions[${this.row_num-1}][${name}]`;
		input.value =value;

		return input;
	}

	createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.textContent = <?= json_encode(_('Remove')) ?>;
		btn.addEventListener('click', () => btn.closest('tr').remove());

		cell.appendChild(btn);
		// this.processTypeOfCalculation();
		return cell;
	}

	createExistingConditionRow(conditions) {
		conditions.forEach(condition => {
				const row = document.createElement('tr');
				const cell = document.createElement('td');

				this.label = condition.formulaid;
				cell.append(this.label)
				row.append(cell)
				this.row_num ++;
				row.append(this.createNameCell(condition));
				row.append(this.createRemoveCell());

				$('#conditionTable tr:last').before(row);
			}
		)
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

	_clone() {
		// this.overlay.setLoading();
		// const parameters = getFormFields(this.form);

		// PopUp('popup.action.edit', {name: getFormFields(this.form).name}, {
		//	dialogueid: 'action-edit',
		//	dialogue_class: 'modal-popup-large'
		// });
	}

	_delete() {
		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'action.delete');
		curl.addSID();

		// fetch(curl.getUrl(), {
		//	method: 'POST',
		//	headers: {'Content-Type': 'application/json'},
		//	body: JSON.stringify({g_actionid: [this.actionid], eventsource: this.eventsource})
		// })
		//	.then((response) => response.json())
		//	.then((response) => {
		//		if ('error' in response) {
		//			throw {error: response.error};
		//		}
		//		overlayDialogueDestroy(this.overlay.dialogueid);

		//		this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		//	})
		//	.catch((exception) => {
		//		for (const element of this.form.parentNode.children) {
		//			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
		//				element.parentNode.removeChild(element);
		//			}
		//		}

		//		let title, messages;

		//		if (typeof exception === 'object' && 'error' in exception) {
		//			title = exception.error.title;
		//			messages = exception.error.messages;
		//		}
		//		else {
		//			messages = [<?//= json_encode(_('Unexpected server error.')) ?>//];
		//		}

		//		const message_box = makeMessageBox('bad', messages, title)[0];

		//		this.form.parentNode.insertBefore(message_box, this.form);
		//	})
		//	.finally(() => {
		//		this.overlay.unsetLoading();
		//	});
	}

	processTypeOfCalculation() {
		var show_formula = (this.row_count == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>),
			$labels = jQuery('#conditionTable .label');

		jQuery('#evaltype-formfield').toggle(this.row_count > 1);
		jQuery('#evaltype').toggle(this.row_count > 1);
		jQuery('#label-evaltype').toggle(this.row_count > 1);
		jQuery('#formula').toggle(this.row_count > 1);


		// if (this.row_count > 1) {
		//	var conditions = [];

		//	$labels.each(function(index, label) {
		//		$label = jQuery(label);

		//		conditions.push({
		//			id: $label.data('formulaid'),
		//			type: $label.data('conditiontype')
		//		});
		//	});

		//	jQuery('#conditionLabel').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));
		//}
	}
}
