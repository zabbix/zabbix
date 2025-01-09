<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */
?>

<script>
	const view = {
		init({dashboard, widget_defaults, dashboard_time_period}) {
			timeControl.refreshPage = false;

			ZABBIX.Dashboard = new CDashboardPrint(document.querySelector('.wrapper'), {
				containers: {
					grid: document.querySelector('.wrapper'),
					navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: null,
					next_page: null,
					slideshow: null
				},
				data: {
					dashboardid: dashboard.dashboardid,
					name: dashboard.name,
					userid: null,
					templateid: null,
					display_period: dashboard.display_period,
					auto_start: false
				},
				max_dashboard_pages: <?= DASHBOARD_MAX_PAGES ?>,
				cell_width: 100 / <?= DASHBOARD_MAX_COLUMNS ?>,
				cell_height: <?= DASHBOARD_ROW_HEIGHT ?>,
				max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
				max_rows: <?= DASHBOARD_MAX_ROWS ?>,
				widget_defaults,
				is_editable: false,
				is_edit_mode: false,
				can_edit_dashboards: false,
				is_kiosk_mode: true,
				broadcast_options: {
					[CWidgetsData.DATA_TYPE_HOST_ID]: {rebroadcast: false},
					[CWidgetsData.DATA_TYPE_HOST_IDS]: {rebroadcast: false},
					[CWidgetsData.DATA_TYPE_TIME_PERIOD]: {rebroadcast: false}
				}
			});

			const dashboard_page_containers = document.querySelectorAll('.<?= ZBX_STYLE_DASHBOARD_GRID ?>');

			for (const [page_number, page] of dashboard.pages.entries()) {
				for (const widget of page.widgets) {
					widget.fields = Object.keys(widget.fields).length > 0 ? widget.fields : {};
				}

				ZABBIX.Dashboard.addDashboardPage(page, dashboard_page_containers[page_number]);
			}

			const time_period = {
				from: dashboard_time_period.from,
				from_ts: dashboard_time_period.from_ts,
				to: dashboard_time_period.to,
				to_ts: dashboard_time_period.to_ts
			};

			CWidgetsData.setDefault(CWidgetsData.DATA_TYPE_TIME_PERIOD, time_period, {is_comparable: false});

			ZABBIX.Dashboard.broadcast({
				[CWidgetsData.DATA_TYPE_HOST_ID]: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_ID),
				[CWidgetsData.DATA_TYPE_HOST_IDS]: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_IDS),
				[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
			});

			ZABBIX.Dashboard.activate();
		}
	}
</script>
