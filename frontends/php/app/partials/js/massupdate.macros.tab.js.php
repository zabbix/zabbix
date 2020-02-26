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

	jQuery(document.getElementById('tbl_macros'))
		.dynamicRows({template: '#macro-row-tmpl'})
		.on('click', 'button.element-table-add', function() {
			initMacroFields(jQuery(document.getElementById('tbl_macros')));
		});

	initMacroFields(jQuery(document.getElementById('tbl_macros')));

	function removeWarning(event) {
		if (!document.getElementById('visible_macros').checked) {
			return true;
		}

		const checkbox_state = document.getElementById('macros_remove_all').checked;
		const is_remove_block = document.querySelector('[name=mass_update_macros]:checked').value == <?= ZBX_ACTION_REMOVE_ALL ?>;

		if (is_remove_block && !checkbox_state) {
			event.preventDefault();

			overlayDialogue({
				'title': <?= json_encode(_('Warning')) ?>,
				'type': 'popup',
				'class': 'modal-popup modal-popup-medium',
				'content': jQuery('<span>').text(<?= json_encode(_('Please confirm your action.')) ?>),
				'buttons': [
					{
						'title': <?= json_encode(_('Ok')) ?>,
						'focused': true,
						'action': function() {}
					}
				]
			}, event.currentTarget);
		}
	}

	document
		.querySelector('#mass_update_macros')
		.closest('form')
		.addEventListener('submit', removeWarning);

	class MassUpdateMacros {

		constructor() {
			const elem = document.querySelectorAll('[name=mass_update_macros]');

			this.eventHandler = this.controlEventHandle.bind(this);

			[...elem].map((el) => el.addEventListener('click', this.eventHandler));
		}

		controlEventHandle() {
			const elem = document.querySelector('#mass_update_macros');
			const value = elem.querySelector('input:checked').value;
			const macro_table = document.querySelector('#tbl_macros');

			macro_table.style.display = 'table';

			macro_table.classList.remove('massupdate-remove');

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
			// Hide all checkbox.
			[...document.querySelectorAll('.<?= ZBX_STYLE_CHECKBOX_BLOCK ?>')].map((el) => {
				el.style.display = 'none';
			});

			// Show proper checkbox.
			document.querySelector('[data-type=\'' + type + '\']').style.display = 'block';
		}

		destroy() {
			const elem = document.querySelectorAll('[name=mass_update_macros]');

			[...elem].map((el) => el.removeEventListener('click', this.eventHandler));
		}
	}

	new MassUpdateMacros();
</script>
