<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

<script type="text/x-jquery-tmpl" id="macro-row-tmpl">
	<?= (new CRow([
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}')
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(['type' => ZBX_MACRO_TYPE_TEXT, 'value' => ''], 'macros[#{rowNum}]')
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setMaxlength(DB::getFieldLength('globalmacro' , 'description'))
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
	jQuery(function($) {
		function initMacroFields($parent) {
			$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', $parent).not('.initialized-field').each(function() {
				var $obj = $(this);

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

		$('#tbl_macros')
			.on('click', 'button.element-table-remove', function() {
				// check if the macro has an hidden ID element, if it does - increment the deleted macro counter
				var macroNum = $(this).attr('id').split('_')[1];
				if ($('#macros_' + macroNum + '_globalmacroid').length) {
					var count = $('#update').data('removedCount') + 1;
					$('#update').data('removedCount', count);
				}
			})
			.dynamicRows({template: '#macro-row-tmpl'})
			.on('click', 'button.element-table-add', function() {
				initMacroFields($('#tbl_macros'));
			})
			.on('focus blur', '.input-secret input, .input-group .textarea-flexible', function() {
				$(this)
					.closest('.input-group')
					.find('.btn-undo')
					.toggleClass('is-focused');
			})
			.on('click', '.btn-undo', function() {
				var $this = $(this),
					$container = $(this).closest('.input-group'),
					$input_container = $('.input-secret, .textarea-flexible', $container),
					$input = $('.input-secret input[type=password], .textarea-flexible', $container);

				$input_container.replaceWith(
					$('<div>')
						.addClass('input-secret')
						.append($('<input>').attr({
							id: $input.attr('id'),
							name: $input.attr('name'),
							type: 'password',
							value: '<?= ZBX_MACRO_SECRET_MASK ?>',
							placeholder: $input.attr('placeholder'),
							maxlength: $input.attr('maxlength'),
							disabled: true
						}))
						.append($('<button>').attr({
							type: 'button',
							id: $input.attr('name').replace(/\[/g, '_').replace(/\]/g, '') + '_btn',
							class: '<?= CInputSecret::ZBX_STYLE_BTN_CHANGE ?>'
						}).text(<?= json_encode(_('Set new value')) ?>))
						.inputSecret()
				);

				$('.btn-dropdown-container button', $container)
					.addClass(['btn-alt', 'btn-dropdown-toggle', 'icon-secret'].join(' '));

				$this.hide();
			})
			.on('change', '.dropdown-value', function() {
				var $this = $(this),
					value_type = $this.val(),
					$container = $(this).closest('.input-group'),
					$input_container = $('.input-secret', $container),
					$textarea = $('.textarea-flexible', $container);

				if ((value_type == <?= ZBX_MACRO_TYPE_TEXT ?> && $textarea.length)
						|| (value_type == <?= ZBX_MACRO_TYPE_SECRET ?> && $input_container.length)) {
					return false;
				}

				if (value_type == <?= ZBX_MACRO_TYPE_TEXT ?>) {
					var $input = $('input[type=password]', $input_container);

					if (!$input_container.data('is-activated')) {
						$('.btn-undo', $container).show();
						$input_container.data('is-activated', true);
					}

					$input_container.replaceWith(
						$('<textarea>')
							.addClass('textarea-flexible')
							.attr({
								id: $input.attr('id'),
								name: $input.attr('name'),
								placeholder: $input.attr('placeholder'),
								maxlength: $input.attr('maxlength')
							})
							.text($input.val())
							.textareaFlexible()
					);
				}
				else {
					$textarea.replaceWith(
						$('<div>')
							.addClass('input-secret')
							.append($('<input>').attr({
								id: $textarea.attr('id'),
								name: $textarea.attr('name'),
								type: 'password',
								value: $textarea.val(),
								placeholder: $textarea.attr('placeholder'),
								maxlength: $textarea.attr('maxlength')
							}))
							.inputSecret()
					);
				}
			});

		initMacroFields($('#tbl_macros'));

		$('#update').click(function() {
			var removedCount = $(this).data('removedCount');

			if (removedCount) {
				return confirm(<?= json_encode(_('Are you sure you want to delete')) ?> + ' ' + removedCount + ' '
					+ <?= json_encode(_('macro(s)')) ?> + '?'
				);
			}
		});

		function macroToUpperCase(element) {
			var macro = $(element).val(),
				end = macro.indexOf(':');

			if (end == -1) {
				$(element).val(macro.toUpperCase());
			}
			else {
				var macro_part = macro.substr(0, end),
					context_part = macro.substr(end, macro.length);

				$(element).val(macro_part.toUpperCase() + context_part);
			}
		}
	});
</script>
