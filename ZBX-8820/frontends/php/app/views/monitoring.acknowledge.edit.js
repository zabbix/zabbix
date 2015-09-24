<script type="text/javascript">
	jQuery(document).ready(function($) {
		// type of media
		$('form').submit(function() {
			var obj = $(this).find('#message');
			obj.val($.trim(obj.val()));
		});
	});
</script>
