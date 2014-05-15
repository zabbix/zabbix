<script type="text/javascript">
	jQuery(document).ready(function() {
		// type change
		jQuery('#type').change(function() {
			var ipmi = (jQuery(this).val() == 1);

			if (ipmi) {
				jQuery('#execute_on')
					.closest('li')
					.css('display', 'none');

				jQuery('#commandipmi')
					.val(jQuery('#command').val())
					.closest('li')
					.css('display', '')
					.removeClass('hidden');

				jQuery('#command')
					.closest('li')
					.css('display', 'none');
			}
			else {
				jQuery('#execute_on')
					.closest('li')
					.css('display', '')
					.removeClass('hidden');

				jQuery('#command')
					.val(jQuery('#commandipmi').val())
					.closest('li')
					.css('display', '')
					.removeClass('hidden');

				jQuery('#commandipmi')
					.closest('li')
					.css('display', 'none');
			}
		});

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#scriptid, #delete, #clone').remove();
			jQuery('#cancel').addClass('ui-corner-left');
			jQuery('#name').focus();
		});

		// confirmation text input
		jQuery('#confirmation').keyup(function() {
			jQuery('#testConfirmation, #confirmationLabel').prop('disabled', (this.value == ''));
		}).keyup();

		// enable confirmation checkbox
		jQuery('#enableConfirmation').change(function() {
			if (this.checked) {
				jQuery('#confirmation').removeAttr('disabled').keyup();
			}
			else {
				jQuery('#confirmation, #testConfirmation, #confirmationLabel').prop('disabled', true);
			}
		}).change();

		// test confirmation button
		jQuery('#testConfirmation').click(function() {
			executeScript(null, null, jQuery('#confirmation').val());
		});

		// host group selection
		jQuery('#hgstype')
			.change(function() {
				if (jQuery('#hgstype').val() == 1) {
					jQuery('#hostGroupSelection').show();
				}
				else {
					jQuery('#hostGroupSelection').hide();
				}
			})
			.trigger('change');
	});
</script>
