<script type="text/javascript">
	jQuery(document).ready(function() {
		'use strict';

		jQuery('#edit').click(function() {
			jQuery('#comments').val(<?php echo CJs::encodeJson($this->data['trigger']['comments']); ?>);
			jQuery('#comments').removeAttr('readonly');
			jQuery('#edit').button('disable');
			jQuery('#update').button('enable');
		});
	});
</script>
