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
		is_busy: false,
		is_busy_saving: false,

		init({dashboard, widget_defaults, widget_last_type, dashboard_time_period, page}) {
			this.dashboard = dashboard;
			this.page = page;

			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBOARD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_GRID ?>'),
					navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_PREVIOUS_PAGE ?>'),
					next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_NEXT_PAGE ?>')
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
				cell_height: <?= DASHBOARD_ROW_HEIGHT ?>,
				max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
				max_rows: <?= DASHBOARD_MAX_ROWS ?>,
				widget_defaults,
				widget_last_type,
				is_editable: true,
				is_edit_mode: true,
				can_edit_dashboards: true,
				is_kiosk_mode: false,
				broadcast_options: {
					[CWidgetsData.DATA_TYPE_HOST_ID]: {rebroadcast: false},
					[CWidgetsData.DATA_TYPE_HOST_IDS]: {rebroadcast: false},
					[CWidgetsData.DATA_TYPE_TIME_PERIOD]: {rebroadcast: true}
				}
			});

			for (const page of dashboard.pages) {
				for (const widget of page.widgets) {
					widget.fields = Object.keys(widget.fields).length > 0 ? widget.fields : {};
				}

				ZABBIX.Dashboard.addDashboardPage(page);
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

			this.initPopupListeners();
		},

		save() {
			this.is_busy_saving = true;
			this.updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'template.dashboard.update');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('template')) ?>);

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
			this.disableNavigationWarning();

			const curl = new Curl('zabbix.php');

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

		initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					if (data.submit.success.action === 'delete') {
						const url = new URL('zabbix.php', location.href);

						url.searchParams.set('action', 'template.list');

						event.setRedirectUrl(url.href);
					}
				}
			});
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
						my: 'right top',
						at: 'right bottom',
						within: '.wrapper',
						collision: 'fit flip'
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
