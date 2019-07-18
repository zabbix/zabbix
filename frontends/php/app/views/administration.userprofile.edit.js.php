<script type="text/javascript">
	jQuery(function($) {
		$('form[name="user_form"]').submit(function() {
			$(this).trimValues(['#password1', '#password2', '#refresh', '#url']);
		});

		$('#messages_enabled').on('change', function() {
			$('input, button, select', $('#messagingTab'))
				.not('[name="messages[enabled]"]')
				.prop('disabled', !this.checked);
		}).trigger('change');
	});
</script>
