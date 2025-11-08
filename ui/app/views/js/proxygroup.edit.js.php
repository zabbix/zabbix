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

window.proxy_group_edit_popup = new class {

	/** @type {Overlay} */
	#overlay;

	/** @type {HTMLDivElement} */
	#dialogue;

	/** @type {HTMLFormElement} */
	#form_element;

	/** @type {CForm} */
	#form;

	/** @type {string|null} */
	#proxy_groupid;

	/** @type {Object} */
	#initial_form_fields;

	/** @type {Object} */
	#rules_for_clone;

	init({rules, rules_for_clone, proxy_groupid}) {
		this.#rules_for_clone = rules_for_clone;
		this.#overlay = overlays_stack.getById('proxygroup.edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		this.#proxy_groupid = proxy_groupid;
		this.#initial_form_fields = getFormFields(this.#form_element);

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'proxygroup.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initPopupListeners();
		this.#initActions();
	}

	#initActions() {
		const footer_node = this.#overlay.$dialogue.$footer[0];

		footer_node.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		footer_node.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
		footer_node.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
	}

	#initPopupListeners() {
		const subscriptions = [];

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_OPEN,
					action: 'proxy.edit'
				},
				callback: ({event}) => {
					if (!this.#isConfirmed()) {
						event.preventDefault();
					}
				}
			})
		);

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.#overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	}

	#isConfirmed() {
		return JSON.stringify(this.#initial_form_fields) === JSON.stringify(getFormFields(this.#form_element))
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	}

	#clone() {
		const title = <?= json_encode(_('New proxy group')) ?>;
		const buttons = [
			{
				title: <?= json_encode(_('Add')) ?>,
				class: 'js-submit',
				keepOpen: true,
				isSubmit: true
			},
			{
				title: <?= json_encode(_('Cancel')) ?>,
				class: <?= json_encode(ZBX_STYLE_BTN_ALT) ?>,
				cancel: true,
				action: ''
			}
		];

		this.#proxy_groupid = null;

		for (const element of this.#form_element.querySelectorAll('.js-field-proxies')) {
			element.remove();
		}

		this.#overlay.unsetLoading();
		this.#overlay.setProperties({title, buttons});
		this.#overlay.recoverFocus();
		this.#overlay.containFocus();
		this.#initActions();
		this.#form.reload(this.#rules_for_clone);
	}

	#delete() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'proxygroup.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('proxygroup')) ?>);

		this.#post(curl.getUrl(), {proxy_groupids: [this.#proxy_groupid]}, (response) => {
			overlayDialogueDestroy(this.#overlay.dialogueid);

			this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	#submit() {
		const fields = this.#form.getAllValues();
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', this.#proxy_groupid === null ? 'proxygroup.create' : 'proxygroup.update');

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();

					return;
				}

				this.#post(curl.getUrl(), fields, (response) => {
					overlayDialogueDestroy(this.#overlay.dialogueid);

					this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
				});
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

				if ('form_errors' in response) {
					throw {form_errors: response.form_errors};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				if ('form_errors' in exception) {
					this.#form.setErrors(exception.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				for (const element of this.#form_element.parentNode.children) {
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

				this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
			})
			.finally(() => this.#overlay.unsetLoading());
	}
};
