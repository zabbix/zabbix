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
	(function($) {
		$(function() {
			$('#filter-inventory').dynamicRows({ template: '#filter-inventory-row' });
			$('#filter-tags').dynamicRows({ template: '#filter-tag-row' });
		});
	})(jQuery);
</script>
