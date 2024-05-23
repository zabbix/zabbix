<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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
	<script type="text/javascript">
		function initMacroFields($parent) {
			jQuery('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', $parent).not('.initialized-field').each(function() {
				var $obj = jQuery(this);

				$obj.addClass('initialized-field');

				if ($obj.hasClass('macro')) {
					$obj.on('change keydown', function(e) {
						if (e.type === 'change' || e.which === 13) {
							macroToUpperCase(this);
							$obj.textareaFlexible();
						}
					});
				}

				$obj.textareaFlexible();
			});
		}

		function initMacroTable($parent, show_inherited_macros) {
			$parent
				.dynamicRows({
					remove_next_sibling: show_inherited_macros,
					template: show_inherited_macros ? '#macro-row-tmpl-inherited' : '#macro-row-tmpl'
				})
				.on('click', 'button.element-table-add', function() {
					initMacroFields($parent);
				})
				.on('click', 'button.element-table-change', function() {
					const macro_num = jQuery(this).attr('id').split('_')[1];

					if (jQuery('#macros_' + macro_num + '_inherited_type').val() & <?= ZBX_PROPERTY_OWN ?>) {
						const macro_type = jQuery('#macros_' + macro_num + '_inherited_macro_type').val();

						jQuery('#macros_' + macro_num + '_inherited_type')
							.val(jQuery('#macros_' + macro_num + '_inherited_type').val() & (~<?= ZBX_PROPERTY_OWN ?>));

						jQuery('#macros_' + macro_num + '_description')
							.prop('readonly', true)
							.val(jQuery('#macros_' + macro_num + '_inherited_description').val())
							.trigger('input');

						const $dropdown_btn = jQuery('#macros_' + macro_num + '_type_btn');

						$dropdown_btn
							.removeClass()
							.addClass(['btn-alt', 'btn-dropdown-toggle', (macro_type == <?= ZBX_MACRO_TYPE_SECRET ?>)
								? '<?= ZBX_STYLE_ICON_SECRET_TEXT ?>'
								: '<?= ZBX_STYLE_ICON_TEXT ?>'
							].join(' '));

						jQuery('input[type=hidden]', $dropdown_btn.parent())
							.val(macro_type)
							.trigger('change');

						$dropdown_btn
							.prop('disabled', true)
							.attr({'aria-haspopup': false});

						jQuery('#macros_' + macro_num + '_value')
							.prop('readonly', true)
							.val(jQuery('#macros_' + macro_num + '_inherited_value').val())
							.trigger('input');

						if (macro_type == <?= ZBX_MACRO_TYPE_SECRET ?>) {
							jQuery('#macros_' + macro_num + '_value').prop('disabled', true);
						}

						jQuery('#macros_' + macro_num + '_value_btn')
							.prop('disabled', true)
						jQuery('#macros_' + macro_num + '_value')
							.closest('.input-group')
							.find('.btn-undo')
							.hide();

						jQuery('#macros_' + macro_num + '_change').text(<?= json_encode(_x('Change', 'verb')) ?>);
					}
					else {
						jQuery('#macros_' + macro_num + '_inherited_type')
							.val(jQuery('#macros_' + macro_num + '_inherited_type').val() | <?= ZBX_PROPERTY_OWN ?>);
						jQuery('#macros_' + macro_num + '_value')
							.prop('readonly', false)
							.focus();
						jQuery('#macros_' + macro_num + '_value_btn').prop('disabled', false);
						jQuery('#macros_' + macro_num + '_description').prop('readonly', false);
						jQuery('#macros_' + macro_num + '_type_btn')
							.prop('disabled', false)
							.attr({'aria-haspopup': 'true'});
						jQuery('#macros_' + macro_num + '_change').text(<?= json_encode(_('Remove')) ?>);
					}
				})
				.on('afteradd.dynamicRows', function() {
					jQuery('.input-group').macroValue();
				});

			initMacroFields($parent);
		}

		function macroToUpperCase(element) {
			var macro = jQuery(element).val(),
				end = macro.indexOf(':');

			if (end == -1) {
				jQuery(element).val(macro.toUpperCase());
			}
			else {
				var macro_part = macro.substr(0, end),
					context_part = macro.substr(end, macro.length);

				jQuery(element).val(macro_part.toUpperCase() + context_part);
			}
		}
	</script>
