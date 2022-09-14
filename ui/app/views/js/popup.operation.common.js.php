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
	init() {
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
		// todo E.S. : rewrite jqueries:

		// todo : add this as another function
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

		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('operation-message-user-groups-footer')) {
				this._openUserGroupPopup(e.target);
			}
			else if (e.target.classList.contains('operation-message-users-footer')) {
				this._openUserPopup(e.target);
			}
			else if (e.target.classList.contains('operation-condition-list-footer')) {
				// todo E.S.: add function to open condition popup
				this._openConditionsPopup(e.target);
			}
			else if (e.target.classList.contains('element-table-remove')) {
				this.row_count--;
				this._processTypeOfCalculation();
			}
		});
	}

	_openUserGroupPopup(trigger_element) {
		PopUp('popup.generic', {
			'srctbl': 'usrgrp',
			'srcfld1': 'usrgrpid',
			'srcfld2': 'name',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-user-groups-footer',
			'multiselect': '1'
		}, {dialogue_class: 'modal-popup-generic', trigger_element});

		//this._addUserGroup();
		// todo E.S : ADD DATA TO 'SEND TO USER GROUPS' TABLE
	}

	_openUserPopup(trigger_element) {
		PopUp('popup.generic', {
			'srctbl': 'users',
			'srcfld1': 'userid',
			'srcfld2': 'fullname',
			'dstfrm': 'popup.operation',
			'dstfld1': 'operation-message-users-footer',
			'multiselect': '1'
		}, {dialogue_class: 'modal-popup-generic', trigger_element});

		// todo E.S : ADD DATA TO 'SEND TO USER' TABLE
	}

	_addUserGroup() {
		this.tmpl_usergroup_row = new Template(this._usrgrpTemplate());
		document.getElementById('operation-message-user-groups-footer')
			.before(this.tmpl_usergroup_row);
	}

	_usrgrpTemplate() {
		return `
			<tr data-id="#{usrgrpid}">
				<td>
					<span>#{name}</span>
				</td>
				<td class="<?= ZBX_STYLE_NOWRAP ?>">
					<input name="operation[opmessage_grp][][usrgrpid]" type="hidden" value="#{usrgrpid}" />
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="$(this).closest('tr').remove();">
						<?= _('Remove') ?>
					</button>
				</td>
			</tr>
		`
	}

	_addPopupValues() {
		// todo: pass popup data - objectid - usrgrpid
		//  todo: pass values: usrgrpid name gui_access, user_status ??
		const objectid = 'usrgrpid'
	}

	_openConditionsPopup(trigger_element) {
		const parameters = {
			'type': <?= ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION ?>,
			'source': <?= EVENT_SOURCE_TRIGGERS ?>
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
		this.row = document.createElement('tr');

		this.row.append(this._createLabel(input));
		this.row.append(this._createName(input));
		this.row.append(this._createRemoveCell());

		this.table = document.getElementById('operation-condition-list');
		this.row_count = this.table.rows.length -1;

		$('#operation-condition-list tr:last').before(this.row);
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

		// todo: pass eventsource and recovery
		url.setArgument('eventsource', 0);
		url.setArgument('recovery', 0);

		this._post(url.getUrl(), fields);
	}

	validate() {
		// todo : pass actionid (0 if create new action???)
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'action.operation.validate');
		url.setArgument('actionid', 0);

		// todo: rewrite this:
		return $.ajax({
			url: url.getUrl(),
			processData: false,
			contentType: false,
			data: this.form,
			dataType: 'json',
			method: 'POST'
		});
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
