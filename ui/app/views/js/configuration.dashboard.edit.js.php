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
	function initializeView(dashboard, widget_defaults, time_period, page) {

		const init = () => {
			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBOARD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_GRID ?>'),
					navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE ?>'),
					next_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NEXT_PAGE ?>')
				},
				data: {
					dashboardid: dashboard.dashboardid,
					name: dashboard.name,
					userid: null,
					templateid: dashboard.templateid,
					display_period: dashboard.display_period,
					auto_start: dashboard.auto_start
				},
				max_dashboard_pages: <?= DASHBOARD_MAX_PAGES ?>,
				cell_width: 100 / <?= DASHBOARD_MAX_COLUMNS ?>,
				cell_height: 70,
				max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
				max_rows: <?= DASHBOARD_MAX_ROWS ?>,
				widget_min_rows: <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
				widget_max_rows: <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
				widget_defaults: widget_defaults,
				is_editable: true,
				is_edit_mode: true,
				can_edit_dashboards: true,
				is_kiosk_mode: false,
				time_period: time_period,
				dynamic_hostid: null
			});

			for (const page of dashboard.pages) {
				for (const widget of page.widgets) {
					widget.fields = (typeof widget.fields === 'object') ? widget.fields : {};
					widget.configuration = (typeof widget.configuration === 'object') ? widget.configuration : {};
				}

				ZABBIX.Dashboard.addDashboardPage(page);
			}

			ZABBIX.Dashboard.activate();

			document
				.getElementById('dashboard-config')
				.addEventListener('click', () => ZABBIX.Dashboard.editProperties());

			document
				.getElementById('dashboard-add-widget')
				.addEventListener('click', () => ZABBIX.Dashboard.addNewWidget());

			document
				.getElementById('dashboard-add')
				.addEventListener('click', events.addClick);

			document
				.getElementById('dashboard-save')
				.addEventListener('click', () => save());

			document
				.getElementById('dashboard-cancel')
				.addEventListener('click', (e) => {
					cancelEditing();
					e.preventDefault();
				}
			);

			ZABBIX.Dashboard.on(DASHBOARD_EVENT_BUSY, events.busy);
			ZABBIX.Dashboard.on(DASHBOARD_EVENT_IDLE, events.idle);

			enableNavigationWarning();

			if (dashboard.dashboardid === null) {
				ZABBIX.Dashboard.editProperties();
			}
		};

		const save = () => {
			clearMessages();

			is_busy_saving = true;
			updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			request_data.sharing = dashboard.sharing;

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'template.dashboard.update');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: urlEncodeData(request_data)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('errors' in response) {
						throw {html_string: response.errors};
					}

					if ('system-message-ok' in response) {
						postMessageOk(response['system-message-ok']);
					}

					disableNavigationWarning();
					cancelEditing();
				})
				.catch((error) => {
					if (typeof error === 'object' && 'html_string' in error) {
						addMessage(error.html_string);
					}
					else {
						const message = dashboard.dashboardid === null
							? t('Failed to create dashboard')
							: t('Failed to update dashboard');

						addMessage(makeMessageBox('bad', [], message, true, false));
					}
				})
				.finally(() => {
					is_busy_saving = false;
					updateBusy();
				});
		};

		const updateBusy = () => {
			document.getElementById('dashboard-save').disabled = is_busy || is_busy_saving;
		};

		const cancelEditing = () => {
			const curl = new Curl('zabbix.php', false);

			curl.setArgument('action', 'template.dashboard.list');
			curl.setArgument('templateid', dashboard.templateid);

			if (page !== null) {
				curl.setArgument('page', page);
			}

			location.replace(curl.getUrl());
		};

		const enableNavigationWarning = () => {
			window.addEventListener('beforeunload', events.beforeUnload, {passive: false});
		};

		const disableNavigationWarning = () => {
			window.removeEventListener('beforeunload', events.beforeUnload);
		};

		const events = {
			addClick: (e) => {
				const menu = [
					{
						items: [
							{
								label: t('Add widget'),
								clickCallback: () => ZABBIX.Dashboard.addNewWidget()
							},
							{
								label: t('Add page'),
								clickCallback: () => ZABBIX.Dashboard.addNewDashboardPage()
							}
						]
					},
					{
						items: [
							{
								label: t('Paste widget'),
								clickCallback: () => ZABBIX.Dashboard.pasteWidget(
									ZABBIX.Dashboard.getStoredWidgetDataCopy()
								),
								disabled: (ZABBIX.Dashboard.getStoredWidgetDataCopy() === null)
							},
							{
								label: t('Paste page'),
								clickCallback: () => ZABBIX.Dashboard.pasteDashboardPage(
									ZABBIX.Dashboard.getStoredDashboardPageDataCopy()
								),
								disabled: (ZABBIX.Dashboard.getStoredDashboardPageDataCopy() === null)
							}
						]
					}
				];

				$(e.target).menuPopup(menu, new jQuery.Event(e), {
					position: {
						of: e.target,
						my: 'left top',
						at: 'left bottom',
						within: '.wrapper'
					}
				});
			},

			beforeUnload: (e) => {
				if (ZABBIX.Dashboard.isUnsaved()) {
					// Display confirmation message.
					e.preventDefault();
					e.returnValue = '';
				}
			},

			busy: () => {
				is_busy = true;
				updateBusy();
			},

			idle: () => {
				is_busy = false;
				updateBusy();
			}
		};

		let is_busy = false;
		let is_busy_saving = false;

		init();
	}
</script>
