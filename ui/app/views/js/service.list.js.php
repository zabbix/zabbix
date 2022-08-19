<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

			this._initViewModeSwitcher();
			this._initTagFilter();
			this._initActions();
			this._initRefresh();
		}

		_initViewModeSwitcher() {
			for (const element of document.getElementsByName('list_mode')) {
				if (!element.checked) {
					element.addEventListener('click', () => {
						location.href = this.mode_switch_url;
					});
				}
			}
		}

		_initTagFilter() {
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

		_initActions() {
			document.addEventListener('click', (e) => {
				if (e.target.matches('.js-create-service, .js-add-child-service')) {
					const parameters = e.target.dataset.serviceid !== undefined
						? {parent_serviceids: [e.target.dataset.serviceid]}
						: {};

					this._edit(parameters);
				}
				else if (e.target.classList.contains('js-edit-service')) {
					this._edit({serviceid: e.target.dataset.serviceid});
				}
				else if (e.target.classList.contains('js-delete-service')) {
					this._delete(e.target, [e.target.dataset.serviceid]);
				}
				else if (e.target.classList.contains('js-massupdate-service')) {
					openMassupdatePopup('popup.massupdate.service', {location_url: this.back_url}, {
						dialogue_class: 'modal-popup-static',
						trigger_element: e.target
					});
				}
				else if (e.target.classList.contains('js-massdelete-service')) {
					this._delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		_initRefresh() {
			if (this.refresh_interval > 0) {
				setInterval(() => this._refresh(), this.refresh_interval);
			}
		}

		_edit(parameters = {}) {
			this._pauseRefresh();

			const overlay = PopUp('popup.service.edit', parameters, {
				dialogueid: 'service_edit',
				dialogue_class: 'modal-popup-medium'
			});

			const dialogue = overlay.$dialogue[0];

			dialogue.addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			dialogue.addEventListener('dialogue.delete', (e) => {
				uncheckTableRows(chkbxRange.prefix);

				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = parameters.serviceid === this.serviceid ? this.parent_url : location.href;
			});

			dialogue.addEventListener('overlay.close', () => this._resumeRefresh(), {once: true});
		}

		_delete(target, serviceids) {
			const confirmation = serviceids.length > 1
				? <?= json_encode(_('Delete selected services?')) ?>
				: <?= json_encode(_('Delete selected service?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'service.delete');

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

		_pauseRefresh() {
			this.is_refresh_paused = true;
		}

		_resumeRefresh() {
			this.is_refresh_paused = false;
		}

		_refresh() {
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
	};
</script>
