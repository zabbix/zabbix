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

window.ldap_edit_popup = new class {

	#overlay;
	#dialogue;
	#form_element;
	#form;

	init({rules, provision_groups, provision_media}) {
		this.#overlay = overlays_stack.getById('ldap_edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.allow_jit_chbox = document.getElementById('provision_status');
		this.provision_groups_table = document.getElementById('ldap-user-groups-table');

		this.toggleAllowJitProvisioning(this.allow_jit_chbox.checked);
		this.toggleGroupConfiguration();

		this._addEventListeners();
		this._renderProvisionGroups(provision_groups);
		this._renderProvisionMedia(provision_media);

		new CFormFieldsetCollapsible(document.getElementById('advanced-configuration'));

		this.#form.discoverAllFields();
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
			document.getElementById('host').addEventListener('change', this.showPasswordFieldWithWarning.bind(this));
		}

		this.#overlay.$dialogue.$footer[0].querySelector('.js-submit')
			.addEventListener('click', () => this.#submit());

		this.#overlay.$dialogue.$footer[0].querySelector('.js-test')
			.addEventListener('click', () => this.#openTestPopup());
	}

	toggleAllowJitProvisioning(checked) {
		for (const element of this.#form_element.querySelectorAll('.allow-jit-provisioning')) {
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

	showPasswordField() {
		const button = document.getElementById('bind-password-btn');

		const form_field = button.parentNode;
		const password_field = form_field.querySelector('[name="bind_password"][type="password"]');
		const password_var = form_field.querySelector('[name="bind_password"][type="hidden"]');

		password_field.style.display = '';
		password_field.disabled = false;

		if (password_var !== null) {
			form_field.removeChild(password_var);
		}
		form_field.removeChild(button);
	}

	showPasswordFieldWithWarning() {
		if (document.getElementById('bind-password-btn')) {
			this.showPasswordField();
			document.querySelector('.js-bind-password-warning').style.display = '';
		}
	}

	#openTestPopup() {
		const test_fields = ['host', 'port', 'base_dn', 'search_attribute', 'provision_status',
			'provision_groups', 'provision_media'
		];

		test_fields.forEach(fieldname => {
			this.#form.findFieldByName(fieldname).setChanged();
		});

		this.#form.validateFieldsForAction(test_fields).then((result) => {
			if (!result) {
				this.#overlay.unsetLoading();
				return;
			}

			const fields = this.#form.getAllValues();

			const test_overlay = PopUp('popup.ldap.test.edit', fields,
				{dialogueid: 'ldap_test_edit', dialogue_class: 'modal-popup-medium'}
			);
			test_overlay.xhr.then(() => this.#overlay.unsetLoading());
		});
	}

	#submit() {
		this.#removePopupMessages();
		this.#overlay.setLoading();
		const fields = this.#form.getAllValues();

		this.#form.validateSubmit(fields).then(result => {
			if (!result) {
				this.#overlay.unsetLoading();

				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'popup.ldap.check');

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
						this.#form.setErrors(response.form_errors, true, true);
						this.#form.renderErrors();

						return;
					}

					overlayDialogueDestroy(this.#overlay.dialogueid);

					this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
				})
				.catch((exception) => this.#ajaxExceptionHandler(exception))
				.finally(() => {
					this.#overlay.unsetLoading();
				});
		});
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
				name: '',
				existing_names: this.#getExistingNames('provision_groups')
			};
		}
		else {
			row_index = row.dataset.row_index;

			popup_params.name = row.querySelector(`[name="provision_groups[${row_index}][name]"`).value;

			const user_groups = row.querySelectorAll(
				`[name="provision_groups[${row_index}][user_groups][]"`
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
		popup_params.existing_names = this.#getExistingNames('provision_groups', row_index);

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
				this.#dialogue
					.querySelector('#ldap-media-type-mapping-table tbody')
					.appendChild(this._renderProvisionMediaRow(mapping));
			}
			else {
				row.replaceWith(this._renderProvisionMediaRow(mapping));
			}
		});
	}

	_renderProvisionGroups(groups) {
		for (const [key, group] of Object.entries(groups)) {
			this.provision_groups_table
				.querySelector('tbody')
				.appendChild(this._renderProvisionGroupRow({...group, ...{row_index: key}}));
		}
	}

	_renderProvisionGroupRow(group) {
		const attributes = {
			user_group_names: ('user_groups' in group)
				? Object.values(group.user_groups).map(user_group => user_group.name).join(', ')
				: ''
		};

		const template = document.createElement('template');
		const template_row = new Template(document.getElementById('ldap-user-groups-row-tmpl').innerHTML);
		template.innerHTML = template_row.evaluate({...group, ...attributes}).trim();
		const row = template.content.firstChild;

		if ('user_groups' in group) {
			const div = row.querySelector(`#provision-groups-${group.row_index}-user-groups`);

			for (const user of Object.values(group.user_groups)) {
				const input = document.createElement('input');
				input.name = 'provision_groups[' + group.row_index + '][user_groups][]';
				input.value = user.usrgrpid;
				input.type = 'hidden';
				input.setAttribute('data-field-type', 'hidden');

				div.appendChild(input);
			}
		}

		if ('roleid' in group) {
			const input = document.createElement('input');
			input.name = 'provision_groups[' + group.row_index + '][roleid]';
			input.value = group.roleid;
			input.type = 'hidden';
			input.setAttribute('data-field-type', 'hidden');

			row.appendChild(input);
		}

		return row;
	}

	_renderProvisionMedia(provision_media) {
		for (const [key, media] of Object.entries(provision_media)) {
			document
				.querySelector('#ldap-media-type-mapping-table tbody')
				.appendChild(this._renderProvisionMediaRow({...media, ...{row_index: key}}));
		}
	}

	_renderProvisionMediaRow(provision_media) {
		const template_ldap_media_mapping_row = new Template(
			document.getElementById('ldap-media-type-mapping-tmpl').outerHTML
		);

		const template = template_ldap_media_mapping_row.evaluateToElement(provision_media);

		if (provision_media.userdirectory_mediaid === undefined) {
			template.content.querySelector('[name$="[userdirectory_mediaid]"]').remove();
		}

		return template.content;
	}

	#getExistingNames(fieldname, exclude_row_index = null) {
		const fields = this.#form.getAllValues();
		const result = [];

		if (fieldname in fields && typeof fields[fieldname] === 'object') {
			Object.entries(fields[fieldname]).forEach(([key, row]) => {
				if (key !== exclude_row_index) {
					result.push(row.name);
				}
			});
		}

		return result;
	}

	#removePopupMessages() {
		for (const el of this.#form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
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

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}
};
