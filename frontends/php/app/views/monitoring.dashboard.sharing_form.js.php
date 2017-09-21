<script type="text/javascript">
	jQuery(document).ready(function() {
		var	sharing_form = jQuery('form[name="dashboard_sharing_form"]');

		// overwrite submit action to AJAX call
		sharing_form.submit(function(event) {
			var	me = this;

			event.preventDefault();

			function saveErrors(errors) {
				jQuery(me).data('errors', errors);
			}

			jQuery.ajax({
				async: false, // waiting errors
				data: jQuery(me).serialize(), // get the form data
				type: jQuery(me).attr('method'),
				url: jQuery(me).attr('action'),
				success: function (response) {
					var errors = [];
					if (typeof response === 'object') {
						if ('errors' in response) {
							errors = response.errors;
						}
					}
					else if (typeof response === 'string' && response.indexOf(<?= CJs::encodeJson(_('Access denied')) ?>) !== -1) {
						errors.push(<?= CJs::encodeJson(_('You need permission to perform this action!')) ?>);
					}
					saveErrors(errors);
				},
				error: function (response) {
					saveErrors([<?= CJs::encodeJson(_('Something went wrong. Please try again later!')) ?>]);
				}
			});
		});
	});
</script>
