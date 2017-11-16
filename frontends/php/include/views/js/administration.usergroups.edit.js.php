<script type="text/javascript">
	jQuery(function($) {
		$('#tag_filter_table').on('click', 'button.element-table-remove', function() {
			$(this).closest('tr').remove();
		});
	});
</script>
