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

$linked_templates = ($data['host']['flags'] != ZBX_FLAG_DISCOVERY_CREATED)
	? array_column($data['host']['parentTemplates'], 'templateid')
	: [];
?>

<?php if ($data['host']['flags'] != ZBX_FLAG_DISCOVERY_CREATED): ?>
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
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)->setColSpan(8)
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
				const node = e.target;

				if (node.classList.contains('js-tmpl-unlink')) {
					if (node.dataset.templateid === undefined) {
						return;
					}

					node.closest('tr').remove();
					this.resetNewTemplatesField();
				}
				else if (node.classList.contains('js-tmpl-unlink-and-clear')) {
					if (node.dataset.templateid === undefined) {
						return;
					}

					var clear_tmpl = document.createElement('input');
					clear_tmpl.setAttribute('type', 'hidden');
					clear_tmpl.setAttribute('name', 'clear_templates[]');
					clear_tmpl.setAttribute('value', node.dataset.templateid);
					elt.form.appendChild(clear_tmpl);

					node.closest('tr').remove();
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
				linked_templates = [],
				data = $old_multiselect.multiSelect('getData');

			document.querySelectorAll('[name="templates[]').forEach((input) => {
				linked_templates.push(input.value);
			});

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
							disableids: linked_templates
						}
					}
				});
		},

		/**
		 * Set up of macros functionality.
		 */
		initMacrosTab() {
			var linked_templateids = <?= json_encode($linked_templates) ?>,
				$show_inherited_macros = $('input[name="show_inherited_macros"]');

			this.macros_manager = new HostMacrosManager(<?= json_encode([
				'properties' => [
					'readonly' => ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED)
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
							templateids = this.getNewlyAddedTemplates();

						if (panel_templateids.xor(templateids).length > 0) {
							panel.data('templateids', templateids);
							this.macros_manager.load($show_inherited_macros.val(),
								linked_templateids.concat(templateids)
							);
							panel.data('macros_initialized', true);
						}
					}

					if (macros_initialized) {
						return;
					}

					// Initialize macros.
					<?php if ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_CREATED): ?>
						$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
					<?php else: ?>
						this.macros_manager.initMacroTable(this.macros_manager.getMacroTable(),
							$('input[name="show_inherited_macros"]:checked').val()
						);
					<?php endif ?>

					panel.data('macros_initialized', true);
				}
			});

			$show_inherited_macros.on('change', (e) => {
				if (e.target.name !== 'show_inherited_macros') {
					return;
				}

				let templateids = linked_templateids.concat(this.getNewlyAddedTemplates());
				this.macros_manager.load(e.value, templateids);
				this.updateEncryptionFields();
			});
		},

		/**
		 * Helper to get added template IDs as an array.
		 * @returns {array}
		*/
		getNewlyAddedTemplates() {
			let $template_multiselect = $('#add_templates_'),
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
					|| selected_connection == <?= HOST_ENCRYPTION_CERTIFICATE ?>),
				psk_field_display = use_psk ? '' : 'none',
				cert_field_display = use_cert ? '' : 'none';

			// If PSK is selected or checked.
			if (document.querySelector('#change_psk')) {
				document.querySelector('#change_psk').closest('div').style.display = cert_field_display;
				document.querySelector('[for="change_psk"]').style.display = cert_field_display;

				// As long as button is there, other PSK fields must be hidden.
				psk_field_display = 'none';
			}

			document.querySelector('#tls_psk_identity').closest('div').style.display = psk_field_display;
			document.querySelector('[for="tls_psk_identity"]').style.display = psk_field_display;
			document.querySelector('#tls_psk').closest('div').style.display = psk_field_display;
			document.querySelector('[for="tls_psk"]').style.display = psk_field_display;

			// If certificate is selected or checked.
			document.querySelector('#tls_issuer').closest('div').style.display = cert_field_display;
			document.querySelector('[for="tls_issuer"]').style.display = cert_field_display;
			document.querySelector('#tls_subject').closest('div').style.display = cert_field_display;
			document.querySelector('[for="tls_subject"]').style.display = cert_field_display;

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
			const curl = new Curl('zabbix.php', false);

			curl.setArgument('action', 'host.massdelete');
			curl.setArgument('ids', [document.getElementById('hostid').value]);
			curl.setArgument('back_url', host_popup.original_url);

			redirect(curl.getUrl(), 'post');
		},

		/**
		 * Collect fields & values to transfer to a host clone.
		 *
		 * @param {HTMLFormElement} form Cloneable host form.
		 * @returns {object}             Fields/values to populate for clone form.
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
				else if ('hostid' in response) {
					clearMessages();
					addMessage(response.message_box);

					overlayDialogueDestroy(overlay.dialogueid);

					let current_curl = new Curl(location.href, false);

					if (current_curl.getArgument('action') === 'host.list') {
						// Todo: refresh lists
						alert('todo: refresh host.list or [name="filter_set"] while keeping messages')
					}
					else {
						let filter_btn = document.querySelector('[name="filter_apply"]');

						if (filter_btn) {
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

	document.addEventListener('DOMContentLoaded', () => {
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
