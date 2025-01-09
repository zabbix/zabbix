<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/
?>


window.service_status_rule_edit_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('service_status_rule_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		document
			.getElementById('service-status-rule-type')
			.addEventListener('change', (e) => this._update());

		this._update();
	}

	_update() {
		const type = document.getElementById('service-status-rule-type').value;

		const limit_value_label = document.getElementById('service-status-rule-limit-value-label');
		const limit_value_unit = document.getElementById('service-status-rule-limit-value-unit');

		switch (type) {
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_L ?>':
				limit_value_label.innerText = 'N';
				limit_value_unit.style.display = 'none';

				break;

			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_L ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_L ?>':
				limit_value_label.innerText = 'N';
				limit_value_unit.style.display = '';

				break;

			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_L ?>':
				limit_value_label.innerText = 'W';
				limit_value_unit.style.display = 'none';

				break;
		}
	}

	submit() {
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'service.statusrule.validate');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(getFormFields(this.form))
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
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
};
