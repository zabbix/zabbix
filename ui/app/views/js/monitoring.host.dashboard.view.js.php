<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	function initializeHostDashboard(host, data, widget_defaults, web_layout_mode) {
		// Prevent page reloading on time selector events.
		timeControl.refreshPage = false;

		$('.<?= ZBX_STYLE_DASHBRD_GRID_CONTAINER ?>')
			.dashboardGrid({
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
					'kioskmode': (web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>)
				}
			})
			.dashboardGrid('setWidgetDefaults', widget_defaults)
			.dashboardGrid('addWidgets', data.widgets);

		jqBlink.blink();
	}
</script>
