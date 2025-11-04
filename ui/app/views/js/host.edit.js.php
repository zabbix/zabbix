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
	form_element: null,

	init({rules, host_interfaces, proxy_groupid, host_is_discovered, warnings}) {
		this.overlay = overlays_stack.getById('host.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.initial_proxy_groupid = proxy_groupid;
		this.all_templateids = null;
		this.show_inherited_tags = false;
		this.tags_table = this.form_element.querySelector('.tags-table');
		this.show_inherited_macros = false;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'host.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		if (warnings.length) {
			const message_box = warnings.length == 1
				? makeMessageBox('warning', warnings, null, true, false)[0]
				: makeMessageBox('warning', warnings,
						<?= json_encode(_('Cloned host parameter values have been modified.')) ?>, true, false
					)[0];

			this.form_element.parentNode.insertBefore(message_box, this.form_element);
		}

		this.initHostTab(host_interfaces, host_is_discovered);
		this.initTagsTab();
		this.initMacrosTab();
		this.initInventoryTab();
		this.initEncryptionTab();

		this.initial_form_fields = getFormFields(this.form_element);
		this.initEvents();
		this.initPopupListeners();
	},

	initEvents() {
		this.form_element.addEventListener('click', e => {
			if (e.target.classList.contains('js-unlink')) {
				this.unlinkTemplate(e.target);
			}
			else if (e.target.classList.contains('js-unlink-and-clear')) {
				this.unlinkAndClearTemplate(e.target, e.target.dataset.templateid);
			}
		});
	},

	initPopupListeners() {
		const subscriptions = [];

		for (const action of ['template.edit', 'proxy.edit', 'item.edit']) {
			subscriptions.push(
				ZABBIX.EventHub.subscribe({
					require: {
						context: CPopupManager.EVENT_CONTEXT,
						event: CPopupManagerEvent.EVENT_OPEN,
						action
					},
					callback: ({event}) => {
						if (!this.isConfirmed()) {
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
	},

	/**
	 * Sets up visible name placeholder synchronization.
	 */
	initHostTab(host_interfaces, host_is_discovered) {
		const host_field = this.form_element.querySelector('#host');

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
				[... this.form_element.querySelectorAll('[name^="groups["]')].map((input) => input.value)
			);
		});

		this.form_element.querySelector('#monitored_by').addEventListener('change', () => this.updateMonitoredBy());
		jQuery('#proxy_groupid').on('change', () => this.updateMonitoredBy());

		this.updateMonitoredBy();
	},

	/**
	 * Updates visible name placeholder.
	 *
	 * @param {string} placeholder  Text to display as default host alias.
	 */
	setVisibleNamePlaceholder(placeholder) {
		this.form_element.querySelector('#visiblename').placeholder = placeholder;
	},

	initHostInterfaces(host_interfaces, host_is_discovered) {
		const host_interface_row_tmpl = this.form_element.querySelector('#host-interface-row-tmpl').innerHTML;
		const host_interface_row_snmp_tmpl = document.getElementById('host-interface-row-snmp-tmpl').innerHTML;

		window.hostInterfaceManager = new HostInterfaceManager(host_interfaces, host_interface_row_tmpl,
			host_interface_row_snmp_tmpl
		);

		hostInterfaceManager.render();

		if (host_is_discovered) {
			hostInterfaceManager.makeReadonly();
		}
	},

	updateMonitoredBy() {
		const monitored_by = this.form_element.querySelector('[name="monitored_by"]:checked').value;

		for (const field of this.form_element.querySelectorAll('.js-field-proxy')) {
			field.style.display = monitored_by == <?= ZBX_MONITORED_BY_PROXY ?> ? '' : 'none';
		}

		for (const field of this.form_element.querySelectorAll('.js-field-proxy-group, .js-field-proxy-group-proxy')) {
			field.style.display = monitored_by == <?= ZBX_MONITORED_BY_PROXY_GROUP ?> ? '' : 'none';
		}

		if (monitored_by == <?= ZBX_MONITORED_BY_PROXY_GROUP ?>) {
			const proxy_group = jQuery('#proxy_groupid').multiSelect('getData');
			const proxy_assigned = this.form_element.querySelector('.js-proxy-assigned');
			const proxy_not_assigned = this.form_element.querySelector('.js-proxy-not-assigned');

			for (const element of this.form_element.querySelectorAll('.js-field-proxy-group-proxy')) {
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
			// Node must be kept into DOM to find the position where error must be shown.
			linked_templates.style.display = 'none';
		}
	},

	unlinkAndClearTemplate(button, templateid) {
		const clear_tmpl = document.createElement('input');

		clear_tmpl.type = 'hidden';
		clear_tmpl.name = `clear_templates[${templateid}]`;
		clear_tmpl.setAttribute('data-field-type', 'hidden');
		clear_tmpl.value = templateid;

		this.form_element.appendChild(clear_tmpl);
		this.form.discoverAllFields();

		this.unlinkTemplate(button);
	},

	/**
	 * Helper to get linked template IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	getLinkedTemplates() {
		const linked_templateids = [];

		this.form_element.querySelectorAll('[name^="templates["').forEach(input => {
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
	 * Set up of tags functionality.
	 */
	initTagsTab() {
		const show_inherited_tags_element = document.getElementById('host_show_inherited_tags');

		this.show_inherited_tags = show_inherited_tags_element.querySelector('input:checked').value == 1;

		show_inherited_tags_element.addEventListener('change', e => {
			this.show_inherited_tags = e.target.value == 1;
			this.all_templateids = this.getAllTemplates();

			this.updateTagsList();
		});

		const observer = new IntersectionObserver(entries => {
			if (entries[0].isIntersecting && this.show_inherited_tags) {
				const templateids = this.getAllTemplates();

				if (this.all_templateids === null || this.all_templateids.xor(templateids).length > 0) {
					this.all_templateids = templateids;

					this.updateTagsList();
				}
			}
		});

		observer.observe(document.getElementById('host-tags-tab'));
	},

	updateTagsList() {
		const fields = getFormFields(this.form_element);

		fields.tags = Object.values(fields.tags).reduce((tags, tag) => {
			if (!('type' in tag) || (tag.type & <?= ZBX_PROPERTY_OWN ?>)) {
				tags.push({tag: tag.tag.trim(), value: tag.value.trim(), automatic: tag.automatic});
			}

			return tags;
		}, []);

		const url = new URL('zabbix.php', location.href);
		url.searchParams.set('action', 'host.tags.list');

		const data = {
			source: 'host',
			hostid: fields.hostid,
			templateids: this.getAllTemplates(),
			show_inherited_tags: fields.host_show_inherited_tags,
			tags: fields.tags
		}

		this.overlay.setLoading();

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then(response => response.json())
			.then(response => {
				this.tags_table.innerHTML = response.body;

				const $tags_table = jQuery(this.tags_table);

				$tags_table.data('dynamicRows').counter = this.tags_table.querySelectorAll('tr.form_row').length;
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
	},

	/**
	 * Set up of macros functionality.
	 */
	initMacrosTab() {
		this.macros_manager = new HostMacrosManager({
			container: $('#macros_container .table-forms-td-right'),
			load_callback: () => {
				this.form.discoverAllFields();

				const fields = [];

				Object.values(this.form.findFieldByName('macros').getFields()).forEach(field => {
					fields.push(field.getName());
					field.setChanged();
				});

				this.form.validateChanges(fields, true);
			}
		});

		$('#host-tabs', this.form_element).on('tabscreate tabsactivate', (e, ui) => {
			const panel = (e.type === 'tabscreate') ? ui.panel : ui.newPanel;
			const show_inherited_macros = this.form_element
				.querySelector('input[name=show_inherited_macros]:checked').value == 1;

			if (panel.attr('id') === 'macros-tab') {
				// Please note that macro initialization must take place once and only when the tab is visible.
				if (e.type === 'tabsactivate') {
					const templateids = this.getAllTemplates();

					// First time always load inherited macros.
					if (this.all_templateids === null) {
						this.all_templateids = templateids;

						if (show_inherited_macros) {
							this.macros_manager.load(show_inherited_macros, templateids);
							this.macros_initialized = true;
						}
					}
					// Other times load inherited macros only if templates changed.
					else if (show_inherited_macros && this.all_templateids.xor(templateids).length > 0) {
						this.all_templateids = templateids;
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

		this.form_element.querySelector('#show_inherited_macros').onchange = (e) => {
			this.macros_manager.load(e.target.value == 1, this.getAllTemplates());
		};
	},

	/**
	 * Set up of inventory functionality.
	 */
	initInventoryTab() {
		this.form_element.querySelectorAll('[name=inventory_mode]').forEach((item) => {
			item.addEventListener('change', () => {
				let inventory_fields = this.form_element.querySelectorAll('[name^="host_inventory"]'),
					item_links = this.form_element.querySelectorAll('.populating_item');

				switch (item.value) {
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
			});
		});
	},

	/**
	 * Set up of encryption functionality.
	 */
	initEncryptionTab() {
		this.form_element.querySelectorAll('[name=tls_connect], [name^=tls_in_]').forEach((field) => {
			field.addEventListener('change', () => this.updateEncryptionFields());
		});

		if (this.form_element.querySelector('#change_psk')) {
			this.form_element.querySelector('#change_psk').addEventListener('click', () => {
				this.form_element.querySelector('#change_psk').closest('div').remove();
				this.form_element.querySelector('[for="change_psk"]').remove();
				this.updateEncryptionFields();
			});
		}

		this.updateEncryptionFields();
	},

	/**
	 * Propagate changes of selected encryption type to related inputs.
	 */
	updateEncryptionFields() {
		const selected_connection = this.form_element.querySelector('[name="tls_connect"]:checked').value;
		let use_psk = (this.form_element.querySelector('[name="tls_in_psk"]').checked
				|| selected_connection == <?= HOST_ENCRYPTION_PSK ?>);
		const use_cert = (this.form_element.querySelector('[name="tls_in_cert"]').checked
				|| selected_connection == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

		// If PSK is selected or checked.
		const change_psk = document.getElementById('change_psk');
		if (change_psk !== null) {
			change_psk.closest('div').style.display = use_psk ? '' : 'none';
			this.form_element.querySelector('[for="change_psk"]').style.display = use_psk ? '' : 'none';

			// As long as button is there, other PSK fields must be hidden and disabled.
			use_psk = false;
		}

		['tls_psk_identity', 'tls_psk'].forEach((field_id) => {
			document.getElementById(field_id).disabled = !use_psk;
			document.getElementById(field_id).closest('div').style.display = use_psk ? '' : 'none';
			document.querySelector(`[for="${field_id}"]`).style.display = use_psk ? '' : 'none';
		});

		['tls_issuer', 'tls_subject'].forEach((field_id) => {
			document.getElementById(field_id).disabled = !use_cert;
			document.getElementById(field_id).closest('div').style.display = use_cert ? '' : 'none';
			document.querySelector(`[for="${field_id}"]`).style.display = use_cert ? '' : 'none';
		});
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

		if (this.form_element.querySelector('#change_psk')) {
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
		return JSON.stringify(this.initial_form_fields) === JSON.stringify(getFormFields(this.form_element))
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	},

	submit() {
		this.removePopupMessages();

		const fields = this.form.getAllValues();
		const curl = new Curl(this.form_element.getAttribute('action'));

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
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

						overlayDialogueDestroy(this.overlay.dialogueid);

						this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
					})
					.catch(this.ajaxExceptionHandler)
					.finally(() => {
						this.overlay.unsetLoading();
					});
			});
	},

	clone() {
		this.overlay.setLoading();
		this.hostid = null;

		const parameters = this.preprocessFormFields(getFormFields(this.form_element), true);
		delete parameters.sid;
		parameters.clone = 1;

		this.form.release();
		this.overlay = ZABBIX.PopupManager.open('host.edit', parameters);
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
		for (const el of this.form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	ajaxExceptionHandler: (exception) => {
		const form = host_edit_popup.form_element;

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
