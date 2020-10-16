<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	class Dashboard {
		constructor(data, widget_defaults, time_selector, dynamic, web_layout_mode) {
			this.$target = $('.<?= ZBX_STYLE_DASHBRD_GRID_CONTAINER ?>');

			this.data = data;
			this.widget_defaults = widget_defaults;
			this.time_selector = time_selector;
			this.dynamic = dynamic;
			this.web_layout_mode = web_layout_mode;

			this.original_name = data.name;
			this.original_owner_id = data.owner.id;

			this.is_busy = false;
			this.is_busy_saving = false;

			this.has_properties_modified = false;
		}

		live() {
			// Prevent page reloading on time selector events.
			timeControl.refreshPage = false;

			this.$target
				.dashboardGrid({
					dashboard: {
						dashboardid: this.data.dashboardid,
						dynamic_hostid: this.dynamic.host ? this.dynamic.host.id : null
					},
					options: {
						'widget-height': 70,
						'max-rows': <?= DASHBOARD_MAX_ROWS ?>,
						'max-columns': <?= DASHBOARD_MAX_COLUMNS ?>,
						'widget-min-rows': <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
						'widget-max-rows': <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
						'editable': this.data.allowed_edit ? this.data.editable : false,
						'edit_mode': (this.data.dashboardid === null),
						'kioskmode': (this.web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>),
						'allowed_edit': this.data.allowed_edit
					}
				})
				.dashboardGrid('setWidgetDefaults', this.widget_defaults)
				.dashboardGrid('addWidgets', this.data.widgets);

			if (this.dynamic.has_dynamic_widgets) {
				this.liveDynamicHost();
			}

			jqBlink.blink();

			if (this.data.dashboardid === null) {
				this.edit();
				this.openProperties();
			}
			else {
				$('#dashbrd-edit').click(() => this.$target.dashboardGrid('editDashboard'));
			}

			$.subscribe('dashboard.grid.editDashboard', () => this.edit());
		}

		liveDynamicHost() {
			// Perform dynamic host switch when browser back/previous buttons are pressed.
			window.addEventListener('popstate', (event) => {
				var host = (event.state && event.state.host) ? event.state.host : null,
					hostid = host ? host.id : null;

				$('#dynamic_hostid').multiSelect('addData', host ? [host] : [], false);

				this.$target.dashboardGrid('updateDynamicHost', hostid);
			});

			$('#dynamic_hostid').on('change', () => {
				var hosts = $('#dynamic_hostid').multiSelect('getData'),
					host = hosts.length ? hosts[0] : null,
					url = new Curl('zabbix.php', false);

				url.setArgument('action', 'dashboard.view');

				if (this.data.dashboardid !== null) {
					url.setArgument('dashboardid', this.data.dashboardid);
				}

				if (this.time_selector) {
					url.setArgument('from', this.time_selector.from);
					url.setArgument('to', this.time_selector.to);
				}

				if (host) {
					url.setArgument('hostid', host.id);
				}

				this.$target.dashboardGrid('updateDynamicHost', host ? host.id : null);

				history.pushState({host: host}, '', url.getUrl());

				updateUserProfile('web.dashbrd.hostid', host ? host.id : 1);
			});
		}

		edit() {
			timeControl.disableAllSBox();

			$('.filter-space').hide();

			clearMessages();

			$('#dashbrd-control > li')
				.hide()
				.last()
				.show();

			$('#dashbrd-config').on('click', () => this.openProperties());
			$('#dashbrd-add-widget').on('click', () => this.$target.dashboardGrid('addNewWidget', this));
			$('#dashbrd-paste-widget').on('click', () => this.$target.dashboardGrid('pasteWidget', null, null));
			$('#dashbrd-save').on('click', () => this.$target.dashboardGrid('saveDashboard', this.save.bind(this)));
			$('#dashbrd-cancel').on('click', () => {
				this.cancelEditing();

				return false;
			});

			if (this.$target.dashboardGrid('getCopiedWidget') !== null) {
				$('#dashbrd-paste-widget').attr('disabled', false);
			}
			else {
				$.subscribe('dashboard.grid.copyWidget', () => $('#dashbrd-paste-widget').attr('disabled', false));
			}

			$.subscribe('dashboard.grid.busy', (event, data) => {
				this.is_busy = data.state;
				this.updateBusy();
			});

			this.enableNavigationWarning();
		}

		save(widgets) {
			var url = new Curl('zabbix.php'),
				ajax_data = {
					dashboardid: (this.data.dashboardid !== null) ? this.data.dashboardid : undefined,
					userid: this.data.owner.id,
					name: this.data.name,
					widgets: [],
					sharing: this.data.sharing
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

			this.is_busy_saving = true;
			this.updateBusy();

			url.setArgument('action', 'dashboard.update');

			$.ajax({
				url: url.getUrl(),
				data: ajax_data,
				dataType: 'json',
				method: 'POST'
			})
				.always(() => {
					this.is_busy_saving = false;
					this.updateBusy();
				})
				.then((response) => {
					if ('redirect' in response) {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						this.disableNavigationWarning();

						location.replace(response.redirect);
					}
					else if ('errors' in response) {
						addMessage(response.errors);
					}
				});

		}

		updateBusy() {
			$('#dashbrd-save').prop('disabled', this.is_busy || this.is_busy_saving);
		}

		cancelEditing() {
			var url = new Curl('zabbix.php', false);

			url.setArgument('action', 'dashboard.view');

			if (this.data.dashboardid !== null) {
				url.setArgument('dashboardid', this.data.dashboardid);
			}
			else {
				url.setArgument('cancel', '1');
			}

			this.disableNavigationWarning();

			/**
			 * Redirect to last active dashboard.
			 * (1) In case of New Dashboard from list, it will open the list.
			 * (2) In case of New Dashboard or Clone Dashboard from another dashboard, it will open that dashboard.
			 * (3) In case of editing of the current dashboard, it will reload the same dashboard.
			 */
			location.replace(url.getUrl());
		}

		enableNavigationWarning() {
			this.disableNavigationWarning();

			$(window).on('beforeunload.dashboard', () => {
				if (this.has_properties_modified || this.$target.dashboardGrid('isDashboardUpdated')) {
					return true;
				}
			});
		}

		disableNavigationWarning() {
			$(window).off('beforeunload.dashboard');
		}

		openProperties() {
			var options = {
					userid: this.data.owner.id,
					name: this.data.name
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
						this.has_properties_modified =
							(form_data.userid !== this.original_owner_id || form_data.name !== this.original_name);

						this.data.owner.id = form_data.userid;
						this.data.name = form_data.name;

						$('#<?= ZBX_STYLE_PAGE_TITLE ?>').text(form_data.name);
						$('#dashboard-direct-link').text(form_data.name);

						overlayDialogueDestroy(overlay.dialogueid);
					}
				});
		}
	}
</script>
