<?php declare(strict_types = 1);
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
<script type="text/javascript">
	class UserRoleUiManager {
		constructor(readonly = false) {
			this.readonly = readonly;
		}

		disableUiCheckbox() {
			const usertype = document.querySelector('.js-userrole-usertype');
			if (!usertype || this.readonly) {
				return  false;
			}

			const access = {
				'<?= CRoleHelper::UI_MONITORING_DASHBOARD; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_PROBLEMS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_HOSTS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_OVERVIEW; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_LATEST_DATA; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_SCREENS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_MAPS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_MONITORING_DISCOVERY; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_MONITORING_SERVICES; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_INVENTORY_OVERVIEW; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_INVENTORY_HOSTS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_REPORTS_SYSTEM_INFO; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_REPORTS_TOP_TRIGGERS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::UI_REPORTS_AUDIT; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_REPORTS_ACTION_LOG; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_REPORTS_NOTIFICATIONS; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_HOST_GROUPS; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_TEMPLATES; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_HOSTS; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_MAINTENANCE; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_ACTIONS; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_DISCOVERY; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_CONFIGURATION_SERVICES; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_GENERAL; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_PROXIES; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_USER_GROUPS; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_USER_ROLES; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_USERS; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_SCRIPTS; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::UI_ADMINISTRATION_QUEUE; ?>': <?= USER_TYPE_SUPER_ADMIN; ?>,
				'<?= CRoleHelper::ACTIONS_EDIT_DASHBOARDS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::ACTIONS_EDIT_MAPS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::ACTIONS_EDIT_MAINTENANCE; ?>': <?= USER_TYPE_ZABBIX_ADMIN; ?>,
				'<?= CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::ACTIONS_CLOSE_PROBLEMS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::ACTIONS_CHANGE_SEVERITY; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>,
				'<?= CRoleHelper::ACTIONS_EXECUTE_SCRIPTS; ?>': <?= USER_TYPE_ZABBIX_USER; ?>
			};

			Object.keys(access).forEach((selector) => {
				const checkbox = document.querySelector(`[id='${selector}']`);
				const checkbox_state = checkbox.readOnly;

				if (usertype.value < access[selector]) {
					checkbox.readOnly = true;
					checkbox.checked = false;
				}
				else {
					checkbox.readOnly = false;
					if (checkbox_state) {
						checkbox.checked = true;
					}
				}
			});
		}

		disableApiSection() {
			const checkbox_state = document.querySelector('.js-userrole-apiaccess').checked;
			if (this.readonly) {
				return false;
			}

			[...document.querySelectorAll('.js-userrole-apimode input')].map((elem) => {
				elem.disabled = !checkbox_state;
			});

			$('#api_methods_').multiSelect(!checkbox_state ? 'disable' : 'enable');
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		const ui_manager = new UserRoleUiManager(<?php echo (bool) $this->data['readonly']; ?>);
		const type_elem = document.querySelector('.js-userrole-usertype');

		if (!type_elem) {
			return false;
		}

		type_elem.addEventListener('change', (event) => {
			ui_manager.disableUiCheckbox();

			let user_type = type_elem.options[type_elem.selectedIndex].value,
				$api_methods = $('#api_methods_'),
				url = $api_methods.multiSelect('getOption', 'url'),
				popup = $api_methods.multiSelect('getOption', 'popup'),
				pathname,
				search;

			[pathname, search] = url.split('?', 2);

			let params = new URLSearchParams(search);
			params.set('user_type', user_type);

			popup.parameters.user_type = user_type

			$api_methods.multiSelect('modify', {
				url: pathname + '?' + params.toString(),
				popup: popup
			});
		});

		document
			.querySelector('.js-userrole-apiaccess')
			.addEventListener('change', (event) => {
				ui_manager.disableApiSection();
			});

		ui_manager.disableUiCheckbox();
	});
</script>
