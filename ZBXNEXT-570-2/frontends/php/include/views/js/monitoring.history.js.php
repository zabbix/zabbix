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

		$('zbx_filter').submit();
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
					$('#filter_itemids_' + self.val()).remove();
					self.remove();
				}
				else {
					alert(<?php echo CJs::encodeJson(_('Cannot remove all items, at least one item should remain.')); ?>);
					return false;
				}
			});
		});
	});
</script>
