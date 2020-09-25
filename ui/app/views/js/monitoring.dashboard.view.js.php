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

<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
<?= (new CRow([
	new CCol([
		(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
		'#{name}'
	]),
	new CCol(
		(new CRadioButtonList('userGroups[#{usrgrpid}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_group_#{usrgrpid}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('window.dashboard_share.removeUserGroupShares("#{usrgrpid}");')
			->removeId()
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_group_shares_#{usrgrpid}')
	->toString()
?>
</script>

<script type="text/x-jquery-tmpl" id="user_row_tpl">
<?= (new CRow([
	new CCol([
		(new CTextBox('users[#{id}][userid]', '#{id}'))->setAttribute('type', 'hidden'),
		'#{name}',
	]),
	new CCol(
		(new CRadioButtonList('users[#{id}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_#{id}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_#{id}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('window.dashboard_share.removeUserShares("#{id}");')
			->removeId()
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_shares_#{id}')
	->toString()
?>
</script>

<script>
	class dashboardSingleton {
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
						'editable': this.data.editable,
						'edit_mode': (this.data.dashboardid === null),
						'kioskmode': (this.web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>)
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
			window.addEventListener('popstate', e => {
				var host = (e.state && e.state.host) ? e.state.host : null,
					hostid = host ? host.id : null;

				$('#dynamic_hostid').multiSelect('addData', host ? [host] : [], false);

				this.$target.dashboardGrid('updateDynamicHost', hostid);
			});

			$('#dynamic_hostid').on('change', e => {
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

			$('#dashbrd-control > li').hide().last().show();

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
				$.subscribe('dashboard.grid.copyWidget',
					() => $('#dashbrd-paste-widget').attr('disabled', false)
				);
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
				.then(response => {
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

			$(window).on('beforeunload.dashboardSingleton', () => {
				if (this.has_properties_modified || this.$target.dashboardGrid('isDashboardUpdated')) {
					return true;
				}
			});
		}

		disableNavigationWarning() {
			$(window).off('beforeunload.dashboardSingleton');
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
				.done(response => {
					$form.prevAll('.msg-good, .msg-bad').remove();

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

	class dashboardShareSingleton {
		constructor(data) {
			this.data = data;
		}

		live() {
			this.addPopupValues({'object': 'private', 'values': [this.data.private] });
			this.addPopupValues({'object': 'userid', 'values': this.data.users });
			this.addPopupValues({'object': 'usrgrpid', 'values': this.data.userGroups });
		}

		/**
		 * @param {Overlay} overlay
		 */
		submit(overlay) {
			var $form = overlay.$dialogue.find('form'),
				url = new Curl('zabbix.php', false);

			clearMessages();

			url.setArgument('action', 'dashboard.share.update');

			overlay.setLoading();
			overlay.xhr = $.ajax({
				url: url.getUrl(),
				data: $form.serializeJSON(),
				dataType: 'json',
				method: 'POST'
			});

			overlay.xhr
				.always(() => overlay.unsetLoading())
				.done((response) => {
					$form.prevAll('.msg-good, .msg-bad').remove();

					if ('errors' in response) {
						$(response.errors).insertBefore($form);
					}
					else if ('messages' in response) {
						addMessage(response.messages);

						overlayDialogueDestroy(overlay.dialogueid);
					}
				});
		}

		removeUserGroupShares(usrgrpid) {
			$('#user_group_shares_' + usrgrpid).remove();
		}

		removeUserShares(userid) {
			$('#user_shares_' + userid).remove();
		}

		addPopupValues(list) {
			var	i,
				tpl,
				container;

			for (i = 0; i < list.values.length; i++) {
				var	value = list.values[i];

				if (list.object === 'usrgrpid' || list.object === 'userid') {
					if (typeof value.permission === 'undefined') {
						if ($('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
							value.permission = <?= PERM_READ ?>;
						}
						else {
							value.permission = <?= PERM_READ_WRITE ?>;
						}
					}
				}

				switch (list.object) {
					case 'private':
						$('input[name=private][value=' + value + ']').prop('checked', true);

						break;

					case 'usrgrpid':
						if ($('#user_group_shares_' + value.usrgrpid).length) {
							continue;
						}

						tpl = new Template($('#user_group_row_tpl').html());

						container = $('#user_group_list_footer');
						container.before(tpl.evaluate(value));

						$('#user_group_' + value.usrgrpid + '_permission_' + value.permission + '').prop('checked', true);

						break;

					case 'userid':
						if ($('#user_shares_' + value.id).length) {
							continue;
						}

						tpl = new Template($('#user_row_tpl').html());

						container = $('#user_list_footer');
						container.before(tpl.evaluate(value));

						$('#user_' + value.id + '_permission_' + value.permission + '').prop('checked', true);

						break;
				}
			}
		}
	}

	function initializeDashboard(data, widget_defaults, time_selector, dynamic, web_layout_mode) {
		window.dashboard = new dashboardSingleton(data, widget_defaults, time_selector, dynamic, web_layout_mode);
		window.dashboard.live();
	}

	function initializeDashboardShare(data) {
		window.dashboard_share = new dashboardShareSingleton(data);
		window.dashboard_share.live();
	}

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		window.dashboard_share.addPopupValues(list);
	}

	/**
	 * Reload widget configuration dialogue. Used as callback in widget forms.
	 */
	function updateWidgetConfigDialogue() {
		window.dashboard.$target.dashboardGrid('updateWidgetConfigDialogue');
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
			var widgets = window.dashboard.$target.dashboardGrid('getWidgetsBy', 'type', type);

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

					window.dashboard.$target.dashboardGrid('refreshWidget', widget.uniqueid);
				}
			});
		}
	}
</script>
