<?php declare(strict_types = 0);
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


?>

window.ldap_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
		this.advanced_chbox = null;
	}

	init({ldap_user_groups, ldap_media_type_mappings}) {
		this.overlay = overlays_stack.getById('ldap_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.advanced_chbox = document.getElementById('advanced_configuration');
		this.allow_jit_chbox = document.getElementById('allow_jit_provisioning');

		this.toggleAdvancedConfiguration(this.advanced_chbox.checked);
		this.toggleAllowJitProvisioning(this.allow_jit_chbox.checked);

		this._addEventListeners();
		this._addLdapUserGroups(ldap_user_groups);
		this._addLdapMediaTypeMapping(ldap_media_type_mappings);
		this.initSortable(document.getElementById('ldap-user-groups-table'));

		if (document.getElementById('bind-password-btn') !== null) {
			document.getElementById('bind-password-btn').addEventListener('click', this.showPasswordField);
		}
	}

	_addEventListeners() {
		this.advanced_chbox.addEventListener('change', (e) => {
			this.toggleAdvancedConfiguration(e.target.checked);
		});

		this.allow_jit_chbox.addEventListener('change', (e) => {
			this.toggleAllowJitProvisioning(e.target.checked);
		})

		document
			.getElementById('ldap-user-groups-table')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editLdapUserGroup();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editLdapUserGroup(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove()
				}
				else if (e.target.classList.contains('js-enabled')) {
					this.toggleFallbackStatus('off', e.target.closest('td'));
				}
				else if (e.target.classList.contains('js-disabled')) {
					this.toggleFallbackStatus('on', e.target.closest('td'));
				}
			});

		document
			.getElementById('ldap-media-type-mapping-table')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editLdapMediaTypeMapping();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editLdapMediaTypeMapping(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove()
				}
			});
	}

	toggleAdvancedConfiguration(checked) {
		for (const element of this.form.querySelectorAll('.advanced-configuration')) {
			element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
		}
	}

	toggleAllowJitProvisioning(checked) {
		for (const element of this.form.querySelectorAll('.allow-jit-provisioning')) {
			element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
		}
	}

	toggleFallbackStatus(action, target) {
		const new_action = document.createElement('td');
		if (action === 'on') {
			new_action.innerHTML = '<button type="button" class="<?= ZBX_STYLE_BTN_LINK . ' ' . ZBX_STYLE_GREEN?> js-enabled"><?= _('Enabled') ?></button>';
			new_action.innerHTML += '<input type="hidden" name="ldap_groups[#{row_index}][fallback_status]" value="1">';
		}
		else if (action === 'off') {
			new_action.innerHTML = '<button type="button" class="<?= ZBX_STYLE_BTN_LINK . ' ' . ZBX_STYLE_RED?> js-disabled"><?= _('Disabled') ?></button>';
			new_action.innerHTML += '<input type="hidden" name="ldap_groups[#{row_index}][fallback_status]" value="0">';
		}
		target.replaceWith(new_action);
	}

	initSortable(element) {
		const is_disabled = element.querySelectorAll('tr.sortable').length < 2;

		$(element).sortable({
			disabled: is_disabled,
			items: 'tbody tr.sortable',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			helper: function(e, ui) {
				for (let td of ui.find('>td')) {
					let $td = $(td);
					$td.attr('width', $td.width())
				}

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				return ui;
			},
			stop: function(e, ui) {
				ui.item.find('>td').removeAttr('width');
				ui.item.removeAttr('style');
			},
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		for (const drag_icon of element.querySelectorAll('div.<?= ZBX_STYLE_DRAG_ICON ?>')) {
			drag_icon.classList.toggle('<?= ZBX_STYLE_DISABLED ?>', is_disabled);
		}
	}

	showPasswordField(e) {
		const form_field = e.target.parentNode;
		const password_field = form_field.querySelector('[name="bind_password"][type="password"]');
		const password_var = form_field.querySelector('[name="bind_password"][type="hidden"]');

		password_field.style.display = '';
		password_field.disabled = false;

		if (password_var !== null) {
			form_field.removeChild(password_var);
		}
		form_field.removeChild(e.target);
	}

	openTestPopup() {
		const fields = this.preprocessFormFields(getFormFields(this.form));

		const popup_params = {
			host: fields.host,
			port: fields.port,
			base_dn: fields.base_dn,
			bind_dn: fields.bind_dn,
			search_attribute: fields.search_attribute
		};

		const optional_fields = ['userdirectoryid', 'bind_password', 'start_tls', 'search_filter'];

		for (const field of optional_fields) {
			if (fields[field] !== undefined) {
				popup_params[field] = fields[field];
			}
		}

		const test_overlay = PopUp('popup.ldap.test.edit', popup_params, {dialogueid: 'ldap_test_edit'});
		test_overlay.xhr.then(() => this.overlay.unsetLoading());
	}

	submit() {
		this.removePopupMessages();
		this.overlay.setLoading();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'), false);

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

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
			})
			.catch((exception) => {
				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
				}

				const message_box = makeMessageBox('bad', messages, title, true, true)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	preprocessFormFields(fields) {
		this.trimFields(fields);

		if (fields.advanced_configuration != 1) {
			delete fields.start_tls;
			delete fields.search_filter;
		}

		delete fields.advanced_configuration;

		return fields;
	}

	trimFields(fields) {
		const fields_to_trim = ['name', 'host', 'base_dn', 'bind_dn', 'search_attribute', 'search_filter',
			'description'];
		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}
	}

	editLdapUserGroup(row = null) {
		let popup_params;

		if (row != null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				idp_group_name: row.querySelector(`[name="ldap_groups[${row_index}][idp_group_name]"`).value,
				usrgrpid: row.querySelector(`[name="ldap_groups[${row_index}][usrgrpid]"`).value,
				roleid: row.querySelector(`[name="ldap_groups[${row_index}][roleid]"`).value,
				is_fallback: row.querySelector(`[name="ldap_groups[${row_index}][is_fallback]"`).value
			};
		}
		else {
			popup_params = {
				add_group: 1
			};
		}

		popup_params.name_label = t('LDAP group pattern');

		const overlay = PopUp('popup.usergroupmapping.edit', popup_params, {dialogueid: 'user_group_edit'});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const ldap_user_group = e.detail;

			if (row === null) {
				document
					.querySelector('#ldap-user-groups-table tbody')
					.appendChild(this._prepareLdapUserGroupRow(ldap_user_group));
			}
			else {
				row.parentNode.insertBefore(this._prepareLdapUserGroupRow(ldap_user_group), row);
				row.remove();
			}
		});
	}

	editLdapMediaTypeMapping(row = null) {
		let popup_params;

		if (row != null) {
			const row_index = row.dataset.row_index;

			popup_params = {
				media_type_mapping_name: row.querySelector(`[name="ldap_media_mapping[${row_index}][media_type_mapping_name]"`).value,
				media_type_name: row.querySelector(`[name="ldap_media_mapping[${row_index}][media_type_name]"`).value,
				media_type_attribute: row.querySelector(`[name="ldap_media_mapping[${row_index}][media_type_attribute]"`).value,
				mediatypeid: row.querySelector(`[name="ldap_media_mapping[${row_index}][mediatypeid]"`).value
			};
		}
		else {
			popup_params = {
				add_media_type_mapping: 1
			};
		}

		const overlay = PopUp('popup.mediatypemapping.edit', popup_params, {dialogueid: 'media_type_mapping_edit'});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const ldap_media_type_mapping = e.detail;

			if (row === null) {
				document
					.querySelector('#ldap-media-type-mapping-table tbody')
					.appendChild(this._prepareLdapMediaTypeRow(ldap_media_type_mapping));
			}
			else {
				row.parentNode.insertBefore(this._prepareLdapMediaTypeRow(ldap_media_type_mapping), row);
				row.remove();
			}
		});
	}

	_addLdapUserGroups(ldap_user_groups) {
		for (const key in ldap_user_groups) {

			document
				.querySelector('#ldap-user-groups-table tbody')
				.appendChild(this._prepareLdapUserGroupRow(ldap_user_groups[key]));
		}
	}

	_prepareLdapUserGroupRow(ldap_user_group) {
		const template_ldap_user_group_row = new Template(this._templateLdapUserGroupRow());
		const template = document.createElement('template');

		if (ldap_user_group.is_fallback == true) {
			if (ldap_user_group.fallback_status == 1) {
				ldap_user_group.action = t('Enabled');
				ldap_user_group.action_class = 'js-enabled green';
			}
			else {
				ldap_user_group.action = t('Disabled');
				ldap_user_group.action_class = 'js-disabled red';
			}
		}
		else {
			ldap_user_group.action = t('Remove');
			ldap_user_group.action_class = 'js-remove';
		}

		template.innerHTML = template_ldap_user_group_row.evaluate(ldap_user_group).trim();

		return template.content.firstChild
	}

	_addLdapMediaTypeMapping(ldap_media_type_mappings) {
		for (const key in ldap_media_type_mappings) {

			document
				.querySelector('#ldap-media-type-mapping-table tbody')
				.appendChild(this._prepareLdapMediaTypeRow(ldap_media_type_mappings[key]));
		}
	}

	_prepareLdapMediaTypeRow(ldap_media_mapping) {
		const template_ldap_media_mapping_row = new Template(this._templateLdapMediaMappingRow());
		const template = document.createElement('template');

		template.innerHTML = template_ldap_media_mapping_row.evaluate(ldap_media_mapping).trim();

		return template.content.firstChild;
	}

	_templateLdapUserGroupRow() {
		return `
				<tr data-row_index="#{row_index}" class="sortable">
					<td class="td-drag-icon">
						<div class="drag-icon ui-sortable-handle"></div>
					</td>
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{idp_group_name}</a>
						<input type="hidden" name="ldap_groups[#{row_index}][idp_group_name]" value="#{idp_group_name}">
						<input type="hidden" name="ldap_groups[#{row_index}][usrgrpid]" value="#{usrgrpid}">
						<input type="hidden" name="ldap_groups[#{row_index}][roleid]" value="#{roleid}">
						<input type="hidden" name="ldap_groups[#{row_index}][is_fallback]" value="#{is_fallback}">
						<input type="hidden" name="ldap_groups[#{row_index}][fallback_status]" value="#{fallback_status}">
					</td>
					<td class="wordbreak">#{user_group_name}</td>
					<td class="wordbreak">#{role_name}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> #{action_class}">#{action}</button>
					</td>
				</tr>
			`;
	}

	_templateLdapMediaMappingRow() {
		return `
				<tr data-row_index="#{row_index}">
					<td>
						<a href="javascript:void(0);" class="wordwrap js-edit">#{media_type_mapping_name}</a>
						<input type="hidden" name="ldap_media_mapping[#{row_index}][media_type_mapping_name]" value="#{media_type_mapping_name}">
						<input type="hidden" name="ldap_media_mapping[#{row_index}][media_type_name]" value="#{media_type_name}">
						<input type="hidden" name="ldap_media_mapping[#{row_index}][mediatypeid]" value="#{mediatypeid}">
						<input type="hidden" name="ldap_media_mapping[#{row_index}][media_type_attribute]" value="#{media_type_attribute}">
					</td>
					<td class="wordbreak">#{media_type_name}</td>
					<td class="wordbreak">#{media_type_attribute}</td>
					<td>
						<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
					</td>
				</tr>
			`;
	}
}();
