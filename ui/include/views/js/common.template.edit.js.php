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
	jQuery(function($) {
		$('#template_name')
			.on('input keydown paste', function() {
				$('#visiblename').attr('placeholder', $(this).val());
			})
			.trigger('input');

		const $show_inherited_macros = $('input[name="show_inherited_macros"]');
		const {readonly, parent_hostid} = <?= json_encode(
			array_intersect_key($data, array_flip(['readonly', 'parent_hostid'])) + ['parent_hostid' => null]
		) ?>;

		window.macros_manager = new HostMacrosManager({readonly, parent_hostid});

		$('#tabs').on('tabscreate tabsactivate', function(e, ui) {
			var panel = (e.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'macroTab') {
				const show_inherited_macros = ($show_inherited_macros.filter(':checked').val() == 1);

				if (e.type === 'tabsactivate' && parent_hostid !== null) {
					const templateids = common_template_edit.getAllTemplates();

					if (typeof panel.data('templateids') === 'undefined') {
						panel.data('templateids', []);
					}

					if (show_inherited_macros
							&& common_template_edit.templatesChanged(panel.data('templateids'), templateids)) {
						panel.data('templateids', templateids);
						window.macros_manager.load(show_inherited_macros, templateids);
						panel.data('macros_initialized', true);
					}
				}

				if (panel.data('macros_initialized') ?? false === true) {
					return;
				}

				// Initialize macros.
				if (readonly === true) {
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
				}
				else {
					window.macros_manager.initMacroTable(show_inherited_macros);
				}

				panel.data('macros_initialized', true);
			}
		});

		$show_inherited_macros.on('change', function() {
			window.macros_manager.load(this.value == 1,
				parent_hostid !== null ? common_template_edit.getAllTemplates() : null
			);
		});

		if (parent_hostid !== null) {
			const $template_ms = $('#add_templates_');

			$template_ms.on('change', (e) => {
				$template_ms.multiSelect('setDisabledEntries', common_template_edit.getAllTemplates());
			});
		}

		const $groups_ms = $('#groups_, #group_links_');

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
