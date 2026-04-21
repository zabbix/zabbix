<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */
?>

<script>

	const view = new class {
		/** @type {CForm} */
		form;

		/** @type {HTMLFormElement} */
		form_element;

		/** @type {boolean} */
		readonly;

		init({rules, rules_create, readonly}) {
			this.form_element = document.getElementById('userrole-form');
			this.form = new CForm(this.form_element, rules);
			this.readonly = readonly;

			const usertype_select = document.getElementById('user-type');
			if (usertype_select !== null) {
				usertype_select.addEventListener('change', (e) => this.usertypeChange(e));

				this.updateAccessUiElementsFieldsGroup(usertype_select.value);
			}

			this.form_element.addEventListener('submit', (e) => this.submit(e));

			document
				.getElementById('api-access')
				.addEventListener('change', (e) => this.apiaccessChange(e));

			document
				.getElementById('service-write-access')
				.addEventListener('change', () => this.serviceWriteAccessChange());

			this.updateServicesWriteAccessFields();

			jQuery('#service_write_list_')
				.multiSelect('getSelectButton')
				.addEventListener('click', () => {
					this.selectServiceAccessList(jQuery('#service_write_list_'));
				});

			document
				.getElementById('service-read-access')
				.addEventListener('change', () => this.serviceReadAccessChange());

			this.updateServicesReadAccessFields();

			jQuery('#service_read_list_')
				.multiSelect('getSelectButton')
				.addEventListener('click', () => {
					this.selectServiceAccessList(jQuery('#service_read_list_'));
				});

			this.form_element.querySelector('.form-actions .js-delete')?.addEventListener('click', () => this.delete());

			this.form_element
				.querySelector('.form-actions .js-clone')?.addEventListener('click', () => this.clone(rules_create));
		}

		updateAccessUiElementsFieldsGroup(user_type) {
			if (this.readonly) {
				return;
			}

			const access_min = <?= json_encode([
				CRoleHelper::UI_MONITORING_DASHBOARD => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_PROBLEMS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_HOSTS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_LATEST_DATA => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_MAPS => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_MONITORING_DISCOVERY => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_SERVICES_SERVICES => USER_TYPE_ZABBIX_USER,
				CRoleHelper::UI_SERVICES_SLA => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_SERVICES_SLA_REPORT => USER_TYPE_ZABBIX_USER,
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
				CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_TEMPLATES => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_HOSTS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_MAINTENANCE => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_CONFIGURATION_DISCOVERY => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_GENERAL => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_AUDIT_LOG => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_PROXIES => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_MACROS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_USER_GROUPS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_USER_ROLES => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_USERS => USER_TYPE_SUPER_ADMIN,
				CRoleHelper::UI_ADMINISTRATION_API_TOKENS => USER_TYPE_SUPER_ADMIN,
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
				CRoleHelper::ACTIONS_MANAGE_SLA => USER_TYPE_ZABBIX_ADMIN,
				CRoleHelper::ACTIONS_EDIT_OWN_MEDIA => USER_TYPE_ZABBIX_USER,
				CRoleHelper::ACTIONS_EDIT_USER_MEDIA => USER_TYPE_SUPER_ADMIN
			], JSON_FORCE_OBJECT) ?>;

			for (const [id, value] of Object.entries(access_min)) {
				const checkbox = document.getElementById(id);

				if (user_type < value) {
					checkbox.readOnly = true;
					checkbox.checked = false;
				}
				else {
					if (checkbox.readOnly) {
						checkbox.checked = true;
					}
					checkbox.readOnly = false;
				}
			}

			const access_max = <?= json_encode([
					CRoleHelper::ACTIONS_INVOKE_EXECUTE_NOW => USER_TYPE_ZABBIX_ADMIN
			], JSON_FORCE_OBJECT) ?>;

			for (const [id, value] of Object.entries(access_max)) {
				const checkbox = document.getElementById(id);
				checkbox.readOnly = (user_type > value);

				if (checkbox.readOnly) {
					checkbox.checked = true;
				}
			}
		}

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
		}

		updateServicesWriteAccessFields() {
			const service_write_access = document.querySelector('input[name="service_write_access"]:checked').value;

			document
				.querySelectorAll('.js-service-write-access')
				.forEach((element) => {
					element.style.display = service_write_access == <?= CRoleHelper::SERVICES_ACCESS_LIST ?>
						? ''
						: 'none';
				});

			if (service_write_access) {
				this.form.findFieldByName('service_write_tag_tag').setChanged();
			}
		}

		updateServicesReadAccessFields() {
			const service_read_access = document.querySelector('input[name="service_read_access"]:checked').value;

			document
				.querySelectorAll('.js-service-read-access')
				.forEach((element) => {
					element.style.display = service_read_access == <?= CRoleHelper::SERVICES_ACCESS_LIST ?>
						? ''
						: 'none';
				});

			if (service_read_access) {
				this.form.findFieldByName('service_read_tag_tag').setChanged();
			}
		}

		updateApiAccessFieldsGroup(is_apiaccess_checked) {
			if (this.readonly) {
				return;
			}

			document.querySelectorAll('.js-userrole-apimode input').forEach((element) => {
				element.readOnly = !is_apiaccess_checked;
			});

			$('#api_methods_').multiSelect(is_apiaccess_checked ? 'enable' : 'disable');
		}

		selectServiceAccessList($multiselect) {
			const exclude_serviceids = [];

			for (const service of $multiselect.multiSelect('getData')) {
				exclude_serviceids.push(service.id);
			}

			const overlay = PopUp('popup.services', {
				title: <?= json_encode(_('Add services')) ?>,
				exclude_serviceids
			}, {dialogueid: 'services'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const data = [];

				for (const service of e.detail) {
					data.push({id: service.serviceid, name: service.name});
				}

				$multiselect.multiSelect('addData', data);
			});
		}

		usertypeChange(e) {
			this.updateAccessUiElementsFieldsGroup(e.target.value);
			this.updateApiMethodsMultiselect(e.target.value);
			this.form.validateChanges(['ui', 'actions']);
		}

		apiaccessChange(e) {
			this.updateApiAccessFieldsGroup(e.target.checked);
		}

		serviceWriteAccessChange() {
			if (!this.readonly) {
				this.updateServicesWriteAccessFields();
			}
		}

		serviceReadAccessChange() {
			if (!this.readonly) {
				this.updateServicesReadAccessFields();
			}
		}

		clone(rules) {
			if (this.readonly) {
				const fields = {
					action: 'userrole.edit',
					super_admin_role_clone: 1,
					name: this.form.findFieldByName('name').getValue()
				};

				redirect(zabbixUrl(fields), 'post', 'action', undefined, true);
			}
			else {
				document.getElementById('roleid').remove();

				this.form_element.querySelectorAll('.form-actions .js-delete, .form-actions .js-clone')
					.forEach((element) => element.remove());

				const update_button = this.form_element.querySelector('.form-actions .js-submit');
				update_button.textContent = <?= json_encode(_('Add')) ?>;

				document.getElementById('name').focus();
				clearMessages();
				this.form.reload(rules);
			}
		}

		submit(e) {
			e.preventDefault();
			this.#setLoadingStatus('js-submit');
			clearMessages();
			const fields = this.form.getAllValues();

			this.form.validateSubmit(fields)
				.then((result) => {
					if (!result) {
						this.#unsetLoadingStatus();
						return;
					}

					const action = document.getElementById('roleid') !== null
						? 'userrole.update'
						: 'userrole.create';

					fetch(zabbixUrl({action}), {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify(fields)
					})
						.then((response) => response.json())
						.then((response) => {
							if ('error' in response) {
								throw {error: response.error};
							}

							if ('form_errors' in response) {
								this.form.setErrors(response.form_errors, true, true);
								this.form.renderErrors();
								return;
							}

							if ('success' in response) {
								postMessageOk(response.success.title);

								if ('messages' in response.success) {
									postMessageDetails('success', response.success.messages);
								}

								location.href = new URL(response.success.redirect, location.href).href;
							}
						})
						.catch((exception) => this.#ajaxExceptionHandler(exception))
						.finally(() => this.#unsetLoadingStatus());
				});
		}

		delete() {
			if (window.confirm(<?= json_encode(_('Delete selected role?')) ?>)) {
				this.#setLoadingStatus('js-delete');

				const params = {
					action: 'userrole.delete',
					roleids: [this.form.findFieldByName('roleid').getValue()]
				};
				params[CSRF_TOKEN_NAME] = <?= json_encode(CCsrfTokenHelper::get('userrole')) ?>;

				redirect(zabbixUrl(params), 'post', 'action', undefined, true);
			}
		}

		#ajaxExceptionHandler (exception) {
			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			addMessage(makeMessageBox('bad', messages, title));
		}

		#setLoadingStatus(loading_btn_class) {
			this.form_element.classList.add('is-loading', 'is-loading-fadein');

			this.form_element.querySelectorAll('.form-actions button:not(.js-cancel)').forEach(button => {
				button.disabled = true;

				if (button.classList.contains(loading_btn_class)) {
					button.classList.add('is-loading');
				}
			});
		}

		#unsetLoadingStatus() {
			this.form_element.querySelectorAll('.form-actions button:not(.js-cancel)').forEach(button => {
				button.classList.remove('is-loading');
				button.disabled = false;
			});

			this.form_element.classList.remove('is-loading', 'is-loading-fadein');
		}
	};
</script>
