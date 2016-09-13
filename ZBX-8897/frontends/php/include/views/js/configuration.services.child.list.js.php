<script type="text/javascript">
	jQuery(function($) {
		// select service
		$('.service-name').click(function() {
			var service = $(this).data('service');

			window.opener.add_child_service(service.name, service.id, service.trigger);

			self.close();
			return false;
		});

		// service multiselect
		$('#select').click(function() {
			$('.service-select:checked').each(function(key, cb) {
				var service = $('#service-name-' + $(cb).val()).data('service');

				window.opener.add_child_service(service.name, service.id, service.trigger);
			});

			self.close();
			return false;
		});
	});
</script>
