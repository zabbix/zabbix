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

		init({ldap_servers, ldap_default_row_index, db_authentication_type, saml_provision_groups,
				saml_provision_media, templates, mfa_methods, mfa_default_row_index, is_http_auth_allowed
		}) {
			this.form = document.getElementById('authentication-form');
			this.db_authentication_type = db_authentication_type;
			this.saml_provision_status = document.getElementById('saml_provision_status');
			this.saml_provision_groups_table = document.getElementById('saml-group-table');
			this.saml_media_type_mapping_table = document.getElementById('saml-media-type-mapping-table');
			this.ldap_jit_status = document.getElementById('ldap_jit_status');
			this.ldap_servers_table = document.getElementById('ldap-servers');
			this.templates = templates;
			this.is_http_auth_allowed = is_http_auth_allowed;
			this.ldap_provisioning_fields = this.form.querySelectorAll(
				'[name="ldap_jit_status"],[name="ldap_case_sensitive"],[name="jit_provision_interval"]'
			);
			this.jit_provision_interval = this.form.querySelector('[name="jit_provision_interval"]');
			this.ldap_auth_enabled = this.form.querySelector('[type="checkbox"][name="ldap_auth_enabled"]');
			this.mfa_table = document.getElementById('mfa-methods');
			const saml_readonly = !this.form.querySelector('[type="checkbox"][name="saml_auth_enabled"]').checked;
			const ldap_disabled = this.ldap_auth_enabled === null || !this.ldap_auth_enabled.checked;
			const mfa_readonly = !this.form.querySelector('[type="checkbox"][name="mfa_status"]').checked;

			this._addEventListeners();
			this._addLdapServers(ldap_servers, ldap_default_row_index);
			this.#setTableVisiblityState(this.ldap_servers_table, ldap_disabled);
			this.#disableRemoveLinksWithUserGroups(this.ldap_servers_table);
			this._renderProvisionGroups(saml_provision_groups);
			this.#setTableVisiblityState(this.saml_provision_groups_table, saml_readonly);
			this._renderProvisionMedia(saml_provision_media);
			this.#setTableVisiblityState(this.saml_media_type_mapping_table, saml_readonly)
			this.#addMfaMethods(mfa_methods, mfa_default_row_index);
			this.#setTableVisiblityState(this.mfa_table, mfa_readonly);
			this.#disableRemoveLinksWithUserGroups(this.mfa_table);

			this.form.querySelector('[type="checkbox"][name="saml_auth_enabled"]').dispatchEvent(new Event('change'));
		}

		_addEventListeners() {
			this.#addLdapSettingsEventListeners();

			if (this.is_http_auth_allowed) {
				document.getElementById('http_auth_enabled').addEventListener('change', (e) => {
					this.form.querySelectorAll('[name^=http_]').forEach(field => {
						if (!field.isSameNode(e.target)) {
							field.disabled = !e.target.checked;
						}
					});

					if (e.target.checked) {
						let form_fields = this.form.querySelectorAll('[name^=http_]');

						const http_auth_enabled = document.getElementById('http_auth_enabled');
						overlayDialogue({
							'title': <?= json_encode(_('Confirm changes')) ?>,
							'class': 'position-middle',
							'content': document.createElement('span').innerText = <?= json_encode(
								_('Enable HTTP authentication for all users.')
							) ?>,
							'buttons': [
								{
									'title': <?= json_encode(_('Cancel')) ?>,
									'cancel': true,
									'class': '<?= ZBX_STYLE_BTN_ALT ?>',
									'action': function () {
										for (const form_field of form_fields) {
											if (form_field !== http_auth_enabled) {
												form_field.disabled = true;
											}
										}

										http_auth_enabled.checked = false;
										document.getElementById('tab_http').setAttribute('data-indicator-value', '0');
									}
								},
								{
									'title': <?= json_encode(_('Ok')) ?>,
									'focused': true,
									'action': function () {}
								}
							]
						}, e.target);
					}
				});
			}

			this.form.querySelector('[type="checkbox"][name="saml_auth_enabled"]').addEventListener('change', (e) => {
				const is_readonly = !e.target.checked;

				this.form.querySelectorAll('.saml-enabled').forEach(field => {
					field.toggleAttribute('readonly', is_readonly);
					field.toggleAttribute('disabled', is_readonly);
					field.setAttribute('tabindex', is_readonly ? -1 : 0);
				});
				this.#setTableVisiblityState(this.saml_provision_groups_table, is_readonly);
				this.#setTableVisiblityState(this.saml_media_type_mapping_table, is_readonly);
			});

			this.saml_provision_status.addEventListener('change', (e) => {
				this.form.querySelectorAll('.saml-provision-status').forEach(field =>
					field.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !e.target.checked)
				);
			});

			this.saml_provision_groups_table.addEventListener('click', (e) => {
				if (e.target.classList.contains('disabled')) {
					return;
				}
				else if (e.target.classList.contains('js-add')) {
					this.editSamlProvisionGroup();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editSamlProvisionGroup(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

			this.saml_media_type_mapping_table
				.addEventListener('click', (e) => {
					if (e.target.classList.contains('disabled')) {
						return;
					}
					else if (e.target.classList.contains('js-add')) {
						this.editSamlProvisionMedia();
					}
					else if (e.target.classList.contains('js-edit')) {
						this.editSamlProvisionMedia(e.target.closest('tr'));
					}
					else if (e.target.classList.contains('js-remove')) {
						e.target.closest('tr').remove();
					}
				});

			this.form.addEventListener('submit', (e) => {
				if (!this._authFormSubmit()) {
					e.preventDefault();
				}
			});

			this.mfa_table.addEventListener('click', (e) => {
				if (e.target.classList.contains('disabled')) {
					return;
				}
				else if (e.target.classList.contains('js-add')) {
					this.editMfaMethod();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editMfaMethod(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					const table = e.target.closest('table');
					const mfaid_input = e.target.closest('tr')
						.querySelector('input[name$="[mfaid]"]');

					if (mfaid_input !== null) {
						const input = document.createElement('input');
						input.type = 'hidden';
						input.name = 'mfa_removed_mfaids[]';
						input.value = mfaid_input.value;
						this.form.appendChild(input);
					}

					e.target.closest('tr').remove();

					if (table.querySelector('input[name="mfa_default_row_index"]:checked') === null) {
						const default_mfa = table.querySelector('input[name="mfa_default_row_index"]');

						if (default_mfa !== null) {
							default_mfa.checked = true;
						}
					}
				}
			});

			this.form.querySelector('[type="checkbox"][name="mfa_status"]').addEventListener('change', (e) => {
				const is_readonly = !e.target.checked;
				const default_index = this.form.querySelector('input[name="mfa_default_row_index"]:checked');
				const default_index_hidden = this.form.querySelector('[type="hidden"][name="mfa_default_row_index"]');

				this.#setTableVisiblityState(this.mfa_table, is_readonly);
				this.#disableRemoveLinksWithUserGroups(this.mfa_table);

				if (is_readonly && default_index) {
					default_index_hidden.value = default_index.value;
				}
			});
		}

		#addLdapSettingsEventListeners() {
			if (this.ldap_auth_enabled === null) {
				return;
			}

			this.ldap_servers_table.addEventListener('click', (e) => {
				if (e.target.classList.contains('disabled')) {
					return;
				}
				else if (e.target.classList.contains('js-add')) {
					this.editLdapServer();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editLdapServer(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					const table = e.target.closest('table');
					const userdirectoryid_input = e.target.closest('tr')
						.querySelector('input[name$="[userdirectoryid]"]');

					if (userdirectoryid_input !== null) {
						const input = document.createElement('input');
						input.type = 'hidden';
						input.name = 'ldap_removed_userdirectoryids[]';
						input.value = userdirectoryid_input.value;
						this.form.appendChild(input);
					}

					e.target.closest('tr').remove();

					if (table.querySelector('input[name="ldap_default_row_index"]:checked') === null) {
						const default_ldap = table.querySelector('input[name="ldap_default_row_index"]');

						if (default_ldap !== null) {
							default_ldap.checked = true;
						}
					}
				}
			});

			this.ldap_jit_status.addEventListener('change', this.#updateLdapFieldsState.bind(this));
			this.ldap_auth_enabled.addEventListener('change', this.#updateLdapFieldsState.bind(this));
		}

		#updateLdapFieldsState() {
			const ldap_disabled = !this.ldap_auth_enabled.checked;
			const provision_disabled = ldap_disabled || !this.ldap_jit_status.checked;

			this.ldap_provisioning_fields.forEach(field => field.toggleAttribute('disabled', ldap_disabled));
			this.#setTableVisiblityState(this.ldap_servers_table, ldap_disabled);
			this.#disableRemoveLinksWithUserGroups(this.ldap_servers_table);
			this.jit_provision_interval.toggleAttribute('disabled', provision_disabled);
		}

		_authFormSubmit() {
			const fields_to_trim = ['#http_strip_domains', '#idp_entityid', '#sso_url', '#slo_url',
				'#username_attribute', '#sp_entityid', '#nameid_format', '#saml_group_name', '#saml_user_username',
				'#saml_user_lastname'
			];
			document.querySelectorAll(fields_to_trim.join(', ')).forEach((elem) => {
				elem.value = elem.value.trim();
			});

			const auth_type = document.querySelector('[name=authentication_type]:checked').value;
			const warning_msg = <?= json_encode(
				_('Switching authentication method will reset all except this session! Continue?')
			) ?>;

			return (auth_type == this.db_authentication_type || confirm(warning_msg));
		}

		_addLdapServers(ldap_servers, ldap_default_row_index) {
			for (const [row_index, ldap] of Object.entries(ldap_servers)) {
				ldap.row_index = row_index;
				ldap.is_default = (ldap.row_index == ldap_default_row_index) ? 'checked' : '';

				this.ldap_servers_table
					.querySelector('tbody')
					.appendChild(this._prepareServerRow(ldap));
			}
		}

		#disableRemoveLinksWithUserGroups(table) {
			table.querySelectorAll('[data-disable_remove] .js-remove').forEach(field => field.disabled = true);
		}

		_renderProvisionGroups(saml_provision_groups) {
			for (const [row_index, saml_provision_group] of Object.entries(saml_provision_groups)) {
				saml_provision_group.row_index = row_index;

				this.saml_provision_groups_table
					.querySelector('tbody')
					.appendChild(this._renderProvisionGroupRow(saml_provision_group));
			}
		}

		_renderProvisionMedia(saml_provision_media) {
			for (const [row_index, saml_media] of Object.entries(saml_provision_media)) {
				saml_media.row_index = row_index;

				this.saml_media_type_mapping_table
					.querySelector('tbody')
					.appendChild(this._renderProvisionMediaRow(saml_media));
			}
		}

		#setTableVisiblityState(table, readonly) {
			table.classList.toggle('disabled', readonly);
			table.querySelectorAll('a,input:not([type="hidden"]),button').forEach(node => {
				node.toggleAttribute('disabled', readonly);
				node.classList.toggle('disabled', readonly);
			});
		}

		editSamlProvisionGroup(row = null) {
			let popup_params = {};
			let row_index = 0;

			if (row !== null) {
				row_index = row.dataset.row_index;

				popup_params.name = row.querySelector(`[name="saml_provision_groups[${row_index}][name]"`).value;

				const user_groups = row.querySelectorAll(
					`[name="saml_provision_groups[${row_index}][user_groups][][usrgrpid]"`
				);
				if (user_groups.length) {
					popup_params.usrgrpid = [...user_groups].map(usrgrp => usrgrp.value);
				}

				const roleid = row.querySelector(`[name="saml_provision_groups[${row_index}][roleid]"`);
				if (roleid) {
					popup_params.roleid = roleid.value;
				}
			}
			else {
				while (this.saml_provision_groups_table.querySelector(`[data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					add_group: 1,
					name: ''
				};
			}

			popup_params.idp_type = <?= IDP_TYPE_SAML ?>;

			const overlay = PopUp('popup.usergroupmapping.edit', popup_params,
				{dialogueid: 'user_group_edit', dialogue_class: 'modal-popup-medium'}
			);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const new_row = this._renderProvisionGroupRow({...e.detail, ...{row_index}});

				if (row === null) {
					this.saml_provision_groups_table.querySelector('tbody').appendChild(new_row);
				}
				else {
					row.replaceWith(new_row);
				}
			});
		}

		editSamlProvisionMedia(row = null) {
			let popup_params;
			let row_index = 0;

			if (row !== null) {
				row_index = row.dataset.row_index;

				popup_params = Object.fromEntries(
					[...row.querySelectorAll(`[name^="saml_provision_media[${row_index}]"]`)].map(
						i => [i.name.match(/\[([^\]]+)\]$/)[1], i.value]
				));
			}
			else {
				while (this.saml_media_type_mapping_table.querySelector(`[data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					add_media_type_mapping: 1
				};
			}

			const overlay = PopUp('popup.mediatypemapping.edit', popup_params,
				{dialogueid: 'media_type_mapping_edit', dialogue_class: 'modal-popup-medium'}
			);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const saml_media_type_mapping = {...e.detail, ...{row_index: row_index}};

				if (row === null) {
					this.saml_media_type_mapping_table
						.querySelector('tbody')
						.appendChild(this._renderProvisionMediaRow(saml_media_type_mapping));
				}
				else {
					row.replaceWith(this._renderProvisionMediaRow(saml_media_type_mapping));
				}
			});
		}

		editLdapServer(row = null) {
			let popup_params;
			let row_index = 0;

			if (row !== null) {
				row_index = row.dataset.row_index;

				const provision_group_indexes = [...row.querySelectorAll(
					`[name^="ldap_servers[${row_index}][provision_groups]"][name$="[name]"]`
				)].map((element) => {
					let start = 33 + row_index.toString().length;
					let end = element.name.length - 7;
					return element.name.substring(start, end);
				});

				const provision_groups = provision_group_indexes.map((i) => {
					let user_groups = row.querySelectorAll(
						`[name="ldap_servers[${row_index}][provision_groups][${i}][user_groups][][usrgrpid]"`
					);
					let group_name = row.querySelector(
						`[name="ldap_servers[${row_index}][provision_groups][${i}][name]"`
					);
					let provision_group = {
						roleid: row.querySelector(
							`[name="ldap_servers[${row_index}][provision_groups][${i}][roleid]"`
						).value,
						user_groups: [...user_groups].map(usrgrp => usrgrp.value)
					}

					if (group_name) {
						provision_group.name = group_name.value;
					}

					return provision_group;
				});

				const provision_media_indexes = [...row.querySelectorAll(
					`[name^="ldap_servers[${row_index}][provision_media]"][name$="[name]"]`
				)].map((element) => {
					let start = 32 + row_index.toString().length;
					let end = element.name.length - 7;

					return element.name.substring(start, end);
				});
				const provision_media = provision_media_indexes.map((i) => {
					return Object.fromEntries(
						[...row.querySelectorAll(`[name^="ldap_servers[${row_index}][provision_media][${i}]"]`)].map(
							i => [i.name.match(/\[([^\]]+)\]$/)[1], i.value]
					));
				});

				popup_params = {
					row_index,
					add_ldap_server: 0,
					name: row.querySelector(`[name="ldap_servers[${row_index}][name]"`).value,
					host: row.querySelector(`[name="ldap_servers[${row_index}][host]"`).value,
					port: row.querySelector(`[name="ldap_servers[${row_index}][port]"`).value,
					base_dn: row.querySelector(`[name="ldap_servers[${row_index}][base_dn]"`).value,
					search_attribute: row.querySelector(`[name="ldap_servers[${row_index}][search_attribute]"`).value,
					search_filter: row.querySelector(`[name="ldap_servers[${row_index}][search_filter]"`).value,
					start_tls: row.querySelector(`[name="ldap_servers[${row_index}][start_tls]"`).value,
					bind_dn: row.querySelector(`[name="ldap_servers[${row_index}][bind_dn]"`).value,
					description: row.querySelector(`[name="ldap_servers[${row_index}][description]"`).value,
					provision_status: row.querySelector(`[name="ldap_servers[${row_index}][provision_status]"`).value,
					group_basedn: row.querySelector(`[name="ldap_servers[${row_index}][group_basedn]"`).value,
					group_name: row.querySelector(`[name="ldap_servers[${row_index}][group_name]"`).value,
					group_member: row.querySelector(`[name="ldap_servers[${row_index}][group_member]"`).value,
					user_ref_attr: row.querySelector(`[name="ldap_servers[${row_index}][user_ref_attr]"`).value,
					group_filter: row.querySelector(`[name="ldap_servers[${row_index}][group_filter]"`).value,
					group_membership: row.querySelector(`[name="ldap_servers[${row_index}][group_membership]"`).value,
					user_username: row.querySelector(`[name="ldap_servers[${row_index}][user_username]"`).value,
					user_lastname: row.querySelector(`[name="ldap_servers[${row_index}][user_lastname]"`).value,
					provision_groups,
					provision_media
				};

				const userdirectoryid_input = row.querySelector(`[name="ldap_servers[${row_index}][userdirectoryid]"`);
				const bind_password_input = row.querySelector(`[name="ldap_servers[${row_index}][bind_password]"`);

				if (userdirectoryid_input !== null) {
					popup_params['userdirectoryid'] = userdirectoryid_input.value;
				}

				if (bind_password_input !== null) {
					popup_params['bind_password'] = bind_password_input.value;
				}
			}
			else {
				while (document.querySelector(`#ldap-servers [data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					row_index,
					add_ldap_server: 1
				};
			}

			const overlay = PopUp('popup.ldap.edit', popup_params,
				{dialogueid: 'ldap_edit', dialogue_class: 'modal-popup-generic'}
			);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const ldap = {...e.detail, ...{row_index: row_index}};

				if (row === null) {
					ldap.is_default = document.getElementById('ldap-servers')
							.querySelector('input[name="ldap_default_row_index"]:checked') === null;
					ldap.usrgrps = 0;

					this.ldap_servers_table
						.querySelector('tbody')
						.appendChild(this._prepareServerRow(ldap));
				}
				else {
					ldap.is_default = row.querySelector('input[name="ldap_default_row_index"]').checked === true;
					ldap.usrgrps = row.querySelector('.js-ldap-usergroups').textContent;

					row.parentNode.insertBefore(this._prepareServerRow(ldap), row);
					row.remove();
				}
			});
		}

		_prepareServerRow(ldap) {
			const template_ldap_server_row = new Template(this.templates.ldap_servers_row);
			const template = document.createElement('template');
			template.innerHTML = template_ldap_server_row.evaluate(ldap).trim();
			const row = template.content.firstChild;

			row.querySelector('[name="ldap_default_row_index"]').toggleAttribute('checked', ldap.is_default);

			if ('provision_groups' in ldap) {
				for (const [group_index, provision_group] of Object.entries(ldap.provision_groups)) {
					for (const [name, value] of Object.entries(provision_group)) {
						if (name === 'user_groups') {
							for (const usrgrp of value) {
								const input = document.createElement('input');
								input.name = 'ldap_servers[' + ldap.row_index + '][provision_groups][' + group_index + '][user_groups][][usrgrpid]';
								input.value = usrgrp.usrgrpid;
								input.type = 'hidden';
								row.appendChild(input);
							}
						}
						else {
							const input = document.createElement('input');
							input.name = 'ldap_servers[' + ldap.row_index + '][provision_groups][' + group_index + '][' + name + ']';
							input.value = value;
							input.type = 'hidden';
							row.appendChild(input);
						}
					}
				}
			}

			if ('provision_media' in ldap) {
				for (const [group_index, media] of Object.entries(ldap.provision_media)) {
					for (const [name, value] of Object.entries(media)) {
						if (name === 'mediatype_name') {
							continue;
						}
						const input = document.createElement('input');
						input.name = 'ldap_servers[' + ldap.row_index + '][provision_media][' + group_index + '][' + name + ']';
						input.value = value;
						input.type = 'hidden';
						row.appendChild(input);
					}
				}
			}

			const optional_fields = ['userdirectoryid', 'bind_password', 'start_tls', 'search_filter'];

			for (const field of optional_fields) {
				if (!(field in ldap)) {
					row.querySelector('input[name="ldap_servers[' + ldap.row_index + '][' + field + ']"]').remove();
				}
			}

			if (ldap.usrgrps > 0) {
				row.querySelector('.js-remove').disabled = true;
				row.dataset.disable_remove = true;
			}

			return row;
		}

		_renderProvisionGroupRow(saml_provision_group) {
			saml_provision_group.user_group_names = ('user_groups' in saml_provision_group)
				? Object.values(saml_provision_group.user_groups).map(user_group => user_group.name).join(', ')
				: '';

			const template = document.createElement('template');
			const template_saml_group_row = new Template(this.templates.saml_provisioning_group_row);
			template.innerHTML = template_saml_group_row.evaluate(saml_provision_group).trim();
			const row = template.content.firstChild;

			if ('user_groups' in saml_provision_group) {
				for (const user_group of Object.values(saml_provision_group.user_groups)) {
					const input = document.createElement('input');
					input.name = 'saml_provision_groups[' + saml_provision_group.row_index + '][user_groups][][usrgrpid]';
					input.value = user_group.usrgrpid;
					input.type = 'hidden';

					row.appendChild(input);
				}
			}

			if ('roleid' in saml_provision_group) {
				const input = document.createElement('input');
				input.name = 'saml_provision_groups[' + saml_provision_group.row_index + '][roleid]';
				input.value = saml_provision_group.roleid;
				input.type = 'hidden';

				row.appendChild(input);
			}

			return row;
		}

		_renderProvisionMediaRow(saml_media) {
			const template_saml_media_mapping_row = new Template(this.templates.saml_provisioning_media_row);
			const template = document.createElement('template');

			template.innerHTML = template_saml_media_mapping_row.evaluate(saml_media).trim();

			if (saml_media.userdirectory_mediaid === undefined) {
				template.content.firstChild.querySelector('[name$="[userdirectory_mediaid]"]').remove();
			}

			return template.content.firstChild;
		}

		#addMfaMethods(mfa_methods, mfa_default_row_index) {
			for (const [row_index, mfa] of Object.entries(mfa_methods)) {
				mfa.row_index = row_index;
				mfa.is_default = (mfa.row_index == mfa_default_row_index) ? 'checked' : '';

				this.mfa_table
					.querySelector('tbody')
					.appendChild(this.#prepareMfaRow(mfa));
			}
		}

		#prepareMfaRow(mfa) {
			const template_mfa_methods_row = new Template(this.templates.mfa_methods_row);
			const template = document.createElement('template');
			template.innerHTML = template_mfa_methods_row.evaluate(mfa).trim();
			const row = template.content.firstChild;

			row.querySelector('[name="mfa_default_row_index"]').toggleAttribute('checked', mfa.is_default);

			const optional_fields = ['mfaid', 'hash_function', 'code_length', 'api_hostname', 'clientid',
				'client_secret'
			];

			for (const field of optional_fields) {
				if (!(field in mfa)) {
					row.querySelector(`input[name="mfa_methods[${mfa.row_index}][${field}]"]`).remove();
				}
			}

			if (mfa.usrgrps > 0) {
				row.querySelector('.js-remove').disabled = true;
				row.dataset.disable_remove = true;
			}

			return row;
		}

		editMfaMethod(row = null) {
			let popup_params;
			let row_index = 0;

			if (row !== null) {
				row_index = row.dataset.row_index;

				popup_params = {
					row_index,
					add_mfa_method: 0,
					type: row.querySelector(`[name="mfa_methods[${row_index}][type]"`).value,
					name: row.querySelector(`[name="mfa_methods[${row_index}][name]"`).value,
					hash_function: row.querySelector(`[name="mfa_methods[${row_index}][hash_function]"`)?.value,
					code_length: row.querySelector(`[name="mfa_methods[${row_index}][code_length]"`)?.value,
					api_hostname: row.querySelector(`[name="mfa_methods[${row_index}][api_hostname]"`)?.value,
					clientid: row.querySelector(`[name="mfa_methods[${row_index}][clientid]"`)?.value,
					client_secret: row.querySelector(`[name="mfa_methods[${row_index}][client_secret]"`)?.value
				};

				const mfaid_input = row.querySelector(`[name="mfa_methods[${row_index}][mfaid]"`);

				if (mfaid_input !== null) {
					popup_params['mfaid'] = mfaid_input.value;
				}
			}
			else {
				while (document.querySelector(`#mfa-methods [data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					row_index,
					add_mfa_method: 1
				};
			}

			const overlay = PopUp('mfa.edit', popup_params,
				{dialogueid: 'mfa_edit', dialogue_class: 'modal-popup-small'}
			);

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const mfa = {...e.detail, row_index};

				if (row === null) {
					mfa.is_default = document.getElementById('mfa-methods')
						.querySelector('[name="mfa_default_row_index"]:checked') === null;
					mfa.usrgrps = 0;

					this.mfa_table
						.querySelector('tbody')
						.appendChild(this.#prepareMfaRow(mfa));
				}
				else {
					mfa.is_default = row.querySelector('[name="mfa_default_row_index"]').checked === true;
					mfa.usrgrps = row.querySelector('.js-mfa-usergroups').textContent;

					row.parentNode.insertBefore(this.#prepareMfaRow(mfa), row);
					row.remove();
				}
			});
		}
	};
</script>
