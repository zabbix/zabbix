<script type="text/javascript">
	jQuery(function($) {
		var $form = $('form[name="user_group_form"]');
		$form.submit(function() {
			$form.trimValues(['#name']);
		});
	});
</script>
