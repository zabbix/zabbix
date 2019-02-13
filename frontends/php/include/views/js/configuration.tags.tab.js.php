<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= renderTagTableRow('#{rowNum}') ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#tags-table')
			.dynamicRows({template: '#tag-row-tmpl'})
			.on('click', 'button.element-table-disable', function() {
				var tag_id = $(this).attr('id').split('_')[1];

				$('#tags_' + tag_id + '_type').val(<?= ZBX_PROPERTY_INHERITED ?>);
			});
	});
</script>
