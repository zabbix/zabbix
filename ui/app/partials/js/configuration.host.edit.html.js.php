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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

<script type="text/x-jquery-tmpl" id="macro-row-tmpl-inherited">
	<?= (new CRow([
			(new CCol([
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}'),
				new CInput('hidden', 'macros[#{rowNum}][inherited_type]', ZBX_PROPERTY_OWN),
				new CInput('hidden', 'macros[#{rowNum}][discovery_state]',
					CControllerHostMacrosList::DISCOVERY_STATE_MANUAL
				)
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CButton('macros[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP),
			[
				new CCol(
					(new CDiv())
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
						->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				),
				new CCol(),
				new CCol(
					(new CDiv())
						->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
						->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				)
			]
		]))
			->addClass('form_row')
			->toString().
		(new CRow([
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', _('description'))
			))
				->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)
				->setColSpan(7)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="macro-row-tmpl">
	<?= (new CRow([
			(new CCol([
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}'),
				new CInput('hidden', 'macros[#{rowNum}][discovery_state]',
					CControllerHostMacrosList::DISCOVERY_STATE_MANUAL
				)
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('description'))
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CHorList([
					(new CSimpleButton(_('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="host-interface-row-tmpl">
	<?= (new CPartial('configuration.host.interface.row'))->getOutput() ?>
</script>

<script>
	'use strict';

	window.host_edit = {
		form_name: null,
		form: null,
		macros_initialized: false,
		macros_templateids: null,

		/**
		 * Host form setup.
		 */
		init({form_name, host_interfaces, host_is_discovered}) {
			this.form_name = form_name;
			this.form = document.getElementById(form_name);

			this.initHostTab(host_interfaces, host_is_discovered);
			this.initMacrosTab();
			this.initInventoryTab();
			this.initEncryptionTab();
		},

		/**
		 * Sets up visible name placeholder synchronization.
		 */
		initHostTab(host_interfaces, host_is_discovered) {
			const host_field = document.getElementById('host');

			['input', 'paste'].forEach((event_type) => {
				host_field.addEventListener(event_type, (e) => this.setVisibleNamePlaceholder(e.target.value));
			});

			this.setVisibleNamePlaceholder(host_field.value);
			this.initHostInterfaces(host_interfaces, host_is_discovered);

			const $groups_ms = $('#groups_');
			const $template_ms = $('#add_templates_');

			$template_ms.on('change', (e) => {
				$template_ms.multiSelect('setDisabledEntries', this.getAllTemplates());
			});

			$groups_ms.on('change', (e) => {
				$groups_ms.multiSelect('setDisabledEntries',
					[... this.form.querySelectorAll('[name^="groups["]')].map((input) => input.value)
				);
			});
		},

		/**
		 * Updates visible name placeholder.
		 *
		 * @param {string} placeholder  Text to display as default host alias.
		 */
		setVisibleNamePlaceholder(placeholder) {
			document.getElementById('visiblename').placeholder = placeholder;
		},

		initHostInterfaces(host_interfaces, host_is_discovered) {
			const host_interface_row_tmpl = document.getElementById('host-interface-row-tmpl').innerHTML;

			window.hostInterfaceManager = new HostInterfaceManager(host_interfaces, host_interface_row_tmpl);

			hostInterfaceManager.render();

			if (host_is_discovered) {
				hostInterfaceManager.makeReadonly();
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
			const $show_inherited_macros = $('input[name="show_inherited_macros"]');

			this.macros_manager = new HostMacrosManager({});

			$('#host-tabs').on('tabscreate tabsactivate', (e, ui) => {
				const panel = (e.type === 'tabscreate') ? ui.panel : ui.newPanel;
				const show_inherited_macros = ($show_inherited_macros.filter(':checked').val() == 1);

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

			$show_inherited_macros.on('change', function() {
				host_edit.macros_manager.load(this.value == 1, host_edit.getAllTemplates());
				host_edit.updateEncryptionFields();
			});
		},

		/**
		 * Set up of inventory functionality.
		 */
		initInventoryTab() {
			document.querySelectorAll('[name=inventory_mode]').forEach((item) => {
				item.addEventListener('change', function () {
					let inventory_fields = document.querySelectorAll('[name^="host_inventory"]'),
						item_links = document.querySelectorAll('.populating_item');

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
			document.querySelectorAll('[name=tls_connect], [name^=tls_in_]').forEach((field) => {
				field.addEventListener('change', () => this.updateEncryptionFields());
			});

			if (document.querySelector('#change_psk')) {
				document.querySelector('#change_psk').addEventListener('click', () => {
					document.querySelector('#change_psk').closest('div').remove();
					document.querySelector('[for="change_psk"]').remove();
					this.updateEncryptionFields();
				});
			}

			this.updateEncryptionFields();
		},


		/**
		 * Propagate changes of selected encryption type to related inputs.
		 */
		updateEncryptionFields() {
			let selected_connection = document.querySelector('[name="tls_connect"]:checked').value,
				use_psk = (document.querySelector('[name="tls_in_psk"]').checked
					|| selected_connection == <?= HOST_ENCRYPTION_PSK ?>),
				use_cert = (document.querySelector('[name="tls_in_cert"]').checked
					|| selected_connection == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

			// If PSK is selected or checked.
			if (document.querySelector('#change_psk')) {
				document.querySelector('#change_psk').closest('div').style.display = use_psk ? '' : 'none';
				document.querySelector('[for="change_psk"]').style.display = use_psk ? '' : 'none';

				// As long as button is there, other PSK fields must be hidden.
				use_psk = false;
			}
			document.querySelector('#tls_psk_identity').closest('div').style.display = use_psk ? '' : 'none';
			document.querySelector('[for="tls_psk_identity"]').style.display = use_psk ? '' : 'none';
			document.querySelector('#tls_psk').closest('div').style.display = use_psk ? '' : 'none';
			document.querySelector('[for="tls_psk"]').style.display = use_psk ? '' : 'none';

			// If certificate is selected or checked.
			document.querySelector('#tls_issuer').closest('div').style.display = use_cert ? '' : 'none';
			document.querySelector('[for="tls_issuer"]').style.display = use_cert ? '' : 'none';
			document.querySelector('#tls_subject').closest('div').style.display = use_cert ? '' : 'none';
			document.querySelector('[for="tls_subject"]').style.display = use_cert ? '' : 'none';

			// Update tls_accept.
			let tls_accept = 0x00;

			if (document.querySelector('[name="tls_in_none"]').checked) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}

			if (document.querySelector('[name="tls_in_psk"]').checked) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}

			if (document.querySelector('[name="tls_in_cert"]').checked) {
				tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
			}

			document.getElementById('tls_accept').value = tls_accept;
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

			if (document.querySelector('#change_psk')) {
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
		}
	};
</script>
