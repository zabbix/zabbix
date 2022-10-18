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
		this._initTemplates();

		if(typeof(conditions) === 'object') {
			conditions = Object.values(conditions)
		}

		for (const condition of conditions) {
			this._createConditionsRow(condition);
		}
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
			else if (e.target.classList.contains('js-edit-button')) {
				this._openEditOperationPopup(e, JSON.parse(e.target.getAttribute('data-operation')), $(e.target).closest('tr').attr('id'));
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

	_openEditOperationPopup(e, operation_data, row_id) {
		if (JSON.parse(e.target.getAttribute('data'))) {
			const data = JSON.parse(e.target.getAttribute('data'))
			this.parameters = {
				eventsource: this.eventsource,
				recovery: this.recovery,
				actionid: this.actionid,
				data: data
			}
		}
		else {
			this.parameters = {
				eventsource: this.eventsource,
				recovery: operation_data.operationtype,
				actionid: this.actionid,
				data: operation_data.data
			}
		}

		const overlay = PopUp('popup.action.operation.edit', this.parameters, {
			dialogueid: 'operations',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('operation.submit', (e) => {
			this._createOperationsRow(e, row_id);
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
			this._createOperationsRow(e);
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
		if (is_array(input.value)) {
			input.value.forEach((value, index) => {
				let element = {...input, name: input.name[index], value: input.value[index]};
				let has_row = this._checkConditionRow(element);

				const result = [has_row.some(it => it === true)]
				if (result[0] === true) {
					return;
				}
				else {
					element.label = num2letter(input.row_index);
					input.row_index ++;

					element.condition_name = this.condition_types[element.conditiontype] + ' ' +
						this.condition_operators[element.operator] + ' '
					element.data = element.name

				document
						.querySelector('#conditionTable tbody')
						.insertAdjacentHTML('beforeend', this.condition_default_template.evaluate(element))
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
				input.row_index ++;
				let template;

				switch(parseInt(input.conditiontype)) {
					case <?= CONDITION_TYPE_SUPPRESSED ?>:
						template =  this.condition_suppressed_template;
						break;

					case <?= CONDITION_TYPE_EVENT_TAG_VALUE ?>:
						input.operator_name = this.condition_operators[input.operator]

						template =  this.condition_tag_value_template;
						break;

					default:
						input.condition_name = this.condition_types[input.conditiontype] + ' ' +
							this.condition_operators[input.operator] + ' '
						input.data = input.name

						template = this.condition_default_template;
				}
				document
					.querySelector('#conditionTable tbody')
					.insertAdjacentHTML('beforeend', template.evaluate(input))
			}
			this._processTypeOfCalculation();
		}
	}

	_checkConditionRow(input) {
		// Check if row with the same conditiontype and value already exists.
		let result = [];
		[...document.getElementById('conditionTable').getElementsByTagName('tr')].map(it => {
			const table_row = it.getElementsByTagName('td')[2];

			if (table_row !== undefined) {
				let conditiontype = table_row.getElementsByTagName('input')[0].value;
				let value = table_row.getElementsByTagName('input')[2].value;
				let value2 = table_row.getElementsByTagName('input')[3].value
					? table_row.getElementsByTagName('input')[3].value
					: null;

				result.push(input.conditiontype === conditiontype && input.value === value && input.value2 === value2);
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

	_isActionOperation() {
		return this.recovery == <?=ACTION_OPERATION?>;
	}

	_isRecoveryOperation() {
		return this.recovery == <?=ACTION_RECOVERY_OPERATION?>;
	}

	_isUpdateOperation() {
		return this.recovery == <?=ACTION_UPDATE_OPERATION?>;
	}

	_isTriggerOrInternalOrServiceEventSource() {
		return this.eventsource == <?=EVENT_SOURCE_TRIGGERS?>
			|| this.eventsource == <?=EVENT_SOURCE_INTERNAL?>
			|| this.eventsource == <?=EVENT_SOURCE_SERVICE?>;
	}

	_createOperationsRow(input, row_id = null) {
		const operation_data = input.detail.operation;
		this.recovery = input.detail.operation.recovery;

		let table_id;
		let row_id_prefix;

		if (this._isRecoveryOperation()) {
			table_id = 'rec-table';
			row_id_prefix = 'recovery_operations_';
		}

		else if (this._isUpdateOperation()) {
			table_id = 'upd-table';
			row_id_prefix = 'update_operations_';
		}

		else if (this._isActionOperation()) {
			table_id = 'op-table';
			row_id_prefix = 'operations_';
		}

		this.operation_row = document.createElement('tr');

		const rows = operation_data.details.type.map((type, index) => {
			const data = operation_data.details.data ? operation_data.details.data[index] : null;
			return this._addDetailsColumnNew(type, data);
		})

		const details = document.createElement('span');
		details.innerHTML = rows.join('<br>')

		if (this._isActionOperation() && this._isTriggerOrInternalOrServiceEventSource()) {
			this.operation_row.append(this._addColumn(operation_data.steps));
		}

		this.operation_row.append(details);

		if (this._isActionOperation() && this._isTriggerOrInternalOrServiceEventSource()) {
			this.operation_row.append(this._addColumn(operation_data.start_in));
			this.operation_row.append(this._addColumn(operation_data.duration));
		}

		this.addOperationsData(input);
		this.operation_row.append(this._createActionCell(input));
		this.operation_row.setAttribute('class', 'operation-details-row');

		this.operation_table = document.getElementById(table_id);
		this.operation_row_count = this.operation_table.rows.length - 2;

		this.operation_row.setAttribute('id', row_id_prefix + this.operation_row_count)

		if (row_id) {
			$(`#${row_id}`).replaceWith(this.operation_row);
		}
		else {
			$(`#${table_id} tr:last`).before(this.operation_row);
		}

		this._createTableRowIds(this.operation_table, row_id_prefix);
	}

	_createTableRowIds(table, prefix = '') {
		const rows = $(table).find('.operation-details-row');

		rows.each(index => {
			$(rows[index])
				.attr('id', prefix.concat(index))
		});
	}

	_addDetailsColumnNew(type, data = null) {
		return `<b>${type}</b> ${data ? data.join(' ') : ''}`;
	}

	addOperationsData(input) {
		this.recovery_prefix = '';

		if (this.recovery === <?= ACTION_RECOVERY_OPERATION ?>) {
			this.recovery_prefix = 'recovery_'
		}
		else if (this.recovery === <?= ACTION_UPDATE_OPERATION ?>) {
			this.recovery_prefix = 'update_'
		}

		const form = document.forms['action.edit'];
		const operation_input = document.createElement('input');

		operation_input.setAttribute('type', 'hidden');
		operation_input.setAttribute('name', `add_${this.recovery_prefix}operation`);
		operation_input.setAttribute('value', '1');
		form.appendChild(operation_input);

		const except_keys = ['details', 'start_in', 'steps', 'duration'];

		this.createHiddenInputFromObject(
			input.detail.operation, `operations[${this.operation_row_count}]`, `operations_${this.operation_row_count}`,
			except_keys
		);
		this.operation_row.append(
			this._addHiddenOperationsFields('operationtype', input.detail.operation.operationtype)
		);
	}

	createHiddenInputFromObject(obj, namePrefix, idPrefix, exceptKeys = []) {
		this.recovery_prefix = '';

		if (this.recovery == <?= ACTION_RECOVERY_OPERATION ?>) {
			this.recovery_prefix = 'recovery_'
		}
		else if (this.recovery == <?= ACTION_UPDATE_OPERATION ?>) {
			this.recovery_prefix = 'update_'
		}

		if (!obj || typeof obj !== 'object') {
			return;
		}

		Object.keys(obj).map(key => {
			if (exceptKeys.includes(key)) {
				return;
			}

			if (typeof obj[key] === 'object') {
				this.createHiddenInputFromObject(obj[key], `${namePrefix}[${key}]`, `${idPrefix}_${key}`);
				return;
			}

			const input = document.createElement('input');
			input.setAttribute('type', 'hidden');

			input.setAttribute('name', namePrefix ? `${this.recovery_prefix}${namePrefix}[${key}]` : key);
			input.setAttribute('id', idPrefix ? `${this.recovery_prefix}${idPrefix}_${key}` : key);

			input.setAttribute('value', obj[key]);

			this.operation_row.append(input);
		})
	}

	_addHiddenOperationsFields(name, value) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `${this.recovery_prefix}operations_${this.operation_row_count}_${name}`;
		input.name = `${this.recovery_prefix}operations[${this.operation_row_count}][${name}]`;
		input.value = value;

		return input;
	}

	_addUserFields(index, name, value, group) {
		if (this.recovery == <?=ACTION_OPERATION?>) {
			this.prefix = ''
		}
		else if (this.recovery == <?=ACTION_RECOVERY_OPERATION?>) {
			this.prefix = 'recovery_'
		}
		else if (this.recovery == <?=ACTION_UPDATE_OPERATION?>) {
			this.prefix = 'update_'
		}

		const input = document.createElement('input');
		input.type = 'hidden';
		input.id = `${this.prefix}operations_${this.row_count}_${group}_${index}_${name}`;
		input.name = `${this.prefix}operations[${this.row_count}][${group}][${index}][${name}]`;
		input.value = value;

		return input;
	}

	_addColumn(input) {
		const cell = document.createElement('td');

		cell.append(input);
		return cell;
	}

	_createActionCell(input) {
		const cell = document.createElement('td');
		const remove_btn = document.createElement('button');
		const edit_btn = document.createElement('button');

		remove_btn.type = 'button';
		remove_btn.textContent = <?= json_encode(_('Remove')) ?>;
		remove_btn.classList.add('btn-link', 'js-remove');

		edit_btn.type = 'button';
		edit_btn.classList.add('btn-link', 'js-edit-button');
		edit_btn.textContent = <?= json_encode(_('Edit')) ?>;
		edit_btn.setAttribute('data', JSON.stringify(input.detail.operation))

		cell.appendChild(edit_btn);
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
		this.show_formula = document.querySelector('#evaltype').value === <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;
		let row_count = document.getElementById('conditionTable').rows.length -2;

		document.querySelector('#formula').style.display = this.show_formula ? '' : 'none';
		document.querySelector('#expression').style.display = '';
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

	_initTemplates() {
		this.condition_suppressed_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td class="label" data-conditiontype="#{conditiontype}" data-formulaid= "#{label}">#{label}</td>
				<td class="wordwrap" style="max-width: <?= ZBX_TEXTAREA_BIG_WIDTH ?>px;">#{condition_name} </td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove-condition"><?= _('Remove') ?></button>
						</li>
						<li>
							<input type="hidden" name="conditions[#{row_index}][conditiontype]" value="#{conditiontype}">
							<input type="hidden" name="conditions[#{row_index}][operator]" value="#{operator}">
							<input type="hidden" name="conditions[#{row_index}][value]" value="#{value}">
							<input type="hidden" name="conditions[#{row_index}][value2]" value="#{value2}">
						</li>
					</ul>
				</td>
			</tr>
		`);

		this.condition_default_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td class="label" data-conditiontype="#{conditiontype}" data-formulaid= "#{label}">#{label}</td>
				<td
					class="wordwrap" style="max-width: <?= ZBX_TEXTAREA_BIG_WIDTH ?>px;">#{condition_name}
					<em> #{data} </em>
				</td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove-condition"><?= _('Remove') ?></button>
						</li>
						<li>
							<input type="hidden" name="conditions[#{row_index}][conditiontype]" value="#{conditiontype}">
							<input type="hidden" name="conditions[#{row_index}][operator]" value="#{operator}">
							<input type="hidden" name="conditions[#{row_index}][value]" value="#{value}">
							<input type="hidden" name="conditions[#{row_index}][value2]" value="#{value2}">
						</li>
					</ul>
				</td>
			</tr>
		`);

		this.condition_tag_value_template = new Template(`
			<tr data-row_index="#{row_index}">
				<td class="label" data-conditiontype="#{conditiontype}" data-formulaid= "#{label}">#{label}</td>
				<td
					class="wordwrap" style="max-width: <?= ZBX_TEXTAREA_BIG_WIDTH ?>px;"> Value of Tag
					<em> #{value2} </em>
					#{operator_name}
					<em> #{value} </em>
				</td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove-condition"><?= _('Remove') ?></button>
						</li>
						<li>
							<input type="hidden" name="conditions[#{row_index}][conditiontype]" value="#{conditiontype}">
							<input type="hidden" name="conditions[#{row_index}][operator]" value="#{operator}">
							<input type="hidden" name="conditions[#{row_index}][value]" value="#{value}">
							<input type="hidden" name="conditions[#{row_index}][value2]" value="#{value2}">
						</li>
					</ul>
				</td>
			</tr>
		`);
	}
}
