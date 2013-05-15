<script type="text/javascript">
	jQuery(function() {
		// select service
		jQuery('.service-name').click(function() {
			var e = jQuery(this);
			window.opener.add_child_service(e.data('name'), e.data('serviceid'), e.data('trigger'));

			self.close();
			return false;
		});
		// service multiselect
		jQuery('#select').click(function() {
			var e;
			jQuery('.service-select:checked').each(function(key, cb) {
				e = jQuery('#service-name-' + jQuery(cb).val());
				window.opener.add_child_service(e.data('name'), e.data('serviceid'), e.data('trigger'));
			});

			self.close();
			return false;
		});
	});
</script>
