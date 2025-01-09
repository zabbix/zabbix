<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

		init({templategroup_rights, hostgroup_rights, tag_filters, ldap_status, mfa_status, can_update_group}) {
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
				if (event.target.name == 'gui_access') {
					this.#toggleUserdirectoryAndMfa(event.target.value);
				}
				if (event.target.name == 'mfaid') {
					this.#toggleMfaWarningIcon(event.target.value);
				}
				if (event.target.name == 'userdirectoryid') {
					this.#toggleLdapWarningIcon(event.target.value);
				}
			})

			this.#setMultiselectDisabling('userids', true);
			this.#setMultiselectDisabling('ms_hostgroup');
			this.#setMultiselectDisabling('ms_templategroup');

			if (this.can_update_group) {
				this.#toggleUserdirectoryAndMfa(document.querySelector('[name="gui_access"]').value);
				this.#toggleMfaWarningIcon(document.querySelector('[name="mfaid"]').value);
				this.#toggleLdapWarningIcon(document.querySelector('[name="userdirectoryid"]').value);
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

			const ms = document.getElementById(`ms_${group_type}_right_groupids_${rowid}_`);
			let disable_groupids = [];

			$(ms).multiSelect();

			if (!groups.length) {
				if (group_type === 'templategroup') {
					this.#setMultiselectDisabling('ms_templategroup');
				}
				else if (group_type === 'hostgroup') {
					this.#setMultiselectDisabling('ms_hostgroup');
				}
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
				.querySelector(`input[name="${group_type}_right[permission][${rowid}]"][value="${permission}"]`);

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
				: [...document.querySelectorAll(`[id^="${group_type}_right_groupids_"]`)];

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
			});
		}

		#toggleUserdirectoryAndMfa(gui_access) {
			const userdirectory = document.querySelector('[name="userdirectoryid"]');
			const mfa = document.querySelector('[name="mfaid"]');

			switch (parseInt(gui_access)) {
				case GROUP_GUI_ACCESS_DISABLED:
					userdirectory.disabled = true;
					mfa.disabled = true;
					break;

				case GROUP_GUI_ACCESS_INTERNAL:
					userdirectory.disabled = true;
					mfa.disabled = false;
					break;

				default:
					userdirectory.disabled = false;
					mfa.disabled = false;
			}
		}

		#toggleMfaWarningIcon(mfa_value) {
			const icon = document.getElementById('mfa-warning');

			if (this.mfa_status == <?= MFA_DISABLED ?> && mfa_value != -1) {
				icon.style.display = '';
			}
			else {
				icon.style.display = 'none';
			}
		}

		#toggleLdapWarningIcon(userdirectory_value) {
			const icon = document.getElementById('ldap-warning');

			if (this.ldap_status == <?= ZBX_AUTH_LDAP_DISABLED ?> && userdirectory_value != 0) {
				icon.style.display = '';
			}
			else {
				icon.style.display = 'none';
			}
		}
	};
</script>
