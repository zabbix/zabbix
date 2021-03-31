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
		'#{name}'
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
	function initializeView(dashboard, widget_defaults, has_time_selector, time_period, dynamic, web_layout_mode) {

		const init = () => {
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
						previous_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE ?>'),
						next_page: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_NEXT_PAGE ?>'),
						slideshow: document.querySelector('.<?= ZBX_STYLE_DASHBOARD_TOGGLE_SLIDESHOW ?>')
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
				cell_height: 70,
				max_columns: <?= DASHBOARD_MAX_COLUMNS ?>,
				max_rows: <?= DASHBOARD_MAX_ROWS ?>,
				widget_min_rows: <?= DASHBOARD_WIDGET_MIN_ROWS ?>,
				widget_max_rows: <?= DASHBOARD_WIDGET_MAX_ROWS ?>,
				widget_defaults: widget_defaults,
				is_editable: dashboard.can_edit_dashboards && dashboard.editable
					&& web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>,
				is_edit_mode: dashboard.dashboardid === null,
				can_edit_dashboards: dashboard.can_edit_dashboards,
				is_kiosk_mode: web_layout_mode == <?= ZBX_LAYOUT_KIOSKMODE ?>,
				time_period: time_period,
				dynamic_hostid: dynamic.host ? dynamic.host.id : null
			});

			for (const page of dashboard.pages) {
				for (const widget of page.widgets) {
					widget.fields = (typeof widget.fields === 'object') ? widget.fields : {};
					widget.configuration = (typeof widget.configuration === 'object') ? widget.configuration : {};
				}

				ZABBIX.Dashboard.addDashboardPage(page);
			}

			ZABBIX.Dashboard.activate();

			if (web_layout_mode != <?= ZBX_LAYOUT_KIOSKMODE ?>) {
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_EDIT, edit);
				ZABBIX.Dashboard.on(DASHBOARD_EVENT_APPLY_PROPERTIES, events.applyProperties);

				if (dynamic.has_dynamic_widgets) {
					$('#dynamic_hostid').on('change', events.dynamicHostChange);
				}

				if (dashboard.dashboardid === null) {
					edit();
					ZABBIX.Dashboard.editProperties();
				}
				else {
					document
						.getElementById('dashboard-edit')
						.addEventListener('click', () => {
							ZABBIX.Dashboard.setEditMode();
							edit();
						});
				}
			}

			if (dynamic.has_dynamic_widgets) {
				// Perform dynamic host switch when browser back/previous buttons are pressed.
				window.addEventListener('popstate', events.popState);
			}

			jqBlink.blink();
		};

		const edit = () => {
			timeControl.disableAllSBox();

			if (dynamic.has_dynamic_widgets) {
				$('#dynamic_hostid').off('change', events.dynamicHostChange);
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

			ZABBIX.Dashboard.on(DASHBOARD_EVENT_BUSY, events.busy);
			ZABBIX.Dashboard.on(DASHBOARD_EVENT_IDLE, events.idle);

			enableNavigationWarning();
		};

		const save = () => {
			clearMessages();

			is_busy_saving = true;
			updateBusy();

			const request_data = ZABBIX.Dashboard.save();

			request_data.sharing = dashboard.sharing;

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.update');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: urlEncodeData(request_data)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('errors' in response) {
						throw {html_string: response.errors};
					}

					if ('redirect' in response) {
						if ('system-message-ok' in response) {
							postMessageOk(response['system-message-ok']);
						}

						disableNavigationWarning();

						location.replace(response.redirect);
					}
				})
				.catch((error) => {
					if (typeof error === 'object' && 'html_string' in error) {
						addMessage(error.html_string);
					}
					else {
						const message = dashboard.dashboardid === null
							? t('Failed to create dashboard')
							: t('Failed to update dashboard');

						addMessage(makeMessageBox('bad', [], message, true, false));
					}
				})
				.finally(() => {
					is_busy_saving = false;
					updateBusy();
				});
		};

		const updateBusy = () => {
			document.getElementById('dashboard-save').disabled = is_busy || is_busy_saving;
		};

		const cancelEditing = () => {
			const curl = new Curl('zabbix.php', false);

			curl.setArgument('action', 'dashboard.view');

			if (dashboard.dashboardid !== null) {
				curl.setArgument('dashboardid', dashboard.dashboardid);
			}
			else {
				curl.setArgument('cancel', '1');
			}

			location.replace(curl.getUrl());
		};

		const enableNavigationWarning = () => {
			window.addEventListener('beforeunload', events.beforeUnload, {passive: false});
		};

		const disableNavigationWarning = () => {
			window.removeEventListener('beforeunload', events.beforeUnload);
		};

		const events = {
			addClick: (e) => {
				const menu = [
					{
						items: [
							{
								label: t('Add widget'),
								clickCallback: () => ZABBIX.Dashboard.addNewWidget()
							},
							{
								label: t('Add page'),
								clickCallback: () => ZABBIX.Dashboard.addNewDashboardPage()
							}
						]
					},
					{
						items: [
							{
								label: t('Paste widget'),
								clickCallback: () => ZABBIX.Dashboard.pasteWidget(
									ZABBIX.Dashboard.getStoredWidgetDataCopy()
								),
								disabled: (ZABBIX.Dashboard.getStoredWidgetDataCopy() === null)
							},
							{
								label: t('Paste page'),
								clickCallback: () => ZABBIX.Dashboard.pasteDashboardPage(
									ZABBIX.Dashboard.getStoredDashboardPageDataCopy()
								),
								disabled: (ZABBIX.Dashboard.getStoredDashboardPageDataCopy() === null)
							}
						]
					}
				];

				$(e.target).menuPopup(menu, new jQuery.Event(e), {
					position: {
						of: e.target,
						my: 'left top',
						at: 'left bottom',
						within: '.wrapper'
					}
				});
			},

			beforeUnload: (e) => {
				if (ZABBIX.Dashboard.isUnsaved()) {
					// Display confirmation message.
					e.preventDefault();
					e.returnValue = '';
				}
			},

			popState: (e) => {
				const host = (e.state !== null && 'host' in e.state) ? e.state.host : null;

				$('#dynamic_hostid').multiSelect('addData', host ? [host] : [], false);

				ZABBIX.Dashboard.setDynamicHost(host ? host.id : null);
			},

			dynamicHostChange: () => {
				const hosts = $('#dynamic_hostid').multiSelect('getData');
				const host = hosts.length ? hosts[0] : null;
				const curl = new Curl('zabbix.php', false);

				curl.setArgument('action', 'dashboard.view');

				if (dashboard.dashboardid !== null) {
					curl.setArgument('dashboardid', dashboard.dashboardid);
				}

				if (has_time_selector) {
					curl.setArgument('from', time_period.from);
					curl.setArgument('to', time_period.to);
				}

				if (host !== null) {
					curl.setArgument('hostid', host.id);
				}

				ZABBIX.Dashboard.setDynamicHost(host ? host.id : null);

				history.pushState({host: host}, '', curl.getUrl());

				updateUserProfile('web.dashboard.hostid', host ? host.id : 1);
			},

			applyProperties: () => {
				const dashboard_data = ZABBIX.Dashboard.getData();

				document.getElementById('<?= ZBX_STYLE_PAGE_TITLE ?>').textContent = dashboard_data.name;
				document.getElementById('dashboard-direct-link').textContent = dashboard_data.name;
			},

			busy: () => {
				is_busy = true;
				updateBusy();
			},

			idle: () => {
				is_busy = false;
				updateBusy();
			}
		};

		let is_busy = false;
		let is_busy_saving = false;

		init();
	}

	function initializeDashboardShare(data) {

		window.dashboard_share = {
			submit: (overlay) => {
				clearMessages();

				const form = overlay.$dialogue.$body[0].querySelector('form');

				const curl = new Curl('zabbix.php', false);

				curl.setArgument('action', 'dashboard.share.update');

				overlay.setLoading();

				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: urlEncodeData(getFormFields(form))
				})
					.then((response) => response.json())
					.then((response) => {
						if ('errors' in response) {
							throw {html_string: response.errors};
						}

						overlay.unsetLoading();

						addMessage(response.messages);
						overlayDialogueDestroy(overlay.dialogueid);

						delete window.dashboard_share;
					})
					.catch((error) => {
						overlay.unsetLoading();

						for (const el of form.parentNode.children) {
							if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
								el.parentNode.removeChild(el);
							}
						}

						const message_box = (typeof error === 'object' && 'html_string' in error)
							? new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild
							: makeMessageBox('bad', [], t('Failed to update dashboard sharing.'), true, false)[0];

						form.parentNode.insertBefore(message_box, form);
					});
			},

			removeUserGroupShares: (usrgrpid) => {
				const element = document.getElementById(`user_group_shares_${usrgrpid}`);

				if (element !== null) {
					element.remove();
				}
			},

			removeUserShares: (userid) => {
				const element = document.getElementById(`user_shares_${userid}`);

				if (element !== null) {
					element.remove();
				}
			},

			addPopupValues: (list) => {
				for (let i = 0; i < list.values.length; i++) {
					const value = list.values[i];

					if (list.object === 'usrgrpid' || list.object === 'userid') {
						if (value.permission === undefined) {
							if (document.querySelector('input[name="private"]:checked').value == <?= PRIVATE_SHARING ?>) {
								value.permission = <?= PERM_READ ?>;
							}
							else {
								value.permission = <?= PERM_READ_WRITE ?>;
							}
						}
					}

					switch (list.object) {
						case 'private':
							document
								.querySelector(`input[name="private"][value="${value}"]`)
								.checked = true;

							break;

						case 'usrgrpid':
							if (document.getElementById(`user_group_shares_${value.usrgrpid}`) !== null) {
								continue;
							}

							template = new Template(document.getElementById('user_group_row_tpl').innerHTML);

							document.getElementById('user_group_list_footer')
								.insertAdjacentHTML('beforebegin', template.evaluate(value));

							document
								.getElementById(`user_group_${value.usrgrpid}_permission_${value.permission}`)
								.checked = true;

							break;

						case 'userid':
							if (document.getElementById(`user_shares_${value.id}`) !== null) {
								continue;
							}

							template = new Template(document.getElementById('user_row_tpl').innerHTML);

							document.getElementById('user_list_footer')
								.insertAdjacentHTML('beforebegin', template.evaluate(value));

							document
								.getElementById(`user_${value.id}_permission_${value.permission}`)
								.checked = true;

							break;
					}
				}
			}
		};

		window.dashboard_share.addPopupValues({'object': 'private', 'values': [data.private]});
		window.dashboard_share.addPopupValues({'object': 'userid', 'values': data.users});
		window.dashboard_share.addPopupValues({'object': 'usrgrpid', 'values': data.userGroups});

		/**
		 * @see init.js add.popup event
		 */
		window.addPopupValues = (list) => {
			window.dashboard_share.addPopupValues(list);
		};
	}
</script>
