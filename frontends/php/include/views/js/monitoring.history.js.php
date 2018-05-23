<script type="text/javascript">
	jQuery(function($){
		var time_range = <?= CJs::encodeJson([
			'from' => $data['from'] === null ? ZBX_PERIOD_DEFAULT_FROM : $data['from'],
			'to' => $data['to'] === null ? 'now' : $data['to']
		]) ?>;
		$.subscribe('timeselector.rangeupdate', function(e, data) {
			time_range.from = data.from;
			time_range.to = data.to;
		});
		$('input[name="graphtype"]').change(function() {
			var form = $(this).parents('form');
			form.append($('<input>', {
				type: 'hidden',
				name: 'from',
				value: time_range.from
			}))
				.append($('<input>', {
					type: 'hidden',
					name: 'to',
					value: time_range.to
				}));
			form.submit();
		});

		$('#filter-space').submit(function() {
			$('[name="from"]').first().attr("disabled", "disabled");
			$('[name="to"]').first().attr("disabled", "disabled");
		});
	});
</script>
