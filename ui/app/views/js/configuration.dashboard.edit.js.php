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
			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBRD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBRD_GRID ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBRD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBRD_PREVIOUS_PAGE ?>'),
					next_page: document.querySelector('.<?= ZBX_STYLE_DASHBRD_NEXT_PAGE ?>')
				},
				dashboard: {
					templateid: data.templateid,
					dashboardid: data.dashboardid
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
				.getElementById('dashbrd-config')
				.addEventListener('click', () => openProperties());

			document
				.getElementById('dashbrd-add-widget')
				.addEventListener('click', (e) => ZABBIX.Dashboard.addNewWidget(e.target));

			document
				.getElementById('dashbrd-paste-widget')
				.addEventListener('click', () => ZABBIX.Dashboard.pasteWidget(null, null));

			document
				.getElementById('dashbrd-save')
				.addEventListener('click', () => ZABBIX.Dashboard.saveDashboard(save.bind(this)));

			document
				.getElementById('dashbrd-cancel')
				.addEventListener('click', (e) => {
					cancelEditing();
					e.preventDefault();
				}
			);

			if (ZABBIX.Dashboard.getCopiedWidget() !== null) {
				document.getElementById('dashbrd-paste-widget').disabled = false;
			}
			else {
				$.subscribe('dashboard.grid.copyWidget', () => {
					document.getElementById('dashbrd-paste-widget').disabled = false;
				});
			}

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

		const save = (widgets) => {
			clearMessages();

			const ajax_data = {
				templateid: data.templateid,
				dashboardid: (data.dashboardid !== null) ? data.dashboardid : undefined,
				name: data.name,
				widgets: []
			};

			for (const widget of widgets) {
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
			document.getElementById('dashbrd-save').disabled = is_busy || is_busy_saving;
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
				name: data.name
			};

			PopUp('dashboard.properties.edit', options, 'dashboard_properties', this);
		};

		const events = {
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
							has_properties_modified = (form_data.name !== original_name);

							data.name = form_data.name;

							overlayDialogueDestroy(overlay.dialogueid);
						}
					});
			}
		};

		const original_name = data.name;

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
