<script type="text/javascript">
	jQuery(document).ready(function() {
		// type change
		jQuery('#type').change(function() {
			var ipmi = (jQuery(this).val() == 1);

			if (ipmi) {
				jQuery('#execute_on, #command').closest('li').hide();

				jQuery('#commandipmi')
					.closest('li')
					.show();

				if (jQuery('#command').val() != '') {
					jQuery('#commandipmi').val(jQuery('#command').val());
					jQuery('#command').val('');
				}
			}
			else {
				jQuery('#execute_on')
					.closest('li')
					.show();

				jQuery('#command')
					.closest('li')
					.show();

				if (jQuery('#commandipmi').val() != '') {
					jQuery('#command').val(jQuery('#commandipmi').val());
					jQuery('#commandipmi').val('');
				}
				jQuery('#commandipmi').closest('li').hide();
			}
		})
			.trigger('change');

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#scriptid, #delete, #clone').remove();
			jQuery('#update').text(<?= CJs::encodeJson(_('Add')) ?>);
			jQuery('#update').val('script.create').attr({id: 'add'});
			jQuery('#name').focus();
		});

		// confirmation text input
		jQuery('#confirmation').keyup(function() {
			jQuery('#testConfirmation').prop('disabled', (this.value == ''));
		}).keyup();

		// enable confirmation checkbox
		jQuery('#enable_confirmation').change(function() {
			if (this.checked) {
				jQuery('#confirmation').removeAttr('disabled').keyup();
			}
			else {
				jQuery('#confirmation, #testConfirmation').prop('disabled', true);
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

		// trim spaces on sumbit
		jQuery('#scriptForm').submit(function() {
			jQuery(this).trimValues(['#name', '#command', '#description']);
		});
	});
</script>
