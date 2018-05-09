<script type="text/javascript">
	jQuery(function($){
		$('input[name="graphtype"]').change(function() {
			var form = $(this).parents('form');
			form.append($('<input>', {
				type: 'hidden',
				name: 'period',
				value: timeControl.timeline.period()
			}));
			if (!timeControl.timeline.isNow()) {
				form.append($('<input>', {
					type: 'hidden',
					name: 'stime',
					value: timeControl.timeline.usertime() - timeControl.timeline.period()
				}));
			}
			form.submit();
		})
	});
</script>
