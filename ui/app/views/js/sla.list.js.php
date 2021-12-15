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

<script>
	const view = {
		list_update_url: null,
		list_delete_url: null,

		init({list_update_url, list_delete_url}) {
			this.list_update_url = list_update_url;
			this.list_delete_url = list_delete_url;

			this.initTagFilter();
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

		edit(options = {}) {
			const overlay = PopUp('popup.sla.edit', options, 'sla_edit', document.activeElement);

			overlay.$dialogue[0].classList.add('sticked-to-top');

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if (e.detail.messages !== null) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		},

		massEnable(button) {
			if (!confirm(button.getAttribute('confirm'))) {
				return false;
			}

			this.massToggle(<?= CSlaHelper::SLA_STATUS_ENABLED?>)
		},

		massDisable(button) {
			if (!confirm(button.getAttribute('confirm'))) {
				return false;
			}

			this.massToggle(<?= CSlaHelper::SLA_STATUS_DISABLED?>)
		},

		massDelete(button) {
			if (!confirm(button.getAttribute('confirm'))) {
				return false;
			}

			this.massProcess(this.list_delete_url)
		},

		massToggle(status) {
			this.massProcess(this.list_update_url, {status});
		},

		massProcess(endpoint_url, data = {}) {
			const record_list = document.getElementById('sla-list');

			record_list.classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');

			data = Object.assign(data, {
				slaids: Object.values(chkbxRange.getSelectedIds()),
				sid: document.querySelector('meta[name="csrf-token"]').content
			});

			fetch(endpoint_url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					if ('error' in response) {
						uncheckTableRows('sla', ('keepids' in response.error) ? response.error.keepids : []);

						const message_box = makeMessageBox('bad', [], response.error.title)[0];
						addMessage(message_box);

						if ('messages' in response.error) {
							addMessage(response.messages);
						}
					}
					else {
						if ('success' in response) {
							uncheckTableRows('sla', ('keepids' in response.success) ? response.success.keepids : []);

							postMessageOk(response.success.title);

							if ('messages' in response.success) {
								postMessageDetails('success', response.success.messages);
							}

							location.href = location.href;
							return true;
						}

						if ('messages' in response) {
							addMessage(response.messages);
						}
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					record_list.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');
				});
		}
	};
</script>
