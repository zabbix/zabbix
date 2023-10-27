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
	const view = new class {

		/**
		 * Current host ID.
		 * @type {string}
		 */
		#hostid;

		/**
		 * Selected dashboard ID.
		 * @type {string}
		 */
		#dashboardid;

		/**
		 * @tape {Object}
		 */
		#host_dashboards;

		/**
		 * @type {Map}
		 */
		#host_dashboard_tabs = new Map();

		/**
		 * @type {CSortable}
		 */
		#dashboard_tabs;

		/**
		 * @type {HTMLElement|null}
		 */
		#selected_dashboard_tab = null;

		/**
		 * @type {HTMLElement}
		 */
		#host_dashboard_navigation_tabs

		/**
		 * @type {HTMLElement}
		 */
		#previous_dashboard;

		/**
		 * @type {HTMLElement}
		 */
		#next_dashboard;

		/**
		 * @type {boolean}
		 */
		#skip_time_selector_range_update = false;

		init({
			host_dashboards,
			dashboard,
			widget_defaults,
			configuration_hash,
			broadcast_requirements,
			dashboard_host,
			dashboard_time_period,
			web_layout_mode
		}) {
			this.#hostid = dashboard_host.hostid;
			this.#dashboardid = dashboard.dashboardid;

			if (dashboard.pages.length > 1 || (dashboard.pages.length === 1 && dashboard.pages[0].widgets.length > 0)) {
				timeControl.refreshPage = false;

				ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBOARD ?>'), {
					containers: {
						grid: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_GRID ?>'),
						navigation: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION ?>'),
						navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>')
					},
					buttons: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>
						? {
							previous_page: document
								.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_PREVIOUS_PAGE?>'),
							next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_NEXT_PAGE ?>'),
							slideshow: document
								.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_TOGGLE_SLIDESHOW ?>')
						}
						: {
							previous_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_PREVIOUS_PAGE ?>'),
							next_page: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_NEXT_PAGE ?>'),
							slideshow: document.querySelector('.<?= ZBX_STYLE_BTN_DASHBOARD_TOGGLE_SLIDESHOW ?>')
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
					widget_min_rows: <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
					widget_max_rows: <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
					widget_defaults,
					configuration_hash,
					is_editable: false,
					is_edit_mode: false,
					can_edit_dashboards: false,
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
					_hostid: dashboard_host.hostid,
					_timeperiod: {
						from: dashboard_time_period.from,
						from_ts: dashboard_time_period.from_ts,
						to: dashboard_time_period.to,
						to_ts: dashboard_time_period.to_ts
					}
				});

				ZABBIX.Dashboard.activate();

				ZABBIX.Dashboard.on(CDashboard.EVENT_FEEDBACK, (e) => this.#onFeedback(e));

				ZABBIX.Dashboard.on(DASHBOARD_EVENT_CONFIGURATION_OUTDATED, () => {
					location.href = location.href;
				});
			}

			if ('_timeperiod' in broadcast_requirements) {
				jQuery.subscribe('timeselector.rangeupdate', (e, data) => this.#onTimeSelectorRangeUpdate(data));
			}

			jqBlink.blink();

			if (web_layout_mode == <?= ZBX_LAYOUT_NORMAL ?>) {
				this.#host_dashboards = host_dashboards;
				this.#host_dashboard_navigation_tabs = document
					.querySelector('.<?= ZBX_STYLE_HOST_DASHBOARD_NAVIGATION_TABS ?>');
				this.#previous_dashboard = document
					.querySelector('.<?= ZBX_STYLE_BTN_HOST_DASHBOARD_PREVIOUS_DASHBOARD ?>');
				this.#next_dashboard = document.querySelector('.<?= ZBX_STYLE_BTN_HOST_DASHBOARD_NEXT_DASHBOARD ?>');

				this.#activateHostDashboardNavigation();
				this.#addHostDashboardTabs();
				this.#addEventListeners();
			}

			this.#registerEvents();
		}

		#activateHostDashboardNavigation() {
			const sortable_element = document.createElement('div');
			this.#host_dashboard_navigation_tabs.appendChild(sortable_element);
			this.#dashboard_tabs = new CSortable(sortable_element, {
				is_vertical: false,
				is_sorting_enabled: false
			});
		}

		#addHostDashboardTabs() {
			for (const host_dashboard of this.#host_dashboards) {
				const url = new Curl('zabbix.php');
				url.setArgument('action', 'host.dashboard.view');
				url.setArgument('hostid', this.#hostid);
				url.setArgument('dashboardid', host_dashboard.dashboardid);

				host_dashboard.link = url.getUrl();

				const tab = document.createElement('li');
				const tab_contents = document.createElement('div');
				const tab_contents_name = document.createElement('span');

				tab.appendChild(tab_contents);
				tab_contents.appendChild(tab_contents_name);

				tab_contents_name.textContent = host_dashboard.name;
				tab_contents_name.title = host_dashboard.name;

				this.#dashboard_tabs.insertItemBefore(tab);
				this.#host_dashboard_tabs.set(host_dashboard.dashboardid, tab);
			}

			this.#selected_dashboard_tab = this.#host_dashboard_tabs.get(this.#dashboardid);
			this.#selected_dashboard_tab.firstElementChild.classList.add(ZBX_STYLE_DASHBOARD_SELECTED_TAB);
			this.#previous_dashboard.disabled = this.#selected_dashboard_tab.previousElementSibling === null;
			this.#next_dashboard.disabled = this.#selected_dashboard_tab.nextElementSibling === null;
			this.#dashboard_tabs.scrollItemIntoView(this.#selected_dashboard_tab);
		}

		#addEventListeners() {
			this.#host_dashboard_navigation_tabs.addEventListener('click', (e) => {
				this.#selectHostDashboardTab(e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`));
			});

			this.#host_dashboard_navigation_tabs.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					const dashboard_tab = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (dashboard_tab !== null && dashboard_tab !== this.#selected_dashboard_tab) {
						this.#selectHostDashboardTab(dashboard_tab);
					}
				}
			});

			this.#previous_dashboard.addEventListener('click', () => {
				const keys = [...this.#host_dashboard_tabs.keys()];
				const previous_dashboardid = keys[keys.indexOf(this.#dashboardid) - 1];

				this.#selectHostDashboardTab(this.#host_dashboard_tabs.get(previous_dashboardid));
			});

			this.#next_dashboard.addEventListener('click', () => {
				const keys = [...this.#host_dashboard_tabs.keys()];
				const next_dashboardid = keys[keys.indexOf(this.#dashboardid) + 1];

				this.#selectHostDashboardTab(this.#host_dashboard_tabs.get(next_dashboardid));
			});

			const host_dashboard_list = document.querySelector('.<?= ZBX_STYLE_BTN_HOST_DASHBOARD_LIST ?>');

			if (host_dashboard_list !== null) {
				host_dashboard_list.addEventListener('click', (e) => {
					let dropdown_items = [];
					let dropdown = [];

					for (const host_dashboard of this.#host_dashboards) {
						dropdown_items.push({
							label: host_dashboard.name,
							clickCallback: () => {
								window.location.href = host_dashboard.link;
							}
						});
					}

					dropdown.push({items: dropdown_items});

					jQuery(e.target).menuPopup(dropdown, new jQuery.Event(e), {
						position: {
							of: e.target,
							my: 'left bottom',
							at: 'left top'
						}
					});
				});
			}
		}

		#selectHostDashboardTab(dashboard_tab) {
			let selected_dashboardid = null;

			for (const [key, tab] of this.#host_dashboard_tabs.entries()) {
				if (tab === dashboard_tab) {
					selected_dashboardid = key;
					break;
				}
			}

			for (const host_dashboard of this.#host_dashboards) {
				if (host_dashboard.dashboardid === selected_dashboardid) {
					window.location.href = host_dashboard.link;
				}
			}
		}

		#onTimeSelectorRangeUpdate(data) {
			if (this.#skip_time_selector_range_update) {
				this.#skip_time_selector_range_update = false;

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
		}

		#onFeedback(e) {
			if (e.detail.type === '_timeperiod') {
				this.#skip_time_selector_range_update = true;

				$.publish('timeselector.rangechange', {
					from: e.detail.value.from,
					to: e.detail.value.to
				});
			}
		}

		editItem(target, data) {
			const overlay = PopUp('item.edit', data, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		}

		editHost(hostid) {
			const host_data = {hostid};

			this.#openHostPopup(host_data);
		}

		editTrigger(trigger_data) {
			const overlay = PopUp('trigger.edit', trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		}

		#openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.close', () => history.replaceState({}, '', original_url));
		}

		#registerEvents() {
			this.events = {
				elementSuccess(e) {
					let curl = null;
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}

						if ('action' in data.success && data.success.action === 'delete') {
							curl = new Curl('zabbix.php');
							curl.setArgument('action', 'host.view');
						}
					}
					else {
						postMessageError(data.error.title);

						if ('messages' in data.error) {
							postMessageDetails('error', data.error.messages);
						}
					}

					location.href = curl === null ? location.href : curl.getUrl();
				}
			};
		}
	}
</script>
