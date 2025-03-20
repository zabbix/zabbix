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
	const view = new class {
		/**
		 * @type {Object}
		 */
		#dashboard;

		/**
		 * @type {Object}
		 */
		#broadcast_requirements;

		/**
		 * @type {Object}
		 */
		#dashboard_time_period;

		/**
		 * @type {boolean}
		 */
		#clone;

		/**
		 * @type {boolean}
		 */
		#is_busy = false;

		/**
		 * @type {boolean}
		 */
		#is_busy_saving = false;

		/**
		 * @type {boolean}
		 */
		#skip_time_selector_range_update = false;

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
			this.#dashboard = dashboard;
			this.#broadcast_requirements = broadcast_requirements;
			this.#dashboard_time_period = dashboard_time_period;
			this.#clone = clone;

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
				widget_defaults,
				widget_last_type,
				configuration_hash,
				is_editable: dashboard.can_edit_dashboards && dashboard.editable
					&& web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>,
				is_edit_mode: dashboard.dashboardid === null || clone,
				can_edit_dashboards: dashboard.can_edit_dashboards,
				is_kiosk_mode: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>,
				broadcast_options: {
					[CWidgetsData.DATA_TYPE_HOST_ID]: {rebroadcast: false},
					[CWidgetsData.DATA_TYPE_HOST_IDS]: {rebroadcast: false},
					[CWidgetsData.DATA_TYPE_TIME_PERIOD]: {rebroadcast: true}
				},
				csrf_token: <?= json_encode(CCsrfTokenHelper::get('dashboard')) ?>
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
				[CWidgetsData.DATA_TYPE_HOST_ID]: dashboard_host !== null
					? [dashboard_host.id]
					: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_ID),
				[CWidgetsData.DATA_TYPE_HOST_IDS]: dashboard_host !== null
					? [dashboard_host.id]
					: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_IDS),
				[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
			});

			ZABBIX.Dashboard.activate();

			if (web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_EDIT, () => this.#edit());
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_APPLY_PROPERTIES, () => this.#applyProperties());

				if (CWidgetsData.DATA_TYPE_HOST_ID in broadcast_requirements
						|| CWidgetsData.DATA_TYPE_HOST_IDS in broadcast_requirements) {
					jQuery('#dashboard_hostid').on('change', this.#listeners.onDashboardHostChange);

					window.addEventListener('popstate', e => this.#onPopState(e));
				}

				if (CWidgetsData.DATA_TYPE_TIME_PERIOD in broadcast_requirements) {
					jQuery.subscribe('timeselector.rangeupdate', (e, data) => this.#onTimeSelectorRangeUpdate(e, data));
				}

				if (dashboard.dashboardid === null || clone) {
					this.#edit();
					ZABBIX.Dashboard.editProperties();
				}
				else {
					document
						.getElementById('dashboard-edit')
						.addEventListener('click', () => {
							ZABBIX.Dashboard.setEditMode();
							this.#edit();
						});

					this.#updateHistory(false);
				}
			}

			ZABBIX.Dashboard.on(CDashboard.EVENT_FEEDBACK, e => this.#onFeedback(e));

			ZABBIX.Dashboard.on(DASHBOARD_EVENT_CONFIGURATION_OUTDATED, () => {
				location.href = location.href;
			});

			jqBlink.blink();
		}

		#edit() {
			timeControl.disableAllSBox();

			if (CWidgetsData.DATA_TYPE_HOST_ID in this.#broadcast_requirements
					|| CWidgetsData.DATA_TYPE_HOST_IDS in this.#broadcast_requirements) {
				jQuery('#dashboard_hostid').off('change', this.#listeners.onDashboardHostChange);
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
				.addEventListener('click', e => this.#onAddClick(e));

			document
				.getElementById('dashboard-save')
				.addEventListener('click', () => this.#save());

			document
				.getElementById('dashboard-cancel')
				.addEventListener('click', e => {
					this.#cancelEditing();
					e.preventDefault();
				});

			ZABBIX.Dashboard.on(DASHBOARD_EVENT_BUSY, () => {
				this.#is_busy = true;
				this.#updateBusy();
			});

			ZABBIX.Dashboard.on(DASHBOARD_EVENT_IDLE, () => {
				this.#is_busy = false;
				this.#updateBusy();
			});

			this.#enableNavigationWarning();
		}

		#save() {
			this.#is_busy_saving = true;
			this.#updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			request_data.sharing = this.#dashboard.sharing;

			if (this.#clone) {
				request_data.clone = '1';
			}

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.update');
			curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('dashboard')) ?>);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(request_data)
			})
				.then(response => response.json())
				.then(response => {
					if ('error' in response) {
						throw {error: response.error};
					}

					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					this.#disableNavigationWarning();

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
						title = this.#dashboard.dashboardid === null || this.#clone
							? <?= json_encode(_('Failed to create dashboard')) ?>
							: <?= json_encode(_('Failed to update dashboard')) ?>;
					}

					const message_box = makeMessageBox('bad', messages, title);

					addMessage(message_box);
				})
				.finally(() => {
					this.#is_busy_saving = false;
					this.#updateBusy();
				});
		}

		#applyProperties() {
			const dashboard_data = ZABBIX.Dashboard.getData();

			document.getElementById('<?= CHtmlPage::PAGE_TITLE_ID ?>').textContent = dashboard_data.name;
			document.getElementById('dashboard-direct-link').textContent = dashboard_data.name;
		}

		#cancelEditing() {
			this.#disableNavigationWarning();

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.view');

			if (this.#dashboard.dashboardid !== null) {
				curl.setArgument('dashboardid', this.#dashboard.dashboardid);
			}
			else {
				curl.setArgument('cancel', '1');
			}

			location.replace(curl.getUrl());
		}

		#updateBusy() {
			document.getElementById('dashboard-save').disabled = this.#is_busy || this.#is_busy_saving;
		}

		#updateHistory(is_new = true)  {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.view');
			curl.setArgument('dashboardid', this.#dashboard.dashboardid);

			const state = {};

			if (CWidgetsData.DATA_TYPE_HOST_ID in this.#broadcast_requirements
					|| CWidgetsData.DATA_TYPE_HOST_IDS in this.#broadcast_requirements) {
				const hosts = jQuery('#dashboard_hostid').multiSelect('getData');

				if (hosts.length > 0) {
					curl.setArgument('hostid', hosts[0].id);

					state.host = hosts[0];
				}
			}

			if (CWidgetsData.DATA_TYPE_TIME_PERIOD in this.#broadcast_requirements) {
				curl.setArgument('from', this.#dashboard_time_period.from);
				curl.setArgument('to', this.#dashboard_time_period.to);
			}

			const page = new Curl().getArgument('page');

			if (page !== null) {
				curl.setArgument('page', page);
			}

			if (is_new) {
				history.pushState(state, '', curl.getUrl());
			}
			else {
				history.replaceState(state, '', curl.getUrl());
			}
		}

		#enableNavigationWarning() {
			window.addEventListener('beforeunload', this.#listeners.onBeforeUnload, {passive: false});
		}

		#disableNavigationWarning() {
			window.removeEventListener('beforeunload', this.#listeners.onBeforeUnload);
		}

		#onAddClick(e) {
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
					my: 'right top',
					at: 'right bottom',
					within: '.wrapper',
					collision: 'fit flip'
				}
			});
		}

		#onPopState(e) {
			const host = (e.state !== null && 'host' in e.state) ? e.state.host : null;

			jQuery('#dashboard_hostid').multiSelect('addData', host ? [host] : [], false);

			this.#updateHistory(false);

			ZABBIX.Dashboard.broadcast({
				[CWidgetsData.DATA_TYPE_HOST_ID]: host !== null
					? [host.id]
					: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_ID),
				[CWidgetsData.DATA_TYPE_HOST_IDS]: host !== null
					? [host.id]
					: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_IDS)
			});
		}

		#onTimeSelectorRangeUpdate(e, data) {
			this.#dashboard_time_period = data;

			this.#updateHistory(false);

			if (this.#skip_time_selector_range_update) {
				this.#skip_time_selector_range_update = false;

				return;
			}

			const time_period = {
				from: data.from,
				from_ts: data.from_ts,
				to: data.to,
				to_ts: data.to_ts
			};

			CWidgetsData.setDefault(CWidgetsData.DATA_TYPE_TIME_PERIOD, time_period, {is_comparable: false});

			ZABBIX.Dashboard.broadcast({
				[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
			});
		}

		#onFeedback(e) {
			if (e.detail.type === CWidgetsData.DATA_TYPE_TIME_PERIOD && e.detail.value !== null) {
				this.#skip_time_selector_range_update = true;

				$.publish('timeselector.rangechange', {
					from: e.detail.value.from,
					to: e.detail.value.to
				});
			}
		}

		#listeners = {
			onDashboardHostChange: () => {
				this.#updateHistory();

				const hosts = jQuery('#dashboard_hostid').multiSelect('getData');
				const host = hosts.length > 0 ? hosts[0] : null;

				ZABBIX.Dashboard.broadcast({
					[CWidgetsData.DATA_TYPE_HOST_ID]: host !== null
						? [host.id]
						: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_ID),
					[CWidgetsData.DATA_TYPE_HOST_IDS]: host !== null
						? [host.id]
						: CWidgetsData.getDefault(CWidgetsData.DATA_TYPE_HOST_IDS)
				});

				updateUserProfile('web.dashboard.hostid', host !== null ? host.id : 1, []);
			},

			onBeforeUnload: e => {
				if (ZABBIX.Dashboard.isUnsaved()) {
					// Display confirmation message.
					e.preventDefault();
					e.returnValue = '';
				}
			}
		};

		executeNow(target, data) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'item.execute');

			data[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('item')) ?>;

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then(response => response.json())
				.then(response => {
					clearMessages();
					if (response.error) {
						addMessage(makeMessageBox('bad', response.error.messages, response.error.title))
					}
					else {
						addMessage(makeMessageBox('good', [], response.success.title))
					}
				})
				.catch(() => {
					clearMessages();
					addMessage(makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]));
				});
		}
	}
</script>
