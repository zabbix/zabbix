<script type="text/javascript">
	jQuery(function($) {
		$('input[name=filter_monitored_by]').on('click', function() {
			$('.multiselect').multiSelect('resize');
		});

		$('#filter_monitored_by').change(function() {
			var	filter_monitored_by = $('input[name=filter_monitored_by]:checked').val();

			if (filter_monitored_by == <?= ZBX_MONITORED_BY_PROXY ?>) {
				$('#filter_proxyids_row').css('visibility', 'visible');
			}
			else {
				$('#filter_proxyids_row').css('visibility', 'hidden');
			}
		});

		$('#filter_monitored_by').trigger('change');
	});
</script>
