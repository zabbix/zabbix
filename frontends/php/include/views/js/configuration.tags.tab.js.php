<script type="text/x-jquery-tmpl" id="tag-row">
	<?= renderTagTableRow('#{rowNum}') ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#tbl-tags')
			.dynamicRows({
				template: '#tag-row'
			})
			.on('click', 'button.element-table-disable', function() {
				var tag_id = $(this).attr('id').split('_')[1];

				$('#tags_' + tag_id + '_type').val(<?= ZBX_PROPERTY_INHERITED ?>);
			});
	});
</script>
