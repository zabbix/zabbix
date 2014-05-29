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
</script>
