<?php declare(strict_types = 1);
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
	const view = {
		serviceid: null,
		mode_switch_url: null,
		parent_url: null,
		delete_url: null,
		refresh_url: null,
		refresh_interval: null,
		back_url: null,
		is_refresh_paused: false,
		is_refresh_pending: false,

		init({serviceid, mode_switch_url, parent_url, delete_url, refresh_url, refresh_interval, back_url = null}) {
			this.serviceid = serviceid;
			this.mode_switch_url = mode_switch_url;
			this.parent_url = parent_url;
			this.delete_url = delete_url;
			this.refresh_url = refresh_url;
			this.refresh_interval = refresh_interval;
			this.back_url = back_url;

			this.initViewModeSwitcher();
			this.initTagFilter();
			this.initActionButtons();
			this.initRefresh();
		},

		initViewModeSwitcher() {
			for (const element of document.getElementsByName('list_mode')) {
				if (!element.checked) {
					element.addEventListener('click', () => {
						location.href = this.mode_switch_url;
					});
				}
			}
		},

		initTagFilter() {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');

					new CTagFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll('#filter-tags .form_row').forEach((row) => {
				new CTagFilterItem(row);
			});
		},

		initActionButtons() {
			document.addEventListener('click', (e) => {
				if (e.target.matches('.js-create-service, .js-add-child-service')) {
					const parameters = e.target.dataset.serviceid !== undefined
						? {parent_serviceids: [e.target.dataset.serviceid]}
						: {};

					this.edit(parameters);
				}
				else if (e.target.classList.contains('js-edit-service')) {
					this.edit({serviceid: e.target.dataset.serviceid});
				}
				else if (e.target.classList.contains('js-delete-service')) {
					this.delete(e.target, [e.target.dataset.serviceid]);
				}
				else if (e.target.classList.contains('js-massupdate-service')) {
					openMassupdatePopup('popup.massupdate.service', {location_url: this.back_url}, {
						dialogue_class: 'modal-popup-static',
						trigger_element: e.target
					});
				}
				else if (e.target.classList.contains('js-massdelete-service')) {
					this.delete(e.target, Object.values(chkbxRange.getSelectedIds()));
				}
			});
		},

		initRefresh() {
			if (this.refresh_interval > 0) {
				setInterval(() => this.refresh(), this.refresh_interval);
			}
		},

		edit(parameters = {}) {
			this.pauseRefresh();

			const overlay = PopUp('popup.service.edit', parameters, {
				dialogueid: 'service_edit',
				dialogue_class: 'modal-popup-medium'
			});
			const dialogue = overlay.$dialogue[0];

			dialogue.addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if (e.detail.messages !== null) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			dialogue.addEventListener('dialogue.delete', (e) => {
				uncheckTableRows('service');

				postMessageOk(e.detail.title);

				if (e.detail.messages !== null) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = options.serviceid === this.serviceid ? this.parent_url : location.href;
			});

			dialogue.addEventListener('overlay.close', () => this.resumeRefresh(), {once: true});
		},

		delete(target, serviceids) {
			const confirmation = serviceids.length > 1
				? <?= json_encode(_('Delete selected services?')) ?>
				: <?= json_encode(_('Delete selected service?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			target.classList.add('is-loading');

			return fetch(this.delete_url, {
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

						uncheckTableRows('service', response.error.keepids);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('service');
					}

					location.href = location.href;
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title, true, false)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		},

		pauseRefresh() {
			this.is_refresh_paused = true;
		},

		resumeRefresh() {
			this.is_refresh_paused = false;
		},

		refresh() {
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
					if ('errors' in response) {
						clearMessages();
						addMessage(response.errors);
					}
					else {
						if ('messages' in response) {
							clearMessages();
							addMessage(response.messages);
						}

						service_list.outerHTML = response.body;

						chkbxRange.init();
					}
				})
				.finally(() => {
					service_list.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');

					this.is_refresh_pending = false;
				});
		}
	};
</script>
