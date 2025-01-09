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


class Popupmanager {

	constructor() {
		this.submit_callback = (e) => {
			if ('success' in e.detail) {
				postMessageOk(e.detail.success.title);

				if ('messages' in e.detail.success) {
					postMessageDetails('success', e.detail.success.messages);
				}
			}
		}
		this.close_callback = () => {};
		this.actions = () => true;
		this.popup_opening_callback = () => true;

		this.submit_options = {};
		this.overlay = null;
		this.action_is_set = false;
		this.back_url = null;
	}

	init() {
		document.body.addEventListener('click', (e) => {
			// In case link style has been modified and clicking on it catches a child element.
			const target_element = e.target.tagName === 'A' ? e.target : e.target.closest('a');
			const link = target_element && target_element.getAttribute('href')
				? target_element.getAttribute('href')
				: '';

			if (link && link.includes('action=popup') && !(e.ctrlKey || e.metaKey || e.button === 1)) {
				const {action, ...dataset} = target_element.dataset;

				if (this.popup_opening_callback(action)) {
					e.preventDefault();

					if (this.overlay) {
						const result = this.actions();

						if (result) {
							this.openPopup(action, dataset, false, undefined, e.target.href);
						}
					}
					else {
						const {action, ...dataset} = target_element.dataset;
						const is_popup_page = window.location.href.includes('action=popup');

						this.current_url = window.location.href;

						this.openPopup(action, dataset, is_popup_page, undefined, target_element.href);
					}
				}
			}
		});
	}

	openPopup(action, parameters, popup_page = false, options = {
		dialogueid: action,
		prevent_navigation: true
	}, target_url = null) {
		if (this.overlay === null && this.action_is_set) {
			const result = this.actions();

			if (!result) {
				return;
			}
		}

		if (target_url) {
			history.replaceState(null, '', target_url);
		}

		this.overlay = PopUp(action, parameters, options);

		this.overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			history.replaceState(null, '', this.current_url);
			this.submit_callback(e);

			if (this.overlay) {
				this.overlay.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.close', {detail: {action}}));
			}
		}, this.submit_options);

		this.overlay.$dialogue[0].addEventListener('dialogue.close', () => {
			this.close_callback();

			if (popup_page) {
				const backurl = this.overlay.backurl;

				setTimeout(() => redirect(backurl));
			}
			else {
				history.replaceState(null, '', this.current_url);
			}

			this.overlay = null;

			this.actions = () => true;
			this.action_is_set = false;
		});

		return this.overlay;
	}

	setSubmitCallback(callback, options = {}) {
		this.submit_callback = callback;
		this.submit_options = options;
	}

	setCloseCallback(callback) {
		this.close_callback = callback;
	}

	setUrl(url) {
		this.current_url = url;
	}

	getUrl() {
		return this.current_url;
	}

	setBackUrl(url) {
		this.back_url = url;
	}

	getBackUrl() {
		return this.back_url;
	}

	setAdditionalActions(actions) {
		this.actions = actions;
		this.action_is_set = true;
	}

	setPopupOpeningCallback(callback) {
		this.popup_opening_callback = callback;
	}
}

document.addEventListener('DOMContentLoaded', function() {
	window.popupManagerInstance = new Popupmanager();
	window.popupManagerInstance.init();
});
