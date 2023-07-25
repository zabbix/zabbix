<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

<script type="text/x-jquery-tmpl" id="groupPrototypeRow">
	<tr class="form_row">
		<td>
			<input name="group_prototypes[#{i}][name]" type="text" value="#{name}" style="width: 448px"
				placeholder="{#MACRO}" maxlength="255" />
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<button class="<?= ZBX_STYLE_BTN_LINK ?> group-prototype-remove" type="button" name="remove">
				<?= _('Remove') ?>
			</button>
		</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="host-interface-row-tmpl">
	<?= (new CPartial('configuration.host.interface.row'))->getOutput() ?>
</script>

<script>
	const view = {
		form_name: null,

		init({form_name}) {
			this.form_name = form_name;
			this.form = document.getElementById('host-prototype-form');
			this.initMacrosTab();

			this.form.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-edit-linked-template')) {
					this.openTemplatePopup({templateid: e.target.dataset.templateid});
				}
			});

			this.updateMultiselect();
		},

		initMacrosTab() {
			let $show_inherited_macros = $('input[name="show_inherited_macros"]'),
				linked_templateids = <?= json_encode($data['macros_tab']['linked_templates']) ?>;

			this.macros_manager = new HostMacrosManager(<?= json_encode([
				'readonly' => $data['readonly'],
				'parent_hostid' =>  array_key_exists('parent_hostid', $data) ? $data['parent_hostid'] : null
			]) ?>);

			$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
				let panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

				if (panel.attr('id') === 'macroTab') {
					const macros_initialized = panel.data('macros_initialized') || false;

					// Please note that macro initialization must take place once and only when the tab is visible.
					if (event.type === 'tabsactivate') {
						const macros_initialized = panel.data('macros_initialized') || false;

						// Please note that macro initialization must take place once and only when the tab is visible.
						if (event.type === 'tabsactivate') {
							let panel_templateids = panel.data('templateids') || [];
							let templateids = this.getAddTemplates();

							if (panel_templateids.xor(templateids).length > 0) {
								panel.data('templateids', templateids);

								this.macros_manager.load(
									$show_inherited_macros.filter(':checked').val() == 1,
									linked_templateids.concat(templateids)
								);

								panel.data('macros_initialized', true);
							}
						}
					}

					if (macros_initialized) {
						return;
					}

					// Initialize macros.
					<?php if ($data['readonly']): ?>
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
					<?php else: ?>
					this.macros_manager.initMacroTable($show_inherited_macros.filter(':checked').val() == 1);
					<?php endif ?>

					panel.data('macros_initialized', true);
				}
			});

			this.form.querySelector('#show_inherited_macros').onchange = () => {
				this.macros_manager.load(
					this.form.querySelector('input[name=show_inherited_macros]:checked').value == 1,
					this.getAllTemplates()
				);
			}
		},

		/**
		 * Collects IDs selected in "Add templates" multiselect.
		 *
		 * @return {array}  Templateids.
		 */
		getAddTemplates() {
			const $ms = $('#add_templates_');
			let templateids = [];

			// Readonly forms don't have multiselect.
			if ($ms.length) {
				// Collect IDs from Multiselect.
				$ms.multiSelect('getData').forEach(function (template) {
					templateids.push(template.id);
				});
			}

			return templateids;
		},

		updateMultiselect() {
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
			const $template_multiselect = $('#add_templates_');
			const templateids = [];

			// Readonly forms don't have multiselect.
			if ($template_multiselect.length) {
				$template_multiselect.multiSelect('getData').forEach(template => {
					templateids.push(template.id);
				});
			}

			return templateids;
		},

		editHost(e, hostid) {
			return;
		},

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.openTemplatePopup(template_data);
		},

		openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.templateSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.close', () => new TabIndicators());
		},

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		events: {
			templateSuccess(e) {
				const data = e.detail;
				let curl = null;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if ('action' in data.success && data.success.action === 'delete') {
						curl = new Curl('host_discovery.php');
						curl.setArgument('context', 'template');
					}
				}

				if (curl) {
					location.href = curl.getUrl();
				}
				else {
					view.refresh();
				}
			}
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
			const host_interface_row_tmpl = document.getElementById('host-interface-row-tmpl').innerHTML;
			const hostInterfaceManagerInherit = new HostInterfaceManager(this._data.inherited_interfaces,
				host_interface_row_tmpl
			);
			hostInterfaceManagerInherit.setAllowEmptyMessage(!this._data.parent_is_template);
			hostInterfaceManagerInherit.render();
			hostInterfaceManagerInherit.makeReadonly();
		}

		initCustom() {
			const host_interface_row_tmpl = document.getElementById('host-interface-row-tmpl').innerHTML;
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

	function addGroupPrototypeRow(groupPrototype) {
		var addButton = jQuery('#group_prototype_add');

		var rowTemplate = new Template(jQuery('#groupPrototypeRow').html());
		groupPrototype.i = addButton.data('group-prototype-count');
		jQuery('#row_new_group_prototype').before(rowTemplate.evaluate(groupPrototype));

		addButton.data('group-prototype-count', addButton.data('group-prototype-count') + 1);
	}

	jQuery(function() {
		'use strict';

		const interface_source_switcher = new InterfaceSourceSwitcher(
			document.getElementById('interfaces-table'),
			document.getElementById('custom_interfaces'),
			document.getElementById('interface-add'),
			<?= json_encode([
				'parent_is_template' => $data['parent_host']['status'] == HOST_STATUS_TEMPLATE,
				'is_templated' => $data['host_prototype']['templateid'] != 0,
				'inherited_interfaces' => array_values($data['parent_host']['interfaces']),
				'custom_interfaces' => array_values($data['host_prototype']['interfaces'])
			]) ?>
		);

		jQuery('#group_prototype_add')
			.data('group-prototype-count', jQuery('#tbl_group_prototypes').find('.group-prototype-remove').length)
			.click(function() {
				addGroupPrototypeRow({})
			});

		jQuery('#tbl_group_prototypes').on('click', '.group-prototype-remove', function() {
			jQuery(this).closest('.form_row').remove();
		});

		<?php if (!$data['host_prototype']['groupPrototypes']): ?>
			addGroupPrototypeRow({'name': ''});
		<?php endif ?>
		<?php foreach ($data['host_prototype']['groupPrototypes'] as $i => $groupPrototype): ?>
			addGroupPrototypeRow(<?= json_encode(['name' => $groupPrototype['name']]) ?>);
		<?php endforeach ?>

		<?php if ($data['host_prototype']['templateid']): ?>
			jQuery('#tbl_group_prototypes').find('input').prop('readonly', true);
			jQuery('#tbl_group_prototypes').find('button').prop('disabled', true);
		<?php endif ?>

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			jQuery('#tls_issuer, #tls_subject').closest('li').toggle(jQuery('#tls_in_cert').is(':checked')
					|| jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>);

			jQuery('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').toggle(jQuery('#tls_in_psk').is(':checked')
					|| jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>);
		});

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

		jQuery('#host')
			.on('input keydown paste', function () {
				$('#name').attr('placeholder', $(this).val());
			})
			.trigger('input');
	});
</script>
