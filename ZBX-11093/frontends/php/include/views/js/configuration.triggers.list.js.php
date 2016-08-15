<script type="text/javascript">
	jQuery(function($) {
		$('#filter_state').change(function() {
			$('input[name=filter_status]').prop('disabled', $('input[name=filter_state]:checked').val() != -1);
		})
		.trigger('change', false);
	});
</script>
