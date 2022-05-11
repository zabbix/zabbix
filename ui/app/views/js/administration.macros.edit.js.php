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
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
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
	$(function() {
		const table = $('#tbl_macros');
		let removed = 0;

		table
			.on('click', 'button.element-table-remove', function() {
				// check if the macro has an hidden ID element, if it does - increment the deleted macro counter
				removed += $('#macros_' + $(this).attr('id').split('_')[1] + '_globalmacroid').length;
			})
			.dynamicRows({template: '#macro-row-tmpl'})
			.on('afteradd.dynamicRows', function() {
				$('.macro-input-group', table).macroValue();
				$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', table).textareaFlexible();
			})
			.find('.macro-input-group')
			.macroValue();

		table
			.on('change keydown', '.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>.macro', function(event) {
				if (event.type === 'change' || event.which === 13) {
					$(this)
						.val($(this).val().replace(/([^:]+)/, (value) => value.toUpperCase('$1')))
						.textareaFlexible();
				}
			})
			.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
			.textareaFlexible();

		$('#update').click(function() {
			if (removed) {
				return confirm(<?= json_encode(_('Are you sure you want to delete?')) ?> + ' ' + removed + ' '
					+ <?= json_encode(_('macro(s)')) ?> + '?'
				);
			}
		});
	});
</script>
