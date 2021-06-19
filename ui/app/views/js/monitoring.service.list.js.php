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
	function initializeView(serviceid, page) {

		const init = () => {
			initViewModeSwitcher();
			initTagFilter();
			initActionButtons();
		}

		const initViewModeSwitcher = () => {
			for (const element of document.getElementsByName('list_mode')) {
				if (!element.checked) {
					element.addEventListener('click', (e) => {
						const url = new Curl('zabbix.php', false);

						url.setArgument('action', (e.target.value == <?= ZBX_LIST_MODE_VIEW ?>)
							? 'service.list'
							: 'service.list.edit'
						);

						if (serviceid !== null) {
							url.setArgument('serviceid', serviceid);
						}

						if (page !== null) {
							url.setArgument('page', page);
						}

						redirect(url.getUrl());
					});
				}
			}
		}

		const initTagFilter = () => {
			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		}

		const initActionButtons = () => {
			for (const element of document.querySelectorAll('.js-create-service, .js-add-child-service')) {
				let popup_options = {};

				if (element.dataset.serviceid !== 'undefined') {
					popup_options = {
						parent_serviceids: [element.dataset.serviceid]
					};
				}

				element.addEventListener('click', (e) => {
					PopUp('popup.service.edit', popup_options, null, e.target);
				});
			}

			for (const element of document.querySelectorAll('.js-edit-service')) {
				element.addEventListener('click', (e) => {
					PopUp('popup.service.edit', {serviceid: element.dataset.serviceid}, null, e.target);
				});
			}

			for (const element of document.querySelectorAll('.js-remove-service')) {
				element.addEventListener('click', (e) => {
					if (window.confirm(<?= json_encode(_('Delete selected service?')) ?>)) {
						const url_delete = new Curl('zabbix.php', false);

						url_delete.setArgument('action', 'service.delete');
						url_delete.setArgument('serviceids', [element.dataset.serviceid]);

						redirect(url_delete.getUrl(), 'post', 'sid', true, true);
					}
				});
			}
		}

		init();
	}
</script>
