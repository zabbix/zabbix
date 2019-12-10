<script type="text/x-jquery-tmpl" id="macro-row-tmpl-inherited">
	<?= (new CRow([
			(new CCol([
				(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
					->addClass('macro')
					->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
					->setAttribute('placeholder', '{$MACRO}'),
				$data['show_inherited_macros']
					? new CInput('hidden', 'macros[#{rowNum}][type]', 2)
					: null
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			'&rArr;',
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][value]', '', ['add_post_js' => false]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('value'))
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
			))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)->setColSpan(8),
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
					->setAttribute('placeholder', '{$MACRO}'),
				$data['show_inherited_macros']
					? new CInput('hidden', 'macros[#{rowNum}][type]', 2)
					: null
			]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
			'&rArr;',
			(new CCol(
				(new CTextAreaFlexible('macros[#{rowNum}][value]', '', ['add_post_js' => false]))
					->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					->setAttribute('placeholder', _('value'))
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
	jQuery(function($) {
		function init_fields($parent) {
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
			.dynamicRows({remove_next_sibling: <?= (int) $data['show_inherited_macros'] ?>,
				template: <?= $data['show_inherited_macros'] ? "'#macro-row-tmpl-inherited'" : "'#macro-row-tmpl'" ?>
			})
			.on('click', 'button.element-table-add', function() {
				init_fields($('#tbl_macros'));
			})
			.on('click', 'button.element-table-change', function() {
				var macroNum = $(this).attr('id').split('_')[1];

				if ($('#macros_' + macroNum + '_type').val() & <?= ZBX_PROPERTY_OWN ?>) {
					$('#macros_' + macroNum + '_type')
						.val($('#macros_' + macroNum + '_type').val() & (~<?= ZBX_PROPERTY_OWN ?>));
					$('#macros_' + macroNum + '_value')
						.prop('readonly', true)
						.val($('#macros_' + macroNum + '_inherited_value').val())
						.trigger('input');
					$('#macros_' + macroNum + '_description')
						.prop('readonly', true)
						.val($('#macros_' + macroNum + '_inherited_description').val())
						.trigger('input');
					$('#macros_' + macroNum + '_change')
						.text(<?= CJs::encodeJson(_x('Change', 'verb')) ?>);
				}
				else {
					$('#macros_' + macroNum + '_type')
						.val($('#macros_' + macroNum + '_type').val() | <?= ZBX_PROPERTY_OWN ?>);
					$('#macros_' + macroNum + '_value')
						.prop('readonly', false)
						.focus();
					$('#macros_' + macroNum + '_description')
						.prop('readonly', false);
					$('#macros_' + macroNum + '_change')
						.text(<?= CJs::encodeJson(_('Remove')) ?>);
				}
			});

		init_fields($('#tbl_macros'));

		$('form[name="hostsForm"], form[name="templatesForm"]').submit(function() {
			$('input.macro').each(function() {
				macroToUpperCase(this);
			});
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
