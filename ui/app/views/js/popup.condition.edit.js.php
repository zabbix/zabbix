<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


window.condition_popup = new class {

	init() {
		if (overlays_stack.stack.includes('operation-condition')) {
			this.overlay = overlays_stack.getById('operation-condition');
		}
		else if (overlays_stack.stack[0] === 'event_corr_condition') {
			this.overlay = overlays_stack.getById('event_corr_condition');
		}
		else if (overlays_stack.stack[0] === 'action-edit') {
			this.overlay = overlays_stack.getById('action-condition');
		}

		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this._loadViews();
	}

	_loadViews() {
		if (document.querySelector('#condition-type').value == <?= CONDITION_TYPE_SERVICE ?>) {
			$('#service-new-condition')
				.multiSelect('getSelectButton')
				.addEventListener('click', () => {
					this.selectServices();
				});
		}

		this.form.addEventListener('click', (e) => {
			document.querySelector('#condition-type').onchange = function() {
				reloadPopup(e.target.closest('form'), 'popup.condition.edit');
			}
			if (document.querySelector('#trigger_context')) {
				document.querySelector('#trigger_context').onchange = function() {
					reloadPopup(e.target.closest("form"), 'popup.condition.edit');
				}
			}
		})

		this._disableChosenMultiselectValues();
	}

	submit() {
		const curl = new Curl('zabbix.php');
		const fields = getFormFields(this.form);

		if (this.overlay == overlays_stack.getById('event_corr_condition')) {
			curl.setArgument('action', 'popup.condition.event.corr');
			curl.setArgument('validate', '1');
		}
		else if (this.overlay == overlays_stack.getById('operation-condition')) {
			curl.setArgument('action', 'action.operation.condition.check');
		}
		else {
			curl.setArgument('action', 'popup.condition.check');
		}

		if (typeof(fields.value) == 'string') {
			fields.value = fields.value.trim();
		}
		if (fields.value2 !== null && typeof(fields.value2) == 'string') {
			fields.value2 = fields.value2.trim();
		}

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

				document.dispatchEvent(new CustomEvent('condition.dialogue.submit', {detail: response}));
				this.dialogue.dispatchEvent(new CustomEvent('condition.dialogue.submit', {detail: response}));
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

	_disableChosenMultiselectValues() {
		const $trigger_ms = $('#trigger_new_condition');
		const $discovery_rule_ms = $('#drule_new_condition');
		const $host_ms = $('#host_new_condition');
		const $hostgroup_ms = $('#hostgroup_new_condition');
		const $template_ms = $('#template_new_condition');
		const $event_ms = $('#groupids_');

		const multiselects = [$trigger_ms, $discovery_rule_ms, $host_ms, $hostgroup_ms, $template_ms]
		multiselects.forEach((multiselect) => {
			multiselect.on('change', () => {
				multiselect.multiSelect('setDisabledEntries',
					[... this.form.querySelectorAll('[name^="value["]')].map((input) => input.value)
				)
			})
		})

		$event_ms.on('change', () => {
			$event_ms.multiSelect('setDisabledEntries',
				[... this.form.querySelectorAll('[name^="groupids[]"]')].map((input) => input.value)
			)
		})
	}

	selectServices() {
		const overlay = PopUp('popup.services', {title: t('Services')}, {dialogueid: 'services'});
		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const data = [];
			for (const service of e.detail) {
				data.push({id: service.serviceid, name: service.name});
			}
			$('#service-new-condition').multiSelect('addData', data);
		});
	}
}
