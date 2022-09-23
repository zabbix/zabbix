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
		this.row_count = document.getElementById('conditionTable').rows.length - 2;

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
				this._openOperationPopup(this.eventsource, <?= ACTION_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('js-recovery-operations-create')) {
				this._openOperationPopup(this.eventsource, <?= ACTION_RECOVERY_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('js-update-operations-create')) {
				this._openOperationPopup(this.eventsource, <?= ACTION_UPDATE_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('element-table-remove')) {
				this.row_count--;
				this._processTypeOfCalculation();
			}
			else if (e.target.classList.contains('js-edit-button')) {
				// todo E.S. : pass data to edit form
				this._openOperationPopup(this.eventsource, <?= ACTION_OPERATION ?>, this.actionid);
			}
			else if (e.target.classList.contains('js-remove-button')) {
				e.target.closest('tr').remove();
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
			dialogueid: 'action-condition',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			this._checkRow(e.detail);
		});
	}

	_openOperationPopup(eventsource, recovery_phase, actionid) {
		const parameters = {
			eventsource: eventsource,
			recovery: recovery_phase,
			actionid: actionid
		};

		const overlay = PopUp('popup.action.operations', parameters, {
			dialogueid: 'operations',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('operation.submit', (e) => {
			// todo : add function to create row in operation table
			this._createOperationsRow(e);
		});
	}

	_createOperationsRow(input) {
		const operation_data = input.detail.operation;
		console.log(operation_data);
		this.operation_table = document.getElementById('op-table');
		this.operation_row_count = this.operation_table.rows.length - 2;
		this.operation_row = document.createElement('tr');

		this.operation_row.append(this._addColumn(operation_data.steps));
		this.operation_row.append(this._addColumn(operation_data.details));
		this.operation_row.append(this._addColumn(operation_data.start_in));
		this.operation_row.append(this._addColumn(operation_data.duration));

		this.addOperationsData(input);

		this.operation_row.append(this._createActionCell());
		$('#op-table tr:last').before(this.operation_row);

	}

	addOperationsData(input) {
		// todo : REWRITE THIS
		// add operation data as hidden input to action form
		const recovery_prefix = '';
		const form = document.forms['action.edit'];
		const operation_input = document.createElement('input');
		operation_input.setAttribute('type', 'hidden');
		operation_input.setAttribute('name', `add_${recovery_prefix}operation`);
		operation_input.setAttribute('value', '1');
		form.appendChild(operation_input);

		// todo : remove usrgrp and usrid from here, because now the data repeats

		for (const [name, value] of Object.entries(input.detail.operation)) {
			const operation_input = document.createElement('operation_input');
			operation_input.setAttribute('type', 'hidden');
			operation_input.setAttribute('name', `operations[${this.operation_row_count}][${name}]`);
			operation_input.setAttribute('id', `operations_${this.operation_row_count}_${name}`);
			operation_input.setAttribute('value', value);
			form.appendChild(operation_input);
		}

		if (input.detail.operation.opmessage_grp !== undefined) {
			for (const [index, value] of Object.entries(input.detail.operation.opmessage_grp)) {
				this.operation_row.append(this._addUserFields(index, 'usrgrpid',value.usrgrpid, 'opmessage_grp'));
			}
		}
		if (input.detail.operation.opmessage_usr !== undefined) {
			for (const [index, value] of Object.entries(input.detail.operation.opmessage_usr)) {
				this.operation_row.append(this._addUserFields(index, 'userid', value.userid, 'opmessage_usr'));
			}
		}
		this.operation_row.append(this._addHiddenOperationsFields('operationtype', 0));

	}

	_addHiddenOperationsFields(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `operations_${this.row_count}_${name}`;
		input.name = `operations[${this.row_count}][${name}]`;
		input.value = value;

		return input;
	}

	_addUserFields(index, name, value, group) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `operations_${this.row_count}_${group}_${index}_${name}`;
		input.name = `operations[${this.row_count}][${group}][${index}][${name}]`;
		input.value = value;

		return input;
	}


	_addColumn(input) {
		const cell = document.createElement('td');

		cell.append(input);
		return cell;
	}

	_checkRow(input) {
		// todo: check if condition with the same value already exists in the table.

		// check if identical condition already exists in table
		const hasRows = [...document.getElementById('conditionTable').getElementsByTagName('tr')].map(it => {
			const table_row = it.getElementsByTagName('td')[1];
			if (table_row !== undefined) {
				return (table_row.innerHTML === this._createNameCell(input).getElementsByTagName('td')[0].innerHTML);
			}
		});

		const hasRow = [hasRows.some(it => it === true)]
		if (hasRow[0] === true) {
			return;
		}
		else {
			this._createRow(input)
		}
	}

	_createRow(input) {
		this.row = document.createElement('tr');
		this.row.append(this._createLabelCell(input));
		this.row.append(this._createNameCell(input));
		this.row.append(this._createRemoveCell());

		this.table = document.getElementById('conditionTable');
		this.row_count = this.table.rows.length -1;

		$('#conditionTable tr:last').before(this.row);
		this._processTypeOfCalculation();
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
				span.append(<?= json_encode(_('Problem is suppressed')) ?>);
			}
			else {
				span.append(<?= json_encode(_('Problem is not suppressed')) ?>);
			}
		}
		else if (input.conditiontype == <?= CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
			if (input.value) {
				span.append(<?= json_encode(_('Event is acknowledged')) ?>);
			}
			else {
				span.append(<?= json_encode(_('Event is not acknowledged')) ?>);
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
		input.value = value;

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

	_createActionCell() {
	//->addClass('js-edit-button')
	//->setAttribute('data-operation', json_encode([
	//		'operationid' => $operationid,
	//		'actionid' => $data['actionid'],
	//		'eventsource' => $data['eventsource'],
	//		'operationtype' => ACTION_RECOVERY_OPERATION
	//])),

		const cell = document.createElement('td');
		const remove_btn = document.createElement('button');
		const edit_btn = document.createElement('button');

		remove_btn.type = 'button';
		remove_btn.classList.add('btn-link', 'element-table-remove');
		remove_btn.textContent = <?= json_encode(_('Remove')) ?>;
		remove_btn.addEventListener('click', () => remove_btn.closest('tr').remove());

		// todo : pass data to edit popup
		edit_btn.type = 'button';
		edit_btn.classList.add('btn-link', 'js-edit-button');
		edit_btn.textContent = <?= json_encode(_('Edit')) ?>;

		cell.appendChild(edit_btn);
		// todo: check how to add space between buttons differently
		cell.append(' ');
		cell.appendChild(remove_btn);

		return cell;
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
		// todo E.S.: rewrite jqueries.

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
