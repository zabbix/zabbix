<script type="text/javascript">
	jQuery(document).ready(function() {
		// type change
		jQuery('#type').change(function() {
			var ipmi = (jQuery(this).val() == 1);

			if (ipmi) {
				jQuery('#execute_on, #command').closest('li').addClass('hidden');

				jQuery('#commandipmi')
					.val(jQuery('#command').val())
					.closest('li')
					.removeClass('hidden');
			}
			else {
				jQuery('#execute_on')
					.closest('li')
					.removeClass('hidden');

				jQuery('#command')
					.val(jQuery('#commandipmi').val())
					.closest('li')
					.removeClass('hidden');

				jQuery('#commandipmi').closest('li').addClass('hidden');
			}
		});

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#scriptid, #delete, #clone').remove();
			jQuery('#update span').text(<?php echo CJs::encodeJson(_('Add')); ?>);
			jQuery('#update').val('script.create').attr({id: 'add'});
			jQuery('#cancel').addClass('ui-corner-left');
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
			jQuery('#name').val(jQuery.trim(jQuery('#name').val()));
			jQuery('#command').val(jQuery.trim(jQuery('#command').val()));
			jQuery('#description').val(jQuery.trim(jQuery('#description').val()));
		});
	});
</script>
