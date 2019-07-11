<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= renderTagTableRow('#{rowNum}', '', '', ['add_post_js' => false]) ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#tags-table')
			.dynamicRows({template: '#tag-row-tmpl'})
			.on('click', 'button.element-table-add', function() {
				$('#tags-table .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
			})
			.on('click', 'button.element-table-disable', function() {
				var tag_id = $(this).attr('id').split('_')[1];

				$('#tags_' + tag_id + '_type').val(<?= ZBX_PROPERTY_INHERITED ?>);
			});
	});
</script>
