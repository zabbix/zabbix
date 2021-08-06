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
$linked_templates = $host_is_discovered
	? array_column($data['host']['parentTemplates'], 'templateid')
	: [];
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

	var host_edit = {
		/**
		 * Host form setup.
		 */
		init() {
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

			'input paste'.split(' ').forEach((event_type) => {
				host_field.addEventListener(event_type, (e) => this.setVisibleNamePlaceholder(e.target.value));
			});
			this.setVisibleNamePlaceholder(host_field.value);
		},


		/**
		 * Updates visible name placeholder.
		 * @param {string} placeholder Text to display as default host alias.
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

					var clear_tmpl = document.createElement('input');
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
			var $old_multiselect = $('#add_templates_'),
				$new_multiselect = $('<div>'),
				data = $old_multiselect.multiSelect('getData');

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
							dstfrm: '<?= $data['form_name'] ?>',
							dstfld1: 'add_templates_',
							multiselect: '1',
							disableids: this.getAssignedTemplates()
						}
					}
				});
		},

		/**
		 * Collects ids of currently active (linked + new) templates.
		 *
		 * @return {array} Templateids.
		 */
		getAssignedTemplates() {
			const linked_templateids = [];

			document.querySelectorAll('[name="templates[]').forEach((input) => {
				linked_templateids.push(input.value);
			});

			return linked_templateids.concat(this.getNewlyAddedTemplates());
		},

		/**
		 * Set up of macros functionality.
		 */
		initMacrosTab() {
			const $show_inherited_macros = $('input[name="show_inherited_macros"]');

			this.macros_manager = new HostMacrosManager(<?= json_encode([
				'properties' => [
					'readonly' => $host_is_discovered
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

			$('#tabs').on('tabscreate tabsactivate', (e, ui) => {
				var panel = (e.type === 'tabscreate') ? ui.panel : ui.newPanel;

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
		 * Posts hosts form to backend, triggers formSubmitted event on PopUp.
		 *
		 * @param {HTMLFormElement} form Host form.
		 */
		submit(form) {
			var fields = getFormFields(form),
				curl = new Curl(form.getAttribute('action'));

			// Trim text fields.
			fields.host = fields.host.trim();
			fields.visiblename = fields.visiblename.trim();
			fields.description = fields.description.trim();

			fields.status = fields.status || <?= HOST_STATUS_NOT_MONITORED ?>;
			fields.output = 'ajax';

			if (document.querySelector('#change_psk')) {
				delete fields.tls_psk_identity;
				delete fields.tls_psk;
			}

			// Groups are not extracted properly by getFormFields.
			fields.groups = [];
			form.querySelectorAll('[name^="groups[]"]').forEach((group) => {
				fields.groups.push((group.name === 'groups[][new]') ? {'new': group.value} : group.value);
			});

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData(fields)
			})
				.then(response => response.json())
				.then(response => form.dispatchEvent(new CustomEvent('formSubmitted', {detail: response})));
		},

		/**
		 * Handles current host deletion.
		 */
		deleteHost() {
			const curl = new Curl('zabbix.php', false),
				original_curl = new Curl(host_popup.original_url, false);

			if (basename(original_curl.getPath()) === 'hostinventories.php') {
				original_curl.unsetArgument('hostid');
				host_popup.original_url = original_curl.getUrl();
			}

			curl.setArgument('action', 'host.massdelete');
			curl.setArgument('ids', [document.getElementById('hostid').value]);
			curl.setArgument('back_url', host_popup.original_url);

			redirect(curl.getUrl(), 'post');
		},

		/**
		 * Collect fields & values to transfer to a host clone.
		 *
		 * @param {HTMLFormElement} form Cloneable host form.
		 *
		 * @return {object}             Fields/values to populate for clone form.
		 */
		getCloneData(form) {
			var fields = getFormFields(form);

			// Groups are not extracted properly by getFormFields.
			fields.groups = [];
			form.querySelectorAll('[name^="groups[]"]').forEach(group => {
				fields.groups.push(group.value);
			});

			if (document.querySelector('#change_psk')) {
				delete fields.tls_psk_identity;
				delete fields.tls_psk;
			}

			delete fields.action;
			delete fields.sid;

			return fields;
		}
	};

	<?php if (array_key_exists('popup_form', $data)): ?>
		/**
		 * In-popup listeners and set up, called when we are sure the popup HTML has been populated.
		 */
		function setupHostPopup() {
			document.getElementById('<?= $data['form_name'] ?>').addEventListener('formSubmitted', (e) => {
				let response = e.detail,
					overlay = overlays_stack.end(),
					$form = overlay.$dialogue.find('form');

				overlay.unsetLoading();
				overlay.$dialogue.find('.msg-bad, .msg-good').remove();

				if ('errors' in response) {
					jQuery(response.errors).insertBefore($form);
				}
				else if ('error' in response) {
					overlayDialogueDestroy(overlay.dialogueid);
				}
				else if ('hostid' in response) {
					// Original url restored after dialog close.
					overlayDialogueDestroy(overlay.dialogueid);

					const current_curl = new Curl(location.href, false);
					let filter_btn = document.querySelector('[name=filter_set]');

					if (current_curl.getArgument('action') === 'host.list' || typeof filter_btn !== 'undefined') {
						postMessageOk(response.message)
						redirect(current_curl.getUrl())
					}
					else {
						filter_btn = document.querySelector('[name="filter_apply"]');

						clearMessages();
						addMessage(response.message_box);

						if (typeof filter_btn !== 'undefined') {
							filter_btn.click();
						}
					}
				}
			});

			$('#tabs').on('tabsactivate change', () => {
				overlays_stack.end().centerDialog();
			});

			var clone_button = document.querySelector('.js-clone-host'),
				full_clone_button = document.querySelector('.js-full-clone-host');

			/**
			* Supplies a handler for in-popup clone button click with according action.
			*
			* @param {string} operation_type Either 'clone' or 'full_clone'.
			*
			* @return {callable}             Click handler.
			*/
			function popupCloneHandler(operation_type) {
				return function() {
					var $form = overlays_stack.end().$dialogue.find('form'),
						curl = curl = new Curl(null, false);

					curl.setArgument(operation_type, 1);

					let params = {...host_edit.getCloneData($form[0])};
					params[operation_type] = 1;

					PopUp('popup.host.edit', params, 'host_edit');
					history.replaceState({}, '', curl.getUrl());
				};
			}

			if (clone_button) {
				clone_button.addEventListener('click', popupCloneHandler('clone'));
			}

			if (full_clone_button) {
				full_clone_button.addEventListener('click', popupCloneHandler('full_clone'));
			};

			window.addEventListener('popstate', () => {
				const overlay = overlays_stack.end();

				if (overlay) {
					overlayDialogueDestroy(overlay.dialogueid);
				}
			}, {once: true});
		}
	<?php endif; ?>

	jQuery(document).ready(function() {
		'use strict';

		<?php if (array_key_exists('warnings', $data)): ?>
			jQuery(<?=json_encode($data['warnings'])?>).insertBefore(overlays_stack.end().$dialogue.find('form'));
		<?php endif; ?>

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| jQuery('#tls_in_cert').is(':checked')) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			// If PSK is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| jQuery('#tls_in_psk').is(':checked')) {
				jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').hide();
			}
		});

		// radio button of inventory modes was clicked
		jQuery('input[name=inventory_mode]').click(function() {
			// action depending on which button was clicked
			var inventoryFields = jQuery('#inventorylist :input:gt(2)');

			switch (jQuery(this).val()) {
				case '<?= HOST_INVENTORY_DISABLED ?>':
					inventoryFields.prop('disabled', true);
					jQuery('.populating_item').hide();
					break;
				case '<?= HOST_INVENTORY_MANUAL ?>':
					inventoryFields.prop('disabled', false);
					jQuery('.populating_item').hide();
					break;
				case '<?= HOST_INVENTORY_AUTOMATIC ?>':
					inventoryFields.prop('disabled', false);
					inventoryFields.filter('.linked_to_item').prop('disabled', true);
					jQuery('.populating_item').show();
					break;
			}
		});

		// Refresh field visibility on document load.
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			jQuery('#tls_in_none').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			jQuery('#tls_in_psk').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			jQuery('#tls_in_cert').prop('checked', true);
		}

		jQuery('input[name=tls_connect]').trigger('change');

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
					'SNMPV3_SECURITYLEVEL_AUTHNOPRIV' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,
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
						->toString()
				]
			]) ?>
		);

		hostInterfaceManager.render();

		<?php if ($host_is_discovered): ?>
			hostInterfaceManager.makeReadonly();
		<?php endif; ?>

		<?php if (!array_key_exists('popup_form', $data)): ?>
			const form = document.getElementById('<?= $data['form_name'] ?>');

			form.addEventListener('submit', (e) => {
				e.preventDefault();
				host_edit.submit(form);
			});

			form.addEventListener('formSubmitted', (e) => {
				let response = e.detail;

				clearMessages();

				if ('errors' in response) {
					addMessage(response.errors);
				}
				else if ('error' in response) {
					postMessageError(response.error);

					const curl = new Curl('zabbix.php');
					curl.setArgument('action', 'host.list');
					window.location = curl.getUrl();
				}
				else if ('hostid' in response) {
					const curl = new Curl('zabbix.php');

					postMessageOk(response.message);
					curl.setArgument('action', 'host.list');
					window.location = curl.getUrl();
				}
			});

			var clone_button = document.getElementById('clone'),
				full_clone_button = document.getElementById('full_clone');

			/**
			* Supplies a handler for in-page clone button click with according action.
			*
			* @param {string} operation_type Either 'clone' or 'full_clone'.
			*
			* @return {callable}             Click handler.
			*/
			function inlineCloneHandler(operation_type) {
				return function() {
					var curl = new Curl('zabbix.php', false),
						fields = host_edit.getCloneData(form);

					curl.setArgument('action', 'host.edit');
					curl.setArgument(operation_type, 1);

					for (const [k, v] of Object.entries(fields)) {
						curl.setArgument(k, v);
					}

					redirect(curl.getUrl(), 'post');
				};
			}

			if (clone_button) {
				clone_button.addEventListener('click', inlineCloneHandler('clone'));
			}

			if (full_clone_button) {
				full_clone_button.addEventListener('click', inlineCloneHandler('full_clone'));
			}
		<?php endif; ?>
	});
</script>
