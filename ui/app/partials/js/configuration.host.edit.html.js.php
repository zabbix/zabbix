<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$host_is_discovered = ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED);
$linked_templates = $host_is_discovered ? array_column($data['host']['parentTemplates'], 'templateid') : [];
?>
<?php if (!$host_is_discovered): ?>
	<script type="text/x-jquery-tmpl" id="macro-row-tmpl-inherited">
		<?= (new CRow([
				(new CCol([
					(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
						->addClass('macro')
						->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
						->setAttribute('placeholder', '{$MACRO}'),
					new CInput('hidden', 'macros[#{rowNum}][inherited_type]', ZBX_PROPERTY_OWN)
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
							->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					),
					new CCol(),
					new CCol(
						(new CDiv())
							->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
							->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
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
						->setAttribute('placeholder', '{$MACRO}')
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
					(new CButton('macros[#{rowNum}][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP)
			]))
				->addClass('form_row')
				->toString()
		?>
	</script>
<?php endif ?>

<script>
	'use strict';

	window.host_edit = {
		form_name: null,

		/**
		 * Host form setup.
		 */
		init({form_name}) {
			this.form_name = form_name;

			this.initHostTab();
			this.initTemplatesTab();
			this.initMacrosTab();
			this.initInventoryTab();
			this.initEncryptionTab();
		},

		/**
		 * Sets up visible name placeholder synchronization.
		 */
		initHostTab() {
			const host_field = document.getElementById('host');

			['input', 'paste'].forEach((event_type) => {
				host_field.addEventListener(event_type, (e) => this.setVisibleNamePlaceholder(e.target.value));
			});
			this.setVisibleNamePlaceholder(host_field.value);
		},

		/**
		 * Updates visible name placeholder.
		 *
		 * @param {string} placeholder  Text to display as default host alias.
		 */
		setVisibleNamePlaceholder(placeholder) {
			document.getElementById('visiblename').placeholder = placeholder;
		},

		/**
		 * Sets up template element functionality.
		 */
		initTemplatesTab() {
			document.getElementById('linked-template').addEventListener('click', (e) => {
				const element = e.target;

				if (element.classList.contains('js-tmpl-unlink')) {
					if (typeof element.dataset.templateid === 'undefined') {
						return;
					}

					element.closest('tr').remove();
					this.resetNewTemplatesField();
				}
				else if (element.classList.contains('js-tmpl-unlink-and-clear')) {
					if (typeof element.dataset.templateid === 'undefined') {
						return;
					}

					const clear_tmpl = document.createElement('input');
					clear_tmpl.setAttribute('type', 'hidden');
					clear_tmpl.setAttribute('name', 'clear_templates[]');
					clear_tmpl.setAttribute('value', element.dataset.templateid);
					element.form.appendChild(clear_tmpl);

					element.closest('tr').remove();
					this.resetNewTemplatesField();
				}
			});
		},

		/**
		 * Replaces template multiselect with a copy that has disabled templates updated.
		 */
		resetNewTemplatesField() {
			const $old_multiselect = $('#add_templates_');
			const $new_multiselect = $('<div>');
			const data = $old_multiselect.multiSelect('getData');

			$('#add_templates_').parent().html($new_multiselect);

			$new_multiselect
				.addClass('multiselect active')
				.css('width', '<?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px')
				.attr('id', 'add_templates_')
				.multiSelectHelper({
					object_name: 'templates',
					name: 'add_templates[]',
					data: data,
					popup: {
						parameters: {
							srctbl: 'templates',
							srcfld1: 'hostid',
							dstfrm: this.form_name,
							dstfld1: 'add_templates_',
							multiselect: '1',
							disableids: this.getLinkedTemplates()
						}
					}
				});
		},

		/**
		 * Get ids of linked templates.
		 *
		 * @return {array} Templateids.
		 */
		getLinkedTemplates() {
			const linked_templateids = [];

			document.querySelectorAll('[name^="templates["').forEach((input) => {
				linked_templateids.push(input.value);
			});

			return linked_templateids;
		},

		/**
		 * Collects ids of all assigned templates (linked + new).
		 *
		 * @return {array} Templateids.
		 */
		getAssignedTemplates() {
			return this.getLinkedTemplates().concat(this.getNewlyAddedTemplates());
		},

		/**
		 * Set up of macros functionality.
		 */
		initMacrosTab() {
			const $show_inherited_macros = $('input[name="show_inherited_macros"]');

			this.macros_manager = new HostMacrosManager(<?= json_encode([
				'properties' => [
					'readonly' => $host_is_discovered,
					'parent_hostid' => array_key_exists('parent_hostid', $data) ? $data['parent_hostid'] : null
				],
				'defines' => [
					'ZBX_STYLE_TEXTAREA_FLEXIBLE' => ZBX_STYLE_TEXTAREA_FLEXIBLE,
					'ZBX_PROPERTY_OWN' => ZBX_PROPERTY_OWN,
					'ZBX_MACRO_TYPE_TEXT' => ZBX_MACRO_TYPE_TEXT,
					'ZBX_MACRO_TYPE_SECRET' => ZBX_MACRO_TYPE_SECRET,
					'ZBX_MACRO_TYPE_VAULT' => ZBX_MACRO_TYPE_VAULT,
					'ZBX_STYLE_ICON_TEXT' => ZBX_STYLE_ICON_TEXT,
					'ZBX_STYLE_ICON_INVISIBLE' => ZBX_STYLE_ICON_INVISIBLE,
					'ZBX_STYLE_ICON_SECRET_TEXT' => ZBX_STYLE_ICON_SECRET_TEXT
				]
			]) ?>);

			$('#host-tabs').on('tabscreate tabsactivate', (e, ui) => {
				const panel = (e.type === 'tabscreate') ? ui.panel : ui.newPanel;

				if (panel.attr('id') === 'macros-tab') {
					let macros_initialized = (panel.data('macros_initialized') || false);

					// Please note that macro initialization must take place once and only when the tab is visible.
					if (e.type === 'tabsactivate') {
						let panel_templateids = panel.data('templateids') || [],
							templateids = this.getAssignedTemplates();

						if (panel_templateids.xor(templateids).length > 0) {
							panel.data('templateids', templateids);
							this.macros_manager.load($show_inherited_macros.filter(':checked').val(),
								templateids
							);
							panel.data('macros_initialized', true);
						}
					}

					if (macros_initialized) {
						return;
					}

					// Initialize macros.
					<?php if ($host_is_discovered): ?>
						$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
					<?php else: ?>
						this.macros_manager.initMacroTable($show_inherited_macros.filter(':checked').val());
					<?php endif ?>

					panel.data('macros_initialized', true);
				}
			});

			$show_inherited_macros.on('change', (e) => {
				if (e.target.name !== 'show_inherited_macros') {
					return;
				}

				this.macros_manager.load(e.target.value, this.getAssignedTemplates());
				this.updateEncryptionFields();
			});
		},

		/**
		 * Helper to get added template IDs as an array.
		 *
		 * @return {array}
		*/
		getNewlyAddedTemplates() {
			let $template_multiselect = $('#add_templates_'),
				templateids = [];

			if (typeof $template_multiselect.data('multiSelect') === 'undefined') {
				return templateids;
			}

			// Readonly forms don't have multiselect.
			if ($template_multiselect.length) {
				$template_multiselect.multiSelect('getData').forEach(template => {
					templateids.push(template.id);
				});
			}

			return templateids;
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
		 * @param {Object} fields  Fields from host form.
		 *
		 * @return {Object}        Processed fields from host form.
		 */
		preprocessFormFields(fields) {
			// Trim text fields.
			fields.host = fields.host.trim();
			fields.visiblename = fields.visiblename.trim();
			fields.description = fields.description.trim();

			fields.status = fields.status || <?= HOST_STATUS_NOT_MONITORED ?>;

			// TODO VM: check
			if (document.querySelector('#change_psk')) {
				delete fields.tls_psk_identity;
				delete fields.tls_psk;
			}

			return fields;
		}
	};

	jQuery(document).ready(function() {
		'use strict';

		window.hostInterfaceManager = new HostInterfaceManager(
			<?= json_encode($data['host']['interfaces']) ?>,
			<?= json_encode([
				'interface_types' => [
					'AGENT' => INTERFACE_TYPE_AGENT,
					'SNMP' => INTERFACE_TYPE_SNMP,
					'JMX' => INTERFACE_TYPE_JMX,
					'IPMI' => INTERFACE_TYPE_IPMI
				],
				'interface_properties' => [
					'SNMP_V1' => SNMP_V1,
					'SNMP_V2C' => SNMP_V2C,
					'SNMP_V3' => SNMP_V3,
					'BULK_ENABLED' => SNMP_BULK_ENABLED,
					'INTERFACE_PRIMARY' => INTERFACE_PRIMARY,
					'INTERFACE_SECONDARY' => INTERFACE_SECONDARY,
					'INTERFACE_USE_IP' => INTERFACE_USE_IP,
					'SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
					'SNMPV3_SECURITYLEVEL_AUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,
					'SNMPV3_SECURITYLEVEL_AUTHPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,
					'SNMPV3_AUTHPROTOCOL_MD5' => ITEM_SNMPV3_AUTHPROTOCOL_MD5,
					'SNMPV3_PRIVPROTOCOL_DES' => ITEM_SNMPV3_PRIVPROTOCOL_DES
				],
				'styles' => [
					'ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE' => ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE,
					'ZBX_STYLE_HOST_INTERFACE_CONTAINER' => ZBX_STYLE_HOST_INTERFACE_CONTAINER,
					'ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER' => ZBX_STYLE_HOST_INTERFACE_CONTAINER_HEADER,
					'ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS' => ZBX_STYLE_HOST_INTERFACE_CELL_DETAILS,
					'ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE' => ZBX_STYLE_HOST_INTERFACE_BTN_MAIN_INTERFACE,
					'ZBX_STYLE_HOST_INTERFACE_CELL_USEIP' => ZBX_STYLE_HOST_INTERFACE_CELL_USEIP,
					'ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE' => ZBX_STYLE_HOST_INTERFACE_BTN_TOGGLE,
					'ZBX_STYLE_LIST_ACCORDION_ITEM' => ZBX_STYLE_LIST_ACCORDION_ITEM,
					'ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED' => ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED,
					'ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND' => ZBX_STYLE_HOST_INTERFACE_INPUT_EXPAND,
					'ZBX_STYLE_HOST_INTERFACE_ROW' => ZBX_STYLE_HOST_INTERFACE_ROW,
					'ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE' => ZBX_STYLE_HOST_INTERFACE_BTN_REMOVE,
				],
				'templates' => [
					'interface_row' => (new CPartial('configuration.host.interface.row'))->getOutput(),
					'no_interface_msg' => (new CDiv(_('No interfaces are defined.')))
						->addClass(ZBX_STYLE_GREY)
						->addStyle('padding: 5px 0px;')
						->toString()
				]
			]) ?>
		);

		hostInterfaceManager.render();

		<?php if ($host_is_discovered): ?>
			hostInterfaceManager.makeReadonly();
		<?php endif; ?>
	});
</script>
