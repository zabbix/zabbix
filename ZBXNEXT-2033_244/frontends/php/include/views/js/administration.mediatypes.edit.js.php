<script type="text/javascript">
	jQuery(document).ready(function($) {
		<?php if (!empty($data['typeVisibility'])) { ?>
			var typeSwitcher = new CViewSwitcher('type', 'change',
				<?php echo zbx_jsvalue($data['typeVisibility'], true); ?>);
		<?php } ?>

		$('#type')
			.change(function() {
				var ezTextingLink = $('#ezTextingLink'),
					changePassBtn = $('#chPass_btn'),
					passwordBox = $('#password');

				if ($(this).val() == <?php echo MEDIA_TYPE_EZ_TEXTING; ?>) {
					ezTextingLink.show();
				}
				else {
					ezTextingLink.hide();
				}

				if (changePassBtn.length) {
					passwordBox.hide();
				}
			})
			.trigger('change');
	});
</script>
