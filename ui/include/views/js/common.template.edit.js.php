<?php
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

<?php if (!$data['readonly']): ?>
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

<script type="text/javascript">
	/**
	 * Collects IDs selected in "Add templates" multiselect.
	 *
	 * @returns {array|getAddTemplates.templateids}
	 */
	function getAddTemplates() {
		const $ms = $('#add_templates_');
		let templateids = [];

		// Readonly forms don't have multiselect.
		if ($ms.length) {
			// Collect IDs from Multiselect.
			$ms.multiSelect('getData').forEach(function(template) {
				templateids.push(template.id);
			});
		}

		return templateids;
	}

	jQuery(function($) {
		var $show_inherited_macros = $('input[name="show_inherited_macros"]'),
			linked_templateids = <?= json_encode($data['macros_tab']['linked_templates']) ?>;

		$('#template_name')
			.on('input keydown paste', function() {
				$('#visiblename').attr('placeholder', $(this).val());
			})
			.trigger('input');

		window.macros_manager = new HostMacrosManager(<?= json_encode([
			'readonly' => $data['readonly'],
			'parent_hostid' =>  array_key_exists('parent_hostid', $data) ? $data['parent_hostid'] : null
		]) ?>);

		$('#tabs').on('tabscreate tabsactivate', function(event, ui) {
			var panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'macroTab') {
				const macros_initialized = panel.data('macros_initialized') || false;

				// Please note that macro initialization must take place once and only when the tab is visible.
				if (event.type === 'tabsactivate') {
					let panel_templateids = panel.data('templateids') || [],
						templateids = getAddTemplates();

					if (panel_templateids.xor(templateids).length > 0) {
						panel.data('templateids', templateids);

						window.macros_manager.load(
							$show_inherited_macros.filter(':checked').val() == 1,
							linked_templateids.concat(templateids)
						);

						panel.data('macros_initialized', true);
					}
				}

				if (macros_initialized) {
					return;
				}

				// Initialize macros.
				<?php if ($data['readonly']): ?>
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
				<?php else: ?>
					window.macros_manager.initMacroTable($show_inherited_macros.filter(':checked').val() == 1);
				<?php endif ?>

				panel.data('macros_initialized', true);
			}
		});

		$show_inherited_macros.on('change', function() {
			if (!$(this).is(':checked')) {
				return;
			}

			window.macros_manager.load($(this).val() == 1, linked_templateids.concat(getAddTemplates()));
		});

		const $groups_ms = $('#groups_, #group_links_');
		const $template_ms = $('#add_templates_');

		$template_ms.on('change', (e) => {
			$template_ms.multiSelect('setDisabledEntries',
				[... document.querySelectorAll('[name^="add_templates["], [name^="templates["]')]
					.map((input) => input.value)
			);
		});

		$groups_ms.on('change', (e) => {
			$groups_ms.multiSelect('setDisabledEntries',
				[... document.querySelectorAll('[name^="groups["], [name^="group_links["]')]
					.map((input) => input.value)
			);
		});

		document
			.querySelector('#templates-form, #host-prototype-form')
			.addEventListener('submit', (e) => {
				e.preventDefault();

				const form = e.target;
				const form_fields = getFormFields(form);

				let submitter = e.submitter || document.activeElement;

				if (submitter.tagName !== 'BUTTON') {
					submitter = form.querySelector('button[type="submit"]');
				}

				form_fields[submitter.name] = submitter.value;

				const proxy_form = document.createElement('form');
				proxy_form.action = form.action;
				proxy_form.method = 'post';
				proxy_form.hidden = true;
				document.body.appendChild(proxy_form);

				const formdata_input = document.createElement('input');
				formdata_input.name = 'formdata_json';
				formdata_input.value = JSON.stringify(form_fields);
				proxy_form.appendChild(formdata_input);

				proxy_form.submit();
			}, {passive: false});
	});
</script>
