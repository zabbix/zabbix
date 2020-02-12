<script type="text/javascript">
	jQuery(function($) {
		$('#import').click(function() {
			if ($('.deleteMissing:checked').length > 0) {
				return confirm(<?= json_encode(_('Delete all elements that are not present in the XML file?')) ?>);
			}
		});
	});

	function updateWarning(obj, content) {
		if (jQuery(obj).is(':checked')) {
			overlayDialogue({
				'content': jQuery('<span>').text(content),
				'buttons': [
					{
						'title': <?= json_encode(_('Cancel')) ?>,
						'cancel': true,
						'class': '<?= ZBX_STYLE_BTN_ALT ?>',
						'action': function() {
							jQuery(obj).prop('checked', false);
						}
					},
					{
						'title': <?= json_encode(_('Ok')) ?>,
						'focused': true,
						'action': function() {}
					}
				]
			}, obj);
		}
	}
</script>
