<?php declare(strict_types = 1);
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
	const view = {
		readonly: false,

		init({readonly}) {
			this.readonly = readonly;

			const usertype_select = document.getElementById('user-type');
			if (usertype_select !== null) {
				usertype_select.addEventListener('change', this.events.usertypeChange);

				this.updateCheckboxes(usertype_select.value);
			}

			document
				.getElementById('api-access')
				.addEventListener('change', this.events.apiaccessChange);

			const clone_button = document.getElementById('clone');
			if (clone_button !== null) {
				clone_button.addEventListener('click', this.events.cloneClick);
			}
		},

		updateCheckboxes(user_type) {
			if (this.readonly) {
				return;
			}

			const access = <?= json_encode([
				CRoleHelper::UI_MONITORING_DASHBOARD => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_PROBLEMS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_HOSTS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_OVERVIEW => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_LATEST_DATA => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_MAPS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_DISCOVERY => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_MONITORING_SERVICES => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_INVENTORY_OVERVIEW => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_INVENTORY_HOSTS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_REPORTS_SYSTEM_INFO => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_REPORTS_TOP_TRIGGERS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_REPORTS_AUDIT => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_REPORTS_ACTION_LOG => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_REPORTS_NOTIFICATIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_HOST_GROUPS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_TEMPLATES => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_HOSTS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_MAINTENANCE => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_ACTIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_CONFIGURATION_DISCOVERY => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_GENERAL => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_PROXIES => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_USER_GROUPS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_USER_ROLES => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_USERS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_SCRIPTS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_QUEUE => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::ACTIONS_EDIT_DASHBOARDS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_EDIT_MAPS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_EDIT_MAINTENANCE => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_CLOSE_PROBLEMS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_CHANGE_SEVERITY => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_EXECUTE_SCRIPTS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_MANAGE_API_TOKENS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::ACTIONS_MANAGE_SERVICES => USER_TYPE_ZABBIX_ADMIN
			], JSON_FORCE_OBJECT) ?>;

			for (const [id, value] of Object.entries(access)) {
				const checkbox = document.getElementById(id);

				if (user_type < value) {
					checkbox.readOnly = true;
					checkbox.checked = false;
				} else {
					if (checkbox.readOnly) {
						checkbox.checked = true;
					}
					checkbox.readOnly = false;
				}
			}
		},

		updateApiMethodsMultiselect(user_type) {
			if (this.readonly) {
				return;
			}

			const $api_methods = $('#api_methods_');
			const url = $api_methods.multiSelect('getOption', 'url');
			const popup = $api_methods.multiSelect('getOption', 'popup');

			const [pathname, search] = url.split('?', 2);

			const params = new URLSearchParams(search);
			params.set('user_type', user_type);

			popup.parameters.user_type = user_type;

			$api_methods.multiSelect('modify', {
				url: pathname + '?' + params.toString(),
				popup: popup
			});
		},

		updateApiAccess(is_apiaccess_checked) {
			if (this.readonly) {
				return;
			}

			document.querySelectorAll('.js-userrole-apimode input').forEach((element) => {
				element.disabled = !is_apiaccess_checked;
			});

			$('#api_methods_').multiSelect(is_apiaccess_checked ? 'enable' : 'disable');
		},

		events: {
			usertypeChange(e) {
				view.updateCheckboxes(e.target.value);
				view.updateApiMethodsMultiselect(e.target.value);
			},

			apiaccessChange(e) {
				view.updateApiAccess(e.target.checked);
			},

			cloneClick() {
				if (this.readonly) {
					const url = new Curl('zabbix.php');
					url.setArgument('action', 'userrole.edit');

					document
						.querySelectorAll('#name, #user-type')
						.forEach((element) => {
							url.setArgument(element.getAttribute('name'), element.getAttribute('value'));
						});

					redirect(url.getUrl(), 'post', 'action', undefined, false, true);
				}

				document
					.querySelectorAll('#roleid, #delete, #clone')
					.forEach((element) => {
						element.remove();
					});

				const update_button = document.getElementById('update');
				update_button.textContent = <?= json_encode(_('Add')) ?>;
				update_button.setAttribute('id', 'add');
				update_button.setAttribute('value', 'userrole.create');

				document.getElementById('name').focus();
			}
		}
	}
</script>
