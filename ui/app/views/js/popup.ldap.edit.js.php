<?php declare(strict_types = 0);
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

window.ldap_edit_popup = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
	}

	init({provision_groups, provision_media}) {
		this.overlay = overlays_stack.getById('ldap_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.allow_jit_chbox = document.getElementById('provision_status');
		this.provision_groups_table = document.getElementById('ldap-user-groups-table');

		this.toggleAllowJitProvisioning(this.allow_jit_chbox.checked);
		this.toggleGroupConfiguration();

		this._addEventListeners();
		this._renderProvisionGroups(provision_groups);
		this._renderProvisionMedia(provision_media);

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));
	}

	_addEventListeners() {
		this.allow_jit_chbox.addEventListener('change', (e) => {
			this.toggleAllowJitProvisioning(e.target.checked);
		});

		document.querySelector('#group-configuration').addEventListener('change', () => {
			this.toggleGroupConfiguration();
		});

		this.provision_groups_table.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add')) {
				this.editProvisionGroup();
			}
			else if (e.target.classList.contains('js-edit')) {
				this.editProvisionGroup(e.target.closest('tr'));
			}
			else if (e.target.classList.contains('js-remove')) {
				e.target.closest('tr').remove();
			}
		});

		document
			.getElementById('ldap-media-type-mapping-table')
			.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.editProvisionMediaType();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.editProvisionMediaType(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
			});

		if (document.getElementById('bind-password-btn') !== null) {
			document.getElementById('bind-password-btn').addEventListener('click', this.showPasswordField);
		}
	}

	toggleAllowJitProvisioning(checked) {
		for (const element of this.form.querySelectorAll('.allow-jit-provisioning')) {
			element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !checked);
		}

		this.toggleGroupConfiguration();
	}

	toggleGroupConfiguration() {
		if (this.allow_jit_chbox.checked) {
			const group_configuration = document.querySelector('#group-configuration input:checked').value;

			for (const element of document.querySelectorAll('.member-of')) {
				element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
					group_configuration == <?= CControllerPopupLdapEdit::LDAP_GROUP_OF_NAMES ?>
				);
			}

			for (const element of document.querySelectorAll('.group-of-names')) {
				element.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
					group_configuration == <?= CControllerPopupLdapEdit::LDAP_MEMBER_OF ?>
				);
			}
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
		let fields = {...{provision_status: <?= JIT_PROVISIONING_DISABLED ?>}, ...getFormFields(this.form)};
		fields = this.preprocessFormFields(fields);

		const test_overlay = PopUp('popup.ldap.test.edit', fields,
			{dialogueid: 'ldap_test_edit', dialogue_class: 'modal-popup-medium'}
		);
		test_overlay.xhr.then(() => this.overlay.unsetLoading());
	}

	submit() {
		this.removePopupMessages();
		this.overlay.setLoading();

		const fields = this.preprocessFormFields(getFormFields(this.form));
		const curl = new Curl(this.form.getAttribute('action'));

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

		if (fields.provision_status != <?= JIT_PROVISIONING_ENABLED ?>) {
			delete fields.group_basedn;
			delete fields.group_name;
			delete fields.group_member;
			delete fields.group_filter;
			delete fields.group_membership;
			delete fields.user_username;
			delete fields.user_lastname;
			delete fields.provision_groups;
			delete fields.provision_media;
		}

		if (fields.userdirectoryid == null) {
			delete fields.userdirectoryid;
		}

		return fields;
	}

	trimFields(fields) {
		const fields_to_trim = ['name', 'host', 'base_dn', 'bind_dn', 'search_attribute', 'search_filter',
			'description', 'group_basedn', 'group_name', 'group_member', 'group_filter', 'group_membership',
			'user_username', 'user_lastname'
		];

		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}
	}

	editProvisionGroup(row = null) {
		let popup_params = {};
		let row_index = 0;

		if (row === null) {
			while (this.provision_groups_table.querySelector(`[data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {
				add_group: 1,
				name: ''
			};
		}
		else {
			row_index = row.dataset.row_index;

			popup_params.name = row.querySelector(`[name="provision_groups[${row_index}][name]"`).value;

			const user_groups = row.querySelectorAll(
				`[name="provision_groups[${row_index}][user_groups][][usrgrpid]"`
			);
			if (user_groups.length) {
				popup_params.usrgrpid = [...user_groups].map(usrgrp => usrgrp.value);
			}

			const roleid = row.querySelector(`[name="provision_groups[${row_index}][roleid]"`);
			if (roleid) {
				popup_params.roleid = roleid.value;
			}
		}

		popup_params.idp_type = <?= IDP_TYPE_LDAP ?>;

		const overlay = PopUp('popup.usergroupmapping.edit', popup_params,
			{dialogueid: 'user_group_edit', dialogue_class: 'modal-popup-medium'}
		);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const new_row = this._renderProvisionGroupRow({...e.detail, ...{row_index}});

			if (row === null) {
				this.provision_groups_table.querySelector('tbody').appendChild(new_row);
			}
			else {
				row.replaceWith(new_row);
			}
		});
	}

	editProvisionMediaType(row = null) {
		let popup_params;
		let row_index = 0;

		if (row === null) {
			while (document.querySelector(`#ldap-media-type-mapping-table [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			popup_params = {
				add_media_type_mapping: 1
			};
		}
		else {
			row_index = row.dataset.row_index;

			popup_params = Object.fromEntries(
				[...row.querySelectorAll(`[name^="provision_media[${row_index}]"]`)].map(
					i => [i.name.match(/\[([^\]]+)\]$/)[1], i.value]
			));
		}

		const overlay = PopUp('popup.mediatypemapping.edit', popup_params,
			{dialogueid: 'media_type_mapping_edit', dialogue_class: 'modal-popup-medium'}
		);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const mapping = {...e.detail, ...{row_index: row_index}};

			if (row === null) {
				this.dialogue
					.querySelector('#ldap-media-type-mapping-table tbody')
					.appendChild(this._renderProvisionMediaRow(mapping));
			}
			else {
				row.replaceWith(this._renderProvisionMediaRow(mapping));
			}
		});
	}

	_renderProvisionGroups(groups) {
		for (const key in groups) {
			this.provision_groups_table
				.querySelector('tbody')
				.appendChild(this._renderProvisionGroupRow({...groups[key], ...{row_index: key}}));
		}
	}

	_renderProvisionGroupRow(group) {
		const attributes = {
			user_group_names: ('user_groups' in group)
				? Object.values(group.user_groups).map(user_group => user_group.name).join(', ')
				: ''
		};

		const template = document.createElement('template');
		const template_row = new Template(this._templateProvisionGroupRow());
		template.innerHTML = template_row.evaluate({...group, ...attributes}).trim();
		const row = template.content.firstChild;

		if ('user_groups' in group) {
			for (const user of Object.values(group.user_groups)) {
				const input = document.createElement('input');
				input.name = 'provision_groups[' + group.row_index + '][user_groups][][usrgrpid]';
				input.value = user.usrgrpid;
				input.type = 'hidden';

				row.appendChild(input);
			}
		}

		if ('roleid' in group) {
			const input = document.createElement('input');
			input.name = 'provision_groups[' + group.row_index + '][roleid]';
			input.value = group.roleid;
			input.type = 'hidden';

			row.appendChild(input);
		}

		return row;
	}

	_templateProvisionGroupRow() {
		return `
			<tr data-row_index="#{row_index}">
				<td>
					<a href="javascript:void(0);" class="wordwrap js-edit">#{name}</a>
					<input type="hidden" name="provision_groups[#{row_index}][name]" value="#{name}">
				</td>
				<td class="wordbreak">#{user_group_names}</td>
				<td class="wordbreak">#{role_name}</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>
		`;
	}

	_renderProvisionMedia(provision_media) {
		for (const key in provision_media) {
			document
				.querySelector('#ldap-media-type-mapping-table tbody')
				.appendChild(this._renderProvisionMediaRow({...provision_media[key], ...{row_index: key}}));
		}
	}

	_renderProvisionMediaRow(provision_media) {
		const template_ldap_media_mapping_row = new Template(`
			<tr data-row_index="#{row_index}">
				<td>
					<a href="javascript:void(0);" class="wordwrap js-edit">#{name}</a>
					<input type="hidden" name="provision_media[#{row_index}][userdirectory_mediaid]" value="#{userdirectory_mediaid}">
					<input type="hidden" name="provision_media[#{row_index}][name]" value="#{name}">
					<input type="hidden" name="provision_media[#{row_index}][mediatype_name]" value="#{mediatype_name}">
					<input type="hidden" name="provision_media[#{row_index}][mediatypeid]" value="#{mediatypeid}">
					<input type="hidden" name="provision_media[#{row_index}][attribute]" value="#{attribute}">
					<input type="hidden" name="provision_media[#{row_index}][period]" value="#{period}">
					<input type="hidden" name="provision_media[#{row_index}][severity]" value="#{severity}">
					<input type="hidden" name="provision_media[#{row_index}][active]" value="#{active}">
				</td>
				<td class="wordbreak">#{mediatype_name}</td>
				<td class="wordbreak">#{attribute}</td>
				<td>
					<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?> js-remove"><?= _('Remove') ?></button>
				</td>
			</tr>`);

		const template = document.createElement('template');
		template.innerHTML = template_ldap_media_mapping_row.evaluate(provision_media).trim();

		if (provision_media.userdirectory_mediaid === undefined) {
			template.content.firstChild.querySelector('[name$="[userdirectory_mediaid]"]').remove();
		}

		return template.content.firstChild;
	}
}();
