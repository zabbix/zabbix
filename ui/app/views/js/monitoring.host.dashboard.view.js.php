<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
		init({host, dashboard, widget_defaults, configuration_hash, time_period, web_layout_mode, dashboard_tabs}) {
			timeControl.refreshPage = false;

			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBOARD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_GRID ?>'),
					navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>'),
					dashboard_navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_TABS_NAVIGATION ?>'),
					dashboard_navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_TABS_NAVIGATION_TABS ?>')
				},
				buttons: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>
					? {
						previous_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_PREVIOUS_PAGE?>'),
						next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_NEXT_PAGE ?>'),
						slideshow: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_TOGGLE_SLIDESHOW ?>')
					}
					: {
						previous_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_PREVIOUS_PAGE ?>'),
						next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_NEXT_PAGE ?>'),
						slideshow: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_TOGGLE_SLIDESHOW ?>'),
						previous_dashboard: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_PREVIOUS_DASHBOARD ?>'),
						next_dashboard: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_NEXT_DASHBOARD ?>'),
						dashboard_list: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_LIST ?>'),
					},
				data: {
					dashboardid: dashboard.dashboardid,
					name: dashboard.name,
					userid: null,
					templateid: dashboard.templateid,
					display_period: dashboard.display_period,
					auto_start: dashboard.auto_start,
					with_dashboard_tabs: true,
					dashboard_tabs: dashboard_tabs
				},
				max_dashboard_pages: <?= DASHBOARD_MAX_PAGES ?>,
				cell_width: 100 / <?= DASHBOARD_MAX_COLUMNS ?>,
				cell_height: 70,
				max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
				max_rows: <?= DASHBOARD_MAX_ROWS ?>,
				widget_min_rows: <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
				widget_max_rows: <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
				widget_defaults,
				configuration_hash,
				is_editable: false,
				is_edit_mode: false,
				can_edit_dashboards: false,
				is_kiosk_mode: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>,
				time_period: time_period,
				dynamic_hostid: host.hostid,
				csrf_token: <?= json_encode(CCsrfTokenHelper::get('dashboard')) ?>
			});

			if (web_layout_mode == <?= ZBX_LAYOUT_NORMAL ?>) {
				for (const dashboard_tab of dashboard_tabs) {
					const url = new Curl('zabbix.php');
					url.setArgument('action', 'host.dashboard.view');
					url.setArgument('hostid', host.hostid);
					url.setArgument('dashboardid', dashboard_tab.dashboardid);

					dashboard_tab.link = url.getUrl();

					ZABBIX.Dashboard.addDashboardTab(dashboard_tab);
				}
			}

			if (dashboard.pages.length === 1 && dashboard.pages[0].widgets.length === 0) {
				ZABBIX.Dashboard.activateDashboardTabs();
			}
			else {
				for (const page of dashboard.pages) {
					for (const widget of page.widgets) {
						widget.fields = (typeof widget.fields === 'object') ? widget.fields : {};
					}

					ZABBIX.Dashboard.addDashboardPage(page);
				}

				ZABBIX.Dashboard.activate();

				ZABBIX.Dashboard.on(DASHBOARD_EVENT_CONFIGURATION_OUTDATED, this.events.configurationOutdated);
			}

			jqBlink.blink();
		},

		events: {
			configurationOutdated() {
				location.href = location.href;
			}
		}
	}
</script>
