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
			this.template_ldap_server_row = null;
			this.template_ldap_server_row_with_password = null;
			this.form = null;
			this.db_authentication_type = null;
			this.ldap_defaultid = null;
		}

		init({ldap_servers, ldap_defaultid, db_authentication_type}) {
			this.form = document.getElementById('authentication-form');
			this.db_authentication_type = db_authentication_type;
			this.ldap_defaultid = ldap_defaultid;

			this._initTemplates();
			this._addEventListeners();

			// Parse LDAP servers.
			for (const ldap of ldap_servers) {
				// TODO VM: why this needs to be checked for each entry, instead of using them sequencively.
				ldap.row_index = 0;

				while (document.querySelector(`#ldap-servers [data-row_index="${ldap.row_index}"]`) !== null) {
					ldap.row_index++;
				}

				this.appendLdapServer(ldap);
			}
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
						e.target.closest('tr').remove();
					}
				});

			document.getElementById('http_auth_enabled').addEventListener('change', (e) => {
				this.form.querySelectorAll('[name^=http_]').forEach(field => {
					if (!field.isSameNode(e.target)) {
						field.disabled = !e.target.checked;
					}
				});
			});

			document.getElementById('saml_auth_enabled').addEventListener('change', (e) => {
				this.form.querySelectorAll('[name^=saml_]').forEach(field => {
					if (!field.isSameNode(e.target)) {
						field.disabled = !e.target.checked;
					}
				});
			});

			this.form.addEventListener('submit', this.authFormSubmit);
		}

		authFormSubmit() {
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

		appendLdapServer(ldap) {
			ldap.is_default = (ldap.userdirectoryid == this.ldap_defaultid) ? 'checked' : '';
			const template = "bind_password" in ldap
				? this.template_ldap_server_row_with_password
				: this.template_ldap_server_row;

			document
				.querySelector('#ldap-servers tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(ldap));
		}

		editLdapServer(row = null) {
			let popup_params;

			if (row !== null) {
				const row_index = row.dataset.row_index;

				popup_params = {
					row_index,
					add_ldap_server: 0,
					name: row.querySelector(`[name="ldap_server[${row_index}][name]"`).value,
					host: row.querySelector(`[name="ldap_server[${row_index}][host]"`).value,
					port: row.querySelector(`[name="ldap_server[${row_index}][port]"`).value,
					base_dn: row.querySelector(`[name="ldap_server[${row_index}][base_dn]"`).value,
					search_attribute: row.querySelector(`[name="ldap_server[${row_index}][search_attribute]"`).value,
					userfilter: row.querySelector(`[name="ldap_server[${row_index}][userfilter]"`).value,
					start_tls: row.querySelector(`[name="ldap_server[${row_index}][start_tls]"`).checked ? 1 : 0,
					bind_dn: row.querySelector(`[name="ldap_server[${row_index}][bind_dn]"`).value,
					case_sensitive: row.querySelector(`[name="ldap_server[${row_index}][case_sensitive]"`).checked
						? 1
						: 0,
					description: row.querySelector(`[name="ldap_server[${row_index}][description]"`).value
				};

				const userdirecotryid_input = row.querySelector(`[name="ldap_server[${row_index}][userdirectoryid]"`);
				const bind_password_input = row.querySelector(`[name="ldap_server[${row_index}][bind_password]"`);

				if (userdirecotryid_input !== null) {
					popup_params['userdirectoryid'] = userdirecotryid_input.value;
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

			popup_params['ldap_configured'] = document.getElementById('ldap_configured').checked ? 1 : 0;

			const overlay = PopUp('popup.ldap.edit', popup_params, {dialogueid: 'ldap_edit'});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				const template = "bind_password" in e.detail
					? this.template_ldap_server_row_with_password
					: this.template_ldap_server_row;

				if (row === null) {
					document
						.querySelector('#ldap-servers tbody')
						.insertAdjacentHTML('beforeend', template.evaluate(e.detail));
				}
				else {
					e.detail.is_default = (e.detail.userdirectoryid == this.ldap_defaultid) ? 'checked' : '';
					e.detail.user_groups = row.querySelector('.js-ldap-usergroups').textContent;

					row.insertAdjacentHTML('afterend', template.evaluate(e.detail));
					row.remove();
				}
			});
		}

		_initTemplates() {
			this.template_ldap_server_row = new Template(this._templateLdapServerRow());
			this.template_ldap_server_row_with_password = new Template(this._templateLdapServerRow(true));
		}

		_templateLdapServerRow(has_bind_password = false) {
			return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="#" class="wordwrap js-edit">#{name}</a>
						<input type="hidden" name="ldap_server[#{row_index}][userdirectoryid]" value="#{userdirectoryid}">
						<input type="hidden" name="ldap_server[#{row_index}][name]" value="#{name}">
						<input type="hidden" name="ldap_server[#{row_index}][host]" value="#{host}">
						<input type="hidden" name="ldap_server[#{row_index}][port]" value="#{port}">
						<input type="hidden" name="ldap_server[#{row_index}][base_dn]" value="#{base_dn}">
						<input type="hidden" name="ldap_server[#{row_index}][search_attribute]" value="#{search_attribute}">
						<input type="hidden" name="ldap_server[#{row_index}][userfilter]" value="#{userfilter}">
						<input type="hidden" name="ldap_server[#{row_index}][start_tls]" value="#{start_tls}">
						<input type="hidden" name="ldap_server[#{row_index}][bind_dn]" value="#{bind_dn}">` +
						(has_bind_password
							? `<input type="hidden" name="ldap_server[#{row_index}][bind_password]" value="#{bind_password}">`
							: '') + `
						<input type="hidden" name="ldap_server[#{row_index}][case_sensitive]" value="#{case_sensitive}">
						<input type="hidden" name="ldap_server[#{row_index}][description]" value="#{description}">
					</td>
					<td>#{host}</td>
					<td class="js-ldap-usergroups">#{user_groups}</td>
					<td>
						<input type="radio" name="ldap_defaultid" value="#{userdirectoryid}" #{is_default}>
					</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
		}
	};
</script>
