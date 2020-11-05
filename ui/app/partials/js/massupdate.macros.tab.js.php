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
 * @var CPartial $this
 */
?>

<script type="text/x-jquery-tmpl" id="macro-row-tmpl">
	<?= (new CRow([
			(new CCol([
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}')
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
					->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
					->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
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
$('#tbl_macros')
	.dynamicRows({template: '#macro-row-tmpl'})
	.on('afteradd.dynamicRows', function() {
		$('.input-group', $('#tbl_macros')).macroValue();
	})
	.find('.input-group')
	.macroValue();

$('#tbl_macros')
	.on('change keydown', '.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>.macro', function(event) {
		if (event.type === 'change' || event.which === 13) {
			$(this)
				.val($(this).val().replace(/([^:]+)/, (value) => value.toUpperCase('$1')))
				.textareaFlexible();
		}
	})
	.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
	.textareaFlexible();

if (!window.hasOwnProperty('MassUpdateMacros')) {
	class MassUpdateMacros {
		constructor() {
			const elem = document.querySelectorAll('[name=mass_update_macros]');
			const form = document.getElementById('mass_update_macros').closest('form');

			this.eventHandler = this.controlEventHandle.bind(this);

			[...elem].map((el) => el.addEventListener('click', this.eventHandler));

			// If form updated select proper checkbox blocks.
			this.eventHandler();
		}

		controlEventHandle() {
			const elem = document.getElementById('mass_update_macros');
			const value = elem.querySelector('input:checked').value;
			const macro_table = document.getElementById('tbl_macros');

			macro_table.classList.remove('massupdate-remove');
			macro_table.style.display = 'table';

			this.showCheckboxBlock(value);

			// Hide value and description cell from table.
			if (value == <?= ZBX_ACTION_REMOVE ?>) {
				macro_table.classList.add('massupdate-remove');
			}

			// Hide macros table.
			if (value == <?= ZBX_ACTION_REMOVE_ALL ?>) {
				macro_table.style.display = 'none';
			}
		}

		showCheckboxBlock(type) {
			// Hide all checkboxes.
			[...document.querySelectorAll('.<?= ZBX_STYLE_CHECKBOX_BLOCK ?>')].map((el) => {
				el.style.display = 'none';
			});

			// Show proper checkbox.
			document.querySelector(`[data-type='${type}']`).style.display = 'block';
		}

		destroy() {
			const elem = document.querySelectorAll('[name=mass_update_macros]');
			const form = document.getElementById('mass_update_macros').closest('form');

			[...elem].map((el) => el.removeEventListener('click', this.eventHandler));
		}
	}
	new MassUpdateMacros();
}

</script>
