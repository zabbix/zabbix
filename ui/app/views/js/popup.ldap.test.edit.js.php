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


/**
 * @var CView $this
 */
?>

window.ldap_test_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
	}

	init() {
		this.overlay = overlays_stack.getById('ldap_test_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
	}

	submit() {
		this.removePopupMessages();
		this.overlay.setLoading();

		const fields = this.trimFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'));

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('provisioning' in response) {
					this.appendProvisioning(document.getElementById('provisioning_role'), response.provisioning.role);
					this.appendProvisioning(document.getElementById('provisioning_groups'), response.provisioning.groups);
					this.appendProvisioning(document.getElementById('provisioning_medias'), response.provisioning.medias);
				}

				if ('error' in response) {
					throw {error: response.error};
				}
				else if ('success' in response) {
					const message_box = makeMessageBox('good', [], response.success.title, false, true)[0];

					this.form.parentNode.insertBefore(message_box, this.form);
				}
			})
			.catch((exception) => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
				}

				const message_box = makeMessageBox('bad', messages, title, true, true)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	appendProvisioning(parent, names) {
		let span;
		parent.innerHTML = '';

		if (names.length > 0) {
			for (const name of names) {
				span = document.createElement('span');
				span.innerText = name;
				span.classList.add(<?= json_encode(ZBX_STYLE_TAG) ?>);

				parent.appendChild(span);
			}
		}
		else {
			span = document.createElement('span');
			span.innerText = <?= json_encode(_('No value')) ?>;
			span.classList.add(<?= json_encode(ZBX_STYLE_DISABLED) ?>);
			parent.appendChild(span);
		}
	}

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	trimFields(fields) {
		fields.test_username = fields.test_username.trim();

		return fields;
	}
};
