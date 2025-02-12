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

<script>
	const view = new class {

		init({form_name, readonly, parent_hostid, group_prototypes, prototype_templateid, prototype_interfaces,
				parent_host_interfaces, parent_host_status}) {
			this.form = document.getElementById('host-prototype-form');
			this.form_name = form_name;
			this.readonly = readonly;
			this.parent_hostid = parent_hostid;
			this.group_prototypes = group_prototypes;
			this.prototype_templateid = prototype_templateid;
			this.prototype_interfaces = prototype_interfaces;
			this.parent_host_interfaces = parent_host_interfaces;
			this.parent_host_status = parent_host_status;
			this.macros_templateids = null;
			this.show_inherited_macros = false;

			this.initHostTab();
			this.initMacrosTab();
			this.initInventoryTab();
			this.initEncryptionTab();
			this.#initPopupListeners();
		}

		initHostTab() {
			jQuery('#host')
				.on('input keydown paste', function () {
					$('#name').attr('placeholder', $(this).val());
				})
				.trigger('input');

			const $groups_ms = $('#groups_, #group_links_');
			const $template_ms = $('#add_templates_');

			$template_ms.on('change', () => {
				$template_ms.multiSelect('setDisabledEntries', this.getAllTemplates());
			});

			$groups_ms.on('change', () => {
				$groups_ms.multiSelect('setDisabledEntries',
					[...document.querySelectorAll('[name^="groups["], [name^="group_links["]')]
						.map((input) => input.value)
				)
			});

			new InterfaceSourceSwitcher(
				document.getElementById('interfaces-table'),
				document.getElementById('custom_interfaces'),
				document.getElementById('interface-add'),
				{
					parent_is_template: this.parent_host_status == <?= HOST_STATUS_TEMPLATE ?>,
					is_templated: this.prototype_templateid != 0,
					inherited_interfaces: this.parent_host_interfaces,
					custom_interfaces: this.prototype_interfaces
				}
			);

			jQuery('#group_prototype_add')
				.data('group-prototype-count', jQuery('#tbl_group_prototypes').find('.group-prototype-remove').length)
				.click(() => {
					this.addGroupPrototypeRow({})
				});

			jQuery('#tbl_group_prototypes').on('click', '.group-prototype-remove', function() {
				jQuery(this).closest('.form_row').remove();
			});

			if (this.group_prototypes.length === 0) {
				this.addGroupPrototypeRow({});
			}

			this.group_prototypes.forEach((group_prototype) => {
				this.addGroupPrototypeRow(group_prototype);
			});

			if (this.prototype_templateid != 0) {
				jQuery('#tbl_group_prototypes').find('input').prop('readonly', true);
				jQuery('#tbl_group_prototypes').find('button').prop('disabled', true);
			}
		}

		initMacrosTab() {
			this.macros_manager = new HostMacrosManager({
				container: $('#macros_container .table-forms-td-right'),
				readonly: this.readonly,
				parent_hostid: this.parent_hostid
			});

			const show_inherited_macros_element = document.getElementById('show_inherited_macros');
			this.show_inherited_macros = show_inherited_macros_element.querySelector('input:checked').value == 1;

			this.macros_manager.initMacroTable(this.show_inherited_macros);

			const observer = new IntersectionObserver(entries => {
				if (entries[0].isIntersecting && this.show_inherited_macros) {
					const templateids = this.getAllTemplates();

					if (this.macros_templateids === null || this.macros_templateids.xor(templateids).length > 0) {
						this.macros_templateids = templateids;

						this.macros_manager.load(this.show_inherited_macros, templateids);
					}
				}
			});
			observer.observe(document.getElementById('macro-tab'));

			show_inherited_macros_element.addEventListener('change', e => {
				this.show_inherited_macros = e.target.value == 1;
				this.macros_templateids = this.getAllTemplates();

				this.macros_manager.load(this.show_inherited_macros, this.macros_templateids);
			});
		}

		initInventoryTab() {
			jQuery('input[name=inventory_mode]').click(function() {
				// Action depending on which button was clicked.
				const $inventory_fields = jQuery('#inventorylist :input:gt(2)');

				switch (this.value) {
					case '<?= HOST_INVENTORY_DISABLED ?>':
						$inventory_fields.prop('disabled', true);
						jQuery('.populating_item').hide();
						break;
					case '<?= HOST_INVENTORY_MANUAL ?>':
						$inventory_fields.prop('disabled', false);
						jQuery('.populating_item').hide();
						break;
					case '<?= HOST_INVENTORY_AUTOMATIC ?>':
						$inventory_fields.prop('disabled', false);
						$inventory_fields.filter('.linked_to_item').prop('disabled', true);
						jQuery('.populating_item').show();
						break;
				}
			});
		}

		initEncryptionTab() {
			jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
				jQuery('#tls_issuer, #tls_subject').closest('li').toggle(jQuery('#tls_in_cert').is(':checked')
					|| jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

				jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').toggle(jQuery('#tls_in_psk').is(':checked')
					|| jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>);
			});

			// Refresh field visibility on document load.
			let tls_accept = jQuery('#tls_accept').val();

			if ((tls_accept & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
				jQuery('#tls_in_none').prop('checked', true);
			}
			if ((tls_accept & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
				jQuery('#tls_in_psk').prop('checked', true);
			}
			if ((tls_accept & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
				jQuery('#tls_in_cert').prop('checked', true);
			}

			jQuery('input[name=tls_connect]').trigger('change');
		}

		#initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_OPEN,
					action: 'host.edit'
				},
				callback: ({data, event}) => {
					event.preventDefault();

					const standalone_url_params = objectToSearchParams({
						action: CPopupManager.STANDALONE_ACTION,
						popup: 'host.edit',
						...data.action_parameters
					}).toString();

					const standalone_url = new URL(`zabbix.php?${standalone_url_params}`, location.href);

					location.href = standalone_url.href;
				}
			});

			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					if (data.submit.success.action === 'delete') {
						const url = new URL('host_discovery.php', location.href);

						url.searchParams.set('context', 'template');

						event.setRedirectUrl(url.href);
					}
					else {
						this.refresh();
					}
				}
			});
		}

		addGroupPrototypeRow(groupPrototype) {
			const addButton = jQuery('#group_prototype_add');

			const rowTemplate = new Template(jQuery('#groupPrototypeRow').html());
			groupPrototype.i = addButton.data('group-prototype-count');
			jQuery('#row_new_group_prototype').before(rowTemplate.evaluate(groupPrototype));

			addButton.data('group-prototype-count', addButton.data('group-prototype-count') + 1);
		}

		/**
		 * Collects ids of currently active (linked + new) templates.
		 *
		 * @return {array}  Templateids.
		 */
		getAllTemplates() {
			return this.getLinkedTemplates().concat(this.getNewTemplates());
		}

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
		}

		/**
		 * Helper to get added template IDs as an array.
		 *
		 * @return {array}  Templateids.
		 */
		getNewTemplates() {
			const $template_multiselect = $('#add_templates_');
			const templateids = [];

			// Readonly forms don't have multiselect.
			if ($template_multiselect.length) {
				$template_multiselect.multiSelect('getData').forEach(template => {
					templateids.push(template.id);
				});
			}

			return templateids;
		}

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		}
	}
