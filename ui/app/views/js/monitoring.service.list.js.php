<?php declare(strict_types = 1);
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
		serviceid: null,
		mode_switch_url: null,
		refresh_url: null,
		refresh_interval: null,
		back_url: null,
		is_refresh_paused: false,
		is_refresh_pending: false,

		init({serviceid, mode_switch_url, refresh_url, refresh_interval, back_url = null}) {
			this.serviceid = serviceid;
			this.mode_switch_url = mode_switch_url;
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

			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		},

		initActionButtons() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-create-service')) {
					this.edit();
				}
				else if (e.target.classList.contains('js-add-child-service')) {
					this.edit({parent_serviceids: [e.target.dataset.serviceid]});
				}
				else if (e.target.classList.contains('js-edit-service')) {
					this.edit({serviceid: e.target.dataset.serviceid});
				}
				else if (e.target.classList.contains('js-remove-service')) {
					if (window.confirm(<?= json_encode(_('Delete selected service?')) ?>)) {
						const curl = new Curl('zabbix.php', false);

						curl.setArgument('action', 'service.delete');
						curl.setArgument('serviceids', [e.target.dataset.serviceid]);
						curl.setArgument('back_url', this.back_url);

						redirect(curl.getUrl(), 'post');
					}
				}
				else if (e.target.classList.contains('js-massupdate-service')) {
					openMassupdatePopup(e.target, 'popup.massupdate.service', {location_url: this.back_url});
				}
			});
		},

		initRefresh() {
			if (this.refresh_interval > 0) {
				setInterval(() => this.refresh(), this.refresh_interval);
			}
		},

		edit(options = {}) {
			this.pauseRefresh();

			const overlay = PopUp('popup.service.edit', options, 'service_edit', document.activeElement);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if (e.detail.messages !== null) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});

			overlay.$dialogue[0].addEventListener('overlay.close', () => this.resumeRefresh(), {once: true});
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
