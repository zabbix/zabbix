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

	static CONTEXT_POPUP =			'popup';

	static EVENT_BEFORE_OPEN =		'before.open';
	static EVENT_OPEN =				'open';
	static EVENT_CLOSE =			'close';
	static EVENT_SUBMIT =			'submit';

	#overlay = null;
	#current_url = null;
	#back_url = null;
	#is_popup_page = false;
	#action = '';
	#subscribers = [];

	constructor() {
		document.addEventListener('click', e => {
			if (e.ctrlKey || e.metaKey || e.button === 1) {
				return;
			}

			const target_element = e.target.closest('a') || e.target.closest('button');
			const href = target_element?.getAttribute('href') || target_element?.dataset.href || '';

			if (href === '') {
				return;
			}

			const url = new Curl(href);

			if (url.getArgument('action') !== CPopupManager.CONTEXT_POPUP) {
				return;
			}

			e.preventDefault();

			const action = url.getArgument('popup');

			// Get all arguments from url except "action" and "popup".
			const parameters = (({action, popup, ...args}) => args)(url.getArguments());

			const event = new CEventHubEvent({
				data: {
					href,
					parameters
				},
				descriptor: {
					context: CPopupManager.CONTEXT_POPUP,
					event: CPopupManager.EVENT_BEFORE_OPEN,
					action
				}
			});

			ZABBIX.EventHub.publish(event);

			if (event.isDefaultPrevented()) {
				return;
			}

			if (this.#overlay === null) {
				this.#is_popup_page = new URL(location.href).searchParams.get('action') === CPopupManager.CONTEXT_POPUP;

				this.#current_url = location.href;
			}
			else {
				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#overlay = null;

				this.stopEvents();
			}

			this.openPopup(action, parameters, this.#is_popup_page, undefined, href);
		});
	}

	openPopup(action, parameters = {}, is_popup_page = false, options = {
		dialogueid: action,
		prevent_navigation: true
	}, target_url = null) {
		this.#is_popup_page = is_popup_page;
		this.#action = action;

		const event = new CEventHubEvent({
			data: {
				...parameters
			},
			descriptor: {
				context: CPopupManager.CONTEXT_POPUP,
				event: CPopupManager.EVENT_OPEN,
				action
			}
		});

		ZABBIX.EventHub.publish(event);

		if (event.isDefaultPrevented()) {
			return;
		}

		if (target_url !== null) {
			history.replaceState(null, '', target_url);
		}

		this.#overlay = PopUp(action, parameters, options);

		this.#overlay.$dialogue[0].addEventListener('dialogue.submit', e => {
			const detail = e.detail;

			const event = new CEventHubEvent({
				data: {
					...detail,
					...parameters
				},
				descriptor: {
					context: CPopupManager.CONTEXT_POPUP,
					event: CPopupManager.EVENT_SUBMIT,
					action
				}
			});

			ZABBIX.EventHub.publish(event);

			if (event.isDefaultPrevented()) {
				return;
			}

			if ('success' in detail) {
				postMessageOk(detail.success.title);

				if ('messages' in detail.success) {
					postMessageDetails('success', detail.success.messages);
				}
			}

			if (this.#is_popup_page) {
				const back_url = this.#back_url;

				setTimeout(() => redirect(back_url));
			}
			else {
				history.replaceState(null, '', this.#current_url);

				location.href = location.href;
			}

			this.#overlay = null;

		}, {once: true});

		this.#overlay.$dialogue[0].addEventListener('dialogue.close', () => {
			const event = new CEventHubEvent({
				descriptor: {
					context: CPopupManager.CONTEXT_POPUP,
					event: CPopupManager.EVENT_CLOSE,
					action
				}
			});

			ZABBIX.EventHub.publish(event);

			if (event.isDefaultPrevented()) {
				return;
			}

			if (this.#is_popup_page) {
				const back_url = this.#back_url;

				setTimeout(() => redirect(back_url));
			}
			else {
				history.replaceState(null, '', this.#current_url);
			}

			this.#overlay = null;

			this.stopEvents();
		});

		return this.#overlay;
	}

	stopEvents() {
		for (const subscription of this.#subscribers) {
			ZABBIX.EventHub.unsubscribe(subscription);
		}

		this.#subscribers = [];

		ZABBIX.EventHub.invalidateData({
			context: CPopupManager.CONTEXT_POPUP,
			action: this.#action
		});
	}

	setCurrentUrl(url) {
		this.#current_url = url;
	}

	getCurrentUrl() {
		return this.#current_url;
	}

	setBackUrl(url) {
		this.#back_url = url;
	}

	getBackUrl() {
		return this.#back_url;
	}

	addSubscriber(subscription) {
		this.#subscribers.push(subscription);
	}
}