</script>

<script type="text/javascript">
	class InterfaceSourceSwitcher extends CBaseComponent {
		constructor(target, source_input, add_button, data) {
			super(target);

			this._source_input = source_input;
			this._add_button = add_button;
			this._data = data;

			this._target_clone = this._target.cloneNode(true);

			this.register_events();
		}

		register_events() {
			switch (this.getSourceValue()) {
				case '<?= HOST_PROT_INTERFACES_INHERIT ?>':
					this.initInherit();
					this._target.customInterfaces = null;
					break;
				case '<?= HOST_PROT_INTERFACES_CUSTOM ?>':
					this.initCustom();
					this._target.inheritInterfaces = null;
					break;
			}

			this._source_input.addEventListener('change', () => {
				this.switchTo(this.getSourceValue());
			});
		}

		getSourceValue() {
			return this._source_input.querySelector('input[name=custom_interfaces]:checked').value;
		}

		initInherit() {
			const form = document.getElementById('host-prototype-form');
			const host_interface_row_tmpl = form.querySelector('#host-interface-row-tmpl').innerHTML;

			const hostInterfaceManagerInherit = new HostInterfaceManager(this._data.inherited_interfaces,
				host_interface_row_tmpl
			);
			hostInterfaceManagerInherit.setAllowEmptyMessage(!this._data.parent_is_template);
			hostInterfaceManagerInherit.render();
			hostInterfaceManagerInherit.makeReadonly();
		}

		initCustom() {
			const form = document.getElementById('host-prototype-form');
			const host_interface_row_tmpl = form.querySelector('#host-interface-row-tmpl').innerHTML;

			// This is in global space, as Add functions uses it.
			window.hostInterfaceManager = new HostInterfaceManager(this._data.custom_interfaces,
				host_interface_row_tmpl
			);
			hostInterfaceManager.render();

			if (this._data.is_templated) {
				hostInterfaceManager.makeReadonly();
			}
		}

		switchTo(source) {
			switch (source) {
				case '<?= HOST_PROT_INTERFACES_INHERIT ?>':
					this._add_button.style.display = 'none';

					if (!('inheritInterfaces' in this._target)) {
						// Do nothing.
					}
					else if (this._target.inheritInterfaces === null) {
						this._target.inheritInterfaces = this._target_clone;
						this._target_clone = null;
						this.switchToInherit();
						this.initInherit();
					}
					else {
						this.switchToInherit();
					}
					break;
				case '<?= HOST_PROT_INTERFACES_CUSTOM ?>':
					if (!('customInterfaces' in this._target)) {
						// Do nothing.
					}
					else if (this._target.customInterfaces === null) {
						this._target.customInterfaces = this._target_clone;
						this._target_clone = null;
						this.switchToCustom();
						this.initCustom();
					}
					else {
						this.switchToCustom();
					}

					this._add_button.style.display = 'inline-block';

					break;
			}
		}

		switchToInherit() {
			var obj_inherit = this._target.inheritInterfaces;
			obj_inherit.customInterfaces = this._target;

			this._target.replaceWith(obj_inherit);
			this._target = obj_inherit;
		}

		switchToCustom() {
			var obj_custom = this._target.customInterfaces;
			obj_custom.inheritInterfaces = this._target;

			this._target.replaceWith(obj_custom);
			this._target = obj_custom;
		}
	}
</script>
