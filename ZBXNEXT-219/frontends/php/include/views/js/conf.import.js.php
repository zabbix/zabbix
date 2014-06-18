<script type="text/javascript">
	function confirmDeleteMissing() {
		var deleteMissing;

		jQuery('.deleteMissing').each(function() {
			if (this.checked) {
				deleteMissing = confirm('<?php echo _('Delete all elements that are not present in the XML file?') ?>');
				return false;
			}
		});

		return deleteMissing;
	}
</script>
