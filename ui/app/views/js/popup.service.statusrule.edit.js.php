<?php declare(strict_types = 1);
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


/**
 * @var CView $this
 */
?>

window.service_status_rule_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init() {
		this.overlay = overlays_stack.getById('service_status_rule_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		const type_selector = document.getElementById('service-status-rule-type');

		type_selector.addEventListener('change', (e) => this.typeChange(e.target.value));
		this.typeChange(type_selector.value)
	},

	typeChange(type) {
		const label = document.getElementById('service-status-rule-limit-value-label');
		const unit = document.getElementById('service-status-rule-limit-value-unit');

		switch (type) {
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_L ?>':
				label.innerText = 'N';
				unit.style.display = 'none';
				break;
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_L ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_L ?>':
				label.innerText = 'N';
				unit.style.display = '';
				break;
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_L ?>':
				label.innerText = 'W';
				unit.style.display = 'none';
				break;
		}
	},

	submit() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php', false);

		curl.setArgument('action', 'service.statusrule.validate');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(getFormFields(this.form))
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy('service_status_rule_edit');

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
			})
			.catch((error) => {
				let message_box;

				if (typeof error === 'object' && 'html_string' in error) {
					message_box = new DOMParser().parseFromString(error.html_string, 'text/html').body.
						firstElementChild;
				}
				else {
					const error = <?= json_encode(_('Unexpected server error.')) ?>;

					message_box = makeMessageBox('bad', [], error, true, false)[0];
				}

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
