<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

window.mediatype_test_edit_popup = new class {

	#overlay;
	#dialogue;
	#footer;
	#form;
	#form_element;

	init({rules}) {
		this.#overlay = overlays_stack.getById('mediatype_test_edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		if (this.#form_element.querySelector('#mediatypetest_log')) {
			this.#form_element.querySelector('#mediatypetest_log').addEventListener('click', (event) =>
				this.#openLogPopup(event.target)
			);
		}

		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.#form.getAllValues();

		this.#form_element.querySelector('#mediatypetest_log')?.classList.add('<?= ZBX_STYLE_DISABLED ?>');

		// Trim fields.
		for (let key in fields) {
			if (['sendto', 'subject', 'message'].includes(key)) {
				fields[key] = fields[key].trim();
			}
		}

		this.#overlay.setLoading();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();
					return;
				}

				this.#post(zabbixUrl({action: 'mediatype.test.send'}), fields);
			});
	}

	/**
	 * Opens Media type test log popup.
	 *
	 * @param {string} trigger_element  Element that triggered the opening of the popup.
	 */
	#openLogPopup(trigger_element) {
		if (trigger_element.classList.contains('<?= ZBX_STYLE_DISABLED ?>')) {
			return;
		}

		const debug = JSON.parse(sessionStorage.getItem('mediatypetest') || 'null');
		const content = document.createElement('div');
		const logitems = document.createElement('div');
		const footer = document.createElement('div');

		if (debug) {
			debug.log.forEach(function (entry) {
				var preElement = document.createElement('pre');
				preElement.textContent = entry.ms + ' ' + entry.level + ' ' + entry.message;
				logitems.appendChild(preElement);
				logitems.classList.add('logitems');
			});

			footer.textContent = <?= json_encode(_('Time elapsed:')) ?> + " " + debug.ms + 'ms';
			footer.classList.add('logtotalms');
			content.appendChild(logitems);
		}

		overlayDialogue({
			title: <?= json_encode(_('Media type test log')) ?>,
			content,
			class: 'modal-popup modal-popup-generic debug-modal',
			footer,
			buttons: [
				{
					title: <?= json_encode(_('Ok')) ?>,
					cancel: true,
					focused: true,
					action: function () {}
				}
			]
		}, {
			position: Overlay.prototype.POSITION_CENTER,
			trigger_element
		});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data.
	 *
	 * @param {string}   url			   The URL to send the POST request to.
	 * @param {object}   data			  The data to send with the POST request.
	 */
	#post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if ('form_errors' in response) {
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				if ('debug' in response) {
					this.#form_element.querySelector('#mediatypetest_log').classList.remove('disabled');
					sessionStorage.setItem('mediatypetest', JSON.stringify(response.debug));
				}

				if ('response' in response) {
					// Set 'webhook_response_value' input field value
					const response_value_element = this.#form_element.querySelector('#webhook_response_value');

					if (response_value_element) {
						response_value_element.value = response.response.value;
					}

					// Set 'webhook_response_type' text element value
					const response_type_element = this.#form_element.querySelector('#webhook_response_type');

					if (response_type_element) {
						response_type_element.textContent = response.response.type;
					}
				}

				if ('success' in response) {
					const message_box = makeMessageBox('good', response.success.messages, response.success.title);
					this.#form_element.parentNode.insertBefore(message_box[0], this.#form_element);
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.#overlay.unsetLoading());
	}

	#removePopupMessages() {
		for (const el of this.#form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}
}
