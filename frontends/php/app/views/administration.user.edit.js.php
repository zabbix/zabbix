<script type="text/javascript">
	jQuery(function($) {
		$('form[name="user_form"]').submit(function() {
			$(this).trimValues(['#alias', '#name', '#surname', '#password1', '#password2', '#refresh', '#url']);
		});
	});
</script>
