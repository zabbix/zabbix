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
 *  class.dashboard.js - коллекция слайдов
 *    actions:
 *      update dashboard
 *      copy/paste slides
 *
 *  class.slide.js - коллекция виджетов
 *    actions:
 *      create/update/delete slide
 *      copy/paste widgets
 *      move/resize widgets
 *
 *  class.widget.js
 *    actions:
 *      create/update/delete widget
 */


/**
 * @var CView $this
 */
?>

<script>
	class DashboardView {

		constructor(data, widget_defaults, time_selector, dynamic, web_layout_mode) {
			this._data = data;
			this._widget_defaults = widget_defaults;
			this._time_selector = time_selector;
			this._dynamic = dynamic;
			this._web_layout_mode = web_layout_mode;

			this._original_name = data.name;
			this._original_owner_id = data.owner.id;

			this._is_busy = false;
			this._is_busy_saving = false;

			this._has_properties_modified = false;

			this._init();
		}

		_init() {
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
					dashboardid: this._data.dashboardid,
					dynamic_hostid: this._dynamic.host ? this._dynamic.host.id : null
				},
				options: {
					'widget-height': 70,
					'max-rows': <?= DASHBOARD_MAX_ROWS ?>,
					'max-columns': <?= DASHBOARD_MAX_COLUMNS ?>,
					'widget-min-rows': <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
					'widget-max-rows': <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
					'editable': this._data.allowed_edit && this._data.editable,
					'edit_mode': (this._data.dashboardid === null),
					'kioskmode': (this._web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>),
					'allowed_edit': this._data.allowed_edit
				}
			});

			ZABBIX.Dashboard.setWidgetDefaults(this._widget_defaults);
			ZABBIX.Dashboard.addPages(this._data.pages);

			if (this._dynamic.has_dynamic_widgets) {
				this._liveDynamicHost();
			}

			jqBlink.blink();

			if (this._data.dashboardid === null) {
				this._edit();
				this._openProperties();
			}
			else {
				$('#dashbrd-edit').click(() => ZABBIX.Dashboard.editDashboard());
			}

			$.subscribe('dashboard.grid.editDashboard', () => this._edit());
		}

		_liveDynamicHost() {
			// Perform dynamic host switch when browser back/previous buttons are pressed.
			window.addEventListener('popstate', (event) => {
				var host = (event.state && event.state.host) ? event.state.host : null,
					hostid = host ? host.id : null;

				$('#dynamic_hostid').multiSelect('addData', host ? [host] : [], false);

				ZABBIX.Dashboard.updateDynamicHost(hostid);
			});

			$('#dynamic_hostid').on('change', () => {
				var hosts = $('#dynamic_hostid').multiSelect('getData'),
					host = hosts.length ? hosts[0] : null,
					url = new Curl('zabbix.php', false);

				url.setArgument('action', 'dashboard.view');

				if (this._data.dashboardid !== null) {
					url.setArgument('dashboardid', this._data.dashboardid);
				}

				if (this._time_selector) {
					url.setArgument('from', this._time_selector.from);
					url.setArgument('to', this._time_selector.to);
				}

				if (host) {
					url.setArgument('hostid', host.id);
				}

				ZABBIX.Dashboard.updateDynamicHost(host ? host.id : null);

				history.pushState({host: host}, '', url.getUrl());

				updateUserProfile('web.dashbrd.hostid', host ? host.id : 1);
			});
		}

		_edit() {
			timeControl.disableAllSBox();

			$('.filter-space').hide();

			clearMessages();

			$('#dashbrd-control > li')
				.hide()
				.last()
				.show();

			$('#dashbrd-config').on('click', () => this._openProperties());
			$('#dashbrd-add-widget').on('click', () => ZABBIX.Dashboard.addNewWidget(this));
			$('#dashbrd-paste-widget').on('click', () => ZABBIX.Dashboard.pasteWidget(null, null));
			$('#dashbrd-save').on('click', () => ZABBIX.Dashboard.saveDashboard(this._save.bind(this)));
			$('#dashbrd-cancel').on('click', () => {
				this._cancelEditing();

				return false;
			});

			if (ZABBIX.Dashboard.getCopiedWidget() !== null) {
				$('#dashbrd-paste-widget').attr('disabled', false);
			}
			else {
				$.subscribe('dashboard.grid.copyWidget', () => $('#dashbrd-paste-widget').attr('disabled', false));
			}

			$.subscribe('dashboard.grid.busy', (event, data) => {
				this._is_busy = data.state;
				this._updateBusy();
			});

			this._enableNavigationWarning();
		}

		_save(widgets) {
			var url = new Curl('zabbix.php'),
				ajax_data = {
					dashboardid: (this._data.dashboardid !== null) ? this._data.dashboardid : undefined,
					userid: this._data.owner.id,
					name: this._data.name,
					widgets: [],
					sharing: this._data.sharing
				};

			clearMessages();

			$.each(widgets, function(index, widget) {
				var ajax_widget = {};

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
			});

			this._is_busy_saving = true;
			this._updateBusy();

			url.setArgument('action', 'dashboard.update');

			$.ajax({
				url: url.getUrl(),
				data: ajax_data,
				dataType: 'json',
				method: 'POST'
			})
				.always(() => {
					this._is_busy_saving = false;
					this._updateBusy();
				})
				.then((response) => {
					if ('redirect' in response) {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						this._disableNavigationWarning();

						location.replace(response.redirect);
					}
					else if ('errors' in response) {
						addMessage(response.errors);
					}
				});

		}

		_updateBusy() {
			$('#dashbrd-save').prop('disabled', this._is_busy || this._is_busy_saving);
		}

		_cancelEditing() {
			var url = new Curl('zabbix.php', false);

			url.setArgument('action', 'dashboard.view');

			if (this._data.dashboardid !== null) {
				url.setArgument('dashboardid', this._data.dashboardid);
			}
			else {
				url.setArgument('cancel', '1');
			}

			this._disableNavigationWarning();

			/**
			 * Redirect to the last active dashboard.
			 * (1) In case of New Dashboard from list, open the list.
			 * (2) In case of New Dashboard or Clone Dashboard from another dashboard, open that dashboard.
			 * (3) In case of editing of the current dashboard, reload the same dashboard.
			 */
			location.replace(url.getUrl());
		}

		_enableNavigationWarning() {
			this._disableNavigationWarning();

			$(window).on('beforeunload.dashboard', () => {
				if (this._has_properties_modified || ZABBIX.Dashboard.isDashboardUpdated()) {
					return true;
				}
			});
		}

		_disableNavigationWarning() {
			$(window).off('beforeunload.dashboard');
		}

		_openProperties() {
			var options = {
					userid: this._data.owner.id,
					name: this._data.name
				};

			PopUp('dashboard.properties.edit', options, 'dashboard_properties', this);
		}

		/**
		 * @param {Overlay} overlay
		 */
		applyProperties(overlay) {
			var url = new Curl('zabbix.php', false),
				$form = overlay.$dialogue.find('form'),
				form_data;

			$form.trimValues(['#name']);
			form_data = $form.serializeJSON();

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
					$form
						.prevAll('.msg-good, .msg-bad')
						.remove();

					if ('errors' in response) {
						$(response.errors).insertBefore($form);
					}
					else {
						this._has_properties_modified =
							(form_data.userid !== this._original_owner_id || form_data.name !== this._original_name);

						this._data.owner.id = form_data.userid;
						this._data.name = form_data.name;

						$('#<?= ZBX_STYLE_PAGE_TITLE ?>').text(form_data.name);
						$('#dashboard-direct-link').text(form_data.name);

						overlayDialogueDestroy(overlay.dialogueid);
					}
				});
		}
	}
</script>
