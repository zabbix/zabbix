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

window.host_edit_popup = {
	overlay: null,
	dialogue: null,
	form: null,

	init({popup_url, form_name, host_interfaces, proxy_groupid, host_is_discovered, warnings}) {
		this.overlay = overlays_stack.getById('host.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.initial_proxy_groupid = proxy_groupid;
		this.macros_templateids = null;

		const backurl = new Curl('zabbix.php');

		backurl.setArgument('action', 'host.list');
		this.overlay.backurl = backurl.getUrl();

		if (warnings.length) {
			const message_box = warnings.length == 1
				? makeMessageBox('warning', warnings, null, true, false)[0]
				: makeMessageBox('warning', warnings,
						<?= json_encode(_('Cloned host parameter values have been modified.')) ?>, true, false
					)[0];

			this.form.parentNode.insertBefore(message_box, this.form);
		}

		this.initHostTab(host_interfaces, host_is_discovered);
		this.initMacrosTab();
		this.initInventoryTab();
		this.initEncryptionTab();

		this.initial_form_fields = getFormFields(this.form);
		this.initEvents();
	},

	initEvents() {
		this.form.addEventListener('click', (e) => {
			const target = e.target;

			if (target.matches('.js-edit-template') || target.matches('.js-edit-proxy')
					|| target.matches('.js-update-item')) {
				this.setActions(e.target.dataset);
			}
			else if (e.target.classList.contains('js-unlink')) {
				this.unlinkTemplate(e.target)
			}
			else if (e.target.classList.contains('js-unlink-and-clear')) {
				this.unlinkAndClearTemplate(e.target, e.target.dataset.templateid)
			}
		});
	},

	/**
	 * Sets up visible name placeholder synchronization.
	 */
	initHostTab(host_interfaces, host_is_discovered) {
		const host_field = this.form.querySelector('#host');

		['input', 'paste'].forEach((event_type) => {
			host_field.addEventListener(event_type, (e) => this.setVisibleNamePlaceholder(e.target.value));
		});

		this.setVisibleNamePlaceholder(host_field.value);
		this.initHostInterfaces(host_interfaces, host_is_discovered);

		const $groups_ms = $('#groups_');
		const $template_ms = $('#add_templates_');

		$template_ms.on('change', () => {
			$template_ms.multiSelect('setDisabledEntries', this.getAllTemplates());
		});

		$groups_ms.on('change', () => {
			$groups_ms.multiSelect('setDisabledEntries',
				[... this.form.querySelectorAll('[name^="groups["]')].map((input) => input.value)
			);
		});

		this.form.querySelector('#monitored_by').addEventListener('change', () => this.updateMonitoredBy());
		jQuery('#proxy_groupid').on('change', () => this.updateMonitoredBy());

		this.updateMonitoredBy();
	},

	/**
	 * Updates visible name placeholder.
	 *
	 * @param {string} placeholder  Text to display as default host alias.
	 */
	setVisibleNamePlaceholder(placeholder) {
		this.form.querySelector('#visiblename').placeholder = placeholder;
	},

	initHostInterfaces(host_interfaces, host_is_discovered) {
		const host_interface_row_tmpl = this.form.querySelector('#host-interface-row-tmpl').innerHTML;

		window.hostInterfaceManager = new HostInterfaceManager(host_interfaces, host_interface_row_tmpl);

		hostInterfaceManager.render();

		if (host_is_discovered) {
			hostInterfaceManager.makeReadonly();
		}
	},

	updateMonitoredBy() {
		const monitored_by = this.form.querySelector('[name="monitored_by"]:checked').value;

		for (const field of this.form.querySelectorAll('.js-field-proxy')) {
			field.style.display = monitored_by == <?= ZBX_MONITORED_BY_PROXY ?> ? '' : 'none';
		}

		for (const field of this.form.querySelectorAll('.js-field-proxy-group, .js-field-proxy-group-proxy')) {
			field.style.display = monitored_by == <?= ZBX_MONITORED_BY_PROXY_GROUP ?> ? '' : 'none';
		}

		if (monitored_by == <?= ZBX_MONITORED_BY_PROXY_GROUP ?>) {
			const proxy_group = jQuery('#proxy_groupid').multiSelect('getData');
			const proxy_assigned = this.form.querySelector('.js-proxy-assigned');
			const proxy_not_assigned = this.form.querySelector('.js-proxy-not-assigned');

			for (const element of this.form.querySelectorAll('.js-field-proxy-group-proxy')) {
				element.style.display = proxy_group.length ? '' : 'none';
			}

			if (proxy_group.length && proxy_assigned !== null
				&& proxy_group[0]['id'] === this.initial_proxy_groupid) {
				proxy_assigned.style.display = '';
				proxy_not_assigned.style.display = 'none';
			}
			else {
				if (proxy_assigned !== null) {
					proxy_assigned.style.display = 'none';
				}

				proxy_not_assigned.style.display = '';
			}
		}
	},

	unlinkTemplate(button) {
		const linked_templates = button.closest('table');

		button.closest('tr').remove();
		$('#add_templates_').trigger('change');

		if (linked_templates.querySelector('tbody:empty') !== null) {
			linked_templates.remove();
		}
	},

	unlinkAndClearTemplate(button, templateid) {
		const clear_tmpl = document.createElement('input');
		clear_tmpl.type = 'hidden';
		clear_tmpl.name = 'clear_templates[]';
		clear_tmpl.value = templateid;
		button.form.appendChild(clear_tmpl);

		this.unlinkTemplate(button);
	},

	setActions(dataset) {
		const {action, ...params} = dataset;

		window.popupManagerInstance.setAdditionalActions(() => {
			const form_fields = getFormFields(this.form);

			const url = new Curl('zabbix.php');
			url.setArgument('action', 'popup');
			url.setArgument('popup', action);

			for (const [key, value] of Object.entries(params)) {
				url.setArgument(key, value);
			}

			if (JSON.stringify(this.initial_form_fields) !== JSON.stringify(form_fields)) {
				if (!window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
					return false;
				}
				else {
					overlayDialogueDestroy(this.overlay.dialogueid);
					history.replaceState(null, '', url.getUrl());

					return true;
				}
			}

			overlayDialogueDestroy(this.overlay.dialogueid);
			history.replaceState(null, '', url.getUrl());

			return true;
		});
	},

	/**
	 * Helper to get linked template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	getLinkedTemplates() {
		const linked_templateids = [];

		this.form.querySelectorAll('[name^="templates["').forEach((input) => {
			linked_templateids.push(input.value);
		});

		return linked_templateids;
	},

	/**
	 * Helper to get added template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	getNewTemplates() {
		const $template_multiselect = $('#add_templates_'),
			templateids = [];

		// Readonly forms don't have multiselect.
		if ($template_multiselect.length) {
			$template_multiselect.multiSelect('getData').forEach(template => {
				templateids.push(template.id);
			});
		}

		return templateids;
	},

	/**
	 * Collects ids of currently active (linked + new) templates.
	 *
	 * @return {array}  Templateids.
	 */
	getAllTemplates() {
		return this.getLinkedTemplates().concat(this.getNewTemplates());
	},

	/**
	 * Set up of macros functionality.
	 */
	initMacrosTab() {
		this.macros_manager = new HostMacrosManager({
			'container': $('#macros_container .table-forms-td-right')
		});

		$('#host-tabs', this.form).on('tabscreate tabsactivate', (e, ui) => {
			const panel = (e.type === 'tabscreate') ? ui.panel : ui.newPanel;
			const show_inherited_macros = this.form
				.querySelector('input[name=show_inherited_macros]:checked').value == 1;

			if (panel.attr('id') === 'macros-tab') {
				// Please note that macro initialization must take place once and only when the tab is visible.
				if (e.type === 'tabsactivate') {
					const templateids = this.getAllTemplates();

					// First time always load inherited macros.
					if (this.macros_templateids === null) {
						this.macros_templateids = templateids;

						if (show_inherited_macros) {
							this.macros_manager.load(show_inherited_macros, templateids);
							this.macros_initialized = true;
						}
					}
					// Other times load inherited macros only if templates changed.
					else if (show_inherited_macros && this.macros_templateids.xor(templateids).length > 0) {
						this.macros_templateids = templateids;
						this.macros_manager.load(show_inherited_macros, templateids);
					}
				}

				if (this.macros_initialized) {
					return;
				}

				// Initialize macros.
				this.macros_manager.initMacroTable(show_inherited_macros);

				this.macros_initialized = true;
			}
		});

		this.form.querySelector('#show_inherited_macros').onchange = (e) => {
			this.macros_manager.load(e.target.value == 1, this.getLinkedTemplates().concat(this.getNewTemplates()));
		};
	},

	/**
	 * Set up of inventory functionality.
	 */
	initInventoryTab() {
		this.form.querySelectorAll('[name=inventory_mode]').forEach((item) => {
			item.addEventListener('change', function () {
				let inventory_fields = this.form.querySelectorAll('[name^="host_inventory"]'),
					item_links = this.form.querySelectorAll('.populating_item');

				switch (this.value) {
					case '<?= HOST_INVENTORY_DISABLED ?>':
						inventory_fields.forEach((field) => field.disabled = true);
						item_links.forEach((link) => link.style.display = 'none');
						break;

					case '<?= HOST_INVENTORY_MANUAL ?>':
						inventory_fields.forEach((field) => field.disabled = false);
						item_links.forEach((link) => link.style.display = 'none');
						break;

					case '<?= HOST_INVENTORY_AUTOMATIC ?>':
						inventory_fields.forEach((field) =>
							field.disabled = field.classList.contains('linked_to_item')
						);
						item_links.forEach((link) => link.style.display = '');
						break;
				}
			})
		});
	},

	/**
	 * Set up of encryption functionality.
	 */
	initEncryptionTab() {
		this.form.querySelectorAll('[name=tls_connect], [name^=tls_in_]').forEach((field) => {
			field.addEventListener('change', () => this.updateEncryptionFields());
		});

		if (this.form.querySelector('#change_psk')) {
			this.form.querySelector('#change_psk').addEventListener('click', () => {
				this.form.querySelector('#change_psk').closest('div').remove();
				this.form.querySelector('[for="change_psk"]').remove();
				this.updateEncryptionFields();
			});
		}

		this.updateEncryptionFields();
	},


	/**
	 * Propagate changes of selected encryption type to related inputs.
	 */
	updateEncryptionFields() {
		let selected_connection = this.form.querySelector('[name="tls_connect"]:checked').value,
			use_psk = (this.form.querySelector('[name="tls_in_psk"]').checked
				|| selected_connection == <?= HOST_ENCRYPTION_PSK ?>),
			use_cert = (this.form.querySelector('[name="tls_in_cert"]').checked
				|| selected_connection == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

		// If PSK is selected or checked.
		if (this.form.querySelector('#change_psk')) {
			this.form.querySelector('#change_psk').closest('div').style.display = use_psk ? '' : 'none';
			this.form.querySelector('[for="change_psk"]').style.display = use_psk ? '' : 'none';

			// As long as button is there, other PSK fields must be hidden.
			use_psk = false;
		}
		this.form.querySelector('#tls_psk_identity').closest('div').style.display = use_psk ? '' : 'none';
		this.form.querySelector('[for="tls_psk_identity"]').style.display = use_psk ? '' : 'none';
		this.form.querySelector('#tls_psk').closest('div').style.display = use_psk ? '' : 'none';
		this.form.querySelector('[for="tls_psk"]').style.display = use_psk ? '' : 'none';

		// If certificate is selected or checked.
		this.form.querySelector('#tls_issuer').closest('div').style.display = use_cert ? '' : 'none';
		this.form.querySelector('[for="tls_issuer"]').style.display = use_cert ? '' : 'none';
		this.form.querySelector('#tls_subject').closest('div').style.display = use_cert ? '' : 'none';
		this.form.querySelector('[for="tls_subject"]').style.display = use_cert ? '' : 'none';

		// Update tls_accept.
		let tls_accept = 0x00;

		if (this.form.querySelector('[name="tls_in_none"]').checked) {
			tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
		}

		if (this.form.querySelector('[name="tls_in_psk"]').checked) {
			tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
		}

		if (this.form.querySelector('[name="tls_in_cert"]').checked) {
			tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
		}

		this.form.querySelector('#tls_accept').value = tls_accept;
	},

	/**
	 * Normalize field values.
	 *
	 * @param {Object}  fields    Fields from host form.
	 * @param {boolean} is_clone  Submit fields for clone instead of update.
	 *
	 * @return {Object}  Processed fields from host form.
	 */
	preprocessFormFields(fields, is_clone) {
		this.trimFields(fields);
		fields.status = fields.status || <?= HOST_STATUS_NOT_MONITORED ?>;

		if (this.form.querySelector('#change_psk')) {
			delete fields.tls_psk_identity;
			delete fields.tls_psk;
		}

		if ('tags' in fields) {
			for (const key in fields.tags) {
				const tag = fields.tags[key];

				if (tag.automatic == <?= ZBX_TAG_AUTOMATIC ?> && !is_clone) {
					delete fields.tags[key];
				}
				else {
					delete tag.automatic;
				}
			}
		}

		return fields;
	},

	trimFields(fields) {
		const fields_to_trim = ['host', 'visiblename', 'description', 'ipmi_username', 'ipmi_password',
			'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk'];
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

		if ('host_inventory' in fields) {
			for (const key in fields.host_inventory) {
				fields.host_inventory[key] = fields.host_inventory[key].trim();
			}
		}

		if ('tags' in fields) {
			for (const key in fields.tags) {
				const tag = fields.tags[key];
				tag.tag = tag.tag.trim();
				tag.value = tag.value.trim();
			}
		}
	},

	isConfirmed() {
		const form_fields = getFormFields(this.form);

		if (JSON.stringify(this.initial_form_fields) !== JSON.stringify(form_fields)) {
			if (!window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
				return false;
			}
		}

		return true;
	},

	submit() {
		this.removePopupMessages();

		const fields = this.preprocessFormFields(getFormFields(this.form), false);
		const curl = new Curl(this.form.getAttribute('action'));

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	clone() {
		this.overlay.setLoading();
		this.hostid = null;

		const parameters = this.preprocessFormFields(getFormFields(this.form), true);
		delete parameters.sid;
		parameters.clone = 1;

		this.overlay = window.popupManagerInstance.openPopup('host.edit', parameters);
	},

	delete(hostid) {
		this.removePopupMessages();

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'host.massdelete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('host')) ?>);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData({hostids: [hostid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	ajaxExceptionHandler: (exception) => {
		const form = host_edit_popup.form;

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
};
