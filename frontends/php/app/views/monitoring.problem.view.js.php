<script type="text/x-jquery-tmpl" id="filter-inventory-row">
	<?= (new CRow([
			new CComboBox('filter_inventory[#{rowNum}][field]', null, null, $data['filter']['inventories']),
			(new CTextBox('filter_inventory[#{rowNum}][value]'))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('filter_inventory[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="filter-tag-row">
	<?= (new CRow([
			(new CTextBox('filter_tags[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('filter_tags[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
				->addValue(_('Like'), TAG_OPERATOR_LIKE)
				->addValue(_('Equal'), TAG_OPERATOR_EQUAL)
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
			$('#filter-inventory').dynamicRows({ template: '#filter-inventory-row' });
			$('#filter-tags').dynamicRows({ template: '#filter-tag-row' });
		});

		$('#filter_show').change(function() {
			var	filter_show = jQuery('input[name=filter_show]:checked').val();

			$('#filter_age').closest('li').toggle(filter_show == <?= TRIGGERS_OPTION_RECENT_PROBLEM ?>
				|| filter_show == <?= TRIGGERS_OPTION_IN_PROBLEM ?>);
		});

		$('#filter_show').trigger('change');

		$('#filter_compact_view').click(function() {
			if ($(this).is(':checked')) {
				$('#filter_show_timeline, #filter_details').attr('disabled', true);
				$('#filter_highlight_row').removeAttr('disabled');
			}
			else {
				$('#filter_show_timeline, #filter_details').removeAttr('disabled');
				$('#filter_highlight_row').attr('disabled', true);
			}
		});

		$('table.compact-view a.link-action').mouseover(function() {
			var anchor = $(this),
				anchor_width = anchor.width(),
				cell_width = anchor.parent('td').width();

			if (!anchor.attr('title') && anchor_width > cell_width) {
				anchor.attr({title: anchor.text()});
			}
		});
	});
</script>
