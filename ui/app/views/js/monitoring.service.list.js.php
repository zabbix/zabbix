<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	const service_list = {
		mode_url: null,
		refresh_url: null,
		refresh_interval: null,
		is_refresh_paused: false,
		is_refresh_pending: false,

		init({mode_url, refresh_url, refresh_interval}) {
			this.mode_url = mode_url;
			this.refresh_url = refresh_url;
			this.refresh_interval = refresh_interval;

			this.initViewModeSwitcher();
			this.initTagFilter();
			this.initActionButtons();
			this.initRefresh();
		},

		initViewModeSwitcher() {
			for (const element of document.getElementsByName('list_mode')) {
				if (!element.checked) {
					element.addEventListener('click', (e) => {
						redirect(this.mode_url);
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

			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		},

		initActionButtons() {
			for (const element of document.querySelectorAll('.js-create-service, .js-add-child-service')) {
				let popup_options = {};

				if (element.dataset.serviceid !== 'undefined') {
					popup_options = {
						parent_serviceids: [element.dataset.serviceid]
					};
				}

				element.addEventListener('click', (e) => {
					PopUp('popup.service.edit', popup_options, 'service_edit', e.target);
				});
			}

			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-edit-service')) {
					PopUp('popup.service.edit', {serviceid: e.target.dataset.serviceid}, 'service_edit', e.target);
				}
				else if (e.target.classList.contains('js-remove-service')) {
					if (window.confirm(<?= json_encode(_('Delete selected service?')) ?>)) {
						const url_delete = new Curl('zabbix.php', false);

						url_delete.setArgument('action', 'service.delete');
						url_delete.setArgument('serviceids', [e.target.dataset.serviceid]);

						redirect(url_delete.getUrl(), 'post', 'sid', true, true);
					}
				}
			});
		},

		initRefresh() {
			setInterval(() => this.refresh(), this.refresh_interval);
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

			clearMessages();

			this.is_refresh_pending = true;

			service_list.classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');

			fetch(this.refresh_url)
				.then((response) => response.json())
				.then((response) => {
					if ('errors' in response) {
						throw {html_string: response.errors};
					}

					if ('messages' in response) {
						addMessage(response.messages);
					}

					service_list.outerHTML = response.body;

					chkbxRange.init();
				})
				.catch((error) => {
					let message_box;

					if (typeof error === 'object' && 'html_string' in error) {
						message_box =
							new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild;
					}
					else {
						const error = <?= json_encode(_('Unexpected server error.')) ?>;

						message_box = makeMessageBox('bad', [], error, true, false)[0];
					}

					addMessage(message_box);

					service_list.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');
				})
				.finally(() => {
					this.is_refresh_pending = false;
				});
		}
	};
</script>
