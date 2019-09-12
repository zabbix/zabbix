<script type="text/javascript">
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(data) {
		if (!isset('object', data) || data.object != 'hostid') {
			return false;
		}

		create_var(data.parentId, 'add_templates[]', data.values[0].id, false);

		submitFormWithParam(data.parentId, "add_template", "1");
	}
</script>
