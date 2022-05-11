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
		time_period: null,
		dynamic: null,
		has_time_selector: null,
		is_busy: false,
		is_busy_saving: false,

		init({dashboard, time_period, dynamic, has_time_selector, widget_defaults, web_layout_mode}) {
			this.dashboard = dashboard;
			this.time_period = time_period;
			this.dynamic = dynamic;
			this.has_time_selector = has_time_selector;

			timeControl.refreshPage = false;

			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBOARD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_GRID ?>'),
					navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>')
				},
				buttons: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>
					? {
						previous_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_PREVIOUS_PAGE?>'),
						next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_NEXT_PAGE ?>'),
						slideshow: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_TOGGLE_SLIDESHOW ?>')
					}
					: {
						previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE ?>'),
						next_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NEXT_PAGE ?>'),
						slideshow: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_TOGGLE_SLIDESHOW ?>')
					},
				data: {
					dashboardid: dashboard.dashboardid,
					name: dashboard.name,
					userid: dashboard.owner.id,
					templateid: null,
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
				is_editable: dashboard.can_edit_dashboards && dashboard.editable
					&& web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>,
				is_edit_mode: dashboard.dashboardid === null,
				can_edit_dashboards: dashboard.can_edit_dashboards,
				is_kiosk_mode: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>,
				time_period: time_period,
				dynamic_hostid: dynamic.host ? dynamic.host.id : null
			});

			for (const page of dashboard.pages) {
				for (const widget of page.widgets) {
					widget.fields = (typeof widget.fields === 'object') ? widget.fields : {};
					widget.configuration = (typeof widget.configuration === 'object') ? widget.configuration : {};
				}

				ZABBIX.Dashboard.addDashboardPage(page);
			}

			ZABBIX.Dashboard.activate();

			if (web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_EDIT, () => this.edit());
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_APPLY_PROPERTIES, this.events.applyProperties);

				if (dynamic.has_dynamic_widgets) {
					jQuery('#dynamic_hostid').on('change', this.events.dynamicHostChange);
				}

				if (dashboard.dashboardid === null) {
					this.edit();
					ZABBIX.Dashboard.editProperties();
				}
				else {
					document
						.getElementById('dashboard-edit')
						.addEventListener('click', () => {
							ZABBIX.Dashboard.setEditMode();
							this.edit();
						});
				}
			}

			if (dynamic.has_dynamic_widgets) {
				// Perform dynamic host switch when browser back/previous buttons are pressed.
				window.addEventListener('popstate', this.events.popState);
			}

			jqBlink.blink();
		},

		edit() {
			timeControl.disableAllSBox();

			if (this.dynamic.has_dynamic_widgets) {
				jQuery('#dynamic_hostid').off('change', this.events.dynamicHostChange);
			}

			document
				.querySelectorAll('.filter-space')
				.forEach((el) => {
					el.style.display = 'none';
				});

			clearMessages();

			document
				.querySelectorAll('#dashboard-control > li')
				.forEach((el) => {
					el.style.display = (el.nextElementSibling === null) ? '' : 'none';
				});

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
		},

		save() {
			clearMessages();

			this.is_busy_saving = true;
			this.updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			request_data.sharing = this.dashboard.sharing;

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.update');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(request_data)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('errors' in response) {
						throw {html_string: response.errors};
					}

					if ('redirect' in response) {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						this.disableNavigationWarning();

						location.replace(response.redirect);
					}
				})
				.catch((error) => {
					if (typeof error === 'object' && 'html_string' in error) {
						addMessage(error.html_string);
					}
					else {
						const message = this.dashboard.dashboardid === null
							? <?= json_encode(_('Failed to create dashboard')) ?>
							: <?= json_encode(_('Failed to update dashboard')) ?>;

						addMessage(makeMessageBox('bad', [], message, true, false));
					}
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

			curl.setArgument('action', 'dashboard.view');

			if (this.dashboard.dashboardid !== null) {
				curl.setArgument('dashboardid', this.dashboard.dashboardid);
			}
			else {
				curl.setArgument('cancel', '1');
			}

			location.replace(curl.getUrl());
		},

		enableNavigationWarning() {
			window.addEventListener('beforeunload', this.events.beforeUnload, {passive: false});
		},

		disableNavigationWarning() {
			window.removeEventListener('beforeunload', this.events.beforeUnload);
		},

		editHost(hostid) {
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;

			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
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

			popState(e) {
				const host = (e.state !== null && 'host' in e.state) ? e.state.host : null;

				jQuery('#dynamic_hostid').multiSelect('addData', host ? [host] : [], false);

				ZABBIX.Dashboard.setDynamicHost(host ? host.id : null);
			},

			dynamicHostChange() {
				const hosts = jQuery('#dynamic_hostid').multiSelect('getData');
				const host = hosts.length ? hosts[0] : null;
				const curl = new Curl('zabbix.php', false);

				curl.setArgument('action', 'dashboard.view');

				if (view.dashboard.dashboardid !== null) {
					curl.setArgument('dashboardid', view.dashboard.dashboardid);
				}

				if (view.has_time_selector) {
					curl.setArgument('from', view.time_period.from);
					curl.setArgument('to', view.time_period.to);
				}

				if (host !== null) {
					curl.setArgument('hostid', host.id);
				}

				ZABBIX.Dashboard.setDynamicHost(host ? host.id : null);

				history.pushState({host: host}, '', curl.getUrl());

				updateUserProfile('web.dashboard.hostid', host ? host.id : 1);
			},

			applyProperties() {
				const dashboard_data = ZABBIX.Dashboard.getData();

				document.getElementById('<?= ZBX_STYLE_PAGE_TITLE ?>').textContent = dashboard_data.name;
				document.getElementById('dashboard-direct-link').textContent = dashboard_data.name;
			},

			busy() {
				view.is_busy = true;
				view.updateBusy();
			},

			idle() {
				view.is_busy = false;
				view.updateBusy();
			},

			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			}
		}
	}
</script>
