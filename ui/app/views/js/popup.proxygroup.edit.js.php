<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

window.proxy_group_edit_popup = new class {

	/** @type {Overlay} */
	#overlay;

	/** @type {HTMLDivElement} */
	#dialogue;

	/** @type {HTMLFormElement} */
	#form;

	/** @type {string|null} */
	#proxy_groupid;

	/** @type {Object} */
	#initial_form_fields;

	init({proxy_groupid}) {
		this.#overlay = overlays_stack.getById('proxy-group-edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = this.#overlay.$dialogue.$body[0].querySelector('form');

		this.#proxy_groupid = proxy_groupid;
		this.#initial_form_fields = getFormFields(this.#form);

		this.#initActions();
	}

	#initActions() {
		this.#form.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-edit-proxy')) {
				this.#editProxy(e.target.dataset.proxyid);
			}
		});
	}

	clone({title, buttons}) {
		this.#proxy_groupid = null;

		for (const element of this.#form.querySelectorAll('.js-field-proxies')) {
			element.remove();
		}

		this.#overlay.unsetLoading();
		this.#overlay.setProperties({title, buttons});
		this.#overlay.recoverFocus();
		this.#overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'proxygroup.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('proxygroup'), JSON_THROW_ON_ERROR) ?>
		);

		this.#post(curl.getUrl(), {proxy_groupids: [this.#proxy_groupid]}, (response) => {
			overlayDialogueDestroy(this.#overlay.dialogueid);

			this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.#form);

		for (const field of ['name', 'failover_delay', 'min_online', 'description']) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.#proxy_groupid === null ? 'proxygroup.create' : 'proxygroup.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.#overlay.dialogueid);

			this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	#post(url, data, success_callback) {
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

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.#form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title;
				let messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => this.#overlay.unsetLoading());
	}

	#editProxy(proxyid) {
		const form_fields = getFormFields(this.#form);

		if (JSON.stringify(this.#initial_form_fields) !== JSON.stringify(form_fields)
				&& !window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
			return;
		}

		overlayDialogueDestroy(this.#overlay.dialogueid);

		const overlay = PopUp('popup.proxy.edit', {proxyid}, {
			dialogueid: 'proxy_edit',
			dialogue_class: 'modal-popup-static',
			prevent_navigation: true
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) =>
			this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: e.detail}))
		);
	}
}
