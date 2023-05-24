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
 * @var array $data
 */
?>

window.template_edit_popup = new class {

	init({data}) {
		this.overlay = overlays_stack.getById('templates-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.data = data;

		this._initMacrosTab();

		const $groups_ms = $('#groups_, #group_links_');
		const $template_ms = $('#add_templates_');

		$template_ms.on('change', (e) => {
			$template_ms.multiSelect('setDisabledEntries', this.getAllTemplates());
		});


		$groups_ms.on('change', () => {
			$groups_ms.multiSelect('setDisabledEntries',
				[...document.querySelectorAll('[name^="groups["], [name^="group_links["]')]
					.map((input) => input.value)
			);
		});
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
		const $template_multiselect = $('#add_templates_'),
			templateids = [];

		// Readonly forms don't have multiselect.
		if ($template_multiselect.length) {
			$template_multiselect.multiSelect('getData').forEach(template => {
				templateids.push(template.id);
			});
		}

		return templateids;
	}

	_initMacrosTab() {
		const linked_templateids = <?= json_encode($data['linked_templates']) ?>;

		// Add visible name input field placeholder.
		$('#template_name')
			.on('input keydown paste', function () {
				$('#visiblename').attr('placeholder', $(this).val());
			})
			.trigger('input');

		//const show_inherited = document.querySelector('input[name=show_inherited_macros]:checked');
		this.macros_manager = new HostMacrosManager(<?= json_encode([
			'readonly' => $data['readonly'],
			'parent_hostid' => array_key_exists('parent_hostid', $data) ? $data['parent_hostid'] : null
		]) ?>);

		$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
			var panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'macroTab') {
				const macros_initialized = panel.data('macros_initialized') || false;

				// Please note that macro initialization must take place once and only when the tab is visible.
				if (event.type === 'tabsactivate') {
					let panel_templateids = panel.data('templateids') || [],
						templateids = this.getAddTemplates();

					if (panel_templateids.xor(templateids).length > 0) {
						panel.data('templateids', templateids);

						this.macros_manager.load(
							document.querySelector('input[name=show_inherited_macros]:checked').value == 1,
							linked_templateids.concat(templateids)
						);

						panel.data('macros_initialized', true);
					}
				}

				if (macros_initialized) {
					return;
				}

				// Initialize macros.
				<?php if ($data['readonly']): ;?>
				$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
				<?php else: ?>
				this.macros_manager.initMacroTable(
					document.querySelector('input[name=show_inherited_macros]:checked').value == 1
				);
				<?php endif ?>

				panel.data('macros_initialized', true);
			}
		});


		document.querySelector('#show_inherited_macros').onchange = (e) => {
			this.macros_manager.load(
				document.querySelector('input[name=show_inherited_macros]:checked').value == 1, linked_templateids.concat(this.getAddTemplates())
			);
		};
	}

	/**
	 * Collects IDs selected in "Add templates" multiselect.
	 *
	 * @returns {array|getAddTemplates.templateids}
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
	}
}
