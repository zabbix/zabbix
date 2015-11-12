<script type="text/x-jquery-tmpl" id="mapping_row">
	<?= (new CRow([
			(new CTextBox('mappings[#{rowNum}][value]', '', false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			'&rArr;',
			(new CTextBox('mappings[#{rowNum}][newvalue]', '', false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CButton('mappings[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/javascript">
	jQuery(function($) {
		$('#mappings_table').dynamicRows({
			template: '#mapping_row'
		});

		// clone button
		$('#clone').click(function() {
			$('#valuemapid, #delete, #clone').remove();
			$('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});
			$('#form').val('clone');
			$('#name').focus();
		});
	});
</script>
