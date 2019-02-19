<script type="text/javascript">
	jQuery(function($) {
		$('#filter_monitored_by').change(function() {
			var	filter_monitored_by = $('input[name=filter_monitored_by]:checked').val();

			if (filter_monitored_by == <?= ZBX_MONITORED_BY_PROXY ?>) {
				$('#filter_proxyids_').multiSelect('enable');
			}
			else {
				$('#filter_proxyids_').multiSelect('disable');
			}
		});
	});
</script>
