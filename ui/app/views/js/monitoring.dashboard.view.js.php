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
	const DASHBOARD_VIEW_EVENT_APPLY_PROPERTIES = 'apply_properties';

	function initializeView(data, widget_defaults, time_selector, dynamic, web_layout_mode) {

		const init = () => {
			// Prevent page reloading on time selector events.
			timeControl.refreshPage = false;

			ZABBIX.Dashboard = new CDashboard(document.querySelector('.<?= ZBX_STYLE_DASHBRD ?>'), {
				containers: {
					grid: document.querySelector('.<?= ZBX_STYLE_DASHBRD_GRID ?>'),
					navigation_tabs: document.querySelector('.<?= ZBX_STYLE_DASHBRD_NAVIGATION_TABS ?>')
				},
				buttons: {
					previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBRD_PREVIOUS_PAGE ?>'),
					next_page: document.querySelector('.<?= ZBX_STYLE_DASHBRD_NEXT_PAGE ?>'),
					slideshow: document.querySelector('.<?= ZBX_STYLE_DASHBRD_TOGGLE_SLIDESHOW ?>')
				},
				dashboard: {
					dashboardid: data.dashboardid,
					dynamic_hostid: dynamic.host ? dynamic.host.id : null
				},
				options: {
					'widget-height': 70,
					'max-rows': <?= DASHBOARD_MAX_ROWS ?>,
					'max-columns': <?= DASHBOARD_MAX_COLUMNS ?>,
					'widget-min-rows': <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
					'widget-max-rows': <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
					'editable': data.allowed_edit && data.editable,
					'edit_mode': (data.dashboardid === null),
					'kioskmode': (web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>),
					'allowed_edit': data.allowed_edit
				}
			});

			ZABBIX.Dashboard.setWidgetDefaults(widget_defaults);
			ZABBIX.Dashboard.addPages(data.pages);
			ZABBIX.Dashboard.activate();

			if (dynamic.has_dynamic_widgets) {
				liveDynamicHost();
			}

			jqBlink.blink();

			if (data.dashboardid === null) {
				edit();
				openProperties();
			}
			else {
				document
					.getElementById('dashbrd-edit')
					.addEventListener('click', () => ZABBIX.Dashboard.editDashboard());
			}

			$.subscribe('dashboard.grid.editDashboard', () => edit());

			document.addEventListener(DASHBOARD_VIEW_EVENT_APPLY_PROPERTIES, events.applyProperties);
		};

		const liveDynamicHost = () => {
			// Perform dynamic host switch when browser back/previous buttons are pressed.
			window.addEventListener('popstate', events.popState);

			document
				.getElementById('dynamic_hostid')
				.addEventListener('change', events.dynamicHostChange);
		};

		const edit = () => {
			timeControl.disableAllSBox();

			document
				.querySelectorAll('.filter-space')
				.forEach((el) => {
					el.style.display = 'none';
				});

			clearMessages();

			document
				.querySelectorAll('#dashbrd-control > li')
				.forEach((el) => {
					el.style.display = (el.nextElementSibling === null) ? '' : 'none';
				});

			document
				.getElementById('dashbrd-config')
				.addEventListener('click', () => openProperties());

			document
				.getElementById('dashbrd-add-widget')
				.addEventListener('click', () => ZABBIX.Dashboard.addNewWidget(this));

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

			enableNavigationWarning();
		};

		const save = (widgets) => {
			const url = new Curl('zabbix.php');
			const ajax_data = {
				dashboardid: (data.dashboardid !== null) ? data.dashboardid : undefined,
				userid: data.owner.id,
				name: data.name,
				widgets: [],
				sharing: data.sharing
			};

			clearMessages();

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

			url.setArgument('action', 'dashboard.update');

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
					if ('redirect' in response) {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						disableNavigationWarning();

						location.replace(response.redirect);
					}
					else if ('errors' in response) {
						addMessage(response.errors);
					}
				});
		};

		const updateBusy = () => {
			document.getElementById('dashbrd-save').disabled = is_busy || is_busy_saving;
		};

		const cancelEditing = () => {
			const url = new Curl('zabbix.php', false);

			url.setArgument('action', 'dashboard.view');

			if (data.dashboardid !== null) {
				url.setArgument('dashboardid', data.dashboardid);
			}
			else {
				url.setArgument('cancel', '1');
			}

			/**
			 * Redirect to the last active dashboard.
			 * (1) In case of New Dashboard from list, open the list.
			 * (2) In case of New Dashboard or Clone Dashboard from another dashboard, open that dashboard.
			 * (3) In case of editing of the current dashboard, reload the same dashboard.
			 */
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
				userid: data.owner.id,
				name: data.name
			};

			PopUp('dashboard.properties.edit', options, 'dashboard_properties', this);
		};

		const events = {
			beforeUnload: () => {
				if (has_properties_modified || ZABBIX.Dashboard.isDashboardUpdated()) {
					return true;
				}
			},

			popState: (e) => {
				const host = (e.state && e.state.host) ? e.state.host : null;
				const hostid = host ? host.id : null;

				$('#dynamic_hostid').multiSelect('addData', host ? [host] : [], false);

				ZABBIX.Dashboard.updateDynamicHost(hostid);
			},

			dynamicHostChange: () => {
				const hosts = $('#dynamic_hostid').multiSelect('getData');
				const host = hosts.length ? hosts[0] : null;
				const url = new Curl('zabbix.php', false);

				url.setArgument('action', 'dashboard.view');

				if (data.dashboardid !== null) {
					url.setArgument('dashboardid', data.dashboardid);
				}

				if (time_selector) {
					url.setArgument('from', time_selector.from);
					url.setArgument('to', time_selector.to);
				}

				if (host) {
					url.setArgument('hostid', host.id);
				}

				ZABBIX.Dashboard.updateDynamicHost(host ? host.id : null);

				history.pushState({host: host}, '', url.getUrl());

				updateUserProfile('web.dashbrd.hostid', host ? host.id : 1);
			},

			applyProperties: (e) => {
				const overlay = e.detail.overlay;

				const form = overlay.$dialogue[0].querySelector('form');
				const form_data = {};

				new FormData(form).forEach((value, key) => form_data[key] = value);

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
							has_properties_modified =
								(form_data.userid !== original_owner_id || form_data.name !== original_name);

							data.owner.id = form_data.userid;
							data.name = form_data.name;

							document.getElementById('<?= ZBX_STYLE_PAGE_TITLE ?>').textContent = form_data.name;
							document.getElementById('dashboard-direct-link').textContent = form_data.name;

							overlayDialogueDestroy(overlay.dialogueid);
						}
					});
			}
		};

		const original_name = data.name;
		const original_owner_id = data.owner.id;

		let is_busy = false;
		let is_busy_saving = false;

		let has_properties_modified = false;

		init();
	}

	function initializeDashboardShare(data) {
		window.dashboard_share = new DashboardShare(data);
		window.dashboard_share.live();

		/**
		 * @see init.js add.popup event
		 */
		window.addPopupValues = function(list) {
			dashboard_share.addPopupValues(list);
		}
	}

	/**
	 * Reload widget configuration dialogue. Used as callback in widget forms.
	 */
	function updateWidgetConfigDialogue() {
		ZABBIX.Dashboard.updateWidgetConfigDialogue();
	}

	/**
	 * Find and refresh widget responsible for launching the "Update problem" popup after it was submitted.
	 *
	 * @param {String} type      Widget type to search for.
	 * @param {object} response  The response object from the "acknowledge.create" action.
	 * @param {object} overlay   The overlay object of the "Update problem" popup form.
	 */
	function refreshWidgetOnAcknowledgeCreate(type, response, overlay) {
		var handle_selector = '.dashbrd-grid-widget-content',
			handle = overlay.trigger_parents.filter(handle_selector).get(0);

		if (!handle) {
			var dialogue = overlay.trigger_parents.filter('.overlay-dialogue');

			if (dialogue.length) {
				var dialogue_overlay = overlays_stack.getById(dialogue.data('hintboxid'));
				if (dialogue_overlay && dialogue_overlay.type === 'hintbox') {
					handle = dialogue_overlay.element.closest(handle_selector);
				}
			}
		}

		if (handle) {
			var widgets = ZABBIX.Dashboard.getWidgetsBy('type', type);

			widgets.forEach(widget => {
				if ($.contains(widget.container[0], handle)) {
					for (var i = overlays_stack.length - 1; i >= 0; i--) {
						var hintbox = overlays_stack.getById(overlays_stack.stack[i]);
						if (hintbox.type === 'hintbox') {
							hintbox_handle = hintbox.element.closest(handle_selector);
							if ($.contains(widget.container[0], hintbox_handle)) {
								hintBox.hideHint(hintbox.element, true);
							}
						}
					}

					clearMessages();
					addMessage(makeMessageBox('good', [], response.message, true, false));

					ZABBIX.Dashboard.refreshWidget(widget.uniqueid);
				}
			});
		}
	}
</script>
