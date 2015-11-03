<script type="text/javascript">
	jQuery(document).ready(function() {
		'use strict';

		jQuery('#edit').click(function() {
			jQuery('#comments')
				.val(<?php echo CJs::encodeJson($this->data['trigger']['comments']); ?>)
				.removeAttr('readonly')
				.focus();
			jQuery('#edit').prop('disabled', true);
			jQuery('#update').prop('disabled', false);
		});
	});
</script>
