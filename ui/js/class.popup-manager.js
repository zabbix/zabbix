/*
** Copyright (C) 2001-2024 Zabbix SIA
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


class CPopupManager {

	/**
	 * Name of standalone popup action.
	 *
	 * @type {string}
	 */
	static STANDALONE_ACTION = 'popup';

	/**
	 * Context name for popup manager events.
	 *
	 * @type {string}
	 */
	static EVENT_CONTEXT = 'popup-manager';

	/**
	 * Current popup overlay.
	 *
	 * @type {Overlay|null}
	 */
	static #overlay = null;

	/**
	 * Close event handler for currently open dialogue overlay.
	 *
	 * @type {function|null}
	 */
	static #on_close = null;

	/**
	 * Submit event handler for currently open dialogue overlay.
	 *
	 * @type {function|null}
	 */
	static #on_submit = null;

	/**
	 * Non-standalone origin URL from where the first popup was opened.
	 *
	 * @type {string|null}
	 */
	static #origin_url = null;

	/**
	 * Default URL to redirect to from standalone popup page after dialogue submit or close.
	 *
	 * @type {string|null}
	 */
	static #return_url = null;

	/**
	 * Initialize popup manager.
	 */
	static init() {
		document.addEventListener('click', e => {
			if (e.ctrlKey || e.metaKey || e.button !== 0) {
				return;
			}

			const target_element = e.target.closest('a, button');

			const standalone_url = target_element?.getAttribute('href') || target_element?.dataset.href || '';

			if (standalone_url === '' || standalone_url[0] === '#') {
				return;
			}

			const {action, popup, ...action_parameters} = searchParamsToObject(
				new URL(standalone_url, location.href).searchParams
			);

			if (action !== CPopupManager.STANDALONE_ACTION) {
				return;
			}

			e.preventDefault();

			CPopupManager.open(popup, action_parameters, {}, true);
		});
	}

	/**
	 * Open popup dialogue.
	 *
	 * @param {string}  action               MVC action of the popup dialogue.
	 * @param {Object}  action_parameters    MVC action parameters.
	 * @param {Object}  popup_options        Popup options ("prevent_navigation", etc.)
	 * @param {boolean} supports_standalone  Whether the popup dialogue supports opening on standalone page.
	 *
	 * @returns {Overlay|null}
	 */
	static open(
		action,
		action_parameters = {},
		popup_options = {},
		supports_standalone = false
	) {
		const open_event = new CPopupManagerEvent({
			data: {action_parameters, popup_options, supports_standalone},
			descriptor: {
				context: CPopupManager.EVENT_CONTEXT,
				event: CPopupManagerEvent.EVENT_OPEN,
				action
			}
		});

		ZABBIX.EventHub.publish(open_event);

		if (open_event.isDefaultPrevented()) {
			return null;
		}

		if (new URLSearchParams(location.search).get('action') !== CPopupManager.STANDALONE_ACTION) {
			CPopupManager.#origin_url = location.href;
		}

		if (supports_standalone) {
			const standalone_url_params = objectToSearchParams({
				action: CPopupManager.STANDALONE_ACTION,
				popup: action,
				...action_parameters
			}).toString();

			const standalone_url = new URL(`zabbix.php?${standalone_url_params}`, location.href);

			history.replaceState(null, '', standalone_url);
		}

		if (CPopupManager.#overlay !== null) {
			CPopupManager.#overlay.$dialogue[0].removeEventListener('dialogue.submit', CPopupManager.#on_submit);
			CPopupManager.#overlay.$dialogue[0].removeEventListener('dialogue.close', CPopupManager.#on_close);

			overlayDialogueDestroy(this.#overlay.dialogueid);
		}

		CPopupManager.#overlay = PopUp(action, action_parameters, {
			dialogueid: action,
			prevent_navigation: true,
			...popup_options
		});

		CPopupManager.#on_close = e => {
			this.#overlay = null;

			if (e.detail.close_by !== Overlay.prototype.CLOSE_BY_USER) {
				return;
			}

			const cancel_event = new CPopupManagerEvent({
				data: {action_parameters, popup_options, supports_standalone},
				descriptor: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_CANCEL,
					action
				}
			});

			ZABBIX.EventHub.publish(cancel_event);

			if (cancel_event.isDefaultPrevented()) {
				return;
			}

			if (CPopupManager.#origin_url !== null) {
				const redirect_url = cancel_event.getRedirectUrl();

				if (redirect_url !== null) {
					history.replaceState(null, '', redirect_url);

					location.href = location.href;
				}
				else {
					history.replaceState(null, '', CPopupManager.#origin_url);
				}
			}
			else {
				const redirect_url = cancel_event.getRedirectUrl() || CPopupManager.#return_url;

				if (redirect_url !== null) {
					location.href = redirect_url;
				}
			}
		}

		CPopupManager.#on_submit = e => {
			const submit_event = new CPopupManagerEvent({
				data: {action_parameters, popup_options, supports_standalone, submit: e.detail},
				descriptor: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT,
					action
				}
			});

			ZABBIX.EventHub.publish(submit_event);

			if (submit_event.isDefaultPrevented()) {
				if (CPopupManager.#origin_url !== null) {
					history.replaceState(null, '', CPopupManager.#origin_url);
				}

				return;
			}

			if ('success' in e.detail) {
				postMessageOk(e.detail.success.title);

				if ('messages' in e.detail.success) {
					postMessageDetails('success', e.detail.success.messages);
				}
			}

			if (CPopupManager.#origin_url !== null) {
				history.replaceState(null, '', submit_event.getRedirectUrl() || CPopupManager.#origin_url);

				location.href = location.href;
			}
			else {
				location.href = submit_event.getRedirectUrl() || CPopupManager.#return_url || location.href;
			}
		};

		CPopupManager.#overlay.$dialogue[0].addEventListener('dialogue.close', CPopupManager.#on_close, {once: true});
		CPopupManager.#overlay.$dialogue[0].addEventListener('dialogue.submit', CPopupManager.#on_submit, {once: true});

		return CPopupManager.#overlay;
	}

	/**
	 * Set default URL to redirect to from standalone popup page after dialogue submit or close.
	 *
	 * @param {string} url
	 */
	static setReturnUrl(url) {
		CPopupManager.#return_url = url;
	}
}
