<script type="text/javascript">
	jQuery(function($) {
		console.log('ok');
		$('#tag_filter_table').on('click', 'button.element-table-remove', function() {
			console.log('ok');
			$(this).closest('tr').remove();
		});
	});
</script>
