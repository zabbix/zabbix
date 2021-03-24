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
	const DASHBOARD_EVENT_APPLY_PROPERTIES = 'apply_properties';

	function initializeView(data, widget_defaults, page) {

		const init = () => {
			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBOARD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_GRID ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE ?>'),
					next_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NEXT_PAGE ?>')
				},
				dashboard: {
					templateid: data.templateid,
					dashboardid: data.dashboardid,
					display_period: data.display_period,
					auto_start: data.auto_start
				},
				options: {
					'widget-height': 70,
					'max-rows': <?= DASHBOARD_MAX_ROWS ?>,
					'max-columns': <?= DASHBOARD_MAX_COLUMNS ?>,
					'widget-min-rows': <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
					'widget-max-rows': <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
					'editable': true,
					'edit_mode': true,
					'kioskmode': false,
					'allowed_edit': true
				}
			});

			ZABBIX.Dashboard.setWidgetDefaults(widget_defaults);
			ZABBIX.Dashboard.addPages(data.pages);
			ZABBIX.Dashboard.activate();

			document
				.getElementById('dashboard-config')
				.addEventListener('click', () => openProperties());

			document
				.getElementById('dashboard-add-widget')
				.addEventListener('click', (e) => ZABBIX.Dashboard.addNewWidget(e.target));

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

			$.subscribe('dashboard.grid.busy', (e, data) => {
				is_busy = data.state;
				updateBusy();
			});

			document.addEventListener(DASHBOARD_EVENT_APPLY_PROPERTIES, events.applyProperties);

			enableNavigationWarning();

			if (data.dashboardid === null) {
				openProperties();
			}
		};

		const save = () => {
			clearMessages();

			ZABBIX.Dashboard.saveDashboard();

			const ajax_data = {
				templateid: data.templateid,
				dashboardid: (data.dashboardid !== null) ? data.dashboardid : undefined,
				name: data.name,
				display_period: data.display_period,
				auto_start: data.auto_start,
				widgets: []
			};

			for (const widget of ZABBIX.Dashboard.getWidgets()) {
				const ajax_widget = {};

				if (widget.widgetid !== '') {
					ajax_widget.widgetid = widget.widgetid;
				}
				ajax_widget.pos = widget.pos;
				ajax_widget.type = widget.type;
				ajax_widget.name = widget.header;
				ajax_widget.view_mode = widget.view_mode;
				if (Object.keys(widget.fields).length != 0) {
					ajax_widget.fields = JSON.stringify(widget.fields);
				}

				ajax_data.widgets.push(ajax_widget);
			}

			is_busy_saving = true;
			updateBusy();

			const url = new Curl('zabbix.php');

			url.setArgument('action', 'template.dashboard.update');

			$.ajax({
				url: url.getUrl(),
				data: ajax_data,
				dataType: 'json',
				method: 'POST'
			})
				.always(() => {
					is_busy_saving = false;
					updateBusy();
				})
				.then((response) => {
					if ('errors' in response) {
						addMessage(response.errors);
					}
					else {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						disableNavigationWarning();
						cancelEditing();
					}
				});
		};

		const updateBusy = () => {
			document.getElementById('dashboard-save').disabled = is_busy || is_busy_saving;
		};

		const cancelEditing = () => {
			const url = new Curl('zabbix.php', false);

			url.setArgument('action', 'template.dashboard.list');
			url.setArgument('templateid', data.templateid);

			if (page !== null) {
				url.setArgument('page', page);
			}

			location.replace(url.getUrl());
		};

		const enableNavigationWarning = () => {
			window.addEventListener('beforeunload', events.beforeUnload);
		};

		const disableNavigationWarning = () => {
			window.removeEventListener('beforeunload', events.beforeUnload);
		};

		const openProperties = () => {
			const options = {
				template: '1',
				name: data.name,
				display_period: data.display_period,
				auto_start: data.auto_start
			};

			PopUp('dashboard.properties.edit', options, 'dashboard_properties', this);
		};

		const events = {
			addClick: (e) => {
				const menu = [
					{
						items: [
							{
								label: t('Add widget'),
								clickCallback: () => ZABBIX.Dashboard.addNewWidget(e.target)
							},
							{
								label: t('Add page'),
								clickCallback: () => ZABBIX.Dashboard.addNewPage(e.target),
								disabled: true
							}
						]
					},
					{
						items: [
							{
								label: t('Paste widget'),
								clickCallback: () => ZABBIX.Dashboard.pasteWidget(null, null),
								disabled: (ZABBIX.Dashboard.getCopiedWidget() === null)
							},
							{
								label: t('Paste page'),
								clickCallback: () => ZABBIX.Dashboard.pastePage(),
								disabled: true
							}
						]
					}
				];

				$(e.target).menuPopup(menu, new jQuery.Event(e), {
					position: {
						of: e.target,
						my: 'left top',
						at: 'left bottom'
					}
				});
			},

			beforeUnload: (e) => {
				if (has_properties_modified || ZABBIX.Dashboard.isDashboardUpdated()) {
					// Display confirmation message.
					e.preventDefault();
				}
			},

			applyProperties: (e) => {
				const overlay = e.detail.overlay;

				const form = overlay.$dialogue[0].querySelector('form');
				const form_data = {};

				new FormData(form).forEach((value, key) => form_data[key] = value);

				form_data.template = '1';
				form_data.name = form_data.name.trim();

				const url = new Curl('zabbix.php', false);

				url.setArgument('action', 'dashboard.properties.check');

				overlay.setLoading();

				overlay.xhr = $.ajax({
					data: form_data,
					url: url.getUrl(),
					dataType: 'json',
					method: 'POST'
				});

				overlay.xhr
					.always(() => overlay.unsetLoading())
					.done((response) => {
						for (const el of form.parentNode.children) {
							if (el.matches('.msg-good, .msg-bad')) {
								el.parentNode.removeChild(el);
							}
						}

						if ('errors' in response) {
							const errors = document.createElement('div');
							errors.innerHTML = response.errors;
							for (const el of errors.children) {
								form.parentNode.insertBefore(el, form);
							}
						}
						else {
							const properties = {
								name: form_data.name,
								display_period: form_data.display_period,
								auto_start: (form_data.auto_start === '1') ? '1' : '0'
							};

							has_properties_modified =
								(JSON.stringify(properties) !== JSON.stringify(original_properties));

							data.name = properties.name;
							data.display_period = properties.display_period;
							data.auto_start = properties.auto_start;

							overlayDialogueDestroy(overlay.dialogueid);
						}
					});
			}
		};

		const original_properties = {
			name: data.name,
			display_period: data.display_period,
			auto_start: data.auto_start
		};

		let is_busy = false;
		let is_busy_saving = false;

		let has_properties_modified = false;

		init();
	}

	/**
	 * Reload widget configuration dialogue. Used as callback in widget forms.
	 */
	function updateWidgetConfigDialogue() {
		ZABBIX.Dashboard.updateWidgetConfigDialogue();
	}
</script>
