<?php
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
		dashboard: null,
		page: null,
		is_busy: false,
		is_busy_saving: false,

		init({dashboard, widget_defaults, time_period, page}) {
			this.dashboard = dashboard;
			this.page = page;

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
				.addEventListener('click', this.events.addClick);

			document
				.getElementById('dashboard-save')
				.addEventListener('click', () => this.save());

			document
				.getElementById('dashboard-cancel')
				.addEventListener('click', (e) => {
					this.cancelEditing();
					e.preventDefault();
				}
			);

			ZABBIX.Dashboard.on(DASHBOARD_EVENT_BUSY, this.events.busy);
			ZABBIX.Dashboard.on(DASHBOARD_EVENT_IDLE, this.events.idle);

			this.enableNavigationWarning();

			if (dashboard.dashboardid === null) {
				ZABBIX.Dashboard.editProperties();
			}
		},

		save() {
			this.is_busy_saving = true;
			this.updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'template.dashboard.update');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(request_data)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						throw {error: response.error};
					}

					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					this.disableNavigationWarning();
					this.cancelEditing();
				})
				.catch((exception) => {
					clearMessages();

					let title;
					let messages = [];

					if (typeof exception === 'object' && 'error' in exception) {
						title = exception.error.title;
						messages = exception.error.messages;
					}
					else {
						title = this.dashboard.dashboardid === null
							? <?= json_encode(_('Failed to create dashboard')) ?>
							: <?= json_encode(_('Failed to update dashboard')) ?>;
					}

					const message_box = makeMessageBox('bad', messages, title);

					addMessage(message_box);
				})
				.finally(() => {
					this.is_busy_saving = false;
					this.updateBusy();
				});
		},

		updateBusy() {
			document.getElementById('dashboard-save').disabled = this.is_busy || this.is_busy_saving;
		},

		cancelEditing() {
			const curl = new Curl('zabbix.php', false);

			curl.setArgument('action', 'template.dashboard.list');
			curl.setArgument('templateid', this.dashboard.templateid);

			if (this.page !== null) {
				curl.setArgument('page', this.page);
			}

			location.replace(curl.getUrl());
		},

		enableNavigationWarning() {
			window.addEventListener('beforeunload', this.events.beforeUnload, {passive: false});
		},

		disableNavigationWarning() {
			window.removeEventListener('beforeunload', this.events.beforeUnload);
		},

		events: {
			addClick(e) {
				const menu = [
					{
						items: [
							{
								label: <?= json_encode(_('Add widget')) ?>,
								clickCallback: () => ZABBIX.Dashboard.addNewWidget()
							},
							{
								label: <?= json_encode(_('Add page')) ?>,
								clickCallback: () => ZABBIX.Dashboard.addNewDashboardPage()
							}
						]
					},
					{
						items: [
							{
								label: <?= json_encode(_('Paste widget')) ?>,
								clickCallback: () => ZABBIX.Dashboard.pasteWidget(
									ZABBIX.Dashboard.getStoredWidgetDataCopy()
								),
								disabled: (ZABBIX.Dashboard.getStoredWidgetDataCopy() === null)
							},
							{
								label: <?= json_encode(_('Paste page')) ?>,
								clickCallback: () => ZABBIX.Dashboard.pasteDashboardPage(
									ZABBIX.Dashboard.getStoredDashboardPageDataCopy()
								),
								disabled: (ZABBIX.Dashboard.getStoredDashboardPageDataCopy() === null)
							}
						]
					}
				];

				jQuery(e.target).menuPopup(menu, new jQuery.Event(e), {
					position: {
						of: e.target,
						my: 'left top',
						at: 'left bottom',
						within: '.wrapper'
					}
				});
			},

			beforeUnload(e) {
				if (ZABBIX.Dashboard.isUnsaved()) {
					// Display confirmation message.
					e.preventDefault();
					e.returnValue = '';
				}
			},

			busy() {
				view.is_busy = true;
				view.updateBusy();
			},

			idle() {
				view.is_busy = false;
				view.updateBusy();
			}
		}
	}
</script>
