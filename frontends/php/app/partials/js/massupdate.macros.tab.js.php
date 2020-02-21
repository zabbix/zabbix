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
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}')
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			(new CCol(
				new CMacroValue(['type' => ZBX_MACRO_TYPE_TEXT, 'value' => ''], 'macros[#{rowNum}]', ['add_post_js' => false])
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

	jQuery(document.getElementById('tbl_macros'))
		.dynamicRows({template: '#macro-row-tmpl'})
		.on('click', 'button.element-table-add', function() {
			initMacroFields(jQuery(document.getElementById('tbl_macros')));
		});

	// TODO: add additional macro helper events.

	class MassUpdateMacros {

		constructor() {
			const elem = document.querySelectorAll('[name=mass_update_macros]');

			[...elem].map((el) => el.addEventListener('click', this.controlEvent.bind(this)));
		}

		controlEvent() {
			const elem = document.querySelector('#mass_update_macros');
			const value = elem.querySelector('input:checked').value;
			const macro_table = document.querySelector('#tbl_macros');
			const custom_style = document.querySelector('style#macros-massupdate-remove');

			macro_table.style.display = 'table';

			if (custom_style) {
				custom_style.remove();
			}

			this.showCheckboxBlock(value);

			// Hide value and description cell from table.
			if (value == <?= ZBX_ACTION_REMOVE ?>) {
				this.createStyleRule();
			}

			// Hide macros table.
			if (value == <?= ZBX_ACTION_REMOVE_ALL ?>) {
				document.querySelector('#tbl_macros').style.display = 'none';
			}
		}

		showCheckboxBlock(type) {
			// Hide all checkbox.
			[...document.querySelectorAll('.<?= ZBX_STYLE_CHECKBOX_BLOCK ?>')].map((el) => {
				el.style.display = 'none';
			});

			// Show proper checkbox.
			document.querySelector('[data-type=\'' + type + '\']').style.display = 'block';
		}

		createStyleRule() {
			const style = document.createElement('style');
			style.id = 'macros-massupdate-remove';
			style.innerHTML = `#tbl_macros td, #tbl_macros th {
					display: none;
				}
				#tbl_macros td:nth-child(1), #tbl_macros th:nth-child(1), #tbl_macros td.nowrap {
					display: table-cell;
				}`;

			document.body.append(style);
		}
	}

	new MassUpdateMacros();
</script>
