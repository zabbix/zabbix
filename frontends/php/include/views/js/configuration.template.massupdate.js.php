<script type="text/x-jquery-tmpl" id="tag-row">
	<?= renderTagTableRow('tags', '#{rowNum}') ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#tbl-tags').dynamicRows({
			template: '#tag-row'
		});

		$('#mass_replace_tpls').on('change', function() {
			$('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();
	});
</script>
