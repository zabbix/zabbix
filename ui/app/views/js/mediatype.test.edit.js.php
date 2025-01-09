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

window.mediatype_test_edit_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('mediatype_test_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		if (this.form.querySelector('#mediatypetest_log')) {
			this.form.querySelector('#mediatypetest_log').addEventListener('click', (event) =>
				this.#openLogPopup(event.target)
			);
		}
	}

	submit() {
		const fields = getFormFields(this.form);
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'mediatype.test.send');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('mediatype')) ?>);

		this.form.querySelector('#mediatypetest_log')?.classList.add('<?= ZBX_STYLE_DISABLED ?>');

		// Trim fields.
		for (let key in fields) {
			if (['sendto', 'subject', 'message'].includes(key)) {
				fields[key] = fields[key].trim();
			}
		}

		this.overlay.setLoading();

		this.#post(curl.getUrl(), fields, (response) => {
			const message_box = makeMessageBox('good', response.success.messages, response.success.title);

			message_box.insertBefore(this.form);
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
			'title': <?= json_encode(_('Media type test log')) ?>,
			'content': content,
			'class': 'modal-popup modal-popup-generic debug-modal position-middle',
			'footer': footer,
			'buttons': [
				{
					'title': <?= json_encode(_('Ok')) ?>,
					'cancel': true,
					'focused': true,
					'action': function () {}
				}
			]
		}, trigger_element);
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and executes the success_callback function.
	 *
	 * @param {string}   url               The URL to send the POST request to.
	 * @param {object}   data              The data to send with the POST request.
	 * @param {callback} success_callback  The function to execute when a successful response is received.
	 */
	#post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				if ('debug' in response) {
					this.form.querySelector('#mediatypetest_log').classList.remove('disabled');
					sessionStorage.setItem('mediatypetest', JSON.stringify(response.debug));
				}

				if ('response' in response) {
					// Set 'webhook_response_value' input field value
					const response_value_element = this.form.querySelector('#webhook_response_value');

					if (response_value_element) {
						response_value_element.value = response.response.value;
					}

					// Set 'webhook_response_type' text element value
					const response_type_element = this.form.querySelector('#webhook_response_type');

					if (response_type_element) {
						response_type_element.textContent = response.response.type;
					}
				}

				if ('error' in response) {
					throw { error: response.error };
				}

				return response;
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
			.finally(() => this.overlay.unsetLoading());
	}
}
