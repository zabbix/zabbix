<script type="text/javascript">
	jQuery(document).ready(function($) {
		<?php if (!empty($this->data['typeVisibility'])) { ?>
			var typeSwitcher = new CViewSwitcher('type', 'change',
				<?php echo zbx_jsvalue($this->data['typeVisibility'], true); ?>);
		<?php } ?>

		var type = $('#type'),
			ezTextingLink = $('#ezTextingLink'),
			changePassBtn = $('#chPass_btn'),
			passwordBox = $('#password');

		if (type.val() == <?php echo MEDIA_TYPE_EZ_TEXTING; ?>) {
			ezTextingLink.show();
		}
		else {
			ezTextingLink.hide();
		}

		if (changePassBtn.css('display') == 'inline') {
			passwordBox.hide();
		}

		type.change(function() {
			if ($(this).val() == <?php echo MEDIA_TYPE_EZ_TEXTING; ?>) {
				ezTextingLink.show();
			}
			else {
				ezTextingLink.hide();
			}

			if (changePassBtn.css('display') == 'inline') {
				passwordBox.hide();
			}
		});
	});
</script>
