<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

window.service_time_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init() {
		this.overlay = overlays_stack.getById('service_time_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		document
			.getElementById('service_time_type')
			.addEventListener('change', (e) => this.typeChange(e));
	},

	typeChange(e) {
		PopUp('popup.service.time.edit', getFormFields(this.form), 'service_time_edit', e.target);
	},

	submit() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl('zabbix.php', false);

		curl.setArgument('action', 'popup.service.time.validate');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(getFormFields(this.form))
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy('service_time_edit');

				this.dialogue.dispatchEvent(new CustomEvent('service-time-submit', {detail: response.body}));
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

service_time_edit_popup.init();
