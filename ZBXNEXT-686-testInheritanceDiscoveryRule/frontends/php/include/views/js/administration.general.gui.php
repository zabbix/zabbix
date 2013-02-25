<script type="text/javascript">
	jQuery(document).ready(function($) {
		'use strict';

		$('#server_check_enabled').change(function() {
			$('#server_check_interval').prop('disabled', !this.checked);
		}).change();
	});
</script>
