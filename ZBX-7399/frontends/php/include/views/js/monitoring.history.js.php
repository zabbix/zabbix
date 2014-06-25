<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		if (!isset('object', list)) {
			throw("Error hash attribute 'list' doesn't contain 'object' index");
			return false;
		}

		var favorites = {'itemid': 1};
		if (isset(list.object, favorites)) {
			for (var i = 0; i < list.values.length; i++) {
				if (!isset(i, list.values) || empty(list.values[i])) {
					continue;
				}
				create_var('zbx_filter', 'itemid[' + list.values[i].itemid + ']', list.values[i].itemid, false);
			}

			$('zbx_filter').submit();
		}
	}

	function removeSelectedItems(objId, name) {
		var obj = jQuery('#' + objId);
		if (empty(obj)) {
			return false;
		}

		jQuery('option:selected', obj).each(function(){
			var self = jQuery(this);

			if (jQuery('option', obj).length > 1) {
				jQuery('#' + name + '_' + self.val()).remove();
				self.remove();
			}
			else {
				alert(<?php echo CJs::encodeJson(_('Cannot remove all items, at least one item should remain.')); ?>);
				return false;
			}
		});
	}
</script>
