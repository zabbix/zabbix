<script type="text/javascript">
	jQuery(function($) {
		$('#import').click(function() {
			if ($('.deleteMissing:checked').length > 0) {
				return confirm('<?= _('Delete all elements that are not present in the XML file?') ?>');
			}
		});
	});

	function updateWarning(obj, content) {
		if (jQuery(obj).is(':checked')) {
			overlayDialogue({
				'content': jQuery('<span>').text(content),
				'buttons': [
					{
						'title': '<?= _('Ok') ?>',
						'focused': true,
						'action': function() {}
					},
					{
						'title': '<?= _('Cancel') ?>',
						'focused': false,
						'class': 'btn-alt',
						'action': function() {
							jQuery(obj).prop('checked', false);
						}
					}
				]
			});
		}
	}
</script>
