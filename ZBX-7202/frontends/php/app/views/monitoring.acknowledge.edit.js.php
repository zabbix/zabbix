<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#acknowledge_form').submit(function() {
			$(this).trimValues(['#message']);
		});
	});
</script>
