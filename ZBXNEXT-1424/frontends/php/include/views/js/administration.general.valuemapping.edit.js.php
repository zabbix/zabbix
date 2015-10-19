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
	jQuery(document).ready(function($) {
		$('#mappings_table').dynamicRows({
			template: '#mapping_row'
		});

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#valuemapid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});
			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});
	});
</script>
