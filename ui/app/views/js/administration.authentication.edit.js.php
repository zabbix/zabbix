<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	const view = new class {

		constructor() {
			this.form = null;
			this.db_authentication_type = null;
			this.allow_scim = null;
		}

		init({ldap_servers, ldap_default_row_index, db_authentication_type, saml_groups, saml_media_type_mappings}) {
			this.form = document.getElementById('authentication-form');
			this.db_authentication_type = db_authentication_type;
			this.allow_scim = document.getElementById('saml_allow_scim');

			this._addEventListeners();
			this._addLdapServers(ldap_servers, ldap_default_row_index);
			this._addSamlUserGroups(saml_groups);
			this._addSamlMediaTypeMapping(saml_media_type_mappings);

			this.toggleScimProvisioning(this.allow_scim.checked);
		}

		_addEventListeners() {
			document
				.getElementById('ldap-servers')
				.addEventListener('click', (e) => {
					if (e.target.classList.contains('js-add')) {
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

			document.getElementById('http_auth_enabled').addEventListener('change', (e) => {
				this.form.querySelectorAll('[name^=http_]').forEach(field => {
					if (!field.isSameNode(e.target)) {
						field.disabled = !e.target.checked;
					}
				});
			});

			if (document.getElementById('saml_auth_enabled') !== null) {
				document.getElementById('saml_auth_enabled').addEventListener('change', (e) => {
					this.form.querySelectorAll('[name^=saml_]').forEach(field => {
						if (!field.isSameNode(e.target)) {
							field.disabled = !e.target.checked;
						}
					});
				});
			}

			this.allow_scim.addEventListener('change', (e) => {
				this.toggleScimProvisioning(e.target.checked);
			});

			document
				.getElementById('saml-group-table')
				.addEventListener('click', (e) => {
					if (e.target.classList.contains('js-add')) {
						this.editSamlUserGroup();
					}
					else if (e.target.classList.contains('js-edit')) {
						this.editSamlUserGroup(e.target.closest('tr'));
					}
					else if (e.target.classList.contains('js-remove')) {
						const table = e.target.closest('table');

						e.target.closest('tr').remove()
					}
				});

			document
				.getElementById('saml-media-type-mapping-table')
				.addEventListener('click', (e) => {
					if (e.target.classList.contains('js-add')) {
						this.editSamlMediaTypeMapping();
					}
					else if (e.target.classList.contains('js-edit')) {
						this.editSamlMediaTypeMapping(e.target.closest('tr'));
					}
					else if (e.target.classList.contains('js-remove')) {
						const table = e.target.closest('table');

						e.target.closest('tr').remove()
					}
				});

			this.form.addEventListener('submit', (e) => {
				if (!this._authFormSubmit()) {
					e.preventDefault();
				}
			});
		}

		_authFormSubmit() {
			const fields_to_trim = ['#saml_idp_entityid', '#saml_sso_url', '#saml_slo_url', '#saml_username_attribute',
				'#saml_sp_entityid', '#saml_nameid_format'
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

				document
						.querySelector('#ldap-servers tbody')
						.appendChild(this._prepareServerRow(ldap));
			}
		}

		_addSamlUserGroups(saml_groups) {
			for (const key in saml_groups) {

				document
					.querySelector('#saml-group-table tbody')
					.appendChild(this._prepareSamlGroupRow(saml_groups[key]));
			}
		}

		_addSamlMediaTypeMapping(saml_media_type_mappings) {
			for (const key in saml_media_type_mappings) {

				document
					.querySelector('#saml-media-type-mapping-table tbody')
					.appendChild(this._prepareSamlMediaTypeRow(saml_media_type_mappings[key]));
			}
		}

		editSamlUserGroup(row = null) {
			let popup_params;

			if (row != null) {
				const row_index = row.dataset.row_index;

				popup_params = {
					idp_group_name: row.querySelector(`[name="saml_groups[${row_index}][idp_group_name]"`).value,
					usrgrpid: row.querySelector(`[name="saml_groups[${row_index}][usrgrpid]"`).value,
					roleid: row.querySelector(`[name="saml_groups[${row_index}][roleid]"`).value,
					name_label: t('SAML group pattern')
				};
			}
			else {
				popup_params = {
					add_group: 1,
					name_label: t('SAML group pattern')
				};
			}

			const overlay = PopUp('popup.usergroupmapping.edit', popup_params, {dialogueid: 'saml_group_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const saml_group = e.detail;

				if (row === null) {
					document
						.querySelector('#saml-group-table tbody')
						.appendChild(this._prepareSamlGroupRow(saml_group));
				}
				else {
					row.parentNode.insertBefore(this._prepareSamlGroupRow(saml_group), row);
					row.remove();
				}
			});
		}

		editSamlMediaTypeMapping(row = null) {
			let popup_params;

			if (row != null) {
				const row_index = row.dataset.row_index;

				popup_params = {
					media_type_mapping_name: row.querySelector(`[name="saml_media_mapping[${row_index}][media_type_mapping_name]"`).value,
					media_type_name: row.querySelector(`[name="saml_media_mapping[${row_index}][media_type_name]"`).value,
					media_type_attribute: row.querySelector(`[name="saml_media_mapping[${row_index}][media_type_attribute]"`).value,
					mediatypeid: row.querySelector(`[name="saml_media_mapping[${row_index}][mediatypeid]"`).value
				};
			}
			else {
				popup_params = {
					add_media_type_mapping: 1
				};
			}

			const overlay = PopUp('popup.mediatypemapping.edit', popup_params, {dialogueid: 'saml_media_type_mapping_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const saml_media_type_mapping = e.detail;

				if (row === null) {
					document
						.querySelector('#saml-media-type-mapping-table tbody')
						.appendChild(this._prepareSamlMediaTypeRow(saml_media_type_mapping));
				}
				else {
					row.parentNode.insertBefore(this._prepareSamlMediaTypeRow(saml_media_type_mapping), row);
					row.remove();
				}
			});
		}

		editLdapServer(row = null) {
			let popup_params;

			if (row !== null) {
				const row_index = row.dataset.row_index;

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
					description: row.querySelector(`[name="ldap_servers[${row_index}][description]"`).value
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
				let row_index = 0;

				while (document.querySelector(`#ldap-servers [data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {
					row_index,
					add_ldap_server: 1
				};
			}

			const overlay = PopUp('popup.ldap.edit', popup_params, {dialogueid: 'ldap_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const ldap = e.detail;

				if (row === null) {
					ldap.is_default = document.getElementById('ldap-servers')
							.querySelector('input[name="ldap_default_row_index"]:checked') === null
						? 'checked'
						: '';
					ldap.usrgrps = 0;

					document
						.querySelector('#ldap-servers tbody')
						.appendChild(this._prepareServerRow(ldap));
				}
				else {
					ldap.is_default = row.querySelector('input[name="ldap_default_row_index"]').checked === true
						? 'checked'
						: '';
					ldap.usrgrps = row.querySelector('.js-ldap-usergroups').textContent;

					row.parentNode.insertBefore(this._prepareServerRow(ldap), row);
					row.remove();
				}
			});
		}

		_prepareServerRow(ldap) {
			const template_ldap_server_row = new Template(this._templateLdapServerRow());
			const template = document.createElement('template');
			template.innerHTML = template_ldap_server_row.evaluate(ldap).trim();
			const row = template.content.firstChild;

			const optional_fields = ['userdirectoryid', 'bind_password', 'start_tls', 'search_filter'];

			for (const field of optional_fields) {
				if (!(field in ldap)) {
					row.querySelector('input[name="ldap_servers[' + ldap.row_index + '][' + field + ']"]').remove();
				}
			}

			if (ldap.usrgrps > 0) {
				row.querySelector('.js-remove').disabled = true;
			}

			return row;
		}

		_prepareSamlGroupRow(saml_group) {
			const template_saml_group_row = new Template(this._templateSamlGroupRow());
			const template = document.createElement('template');

			template.innerHTML = template_saml_group_row.evaluate(saml_group).trim();

			return template.content.firstChild;
		}

		_prepareSamlMediaTypeRow(saml_media_mapping) {
			const template_saml_media_mapping_row = new Template(this._templateSamlMediaMappingRow());
			const template = document.createElement('template');

			template.innerHTML = template_saml_media_mapping_row.evaluate(saml_media_mapping).trim();

			return template.content.firstChild;
		}

		_templateLdapServerRow() {
			return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{name}</a>
						<input type="hidden" name="ldap_servers[#{row_index}][userdirectoryid]" value="#{userdirectoryid}">
						<input type="hidden" name="ldap_servers[#{row_index}][name]" value="#{name}">
						<input type="hidden" name="ldap_servers[#{row_index}][host]" value="#{host}">
						<input type="hidden" name="ldap_servers[#{row_index}][port]" value="#{port}">
						<input type="hidden" name="ldap_servers[#{row_index}][base_dn]" value="#{base_dn}">
						<input type="hidden" name="ldap_servers[#{row_index}][search_attribute]" value="#{search_attribute}">
						<input type="hidden" name="ldap_servers[#{row_index}][search_filter]" value="#{search_filter}">
						<input type="hidden" name="ldap_servers[#{row_index}][start_tls]" value="#{start_tls}">
						<input type="hidden" name="ldap_servers[#{row_index}][bind_dn]" value="#{bind_dn}">
						<input type="hidden" name="ldap_servers[#{row_index}][bind_password]" value="#{bind_password}">
						<input type="hidden" name="ldap_servers[#{row_index}][description]" value="#{description}">
					</td>
					<td class="wordbreak">#{host}</td>
					<td class="js-ldap-usergroups">#{usrgrps}</td>
					<td>
						<input type="radio" name="ldap_default_row_index" value="#{row_index}" #{is_default}>
					</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}

		_templateSamlGroupRow() {
			return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{idp_group_name}</a>
						<input type="hidden" name="saml_groups[#{row_index}][idp_group_name]" value="#{idp_group_name}">
						<input type="hidden" name="saml_groups[#{row_index}][usrgrpid]" value="#{usrgrpid}">
						<input type="hidden" name="saml_groups[#{row_index}][roleid]" value="#{roleid}">
					</td>
					<td class="">#{user_group_name}</td>
					<td class="">#{role_name}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}

		_templateSamlMediaMappingRow() {
			return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{media_type_mapping_name}</a>
						<input type="hidden" name="saml_media_mapping[#{row_index}][media_type_mapping_name]" value="#{media_type_mapping_name}">
						<input type="hidden" name="saml_media_mapping[#{row_index}][media_type_name]" value="#{media_type_name}">
						<input type="hidden" name="saml_media_mapping[#{row_index}][mediatypeid]" value="#{mediatypeid}">
						<input type="hidden" name="saml_media_mapping[#{row_index}][media_type_attribute]" value="#{media_type_attribute}">
					</td>
					<td class="">#{media_type_name}</td>
					<td class="">#{media_type_attribute}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}

		toggleScimProvisioning(checked) {
			for (const element of this.form.querySelectorAll('.saml-allow-scim')) {
				element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
			}
		}
	};
</script>
