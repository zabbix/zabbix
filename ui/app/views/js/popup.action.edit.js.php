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
			else if (e.target.classList.contains('js-edit-operation')) {
				this._openEditOperationPopup(e, JSON.parse(e.target.getAttribute('data_operation')), $(e.target).closest('tr').attr('id'));
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
		const data = JSON.parse(e.target.getAttribute('data_operation'));

		if (data.operationid || data.operationid === 0) {
			this.parameters = {
				eventsource: this.eventsource,
				recovery: operation_data.operationtype,
				actionid: this.actionid,
				data: operation_data.data
			}
		}
		else  {
			this.parameters = {
				eventsource: this.eventsource,
				recovery: this.recovery,
				actionid: this.actionid,
				data: data
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
					element.condition_name = this.condition_types[element.conditiontype] + ' ' +
						this.condition_operators[element.operator] + ' '
					element.data = element.name
					element.label = num2letter(element.row_index);
					input.row_index ++;

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

				result.push(input.conditiontype === conditiontype && input.value === value && input.value2 === value2);

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

	/**
	* Add hidden inputs to template for each possible eventsource/operation type/data combination.
	*/
	_prepareOperationsRow(operation, op_template) {
		const template = document.createElement('template');
		let prefix = operation.prefix;
		template.innerHTML = op_template.evaluate(operation);
		const row = template.content.firstElementChild;
		const except_keys = [
			'details', 'data_operation', 'prefix', 'row_index', 'data', 'steps', 'start_in', 'duration', 'usr_data',
			'usrgrp_data', 'usr_details', 'usrgrp_details'
		];

		for (const [key, value] of Object.entries(operation)) {
			if (!except_keys.includes(key)) {
				let input = document.createElement('input');
				let first = row.getElementsByTagName('td')[0];

				if (is_array(value)) {
					value.map((it, index) => {
						let input = document.createElement('input');
						input.setAttribute('type', 'hidden');
						input.setAttribute('name', `${prefix}operations[${operation.row_index}][${key}][${index}][${Object.keys(it)[0]}]`)
						input.setAttribute('id', `${prefix}operations_${operation.row_index}_${key}_${index}_${Object.keys(it)[0]}`)
						input.setAttribute('value', it[Object.keys(it)[0]])
						first.append(input);
					})
				}
				else if (is_object(value) && !is_array(value)) {
					input.setAttribute('type', 'hidden');
					input.setAttribute('name', `${prefix}operations[${operation.row_index}][${key}][${Object.keys(value)[0]}]`)
					input.setAttribute('id', `${prefix}operations_${operation.row_index}_${key}_${Object.keys(value)[0]}`)
					input.setAttribute('value', value[Object.keys(value)[0]])
					first.append(input);
				}

				else {
					input.setAttribute('type', 'hidden');
					input.setAttribute('id', `${prefix}operations_${operation.row_index}_${key}`);
					input.setAttribute('name', `${prefix}operations[${operation.row_index}][${key}]`);
					input.setAttribute('value', `${value}`);
					first.append(input);
				}
			}
		}

		return row
	}

	/**
	* Add data to specific template based on operation recovery type, input data and eventsource.
	*/
	_createOperationsRow(input, row_id = null) {
		let operation = input.detail.operation;

		if (this.recovery == undefined) {
			this.recovery  = operation.recovery;
		}

		let row_index;
		if (row_id !== null) {
			row_index = row_id;
		}

		let operation_obj = {...operation};
		let data = input.detail.operation.details.data ? input.detail.operation.details.data[0] : [];
		operation_obj.data = data.join(' ');
		operation_obj.details = input.detail.operation.details.type;
		let template = this.operation_template_basic;

		if (input.detail.operation.details.data) {
			if (operation.details.data.length > 1) {
				operation_obj.usr_data = operation.details.data[0].join('');
				operation_obj.usr_details = operation.details.type[0];
				operation_obj.usrgrp_data = operation.details.data[1].join(' ');
				operation_obj.usrgrp_details = operation.details.type[1];
				template = this.operation_template_usr_usrgrps_basic;
			}
		}

		if (row_id) {
			document.getElementById(row_id).remove();
		}
		operation_obj.data_operation = JSON.stringify(operation);

		switch (parseInt(this.recovery)) {
			case <?=ACTION_RECOVERY_OPERATION?>:
				row_index = 0;
				while (document.querySelector(`#rec-table [id="recovery_operations_${row_index}"]`) !== null) {
					row_index++;
				}
				operation_obj.row_index = row_index
				operation_obj.prefix = 'recovery_';

				document
					.querySelector('#rec-table tbody')
					.appendChild(this._prepareOperationsRow(operation_obj, template));
				break;

			case <?=ACTION_UPDATE_OPERATION?>:
				row_index = 0;
				while (document.querySelector(`#upd-table [id="update_operations_${row_index}"]`) !== null) {
					row_index++;
				}
				operation_obj.row_index = row_index;
				operation_obj.prefix = 'update_';

				document
					.querySelector('#upd-table tbody')
					.appendChild(this._prepareOperationsRow(operation_obj, template));
				break;

			case <?=ACTION_OPERATION?>:
				row_index = 0;
				while (document.querySelector(`#op-table [id="operations_${row_index}"]`) !== null) {
					row_index++;
				}
				operation_obj.row_index = row_index;

				switch (parseInt(this.eventsource)) {
					case <?= EVENT_SOURCE_TRIGGERS ?>:
					case <?= EVENT_SOURCE_SERVICE ?>:
					case <?= EVENT_SOURCE_INTERNAL ?>:

						template = this.operation_template_additional
						operation_obj.prefix = '';
						operation_obj.steps = input.detail.operation.steps;
						operation_obj.start_in = input.detail.operation.start_in;
						operation_obj.duration = input.detail.operation.duration;

						if (input.detail.operation.details.data) {
							if (operation.details.data.length > 1) {
								template = this.operation_template_usr_usrgrps_additional;
							}
						}

						document
							.querySelector('#op-table tbody')
							.appendChild(this._prepareOperationsRow(operation_obj, template));
						break;

					default:
						if (input.detail.operation.details.data) {
							if (operation.details.data.length > 1) {
								template = this.operation_template_usr_usrgrps_basic;
							}
						}
						operation_obj.prefix = ''

						document
							.querySelector('#op-table tbody')
							.appendChild(this._prepareOperationsRow(operation_obj, template));
						break;
				}
		}
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
			body: urlEncodeData({actionids: [this.actionid]})
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
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove-condition">
							<?= _('Remove') ?>
							</button>
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
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove-condition">
							<?= _('Remove') ?>
							</button>
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
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove-condition">
							<?= _('Remove') ?>
							</button>
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

		this.operation_template_basic = new Template(`
			<tr id="#{prefix}operations_#{row_index}">
				<td class="wordwrap">
					<span>
						<b> #{details}  </b> #{data}
					</span>
				</td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>
							js-edit-operation" data_operation="#{data_operation}">
							<?= _('Edit') ?>
							</button>
						</li>
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove">
							<?= _('Remove') ?>
							</button>
						</li>
					</ul>
				</td>
			</tr>
		`);

		this.operation_template_additional = new Template(`
			<tr id="#{prefix}operations_#{row_index}">
				<td> #{steps} </td>
				<td class="wordwrap">
					<span>
						<b> #{details}  </b> #{data}
					</span>
				</td>
				<td> #{start_in} </td>
				<td> #{duration} </td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button
							type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-edit-operation" data_operation="#{data_operation}">
							<?= _('Edit') ?>
							</button>
						</li>
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
						</li>
					</ul>
				</td>
			</tr>
		`);

		this.operation_template_usr_usrgrps_basic = new Template(`
			<tr id="#{prefix}operations_#{row_index}">
				<td class="wordwrap">
					<span>
						<b> #{usr_details}  </b> #{usr_data}
					</span> <br>
					<span>
						<b> #{usrgrp_details}  </b> #{usrgrp_data}
					</span>
				</td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button
							type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-edit-operation
							data_operation="#{data_operation}">
							<?= _('Edit') ?>
							</button>
						</li>
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove">
							<?= _('Remove') ?>
							</button>
						</li>
					</ul>
				</td>
			</tr>
		`);

		this.operation_template_usr_usrgrps_additional = new Template(`
			<tr id="#{prefix}operations_#{row_index}">
				<td> #{steps} </td>
				<td class="wordwrap">
					<span>
						<b> #{usr_details}  </b> #{usr_data}
					</span> <br>
					<span>
						<b> #{usrgrp_details}  </b> #{usrgrp_data}
					</span>
				</td>
				<td> #{start_in} </td>
				<td> #{duration} </td>
				<td>
					<ul class="<?= ZBX_STYLE_HOR_LIST ?>">
						<li>
							<button
							type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-edit-operation"
							data_operation="#{data_operation}">
							<?= _('Edit') ?>
							</button>
						</li>
						<li>
							<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
						</li>
					</ul>
				</td>
			</tr>
		`);
	}
}
