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
	class Dashboard {
		constructor(data, widget_defaults, time_selector, dynamic, web_layout_mode) {
			this.data = data;
			this.widget_defaults = widget_defaults;
			this.time_selector = time_selector;
			this.dynamic = dynamic;
			this.web_layout_mode = web_layout_mode;

			this._selected_page = null;

			this.original_name = data.name;
			this.original_owner_id = data.owner.id;

			this.is_busy = false;
			this.is_busy_saving = false;

			this.has_properties_modified = false;
		}

		live() {
			// Prevent page reloading on time selector events.
			timeControl.refreshPage = false;

			ZABBIX.Dashboard = new CDashboardPage($('.dashbrd-grid-container'), {
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
			});

			ZABBIX.Dashboard.setWidgetDefaults(this.widget_defaults);
			ZABBIX.Dashboard.addWidgets(this.data.widgets);

			this._livePages();

			if (this.dynamic.has_dynamic_widgets) {
				this.liveDynamicHost();
			}

			jqBlink.blink();

			if (this.data.dashboardid === null) {
				this.edit();
				this.openProperties();
			}
			else {
				$('#dashbrd-edit').click(() => ZABBIX.Dashboard.editDashboard());
			}

			$.subscribe('dashboard.grid.editDashboard', () => this.edit());
		}

		_livePages() {
			const navigation = document.querySelector('.<?= ZBX_STYLE_DASHBRD_NAVIGATION; ?>');
			const tabs = navigation.querySelector('.<?= ZBX_STYLE_DASHBRD_NAVIGATION_TABS; ?>');
			const controls = navigation.querySelector('.<?= ZBX_STYLE_DASHBRD_NAVIGATION_CONTROLS; ?>');
			const previous_page_button = controls.querySelector('.previous-page');
			const next_page_button = controls.querySelector('.next-page');

			const sortable = new CSortable(tabs.querySelector(`.${ZBX_STYLE_SORTABLE}`), {is_vertical: false});
			const sortable_list = sortable.getList();

			const update_buttons = () => {
				const is_scrollable = sortable.isScrollable();

				previous_page_button.disabled = (this._selected_page.previousSibling === null);
				previous_page_button.style.display = is_scrollable ? 'inline-block' : 'none';

				next_page_button.disabled = (this._selected_page.nextSibling === null);;
				next_page_button.style.display = is_scrollable ? 'inline-block' : 'none';
			};

			const select_page = (page) => {
				if (page == this._selected_page) {
					return;
				}

				if (this._selected_page !== null) {
					this._selected_page.firstElementChild.classList.remove('selected-page');
				}

				sortable.scrollItemIntoView(page);
				page.firstElementChild.classList.add('selected-page');

				this._selected_page = page;

				update_buttons();
			};

			select_page(sortable_list.children[0]);

			new ResizeObserver(update_buttons).observe(navigation);

			sortable.on(SORTABLE_EVENT_DRAG_END, update_buttons);

			sortable_list.addEventListener('click', (e) => {
				const page = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				if (page !== null) {
					select_page(page);
				}
			});

			sortable_list.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					const page = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (page !== null) {
						select_page(page);
					}
				}
			});

			previous_page_button.addEventListener('click', () => {
				select_page(this._selected_page.previousSibling);
			});

			next_page_button.addEventListener('click', () => {
				select_page(this._selected_page.nextSibling);
			});
		}

		liveDynamicHost() {
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

				ZABBIX.Dashboard.updateDynamicHost(host ? host.id : null);

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
			$('#dashbrd-add-widget').on('click', () => ZABBIX.Dashboard.addNewWidget(this));
			$('#dashbrd-paste-widget').on('click', () => ZABBIX.Dashboard.pasteWidget(null, null));
			$('#dashbrd-save').on('click', () => ZABBIX.Dashboard.saveDashboard(this.save.bind(this)));
			$('#dashbrd-cancel').on('click', () => {
				this.cancelEditing();

				return false;
			});

			if (ZABBIX.Dashboard.getCopiedWidget() !== null) {
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
				if (this.has_properties_modified || ZABBIX.Dashboard.isDashboardUpdated()) {
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
