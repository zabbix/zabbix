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


class Script {

	/**
	 * Executes script, handling optional manual input and confirmation.
	 *
	 * @param {string|null} scriptid                    Script ID.
	 * @param {string}      confirmation                Confirmation text.
	 * @param {Node}        trigger_element             UI element that was clicked to open overlay dialogue.
	 * @param {string|null} hostid                      Host ID.
	 * @param {string|null} eventid                     Event ID.
	 * @param {string|null} csrf_token                  CSRF token.
	 * @param {string|null} manualinput                 Manual input enabled/disabled or null.
	 * @param {string|null} manualinput_prompt          Manual input prompt text.
	 * @param {int|null}    manualinput_validator_type  Manual input type - 0 (string) or 1 (dropdown).
	 * @param {string|null} manualinput_validator       Validation rule - regular expression or list of allowed values.
	 * @param {string|null} manualinput_default_value   Default value of manual input.
	 */
	static execute(scriptid, confirmation, trigger_element, hostid = null, eventid = null, csrf_token = null,
			manualinput = null, manualinput_prompt = null, manualinput_validator_type = null,
			manualinput_validator = null, manualinput_default_value = null) {
		if (manualinput == ZBX_SCRIPT_MANUALINPUT_ENABLED) {
			const overlay = Script.#getManualInput(scriptid, confirmation, trigger_element, hostid, eventid,
				manualinput, manualinput_prompt, manualinput_validator_type, manualinput_validator,
				manualinput_default_value
			);

			overlay.$dialogue[0].addEventListener('manualinput.ready', (e) => {
				if (confirmation !== '') {
					Script.#confirm({
						dialogue_title: t('Execution confirmation'),
						confirmation: e.detail.confirmation,
						confirm_button_title: t('Execute'),
						confirm_button_enabled: hostid !== null || eventid !== null,
						trigger_element
					})
						.then(() => {
							overlayDialogueDestroy(overlay.dialogueid);

							Script.#execute(scriptid, eventid, hostid, e.detail.manualinput_value, csrf_token,
								trigger_element
							);
						})
						.catch(() => {
							overlay.unsetLoading();
							overlay.recoverFocus();
							overlay.containFocus();
						});
				}
				else {
					overlayDialogueDestroy(overlay.dialogueid);

					Script.#execute(scriptid, eventid, hostid, e.detail.manualinput_value, csrf_token, trigger_element);
				}
			});
		}
		else if (confirmation !== '') {
			Script.#confirm({
				dialogue_title: t('Execution confirmation'),
				confirmation,
				confirm_button_title: t('Execute'),
				confirm_button_enabled: hostid !== null || eventid !== null,
				trigger_element
			})
				.then(() => {
					Script.#execute(scriptid, eventid, hostid, null, csrf_token, trigger_element);
				})
				.catch(() => {});
		}
		else {
			Script.#execute(scriptid, eventid, hostid, null, csrf_token, trigger_element);
		}
	}

	/**
	 * Redirects user to the provided URL, handling optional manual input and confirmation.
	 *
	 * @param {string|null} scriptid                    Script ID.
	 * @param {string}      confirmation                Confirmation text.
	 * @param {Node}        trigger_element             UI element that was clicked to open overlay dialogue.
	 * @param {string|null} hostid                      Host ID.
	 * @param {string|null} eventid                     Event ID.
	 * @param {string|null} url                         Script URL.
	 * @param {string|null} url_target                  Script URL target.
	 * @param {string|null} manualinput                 Manual input enabled/disabled or null.
	 * @param {string|null} manualinput_prompt          Manual input prompt text.
	 * @param {int|null}    manualinput_validator_type  Manual input type - 0 (string) or 1 (dropdown).
	 * @param {string|null} manualinput_validator       Validation rule - regular expression or list of allowed values.
	 * @param {string|null} manualinput_default_value   Default value of manual input.
	 */
	static openUrl(scriptid, confirmation, trigger_element, hostid = null, eventid = null, url = null,
			url_target = '', manualinput = null, manualinput_prompt = null, manualinput_validator_type = null,
			manualinput_validator = null, manualinput_default_value = null) {
		if (manualinput == ZBX_SCRIPT_MANUALINPUT_ENABLED) {
			const overlay = Script.#getManualInput(scriptid, confirmation, trigger_element, hostid, eventid,
				manualinput, manualinput_prompt, manualinput_validator_type, manualinput_validator,
				manualinput_default_value
			);

			overlay.$dialogue[0].addEventListener('manualinput.ready', (e) => {
				const form = overlay.$dialogue.$body[0].querySelector('form');

				try {
					const url = new URL(e.detail.url, window.location.href);

					if (confirmation !== '') {
						Script.#confirm({
							dialogue_title: t('URL opening confirmation'),
							confirmation: e.detail.confirmation,
							confirm_button_title: t('Open URL'),
							confirm_button_enabled: hostid !== null || eventid !== null,
							trigger_element
						})
							.then(() => {
								overlayDialogueDestroy(overlay.dialogueid);

								if (url_target !== '') {
									window.open(url, url_target);
								}
								else {
									location.href = url.href;
								}
							})
							.catch(() => {
								overlay.unsetLoading();
								overlay.recoverFocus();
								overlay.containFocus();
							});
					}
					else {
						overlayDialogueDestroy(overlay.dialogueid);

						if (url_target !== '') {
							window.open(url, url_target);
						}
						else {
							location.href = url.href;
						}
					}
				}
				catch (exception) {
					overlay.unsetLoading();
					overlay.recoverFocus();
					overlay.containFocus();

					const messages = [sprintf(t('Invalid URL: %1$s'), e.detail.url)];
					const title = t('Cannot open URL');
					const message_box = makeMessageBox('bad', messages, title)[0];

					form.parentNode.insertBefore(message_box, form);
				}
			});
		}
		else if (confirmation !== '') {
			Script.#confirm({
				dialogue_title: t('URL opening confirmation'),
				confirmation,
				confirm_button_title: t('Open URL'),
				confirm_button_enabled: hostid !== null || eventid !== null,
				trigger_element
			})
				.then(() => {
					if (url_target !== '') {
						window.open(url, url_target);
					}
					else {
						location.href = url;
					}
				})
				.catch(() => {});
		}
		else {
			if (url_target !== '') {
				window.open(url, url_target);
			}
			else {
				location.href = url;
			}
		}
	}

	/**
	 * Open manualinput popup and retrieve user submitted manualinput value.
	 *
	 * @param {string|null} scriptid                    Script ID.
	 * @param {string}      confirmation                Confirmation text.
	 * @param {Node}        trigger_element             UI element that was clicked to open overlay dialogue.
	 * @param {string|null} hostid                      Host ID.
	 * @param {string|null} eventid                     Event ID.
	 * @param {string|null} manualinput                 Manual input enabled/disabled or null.
	 * @param {string|null} manualinput_prompt          Manual input prompt text.
	 * @param {int|null}    manualinput_validator_type  Manual input type - 0 (string) or 1 (dropdown).
	 * @param {string|null} manualinput_validator       Validation rule - regular expression or list of allowed values.
	 * @param {string|null} manualinput_default_value   Default value of manual input.
	 */
	static #getManualInput(scriptid, confirmation, trigger_element, hostid, eventid, manualinput, manualinput_prompt,
			manualinput_validator_type, manualinput_validator, manualinput_default_value) {
		const overlay = PopUp('script.userinput.edit', {
			manualinput_prompt,
			manualinput_default_value,
			manualinput_validator_type,
			manualinput_validator,
			confirmation
		}, {
			dialogueid: 'script-userinput-form',
			dialogue_class: 'modal-popup-small position-middle',
			trigger_element
		});

		let abort_controller = null;

		overlay.$dialogue[0].addEventListener('dialogue.close', () => {
			if (abort_controller !== null) {
				abort_controller.abort();
			}
		});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const form = overlay.$dialogue.$body[0].querySelector('form');

			const curl = new Curl('jsrpc.php');

			curl.setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);
			curl.setArgument('scriptid', scriptid);
			curl.setArgument('manualinput', e.detail.data.manualinput);

			if (hostid !== null) {
				curl.setArgument('method', 'get_scripts_by_hosts');
				curl.setArgument('hostid', hostid);
			}
			else if (eventid !== null) {
				curl.setArgument('method', 'get_scripts_by_events');
				curl.setArgument('eventid', eventid);
			}

			abort_controller = new AbortController();

			fetch(curl.getUrl(), {signal: abort_controller.signal})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response.result) {
						throw {error: response.result.error};
					}

					for (const element of form.parentNode.children) {
						if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
							element.parentNode.removeChild(element);
						}
					}

					response.result.manualinput_value = e.detail.data.manualinput;

					overlay.$dialogue[0].dispatchEvent(new CustomEvent('manualinput.ready', {detail: response.result}));
				})
				.catch((exception) => {
					if (abort_controller.signal.aborted) {
						return;
					}

					overlay.unsetLoading();

					for (const element of form.parentNode.children) {
						if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
							element.parentNode.removeChild(element);
						}
					}

					let title = null;
					let messages = [];

					if (typeof exception === 'object' && 'error' in exception) {
						messages = exception.error;
					}
					else {
						title = t('Unexpected server error.');
					}

					const message_box = makeMessageBox('bad', messages, title)[0];

					form.parentNode.insertBefore(message_box, form);
				})
				.finally(() => {
					abort_controller = null;
				});
		});

		return overlay;
	}

	/**
	 * Open confirmation popup.
	 *
	 * @param {string}  dialogue_title          Dialogue title.
	 * @param {string}  confirmation            Confirmation text.
	 * @param {string}  confirm_button_title    Confirmation button title.
	 * @param {boolean} confirm_button_enabled  Boolean whether confirmation button must be enabled or not.
	 * @param {object}  trigger_element         UI element that was clicked to open overlay dialogue.
	 */
	static #confirm({dialogue_title, confirmation, confirm_button_title, confirm_button_enabled, trigger_element}) {
		return new Promise((resolve, reject) => {
			const content = document.createElement('span');
			content.classList.add('confirmation-msg');
			content.textContent = confirmation;

			overlayDialogue({
				title: dialogue_title,
				content: content.outerHTML,
				class: 'modal-popup modal-popup-small position-middle',
				buttons: [
					{
						title: t('Cancel'),
						class: 'btn-alt',
						focused: !confirm_button_enabled,
						cancel: true,
						action: reject
					},
					{
						title: confirm_button_title,
						enabled: confirm_button_enabled,
						focused: confirm_button_enabled,
						action: resolve
					}
				]
			}, trigger_element);
		});
	}

	/**
	 * Execute script.
	 *
	 * @param {string}      scriptid         Script ID.
	 * @param {string|null} eventid          Event ID.
	 * @param {string|null} hostid           Host ID.
	 * @param {string|null} manualinput      Manual input value.
	 * @param {string}      csrf_token       CSRF token.
	 * @param {object}      trigger_element  UI element that was clicked to open overlay dialogue.
	 */
	static #execute(scriptid, eventid, hostid, manualinput, csrf_token, trigger_element) {
		if (hostid === null && eventid === null) {
			return;
		}

		PopUp('popup.scriptexec', {
			scriptid,
			eventid: eventid ?? undefined,
			hostid: hostid ?? undefined,
			manualinput: manualinput ?? undefined,
			[CSRF_TOKEN_NAME]: csrf_token
		}, {
			dialogue_class: 'modal-popup-medium', trigger_element
		});
	}
}
