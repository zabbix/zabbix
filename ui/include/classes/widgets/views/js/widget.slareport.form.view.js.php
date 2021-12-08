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

window.widget_slareport = {

	show_periods_form_row: null,
	$services: null,

	init({serviceids_field_id}) {
		this.show_periods_form_row = document.getElementById('js-show_periods');

		this.$services = jQuery(`#${serviceids_field_id}_`);
		this.$services
			.on('change', this.events.updateServices)
			.multiSelect('getSelectButton').addEventListener('click', this.events.selectServices);

		this.events.updateServices();
	},

	events: {
		selectServices: () => {
			const exclude_serviceids = [];

			for (const service of widget_slareport.$services.multiSelect('getData')) {
				exclude_serviceids.push(service.id);
			}

			const overlay = PopUp('popup.services', {
				title: <?= json_encode(_('Add services')) ?>,
				exclude_serviceids
			}, 'services', document.activeElement);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const data = [];

				for (const service of e.detail) {
					data.push({id: service.serviceid, name: service.name});
				}

				widget_slareport.$services.multiSelect('addData', data);
			});
		},

		updateServices: () => {
			widget_slareport.show_periods_form_row.style.display =
				widget_slareport.$services.multiSelect('getData').length > 0 ? '' : 'none';
		}
	}
};
