<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		if (!isset('object', list)) {
			throw("Error hash attribute 'list' doesn't contain 'object' index");
			return false;
		}

		for (var i = 0; i < list.values.length; i++) {
			if (!isset(i, list.values) || empty(list.values[i])) {
				continue;
			}
			create_var('zbx_filter', 'itemids[' + list.values[i].itemid + ']', list.values[i].itemid, false);
		}

		jQuery('form[name=zbx_filter]').submit();
	}

	jQuery(function($){
		$('#remove_log').click(function() {
			var obj = $('#cmbitemlist_');
			if (empty(obj)) {
				return false;
			}

			jQuery('option:selected', obj).each(function(){
				var self = $(this);

				if ($('option', obj).length > 1) {
					$('form[name=zbx_filter] #itemids_' + self.val()).remove();
					self.remove();
				}
				else {
					alert(<?php echo CJs::encodeJson(_('Cannot remove all items, at least one item should remain.')); ?>);
					return false;
				}
			});

			$('form[name=zbx_filter]').submit();
		});

		var time_range = <?= CJs::encodeJson([
			'from' => $data['from'] === null ? ZBX_PERIOD_DEFAULT : $data['from'],
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
		})
	});
</script>
