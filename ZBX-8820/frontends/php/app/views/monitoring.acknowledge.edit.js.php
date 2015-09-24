<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#acknowledgeForm').submit(function() {
			$(this).trimValues(["#message"]);
		});
	});
</script>
