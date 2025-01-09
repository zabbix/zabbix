<?php
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

window.media_type_mapping_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
	}

	init() {
		this.overlay = overlays_stack.getById('media_type_mapping_edit');
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
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
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

	trimFields(fields) {
		const fields_to_trim = ['name', 'attribute'];

		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}

		return fields;
	}

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}
};
