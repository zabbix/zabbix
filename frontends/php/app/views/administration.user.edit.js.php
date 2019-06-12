<script type="text/javascript">
	jQuery(function($) {
		$('form[name="user_form"]').submit(function() {
			$(this).trimValues(['#password1', '#password2', '#url', '#refresh', '#alias', '#name', '#surname']);
		});
	});
</script>
