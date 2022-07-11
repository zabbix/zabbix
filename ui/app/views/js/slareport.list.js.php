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

<script>
	const view = {
		init() {
			jQuery('#filter_serviceid')
				.multiSelect('getSelectButton')
				.addEventListener('click', () => {
					this.selectService();
				});
		},

		selectService() {
			const overlay = PopUp('popup.services', {
				title: <?= json_encode(_('Service')) ?>,
				multiple: 0
			}, {dialogueid: 'services'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const data = [];

				for (const service of e.detail) {
					data.push({id: service.serviceid, name: service.name});
				}

				jQuery('#filter_serviceid').multiSelect('addData', data);
			});
		}
	};
</script>
