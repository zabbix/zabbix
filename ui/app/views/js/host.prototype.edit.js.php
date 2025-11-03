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

window.host_prototype_edit_popup = new class {
	init({rules, group_prototypes, inherited_interfaces, custom_interfaces, parent_is_template, parent_hostid, readonly,
			warnings}) {
		this.overlay = overlays_stack.getById('host.prototype.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.group_prototypes = group_prototypes;
		this.interfaces_table = document.getElementById('interfaces_table');
		this.inherited_interfaces = inherited_interfaces;
		this.custom_interfaces = custom_interfaces;
		this.interfaces_radio_btn = document.getElementById('custom_interfaces');
		this.host_interface_row_tmpl = document.getElementById('host-interface-row-tmpl').innerHTML;
		this.host_interface_row_snmp_tmpl = document.getElementById('host-interface-row-snmp-tmpl').innerHTML;
		this.parent_is_template = parent_is_template;
		this.readonly = readonly;
		this.add_interface_btn = this.form_element.querySelector('.add-interface');
		this.all_templateids = null;
		this.show_inherited_tags = false;
		this.tags_table = this.form_element.querySelector('.tags-table');
		this.show_inherited_macros = false;
		this.parent_hostid = parent_hostid;

		if (warnings.length) {
			const message_box = warnings.length == 1
				? makeMessageBox('warning', warnings, null, true, false)[0]
				: makeMessageBox('warning', warnings,
					<?= json_encode(_('Cloned host parameter values have been modified.')) ?>, true, false
				)[0];

			this.form_element.parentNode.insertBefore(message_box, this.form_element);
		}

		this.#initHostTab();
		this.#initTagsTab();
		this.#initMacrosTab();

		this.initial_form_fields = getFormFields(this.form_element);

		const return_url = Object.assign(new URL('zabbix.php', location.href), {
			search: new URLSearchParams({
				action: 'host.prototype.list',
				context: this.initial_form_fields.context,
				parent_discoveryid: this.initial_form_fields.parent_discoveryid
			})
		});

		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#initPopupListeners();
	}

	#initPopupListeners() {
		const subscriptions = [];

		for (const action of ['template.edit', 'host.prototype.edit']) {
			subscriptions.push(
				ZABBIX.EventHub.subscribe({
					require: {
						context: CPopupManager.EVENT_CONTEXT,
						event: CPopupManagerEvent.EVENT_OPEN,
						action
					},
					callback: ({event, data}) => {
						const can_proceed = data.action_parameters.clone == 1 || this.#isConfirmed();

						if (!can_proceed) {
							event.preventDefault();
						}
					}
				})
			);
		}

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	}

	#initHostTab() {
		const host_field = this.form_element.querySelector('#host');

		['input', 'paste'].forEach(event_type => {
			host_field.addEventListener(event_type, e => this.#setVisibleNamePlaceholder(e.target.value));
		});

		this.#setVisibleNamePlaceholder(host_field.value);

		const $groups_ms = $('#groups_, #group_links_');
		const $template_ms = $('#add_templates_');

		$template_ms.on('change', () => $template_ms.multiSelect('setDisabledEntries', this.#getAllTemplates()));
		$groups_ms.on('change', () =>
			$groups_ms.multiSelect('setDisabledEntries',
				[...this.form_element.querySelectorAll('[name^="groups["], [name^="group_links["]')]
					.map(input => input.value)
			)
		);

		this.form_element.addEventListener('click', e => {
			if (e.target.classList.contains('js-unlink')) {
				this.#unlinkTemplate(e.target);
			}
		});

		const group_prototypes_table = document.getElementById('group_prototypes_table');
		const group_prototype_add = document.getElementById('group_prototype_add');

		group_prototypes_table.addEventListener('click', e => {
			if (e.target.classList.contains('element-table-remove')) {
				e.target.closest('tr').nextSibling.remove();
				e.target.closest('tr').remove();
			}
		});
		group_prototype_add.addEventListener('click', () => this.#addGroupPrototypeRow({}));

		if (this.group_prototypes.length == 0) {
			this.#addGroupPrototypeRow({});
		}

		let row_index = -1;
		Object.values(this.group_prototypes).forEach(group_prototype => {
			row_index++;
			group_prototype.row_index = row_index;
			this.#addGroupPrototypeRow(group_prototype, row_index);
		});

		this.interfaces_table_clone = this.interfaces_table.cloneNode(true);

		switch (this.#interfacesGetSourceValue()) {
			case '<?= HOST_PROT_INTERFACES_INHERIT ?>':
				this.#initInheritedInterfaces();
				this.interfaces_table.custom_interfaces = null;
				break;

			case '<?= HOST_PROT_INTERFACES_CUSTOM ?>':
				this.#initCustomInterfaces();
				this.interfaces_table.inherited_interfaces = null;
				break;
		}

		this.interfaces_radio_btn.addEventListener('change', () => this.#switchToInterface(
			this.#interfacesGetSourceValue()
		));
	}

	/**
	 * Gets the value of the selected interface source radio button.
	 *
	 * @returns {string} The value of the selected interface source.
	 */
	#interfacesGetSourceValue() {
		return this.interfaces_radio_btn.querySelector('input[name=custom_interfaces]:checked').value;
	}

	#initInheritedInterfaces() {
		const host_interface_manager_inherit = new HostInterfaceManager(this.inherited_interfaces,
			this.host_interface_row_tmpl, this.host_interface_row_snmp_tmpl
		);
		host_interface_manager_inherit.setAllowEmptyMessage(!this.parent_is_template);
		host_interface_manager_inherit.render();
		host_interface_manager_inherit.makeReadonly();
	}

	#initCustomInterfaces() {
		// This is in global space, as "Add" functions uses it.
		window.hostInterfaceManager = new HostInterfaceManager(this.custom_interfaces,
			this.host_interface_row_tmpl, this.host_interface_row_snmp_tmpl
		);
		hostInterfaceManager.render();

		if (this.readonly) {
			hostInterfaceManager.makeReadonly();
		}
	}

	/**
	 * Switches to another interface based on source.
	 *
	 * @param {number} source  Interface to switch to.
	 */
	#switchToInterface(source) {
		switch (source) {
			case '<?= HOST_PROT_INTERFACES_INHERIT ?>':
				this.add_interface_btn.style.display = 'none';

				if (!('inherited_interfaces' in this.interfaces_table)) {
					// Do nothing.
				}
				else if (this.interfaces_table.inherited_interfaces === null) {
					this.interfaces_table.inherited_interfaces = this.interfaces_table_clone;
					this.interfaces_table_clone = null;
					this.#switchInterfaceToInherited();
					this.#initInheritedInterfaces();
				}
				else {
					this.#switchInterfaceToInherited();
				}
				break;

			case '<?= HOST_PROT_INTERFACES_CUSTOM ?>':
				if (!('custom_interfaces' in this.interfaces_table)) {
					// Do nothing.
				}
				else if (this.interfaces_table.custom_interfaces === null) {
					this.interfaces_table.custom_interfaces = this.interfaces_table_clone;
					this.interfaces_table_clone = null;
					this.#switchInterfaceToCustom();
					this.#initCustomInterfaces();
				}
				else {
					this.#switchInterfaceToCustom();
				}

				this.add_interface_btn.style.display = 'inline-block';
				break;
		}
	}

	#switchInterfaceToInherited() {
		const obj_inherit = this.interfaces_table.inherited_interfaces;

		obj_inherit.custom_interfaces = this.interfaces_table;

		this.interfaces_table.replaceWith(obj_inherit);
		this.interfaces_table = obj_inherit;
	}

	#switchInterfaceToCustom() {
		const obj_custom = this.interfaces_table.custom_interfaces;

		obj_custom.inherited_interfaces = this.interfaces_table;

		this.interfaces_table.replaceWith(obj_custom);
		this.interfaces_table = obj_custom;
	}

	/**
	 * Updates visible name placeholder.
	 *
	 * @param {string} placeholder  Text to display as default host alias.
	 */
	#setVisibleNamePlaceholder(placeholder) {
		this.form_element.querySelector('#name').placeholder = placeholder;
	}

	/**
	 * Add a group prototype row.
	 *
	 * @param {object}  group_prototype  Group prototype to add.
	 * @param {number}  row_index        Row index to use, -1 to auto-increment.
	 */
	#addGroupPrototypeRow(group_prototype, row_index = -1) {
		if (row_index == -1) {
			const value_keys = Object.keys(this.form.findFieldByName('group_prototypes').getValue());
			const count = value_keys.length ? Math.max(...value_keys) : -1;

			group_prototype.row_index = count + 1;
		}

		const template = new Template(document.getElementById('group-prototype-row-tmpl').innerHTML);
		const group_prototype_add = document.getElementById('group_prototype_add');

		group_prototype_add.closest('tr').insertAdjacentHTML('beforebegin', template.evaluate(group_prototype));
	}

	/**
	 * Unlink template from the host prototype.
	 *
	 * @param {HTMLElement} button  Unlink button element.
	 */
	#unlinkTemplate(button) {
		const linked_templates = button.closest('table');

		button.closest('tr').remove();
		$('#add_templates_').trigger('change');

		if (linked_templates.querySelector('tbody:empty') !== null) {
			// Node must be kept into DOM to find the position where error must be shown.
			linked_templates.style.display = 'none';
		}
	}

	/**
	 * Helper to get linked template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	#getLinkedTemplates() {
		const linked_templateids = [];

		this.form_element
			.querySelectorAll('[name^="templates["')
			.forEach(input => linked_templateids.push(input.value));

		return linked_templateids;
	}

	/**
	 * Helper to get added template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	#getNewTemplates() {
		const $template_multiselect = $('#add_templates_'),
			templateids = [];

		// Readonly forms don't have multiselect.
		if ($template_multiselect.length) {
			$template_multiselect.multiSelect('getData').forEach(template => templateids.push(template.id));
		}

		return templateids;
	}

	/**
	 * Collects IDs of currently active (linked + new) templates.
	 *
	 * @return {array}  Templateids.
	 */
	#getAllTemplates() {
		return this.#getLinkedTemplates().concat(this.#getNewTemplates());
	}

	#initTagsTab() {
		const show_inherited_tags_element = document.getElementById('show_inherited_tags');

		this.show_inherited_tags = show_inherited_tags_element.querySelector('input:checked').value == 1;

		show_inherited_tags_element.addEventListener('change', e => {
			this.show_inherited_tags = e.target.value == 1;
			this.all_templateids = this.#getAllTemplates();

			this.#updateTagsList();
		});

		const observer = new IntersectionObserver(entries => {
			if (entries[0].isIntersecting && this.show_inherited_tags) {
				const templateids = this.#getAllTemplates();

				if (this.all_templateids === null || this.all_templateids.xor(templateids).length > 0) {
					this.all_templateids = templateids;

					this.#updateTagsList();
				}
			}
		});

		observer.observe(document.getElementById('tags-tab'));
	}

	#updateTagsList() {
		const fields = getFormFields(this.form_element);

		fields.tags = Object.values(fields.tags).reduce((tags, tag) => {
			if (!('type' in tag) || (tag.type & <?= ZBX_PROPERTY_OWN ?>)) {
				tags.push({tag: tag.tag.trim(), value: tag.value.trim()});
			}

			return tags;
		}, []);

		const url = new URL('zabbix.php', location.href);
		url.searchParams.set('action', 'host.tags.list');

		const data = {
			source: 'host_prototype',
			hostid: fields.hostid,
			templateids: this.#getAllTemplates(),
			show_inherited_tags: fields.show_inherited_tags,
			tags: fields.tags
		}

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then(response => response.json())
			.then(response => {
				this.tags_table.innerHTML = response.body;

				const $tags_table = jQuery(this.tags_table);

				if (!this.readonly) {
					$tags_table.data('dynamicRows').counter = this.tags_table.querySelectorAll('tr.form_row').length;
				}

				$tags_table.find(`.${ZBX_STYLE_TEXTAREA_FLEXIBLE}`).textareaFlexible();
			})
			.catch((message) => {
				this.form.addGeneralErrors({[t('Unexpected server error.')]: message});
				this.form.renderErrors();
				throw message;
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	/**
	 * Set up of macros functionality.
	 */
	#initMacrosTab() {
		this.macros_manager = new HostMacrosManager({
			container: $('#macros_container'),
			readonly: this.readonly,
			parent_hostid: this.parent_hostid,
			load_callback: () => {
				if (!this.readonly) {
					this.form.discoverAllFields();

					const fields = [];

					Object.values(this.form.findFieldByName('macros').getFields()).forEach(field => {
						fields.push(field.getName());
						field.setChanged();
					});

					this.form.validateChanges(fields, true);
				}
			}
		});

		const show_inherited_macros_element = document.getElementById('show_inherited_macros');

		this.show_inherited_macros = show_inherited_macros_element.querySelector('input:checked').value == 1;
		this.macros_manager.initMacroTable(this.show_inherited_macros);

		const observer = new IntersectionObserver(entries => {
			if (entries[0].isIntersecting && this.show_inherited_macros) {

				const templateids = this.#getAllTemplates();

				if (this.all_templateids === null || this.all_templateids.xor(templateids).length > 0) {
					this.all_templateids = templateids;

					this.macros_manager.load(this.show_inherited_macros, templateids);
				}
			}
		});
		observer.observe(document.getElementById('macros-tab'));

		show_inherited_macros_element.addEventListener('change', e => {
			this.show_inherited_macros = e.target.value == 1;
			this.all_templateids = this.#getAllTemplates();

			this.macros_manager.load(this.show_inherited_macros, this.all_templateids);
		});
	}

	/**
	 * Normalize field values.
	 *
	 * @param {object} fields  Fields from host form.
	 *
	 * @return {object} Processed fields from host form.
	 */
	#preprocessFormFields(fields) {
		this.#trimFields(fields);
		fields.status = fields.status || <?= HOST_STATUS_NOT_MONITORED ?>;

		return fields;
	}

	/**
	 * Remove extra spaces from fields.
	 *
	 * @param {object} fields  Fields to trim.
	 */
	#trimFields(fields) {
		const fields_to_trim = ['host', 'visiblename'];

		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}

		if ('interfaces' in fields) {
			for (const key in fields.interfaces) {
				const host_interface = fields.interfaces[key];
				host_interface.ip = host_interface.ip.trim();
				host_interface.dns = host_interface.dns.trim();
				host_interface.port = host_interface.port.trim();

				if ('details' in host_interface) {
					const details = host_interface.details;
					details.authpassphrase = details.authpassphrase.trim();
					details.community = details.community.trim();
					details.contextname = details.contextname.trim();
					details.privpassphrase = details.privpassphrase.trim();
					details.securityname = details.securityname.trim();
				}
			}
		}

		if ('macros' in fields) {
			for (const key in fields.macros) {
				const macro = fields.macros[key];

				macro.macro = macro.macro.trim();

				if ('value' in macro) {
					macro.value = macro.value.trim();
				}
				if ('description' in macro) {
					macro.description = macro.description.trim();
				}
			}
		}

		if ('tags' in fields) {
			for (const key in fields.tags) {
				const tag = fields.tags[key];

				tag.tag = tag.tag.trim();
				tag.value = tag.value.trim();
			}
		}
	}

	/**
	 * Checks if the form is confirmed for navigation away.
	 *
	 * @returns {boolean} True if no changes or user confirms navigation.
	 */
	#isConfirmed() {
		return JSON.stringify(this.initial_form_fields) === JSON.stringify(getFormFields(this.form_element))
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	}

	submit() {
		this.#removePopupMessages();

		const fields = this.form.getAllValues();
		const curl = new Curl(this.form_element.getAttribute('action'));

		this.form
			.validateSubmit(fields)
			.then(result => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				this.#post(curl.getUrl(), fields);
			});
	}

	/**
	 * Sends a POST request to the specified URL with the provided data and executes the success_callback function.
	 *
	 * @param {string} url   The URL to send the POST request to.
	 * @param {object} data  The data to send with the POST request.
	 */
	#post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
		.then(response => response.json())
		.then(response => {
			if ('error' in response) {
				throw {error: response.error};
			}

			if ('form_errors' in response) {
				this.form.setErrors(response.form_errors, true, true);
				this.form.renderErrors();

				return;
			}

			overlayDialogueDestroy(this.overlay.dialogueid);
			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		})
		.catch(exception => this.#ajaxExceptionHandler(exception))
		.finally(() => this.overlay.unsetLoading());
	}

	clone() {
		this.overlay.setLoading();
		this.hostid = null;

		const parameters = this.#preprocessFormFields(getFormFields(this.form_element));

		delete parameters.sid;
		parameters.clone = 1;

		this.form.release();
		const overlay = ZABBIX.PopupManager.open('host.prototype.edit', parameters);
		if (overlay) {
			this.overlay = overlay;
		}
	}

	/**
	 * Deletes the current host prototype.
	 */
	delete() {
		this.#removePopupMessages();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'host.prototype.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('host')) ?>);

		this.#post(curl.getUrl(), {
			hostids: [this.initial_form_fields.hostid],
			context: this.initial_form_fields.context
		});
	}

	/**
	 * Removes all popup message boxes above the form.
	 */
	#removePopupMessages() {
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	/**
	 * Handles exceptions from AJAX requests and displays an error message box above the form.
	 *
	 * @param {object} exception  The exception object thrown during AJAX request.
	 */
	#ajaxExceptionHandler(exception) {
		const form = host_prototype_edit_popup.form_element;

		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		form.parentNode.insertBefore(message_box, form);
	}
}
