<script type="text/javascript">
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

	function removeSelectedItems(formobject, name) {
		formobject = $(formobject);
		if (is_null(formobject)) {
			return false;
		}

		for (var i = 0; i < formobject.options.length; i++) {
			if (formobject.options[i].selected) {
				if (formobject.options.length > 1) {
					var obj = $(name + '_' + formobject.options[i].value);
					if (!is_null(obj)) {
						obj.remove();
					}
					$(formobject.options[i]).remove();
				}
				else {
					alert(<?php echo CJs::encodeJson(_('Cannot remove all items, at least one item should remain.')); ?>);
					return false;
				}
			}
		}
	}
</script>
