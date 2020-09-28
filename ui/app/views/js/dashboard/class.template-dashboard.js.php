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
	class TemplateDashboard {
		constructor(data, widget_defaults, page) {
			this.$target = $('.<?= ZBX_STYLE_DASHBRD_GRID_CONTAINER ?>');

			this.data = data;
			this.widget_defaults = widget_defaults;
			this.page = page;

			this.original_name = data.name;

			this.is_busy = false;
			this.is_busy_saving = false;

			this.has_properties_modified = false;
		}

		live() {
			this.$target
				.dashboardGrid({
					'dashboard': {
						templateid: this.data.templateid,
						dashboardid: this.data.dashboardid
					},
					'options': {
						'widget-height': 70,
						'max-rows': <?= DASHBOARD_MAX_ROWS ?>,
						'max-columns': <?= DASHBOARD_MAX_COLUMNS ?>,
						'widget-min-rows': <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
						'widget-max-rows': <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
						'editable': true,
						'edit_mode': true,
						'kioskmode': false
					}
				})
				.dashboardGrid('setWidgetDefaults', this.widget_defaults)
				.dashboardGrid('addWidgets', this.data.widgets);

			$('#dashbrd-config').on('click', () => this.openProperties());
			$('#dashbrd-add-widget').on('click', () => this.$target.dashboardGrid('addNewWidget', this));
			$('#dashbrd-paste-widget').on('click', () => this.$target.dashboardGrid('pasteWidget', null, null));
			$('#dashbrd-save').on('click', () => this.$target.dashboardGrid('saveDashboard', this.save.bind(this)));
			$('#dashbrd-cancel').on('click', () => {
				this.cancelEditing();

				return false;
			});

			var $copied_widget = this.$target.dashboardGrid('getCopiedWidget');

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

			if (this.data.dashboardid === null) {
				this.openProperties();
			}
		}

		save(widgets) {
			var url = new Curl('zabbix.php'),
				ajax_data = {
					templateid: this.data.templateid,
					dashboardid: (this.data.dashboardid !== null) ? this.data.dashboardid : undefined,
					name: this.data.name,
					widgets: []
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

			url.setArgument('action', 'template.dashboard.update');

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
					if ('errors' in response) {
						addMessage(response.errors);
					}
					else {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						this.cancelEditing();
					}
				});
		}

		updateBusy() {
			$('#dashbrd-save').prop('disabled', this.is_busy || this.is_busy_saving);
		}

		cancelEditing() {
			var url = new Curl('zabbix.php', false);

			url.setArgument('action', 'template.dashboard.list');
			url.setArgument('templateid', this.data.templateid);

			if (this.page !== null) {
				url.setArgument('page', this.page);
			}

			this.disableNavigationWarning();

			location.replace(url.getUrl());
		}

		enableNavigationWarning() {
			this.disableNavigationWarning();

			$(window).on('beforeunload.TemplateDashboard', () => {
				if (this.has_properties_modified || this.$target.dashboardGrid('isDashboardUpdated')) {
					return true;
				}
			});
		}

		disableNavigationWarning() {
			$(window).off('beforeunload.TemplateDashboard');
		}

		openProperties() {
			var options = {
					template: '1',
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
			form_data = $.extend({template: '1'}, $form.serializeJSON());

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
						this.has_properties_modified = (form_data.name !== this.original_name);

						this.data.name = form_data.name;

						overlayDialogueDestroy(overlay.dialogueid);
					}
				});
		}
	}
</script>
