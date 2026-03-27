<?php
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

		init({rules, templategroup_rights, hostgroup_rights, tag_filters, ldap_status, mfa_status, can_update_group}) {
			this.form_element = document.getElementById('user-group-form');
			this.form = new CForm(this.form_element, rules);
			this.tag_filters = tag_filters;
			this.templategroup_rights = templategroup_rights;
			this.can_update_group = can_update_group;
			this.ldap_status = ldap_status;
			this.mfa_status = mfa_status;
			this.template_permission_template = new Template(
				document.getElementById('templategroup-right-row-template').innerHTML
			);
			this.template_counter = 0;

			this.hostgroup_rights = hostgroup_rights;
			this.host_permission_template = new Template(
				document.getElementById('hostgroup-right-row-template').innerHTML
			);
			this.host_counter = 0;

			this.form_element.addEventListener('submit', (e) => this.submit(e));

			const permission_types = [<?= PERM_READ_WRITE ?>, <?= PERM_READ ?>, <?= PERM_DENY ?>];

			permission_types.forEach(permission_type => {
				if (this.templategroup_rights[permission_type]) {
					this.#addRightRow('templategroup', this.templategroup_rights[permission_type], permission_type);
				}
				if (this.hostgroup_rights[permission_type]) {
					this.#addRightRow('hostgroup', this.hostgroup_rights[permission_type], permission_type);
				}
			});

			document.querySelector('.js-add-templategroup-right-row').addEventListener('click', () =>
				this.#addRightRow('templategroup')
			);
			document.querySelector('.js-add-hostgroup-right-row').addEventListener('click', () =>
				this.#addRightRow('hostgroup')
			);
			document.getElementById('user-group-form').addEventListener('click', event => {
				if (event.target.classList.contains('js-remove-table-row')) {
					this.#removeRow(event.target);
				}
				if (event.target.classList.contains('js-add-tag-filter')) {
					this.#openAddPopup();
				}
				if (event.target.classList.contains('js-edit-tag-filter')) {
					this.#openAddPopup(event.target.closest('tr'));
				}
				if (event.target.classList.contains('js-remove-tag-filter')) {
					this.#removeTagFilterRow(event.target.closest('tr'));
				}
			});

			document.getElementById('user-group-form').addEventListener('change', event => {
				this.#update();
			})

			this.form_element.querySelector('.table-forms .tfoot-buttons .js-delete')?.addEventListener('click', (e) =>
				this.#delete(e.target)
			);

			this.#setMultiselectDisabling('userids', true);
			this.#setMultiselectDisabling('ms_hostgroup');
			this.#setMultiselectDisabling('ms_templategroup');

			this.#update();
		}

		#update() {
			if (this.can_update_group) {
				const gui_access = this.form.findFieldByName('gui_access').getValue();
				const userdirectory_input = this.form_element.querySelector('[name="userdirectoryid"]');
				const mfa_select = this.form_element.querySelector('[name="mfaid"]');
				const mfa_status_checkbox = this.form_element.querySelector('.checkbox-radio[name="mfa_status"]');

				switch (parseInt(gui_access)) {
					case GROUP_GUI_ACCESS_DISABLED:
						userdirectory_input.disabled = true;
						mfa_status_checkbox.disabled = true;
						mfa_select.disabled = true;
						break;

					case GROUP_GUI_ACCESS_INTERNAL:
						userdirectory_input.disabled = true;
						mfa_status_checkbox.disabled = false;
						mfa_select.disabled = !mfa_status_checkbox.checked;
						break;

					default:
						userdirectory_input.disabled = false;
						mfa_status_checkbox.disabled = false;
						mfa_select.disabled = !mfa_status_checkbox.checked;
				}

				const mfa_warning_icon = document.getElementById('mfa-warning');
				const mfaid_value = this.form.findFieldByName('mfaid').getValue();

				if (this.mfa_status == <?= MFA_DISABLED ?> && mfaid_value != 0 && mfaid_value != null) {
					mfa_warning_icon.style.display = '';
				}
				else {
					mfa_warning_icon.style.display = 'none';
				}

				const ldap_warning_icon = document.getElementById('ldap-warning');
				const userdirectory_value = this.form.findFieldByName('userdirectoryid').getValue();

				if (this.ldap_status == <?= ZBX_AUTH_LDAP_DISABLED ?>
						&& userdirectory_value != 0 && userdirectory_value != null) {
					ldap_warning_icon.style.display = '';
				}
				else {
					ldap_warning_icon.style.display = 'none';
				}
			}
		}

		/**
		 * Adds a new row to the permissions tables, either for template or host groups, with the specified permission.
		 * Initializes the multiselect input with the provided groups and sets the permission radio button accordingly.
		 *
		 * @param {string} group_type  The type of group, either 'templategroup' or 'hostgroup'.
		 * @param {array}  groups      An array of groups for the row's multiselect.
		 * @param {number} permission  The permission level.
		 */
		#addRightRow(group_type = '', groups = [], permission = <?= PERM_DENY ?>) {
			const rowid = group_type === 'templategroup' ? this.template_counter++ : this.host_counter++;
			const template = group_type === 'templategroup'
				? this.template_permission_template
				: this.host_permission_template;
			const new_row = template.evaluate({'rowid': rowid});
			const placeholder_row = document.querySelector(`.js-${group_type}-right-row-placeholder`);

			placeholder_row.insertAdjacentHTML('beforebegin', new_row);

			const ms = document.getElementById(`${group_type}_rights_${rowid}_groupids_`);
			let disable_groupids = [];

			$(ms).multiSelect();

			if (!groups.length) {
				this.#setMultiselectDisabling(group_type);
			}
			else {
				for (const id in groups) {
					if (groups.hasOwnProperty(id)) {
						const group = {
							'id': groups[id]['groupid'],
							'name': groups[id]['name']
						};

						$(ms).multiSelect('addData', [group]);

						disable_groupids.push(group['id']);
					}
				}

				$(ms).multiSelect('setDisabledEntries', disable_groupids);
			}

			const permission_radio = document
				.querySelector(`input[name="${group_type}_rights[${rowid}][permission]"][value="${permission}"]`);

			permission_radio.checked = true;

			document.dispatchEvent(new Event('tab-indicator-update'));
		}

		/**
		 * Sets up disabling of groups in the multiselect popup based on changes in related multiselect's row.
		 *
		 * @param {string} group_type  The prefix to the ID of the multiselects to be observed and updated.
		 *                             Used to target the correct group (user, template, host) of multiselects.
		 * @param {bool}   is_single   Flag to indicate if only one multiselect is the target (e.g., users).
		 */
		#setMultiselectDisabling(group_type, is_single = false) {
			const multiselects = is_single
				? [document.getElementById(`${group_type}_`)]
				: [...document.querySelectorAll(`.multiselect[id^="${group_type}_rights_"]`)];

			multiselects.forEach(ms => {
				$(ms).on('change', (event) => {
					// Get all groupids to disable in the multiselect.
					const input_name = is_single
						? `input[name^="${group_type}"]`
						: `input[name^="${group_type}_right[groupids]"]`;
					const groupids = [...event.target.querySelectorAll(input_name)].map(input => input.value);

					$(ms).multiSelect('setDisabledEntries', groupids);
				});
			});
		}

		/**
		 * Removes the table row and triggers an event to update the tab indicator.
		 *
		 * @param {HTMLElement} button  The button element whose closest table row should be removed.
		 */
		#removeRow(button) {
			button
				.closest('tr')
				.remove();

			document.dispatchEvent(new Event('tab-indicator-update'));
		}

		/**
		 * Removes the tag filter table row and triggers an event to update the tab indicator.
		 * Removes the respective tag filters from tag filters array.
		 *
		 * @param {HTMLElement} button  The button element whose closest table row should be removed.
		 */
		#removeTagFilterRow(button) {
			const groupid = button.querySelector('input[name^="tag_filters["][name$="[groupid]"]').value;

			if (this.tag_filters.hasOwnProperty(groupid)) {
				delete this.tag_filters[groupid];
			}

			button
				.closest('tr')
				.remove();

			document.dispatchEvent(new Event('tab-indicator-update'));
		}

		/**
		 * Opens a popup to add or edit a tag filter, pre-filling the form with existing data if provided.
		 * After submission, the popup reloads the page with the new or updated tag filter data.
		 *
		 * @param {HTMLElement|null} row  An optional table row element containing the tag filter data to edit.
		 *                                If null, the popup will be initialized for adding a new tag filter.
		 */
		#openAddPopup(row = null) {
			let popup_params = {
				tag_filters: this.tag_filters
			};

			if (row !== null) {
				const groupid = row.querySelector('input[name*="[groupid]"]').value;

				popup_params = {
					...popup_params,
					edit: '1',
					groupid: groupid,
					name: this.tag_filters[groupid]['name']
				};
			}

			const overlay = PopUp('usergroup.tagfilter.edit', popup_params, {
				dialogueid: 'tag-filter-edit',
				dialogue_class: 'modal-popup-medium',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this.#reload(e.detail));
		}

		/**
		 * Reloads the tag filters table partial with the new or updated tag filter data from the response.
		 *
		 * @param {object} response  An object containing the updated tag filter data.
		 */
		#reload(response) {
			this.tag_filters = response.tag_filters;
			const tag_filter_form_field = document.getElementById('js-tag-filter-form-field');

			tag_filter_form_field.classList.add('is-loading');

			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'usergroup.tagfilter.list');
			curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(response)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);
					}

					tag_filter_form_field.innerHTML = response.body;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					tag_filter_form_field.classList.remove('is-loading');
					document.dispatchEvent(new Event('tab-indicator-update'));
					this.form.discoverAllFields();
				});
		}

		submit (e) {
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

					var curl = new Curl('zabbix.php');

					const action = document.getElementById('usrgrpid') !== null
						? 'usergroup.update'
						: 'usergroup.create';

					curl.setArgument('action', action);

					fetch(curl.getUrl(), {
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
						.finally(() => this.#unsetLoadingStatus())
				});
		}

		#delete() {
			if (window.confirm(<?= json_encode(_('Delete selected group?')) ?>)) {
				this.#setLoadingStatus('js-delete');
				const fields = this.form.getAllValues();

				const curl = new Curl('zabbix.php');
				curl.setArgument('action', 'usergroup.delete');
				curl.setArgument('usrgrpids', [fields.usrgrpid]);
				curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('usergroup')) ?>);

				redirect(curl.getUrl(), 'post', 'action', undefined, true);
			}
		}

		#ajaxExceptionHandler(exception) {
			let title, messages;

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			}
			else {
				messages = [<?= json_encode(_('Unexpected server error.')) ?>];
			}

			addMessage(makeMessageBox('bad', messages, title)[0]);
		}

		#setLoadingStatus(loading_btn_class) {
			this.form_element.classList.add('is-loading', 'is-loading-fadein');

			this.form_element.querySelectorAll('.table-forms .tfoot-buttons button:not(.js-cancel)')
				.forEach(button => {
					button.disabled = true;

					if (button.classList.contains(loading_btn_class)) {
						button.classList.add('is-loading');
					}
				});
		}

		#unsetLoadingStatus() {
			this.form_element.querySelectorAll('.table-forms .tfoot-buttons button:not(.js-cancel)')
				.forEach(button => {
					button.classList.remove('is-loading');
					button.disabled = false;
				});

			this.form_element.classList.remove('is-loading', 'is-loading-fadein');
		}
	};
</script>
