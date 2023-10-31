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
		is_busy: false,
		is_busy_saving: false,

		skip_time_selector_range_update: false,

		init({
			dashboard,
			widget_defaults,
			widget_last_type,
			configuration_hash,
			broadcast_requirements,
			dashboard_host,
			dashboard_time_period,
			web_layout_mode,
			clone
		}) {
			this.dashboard = dashboard;
			this.broadcast_requirements = broadcast_requirements;
			this.dashboard_time_period = dashboard_time_period;
			this.clone = clone;

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
						previous_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_PREVIOUS_PAGE ?>'),
						next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_NEXT_PAGE ?>'),
						slideshow: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_TOGGLE_SLIDESHOW ?>')
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
				cell_height: <?= DASHBOARD_ROW_HEIGHT ?>,
				max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
				max_rows: <?= DASHBOARD_MAX_ROWS ?>,
				widget_min_rows: <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
				widget_max_rows: <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
				widget_defaults,
				widget_last_type,
				configuration_hash,
				is_editable: dashboard.can_edit_dashboards && dashboard.editable
					&& web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>,
				is_edit_mode: dashboard.dashboardid === null || clone,
				can_edit_dashboards: dashboard.can_edit_dashboards,
				is_kiosk_mode: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>,
				broadcast_options: {
					_hostid: {rebroadcast: false},
					_timeperiod: {rebroadcast: true}
				},
				csrf_token: <?= json_encode(CCsrfTokenHelper::get('dashboard')) ?>
			});

			for (const page of dashboard.pages) {
				for (const widget of page.widgets) {
					widget.fields = Object.keys(widget.fields).length > 0 ? widget.fields : {};
				}

				ZABBIX.Dashboard.addDashboardPage(page);
			}

			ZABBIX.Dashboard.broadcast({
				_hostid: dashboard_host !== null ? dashboard_host.id : null,
				_timeperiod: {
					from: dashboard_time_period.from,
					from_ts: dashboard_time_period.from_ts,
					to: dashboard_time_period.to,
					to_ts: dashboard_time_period.to_ts
				}
			});

			ZABBIX.Dashboard.activate();

			if (web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_EDIT, () => this.edit());
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_APPLY_PROPERTIES, this.events.applyProperties);

				if ('_hostid' in broadcast_requirements) {
					jQuery('#dashboard_hostid').on('change', this.events.dashboardHostChange);
				}

				if (dashboard.dashboardid === null || clone) {
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

			ZABBIX.Dashboard.on(CDashboard.EVENT_FEEDBACK, this.events.feedback);
			ZABBIX.Dashboard.on(DASHBOARD_EVENT_CONFIGURATION_OUTDATED, this.events.configurationOutdated);

			if ('_hostid' in broadcast_requirements) {
				// Perform dynamic host switch when browser back/previous buttons are pressed.
				window.addEventListener('popstate', this.events.popState);
			}

			if ('_timeperiod' in broadcast_requirements) {
				jQuery.subscribe('timeselector.rangeupdate', this.events.timeSelectorRangeUpdate);
			}

			jqBlink.blink();
		},

		edit() {
			timeControl.disableAllSBox();

			if ('_hostid' in this.broadcast_requirements) {
				jQuery('#dashboard_hostid').off('change', this.events.dashboardHostChange);
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
			this.is_busy_saving = true;
			this.updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			request_data.sharing = this.dashboard.sharing;

			if (this.clone) {
				request_data.clone = '1';
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.update');
			curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('dashboard')) ?>
			);

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

					const curl = new Curl('zabbix.php');

					curl.setArgument('action', 'dashboard.view');
					curl.setArgument('dashboardid', response.dashboardid);

					const dashboard_page_index = ZABBIX.Dashboard.getDashboardPageIndex(
						ZABBIX.Dashboard.getSelectedDashboardPage()
					);

					if (dashboard_page_index > 0) {
						curl.setArgument('page', dashboard_page_index + 1);
					}

					location.replace(curl.getUrl());
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
						title = this.dashboard.dashboardid === null || this.clone
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

		editItem(target, data) {
			const overlay = PopUp('item.edit', data, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		editHost(hostid) {
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;

			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		editTemplate(parameters) {
			const overlay = PopUp('template.edit', parameters, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		editTrigger(trigger_data) {
			const overlay = PopUp('trigger.edit', trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
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
								disabled: ZABBIX.Dashboard.getStoredWidgetDataCopy() === null
							},
							{
								label: <?= json_encode(_('Paste page')) ?>,
								clickCallback: () => ZABBIX.Dashboard.pasteDashboardPage(
									ZABBIX.Dashboard.getStoredDashboardPageDataCopy()
								),
								disabled: ZABBIX.Dashboard.getStoredDashboardPageDataCopy() === null
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

				jQuery('#dashboard_hostid').multiSelect('addData', host ? [host] : [], false);

				ZABBIX.Dashboard.broadcast({_hostid: host !== null ? host.id : null});
			},

			dashboardHostChange() {
				const hosts = jQuery('#dashboard_hostid').multiSelect('getData');
				const host = hosts.length ? hosts[0] : null;
				const curl = new Curl('zabbix.php');

				curl.setArgument('action', 'dashboard.view');

				if (view.dashboard.dashboardid !== null) {
					curl.setArgument('dashboardid', view.dashboard.dashboardid);
				}

				if ('_timeperiod' in view.broadcast_requirements) {
					curl.setArgument('from', view.dashboard_time_period.from);
					curl.setArgument('to', view.dashboard_time_period.to);
				}

				if (host !== null) {
					curl.setArgument('hostid', host.id);
				}

				history.pushState({host: host}, '', curl.getUrl());

				ZABBIX.Dashboard.broadcast({_hostid: host !== null ? host.id : null});

				updateUserProfile('web.dashboard.hostid', host !== null ? host.id : 1, []);
			},

			applyProperties() {
				const dashboard_data = ZABBIX.Dashboard.getData();

				document.getElementById('<?= CHtmlPage::PAGE_TITLE_ID ?>').textContent = dashboard_data.name;
				document.getElementById('dashboard-direct-link').textContent = dashboard_data.name;
			},

			configurationOutdated() {
				location.href = location.href;
			},

			busy() {
				view.is_busy = true;
				view.updateBusy();
			},

			idle() {
				view.is_busy = false;
				view.updateBusy();
			},

			elementSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			timeSelectorRangeUpdate: (e, data) => {
				view.dashboard_time_period = data;

				if (view.skip_time_selector_range_update) {
					view.skip_time_selector_range_update = false;

					return;
				}

				ZABBIX.Dashboard.broadcast({
					_timeperiod: {
						from: data.from,
						from_ts: data.from_ts,
						to: data.to,
						to_ts: data.to_ts
					}
				});
			},

			feedback(e) {
				if (e.detail.type === '_timeperiod') {
					view.skip_time_selector_range_update = true;

					$.publish('timeselector.rangechange', {
						from: e.detail.value.from,
						to: e.detail.value.to
					});
				}
			}
		}
	}
</script>
