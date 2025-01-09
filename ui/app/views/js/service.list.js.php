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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = new class {

		constructor() {
			this.is_refresh_paused = false;
			this.is_refresh_pending = false;
		}

		init({serviceid, path = null, is_filtered = null, mode_switch_url, parent_url = null, refresh_url,
				refresh_interval, back_url = null}) {
			this.serviceid = serviceid;
			this.path = path;
			this.is_filtered = is_filtered;
			this.mode_switch_url = mode_switch_url;
			this.parent_url = parent_url;
			this.refresh_url = refresh_url;
			this.refresh_interval = refresh_interval;
			this.back_url = back_url;

			this.#initViewModeSwitcher();
			this.#initTagFilter();
			this.#initActions();
			this.#initRefresh();
		}

		#initViewModeSwitcher() {
			for (const element of document.getElementsByName('list_mode')) {
				if (!element.checked) {
					element.addEventListener('click', () => {
						location.href = this.mode_switch_url;
					});
				}
			}
		}

		#initTagFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');

					new CTagFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll('#filter-tags .form_row').forEach((row) => {
				new CTagFilterItem(row);
			});
		}

		#initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.matches('.js-create-service, .js-add-child-service')) {
					const parameters = e.target.dataset.serviceid !== undefined
						? {parent_serviceids: [e.target.dataset.serviceid]}
						: {};

					this.#edit(parameters);
				}
				else if (e.target.classList.contains('js-edit-service')) {
					popupManagerInstance.setBackUrl(this.#createBackurl());

					this.#edit({serviceid: e.target.dataset.serviceid});
				}
				else if (e.target.classList.contains('js-edit-service-list')) {
					popupManagerInstance.setBackUrl(location.href);

					this.#edit({serviceid: e.target.dataset.serviceid});
				}
				else if (e.target.classList.contains('js-delete-service')) {
					this.#delete(e.target, [e.target.dataset.serviceid]);
				}
				else if (e.target.classList.contains('js-massupdate-service')) {
					openMassupdatePopup('popup.massupdate.service', {
						location_url: this.back_url,
						[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('service')) ?>
					}, {
						dialogue_class: 'modal-popup-static',
						trigger_element: e.target
					});
				}
				else if (e.target.classList.contains('js-massdelete-service')) {
					this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		/**
		 * Generates URL based on current element path (parent service elements).
		 */
		#createBackurl() {
			let url = new Curl(popupManagerInstance.getUrl());
			const url_args = url.getArguments();
			const last_path = Object.keys(url_args).filter(key => key.startsWith('path')).length - 1;
			const last_path_value = url_args[`path[${last_path}]`];

			if (last_path_value) {
				url.setArgument('serviceid', last_path_value);
				url.unsetArgument(`path[${last_path}]`);
			}
			else {
				url = new Curl('zabbix.php');

				url.setArgument('action', 'service.list');
			}

			return url.getUrl();
		}

		#initRefresh() {
			if (this.refresh_interval > 0) {
				setInterval(() => this.#refresh(), this.refresh_interval);
			}
		}

		#edit(parameters = {}) {
			this.#pauseRefresh();

			// Save the current URL.
			this.current_url = location.href;
			window.popupManagerInstance.setUrl(this.current_url);

			// Update the browser's URL to the popup page URL when editing a service.
			if (parameters.serviceid) {
				this.#setPopupLink(parameters);
			}

			this.overlay = window.popupManagerInstance.openPopup('service.edit', parameters);

			this.#setSubmitCallback();
			this.#setCloseCallback();
		}

		#setPopupLink(parameters) {
			const url = new Curl('zabbix.php');

			url.setArgument('action', 'popup');
			url.setArgument('popup', 'service.edit');
			url.setArgument('serviceid', parameters.serviceid);

			history.replaceState(null, '', url.getUrl());
		}

		#delete(target, serviceids) {
			const confirmation = serviceids.length > 1
				? <?= json_encode(_('Delete selected services?')) ?>
				: <?= json_encode(_('Delete selected service?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'service.delete');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('service')) ?>);

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({serviceids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows(chkbxRange.prefix, response.keepids);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows(chkbxRange.prefix);
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}

		#pauseRefresh() {
			this.is_refresh_paused = true;
		}

		#resumeRefresh() {
			this.is_refresh_paused = false;
		}

		#refresh() {
			if (this.is_refresh_paused || this.is_refresh_pending) {
				return;
			}

			const service_list = document.getElementById('service-list');

			if (service_list.querySelectorAll('[data-expanded="true"], [aria-expanded="true"]').length > 0) {
				return;
			}

			this.is_refresh_pending = true;

			service_list.classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');

			fetch(this.refresh_url)
				.then((response) => response.json())
				.then((response) => {
					if ('body' in response) {
						service_list.outerHTML = response.body;

						chkbxRange.init();
					}

					if ('error' in response) {
						throw {error: response.error};
					}
				})
				.catch((exception) => {
					clearMessages();

					let title, messages;

					if (typeof exception === 'object' && 'error' in exception) {
						title = exception.error.title;
						messages = exception.error.messages;
					}
					else {
						messages = [<?= json_encode(_('Unexpected server error.')) ?>];
					}

					const message_box = makeMessageBox('bad', messages, title);

					addMessage(message_box);
				})
				.finally(() => {
					service_list.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');

					this.is_refresh_pending = false;
				});
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				if ('success' in e.detail) {
					postMessageOk(e.detail.success.title);

					if ('messages' in e.detail.success) {
						postMessageDetails('success', e.detail.success.messages);
					}
				}

				uncheckTableRows(chkbxRange.prefix);

				if ('action' in e.detail && e.detail.action === 'delete') {
					location.href = parameters.serviceid === this.serviceid ? this.parent_url : this.current_url;
				}
				else {
					location.href = popupManagerInstance.getUrl();
				}
			});
		}

		#setCloseCallback() {
			window.popupManagerInstance.setCloseCallback(() => {
				this.overlay.$dialogue[0].addEventListener('dialogue.close', () => this.#resumeRefresh(), {once: true});
			});
		}
	};
</script>
