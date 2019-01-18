<script type="text/x-jquery-tmpl" id="filter-tag-row">
	<?= (new CRow([
			(new CTextBox('filter_tags[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('filter_tags[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
				->addValue(_('Contains'), TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
				->setModern(true),
			(new CTextBox('filter_tags[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('filter_tags[#{rowNum}][remove]', _('Remove')))
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
		$(function() {
			$('#filter-tags').dynamicRows({ template: '#filter-tag-row' });
		});

		$('#filter_state').change(function() {
			$('input[name=filter_status]').prop('disabled', $('input[name=filter_state]:checked').val() != -1);
		})
		.trigger('change', false);
	});
</script>
<script type="text/javascript">
	+function($) {
		function flagged_filter_priority() {
			var form = $('form[name="zbx_filter"]');
			var inputs = form.find('input[name^=filter_priority]');
			var flag_input = $('<input>', {value: '0', type:'hidden', name:'filter_priority_flag'});

			inputs.removeAttr('name')
			form.append(flag_input)

			inputs.change(function() {
				var new_flag = 0
				inputs.each(function(i, node) {
					node.checked && (new_flag += (1 << node.value))
				})
				flag_input.val(new_flag)
			})
			inputs.change()
		}
		$(flagged_filter_priority)
	}(jQuery)
</script>
