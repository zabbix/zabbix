<script type="text/x-jquery-tmpl" id="tag-row-tags">
	<?= renderTagTableRow('tags', '#{rowNum}') ?>
</script>
<script type="text/x-jquery-tmpl" id="tag-row-new-tags">
	<?= renderTagTableRow('new_tags', '#{rowNum}') ?>
	</script>
<script type="text/x-jquery-tmpl" id="tag-row-remove-tags">
	<?= renderTagTableRow('remove_tags', '#{rowNum}') ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#tbl-tags').dynamicRows({
			template: '#tag-row-tags'
		});
		$('#tbl-new-tags').dynamicRows({
			template: '#tag-row-new-tags'
		});
		$('#tbl-remove-tags').dynamicRows({
			template: '#tag-row-remove-tags'
		});

		$('#mass_replace_tpls').on('change', function() {
			$('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();
	});
</script>