<?php endif ?>

<script type="text/javascript">
	/**
	 * Collects IDs selected in "Add templates" multiselect.
	 *
	 * @param {jQuery} $ms  jQuery object of multiselect.
	 *
	 * @returns {array|getAddTemplates.templateids}
	 */
	function getAddTemplates($ms) {
		var templateids = [];

		// Readonly forms don't have multiselect.
		if ($ms.length) {
			// Collect IDs from Multiselect.
			$ms.multiSelect('getData').forEach(function(template) {
				templateids.push(template.id);
			});
		}

		return templateids;
	}

	/**
	 * Get macros from Macros tab form.
	 *
	 * @param {jQuery} $form  jQuery object for host edit form.
	 *
	 * @returns {array}        List of all host macros in the form.
	 */
	function getMacros($form) {
		var $macros = $form.find('input[name^="macros"], textarea[name^="macros"]').not(':disabled'),
			macros = {};

		// Find the correct macro inputs and prepare to submit them via AJAX. matches[1] - index, matches[2] field name.
		$macros.each(function() {
			var $this = jQuery(this),
				matches = $this.attr('name').match(/macros\[(\d+)\]\[(\w+)\]/);

			if (!macros.hasOwnProperty(matches[1])) {
				macros[matches[1]] = new Object();
			}

			macros[matches[1]][matches[2]] = $this.val();
		});

		return macros;
	}

	jQuery(function($) {
		var $container = $('#macros_container .table-forms-td-right'),
			$ms = $('#add_templates_'),
			$show_inherited_macros = $('input[name="show_inherited_macros"]'),
			$form = $show_inherited_macros.closest('form'),
			linked_templates = <?= json_encode($data['macros_tab']['linked_templates']) ?>,
			add_templates = <?= json_encode($data['macros_tab']['add_templates']) ?>,
			macros_initialized = false;

		$('#tabs').on('tabscreate tabsactivate', function(event, ui) {
			var panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'macroTab') {
				// Please note that macro initialization must take place once and only when the tab is visible.

				if (event.type === 'tabsactivate') {
					var add_templates_tmp = getAddTemplates($ms);

					if (add_templates.xor(add_templates_tmp).length > 0) {
						add_templates = add_templates_tmp;
						$show_inherited_macros.trigger('change');
						macros_initialized = true;
					}
				}

				if (macros_initialized) {
					return;
				}

				// Initialize macros.
				<?php if ($data['readonly']): ?>
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
				<?php else: ?>
					initMacroTable($('#tbl_macros'), $('input[name="show_inherited_macros"]:checked').val() == 1);
				<?php endif ?>

				macros_initialized = true;
			}
		});

		$show_inherited_macros.on('change', function() {
			if (!$(this).is(':checked')) {
				return;
			}

			var url = new Curl('zabbix.php'),
				macros = getMacros($form),
				show_inherited_macros = $(this).val() == 1;

			url.setArgument('action', 'hostmacros.list');

			$container
				.empty()
				.append($('<span>', {class: 'is-loading'}));

			$.ajax(url.getUrl(), {
				data: {
					macros: macros,
					show_inherited_macros: show_inherited_macros ? 1 : 0,
					templateids: linked_templates.concat(add_templates),
					<?= array_key_exists('parent_hostid', $data) ? 'parent_hostid: '.json_encode($data['parent_hostid']).',' : '' ?>
					readonly: <?= (int) $data['readonly'] ?>
				},
				dataType: 'json',
				method: 'POST'
			})
				.done(function(response) {
					if (typeof response === 'object' && 'errors' in response) {
						$container.append(response.errors);
					}
					else {
						if (typeof response.messages !== 'undefined') {
							$container.append(response.messages);
						}

						$container.append(response.body);

						// Initialize macros.
						<?php if ($data['readonly']): ?>
							$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', '#tbl_macros').textareaFlexible();
						<?php else: ?>
							initMacroTable($('#tbl_macros'), show_inherited_macros);
						<?php endif ?>

						// Display debug after loaded content if it is enabled for user.
						if (typeof response.debug !== 'undefined') {
							$container.append(response.debug);

							// Override margin for inline usage.
							$('.debug-output', $container).css('margin', '10px 0');
						}
					}
				})
				.always(function() {
					$('.is-loading', $container).remove();
				});
		});
	});
</script>
