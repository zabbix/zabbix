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

window.operation_popup = new class {
	init({eventsource, recovery_phase}) {
		this.recovery_phase = recovery_phase;
		this.eventsource = eventsource;
		this.overlay = overlays_stack.getById('operations');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		if (document.getElementById('operation-condition-list')) {
			this.condition_count = (document.getElementById('operation-condition-list').rows.length - 2);
		}

		this._loadViews();
		this._processTypeOfCalculation();
	}

	_loadViews() {
		this._addCustomMessageFields();

		jQuery('#operation-type-select').on('change', (e) => {
			// todo : add functions that change popup view based on operation type
		});

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
				this.row_count--;
				this._processTypeOfCalculation();
			}
		});
	}

	// addPopupValues() {
	//	if (sourceid === 'operation-message-user-groups-footer') {
	//		overlay.$dialogue[0].dispatchEvent(new CustomEvent('submit-usergroups-popup', {detail:values}));
	//	}
	// else if (sourceid === 'operation-command-target-hosts') {
	//	operation_popup.view.operation_command.$targets_hosts_ms.multiSelect('addData', values);
	// }
	// else if (sourceid === 'operation-command-target-groups') {
	//	operation_popup.view.operation_command.$targets_groups_ms.multiSelect('addData', values);
	// }
	//}

	// _createHiddenInput() {
	//	let recovery_prefix = '';
	//	if (this.recovery_phase == operation_details.ACTION_RECOVERY_OPERATION) {
	//		recovery_prefix = 'recovery_';
	//	}
	//	else if (this.recovery_phase == operation_details.ACTION_UPDATE_OPERATION) {
	//		recovery_prefix = 'update_';
	//	}

	//	const form = document.forms['action.edit'];
	//	const input = document.createElement('input');
	//	input.setAttribute('type', 'hidden');
	//	input.setAttribute('name', `add_${recovery_prefix}operation`);
	//	input.setAttribute('value', '1');
	//	form.appendChild(input);

	//	operation_form.forEach((value, name) => {
	//		const input = document.createElement('input');
	//		input.setAttribute('type', 'hidden');
	//		input.setAttribute('name', `new_${recovery_prefix}${name}`);
	//		input.setAttribute('value', value);
	//		form.appendChild(input);
	//	});
	//}

	_openUserGroupPopup(trigger_element) {
		const parameters = {
			'srctbl': 'usrgrp',
			'srcfld1': 'usrgrpid',
			'srcfld2': 'name',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-user-groups-footer',
			'multiselect': '1'
		}

		const overlay = PopUp('popup.generic', parameters, {
			dialogue_class: 'modal-popup-generic', trigger_element, dialogueid: 'usergroup-popup'
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
		values.forEach(value => {
			const row = document.createElement('tr');
			row.append(value.name)
			row.append(this._createRemoveCell())
			row.appendChild(this._createHiddenInput('operation[opmessage_grp][][usrgrpid]',value.usrgrpid));

			document.getElementById('operation-message-user-groups-footer').before(row);
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

	_addUser(values) {
		values.forEach(value => {
			const row = document.createElement('tr');
			row.append(value.name)
			row.append(this._createRemoveCell())
			row.append(this._createHiddenInput('operation[opmessage_usr][][userid]', value.id))

			document.getElementById('operation-message-users-footer')
				.before(row);
		});
	}

	_openConditionsPopup(trigger_element) {
		const parameters = {
			'type': <?= ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION ?>,
			'source': this.eventsource
		};

		const overlay = PopUp('popup.condition.operations', parameters, {
			dialogue_class: 'modal-popup-medium',
			trigger_element: trigger_element,
			dialogueid: 'operation-condition'
		});

		overlay.$dialogue[0].addEventListener('condition.dialogue.submit', (e) => {
			// this._checkRow(e.detail)
			// todo : check if row already exists
			this._createRow(e.detail);
			this._processTypeOfCalculation();

		});
	}

	_checkRow(input) {
	// todo: add function to check if row already exists
	}

	_createRow(input) {
		const row = document.createElement('tr');

		row.append(this._createLabel(input));
		row.append(this._createName(input));
		row.append(this._createRemoveCell());

		row.appendChild(this._createHiddenInput('formulaid',this.label));
		row.appendChild(this._createHiddenInput('conditiontype',input.conditiontype));
		row.appendChild(this._createHiddenInput('operator',input.operator));
		row.appendChild(this._createHiddenInput('value',input.value));

		this.table = document.getElementById('operation-condition-list');
		this.row_count = this.table.rows.length -1;

		$('#operation-condition-list tr:last').before(row);
	}

	_createLabel(input) {
		// todo E.S. : FIX LABEL WHEN DELETE ROW AND ADD A NEW ONE!!
		const cell = document.createElement('td');

		this.label = num2letter(document.getElementById('operation-condition-list').rows.length -2);
		cell.setAttribute('class', 'label');
		cell.setAttribute('data-formulaid', this.label);
		cell.setAttribute('data-conditiontype', input.conditiontype);
		cell.append(this.label);
		return cell;
	}

	_createName(input) {
		const cell = document.createElement('td');
		if (input.conditiontype == <?= CONDITION_TYPE_EVENT_ACKNOWLEDGED ?>) {
			if (input.value == 1) {
				cell.append('Event is acknowledged');
			} else if (input.value == 0) {
				cell.append('Event is not acknowledged');
			}
		}
		return cell;
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
		this.validate();
		const fields = getFormFields(this.form);

		const url = new Curl('zabbix.php');
		url.setArgument('action', 'popup.action.operations');
		url.setArgument('eventsource', this.eventsource);
		url.setArgument('recovery', this.recovery_phase);

		this._post(url.getUrl(), fields);
	}

	validate() {
		// todo : pass actionid (0 if create new action???)
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'action.operation.validate');
		url.setArgument('actionid', 0);

		this.overlay.xhr = $.post(url.getUrl());

		// return $.ajax({
		//	url: url.getUrl(),
		//	processData: false,
		//	contentType: false,
		//	data: this.form,
		//	dataType: 'json',
		//	method: 'POST'
		//});
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
				this.dialogue.dispatchEvent(new CustomEvent('operation.submit', {detail: data}));
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

	_addCustomMessageFields() {
		// todo E.S. : rewrite jqueries:
		$('[id="operation-message-subject"],[id="operation-message-subject-label"]').hide();
		$('[id="operation-message-body"],[id="operation-message-label"]').hide();

		$('#operation_opmessage_default_msg')
			.change(function() {
				if($('#operation_opmessage_default_msg')[0].checked) {
					$('[id="operation-message-subject"],[id="operation-message-subject-label"]').show();
					$('[id="operation-message-body"],[id="operation-message-label"]').show();
				}
				else {
					$('[id="operation-message-subject"],[id="operation-message-subject-label"]').hide();
					$('[id="operation-message-body"],[id="operation-message-label"]').hide();
				}
			})
	}

	_processTypeOfCalculation() {
		// todo E.S.: rewrite jqueries.
		jQuery('#operation-evaltype').toggle(this.row_count > 1);
		jQuery('#operation-evaltype-label').toggle(this.row_count > 1);
		jQuery('#operation-condition-row').toggle(this.row_count > 1);

		const labels = jQuery('#operation-condition-list .label');
		var conditions = [];
		labels.each(function(index, label) {
			var label = jQuery(label);

			conditions.push({
				id: label.data('formulaid'),
				type: label.data('conditiontype')
			});
		});
		jQuery('#operation-condition-evaltype-formula').html(getConditionFormula(conditions, +jQuery('#operation-evaltype').val()));

		jQuery('#operation-evaltype').change(function() {
			const labels = jQuery('#operation-condition-list .label');
			var conditions = [];

			labels.each(function(index, label) {
				var label = jQuery(label);

				conditions.push({
					id: label.data('formulaid'),
					type: label.data('conditiontype')
				});
			});

			jQuery('#operation-condition-evaltype-formula').html(getConditionFormula(conditions, +jQuery('#operation-evaltype').val()));
		});
	}
}


// function submitOperationPopup(response) {
//	var form_param = response.form.param,
//		input_name = response.form.input_name,
//		inputs = response.inputs;

//	var input_keys = {
//		opmessage_grp: 'usrgrpid',
//		opmessage_usr: 'userid',
//		opcommand_grp: 'groupid',
//		opcommand_hst: 'hostid',
//		opgroup: 'groupid',
//		optemplate: 'templateid'
//	};

//	for (var i in inputs) {
//		if (inputs.hasOwnProperty(i) && inputs[i] !== null) {
//			if (i === 'opmessage' || i === 'opcommand' || i === 'opinventory') {
//				for (var j in inputs[i]) {
//					if (inputs[i].hasOwnProperty(j)) {
//						create_var('action.edit', input_name + '[' + i + ']' + '[' + j + ']', inputs[i][j], false);
//					}
//				}
//			}
//			else if (i === 'opconditions') {
//				for (var j in inputs[i]) {
//					if (inputs[i].hasOwnProperty(j)) {
//						create_var(
//							'action.edit',
//							input_name + '[' + i + ']' + '[' + j + '][conditiontype]',
//							inputs[i][j]['conditiontype'],
//							false
//						);
//						create_var(
//							'action.edit',
//							input_name + '[' + i + ']' + '[' + j + '][operator]',
//							inputs[i][j]['operator'],
//							false
//						);
//						create_var(
//							'action.edit',
//							input_name + '[' + i + ']' + '[' + j + '][value]',
//							inputs[i][j]['value'],
//							false
//						);
//					}
//				}
//			}
//			else if (['opmessage_grp', 'opmessage_usr', 'opcommand_grp', 'opcommand_hst', 'opgroup', 'optemplate']
//					.indexOf(i) !== -1) {
//				for (var j in inputs[i]) {
//					if (inputs[i].hasOwnProperty(j)) {
//						create_var(
//							'action.edit',
//							input_name + '[' + i + ']' + '[' + j + ']' + '[' + input_keys[i] + ']',
//							inputs[i][j][input_keys[i]],
//							false
//						);
//					}
//				}
//			}
//			else {
//				create_var('action.edit', input_name + '[' + i + ']', inputs[i], false);
//			}
//		}
//	}

//	submitFormWithParam('action.edit', form_param, '1');
//}
