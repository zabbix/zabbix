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

<script>
	function initializeView(host, data, widget_defaults, web_layout_mode) {

		const init = () => {
			// Prevent page reloading on time selector events.
			timeControl.refreshPage = false;

			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBRD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBRD_GRID ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBRD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBRD_PREVIOUS_PAGE ?>'),
					next_page: document.querySelector('.<?= ZBX_STYLE_DASHBRD_NEXT_PAGE ?>'),
					slideshow: document.querySelector('.<?= ZBX_STYLE_DASHBRD_TOGGLE_SLIDESHOW ?>')
				},
				dashboard: {
					templateid: data.templateid,
					dashboardid: data.dashboardid,
					dynamic_hostid: host.hostid
				},
				options: {
					'widget-height': 70,
					'max-rows': <?= DASHBOARD_MAX_ROWS ?>,
					'max-columns': <?= DASHBOARD_MAX_COLUMNS ?>,
					'widget-min-rows': <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
					'widget-max-rows': <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
					'editable': false,
					'edit_mode': false,
					'kioskmode': (web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>),
					'allowed_edit': true
				}
			});

			ZABBIX.Dashboard.setWidgetDefaults(widget_defaults);
			ZABBIX.Dashboard.addPages(data.pages);
			ZABBIX.Dashboard.activate();

			jqBlink.blink();

			if (web_layout_mode == <?= ZBX_LAYOUT_NORMAL ?>) {
				document.getElementById('dashboardid').addEventListener('change', events.dashboardChange);
			}
		};

		const events = {
			dashboardChange: (e) => {
				e.target.closest('form').submit();
			}
		};

		init();
	}
</script>
