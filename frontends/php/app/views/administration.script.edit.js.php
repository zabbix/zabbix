<script type="text/javascript">
	jQuery(document).ready(function() {
		// type change
		jQuery('#type')
			.change(function() {
				var type = jQuery('input[name=type]:checked').val(),
					command_ipmi = jQuery('#commandipmi'),
					command = jQuery('#command');

				if (type == <?= ZBX_SCRIPT_TYPE_IPMI ?>) {
					if (command.val() !== '') {
						command_ipmi.val(command.val());
						command.val('');
					}

					jQuery('#execute_on').add(command).closest('li').hide();
					command_ipmi.closest('li').show();
				}
				else {
					if (command_ipmi.val() !== '') {
						command.val(command_ipmi.val());
						command_ipmi.val('');
					}

					command_ipmi.closest('li').hide();
					jQuery('#execute_on').add(command).closest('li').show();
				}
			})
			.change();

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#scriptid, #delete, #clone').remove();
			jQuery('#update').text(<?= CJs::encodeJson(_('Add')) ?>);
			jQuery('#update')
				.val('script.create')
				.attr({id: 'add'});
			jQuery('#name').focus();
		});

		// confirmation text input
		jQuery('#confirmation').keyup(function() {
			jQuery('#testConfirmation').prop('disabled', (this.value == ''));
		}).keyup();

		// enable confirmation checkbox
		jQuery('#enable_confirmation')
			.change(function() {
				if (this.checked) {
					jQuery('#confirmation')
						.removeAttr('disabled')
						.keyup();
				}
				else {
					jQuery('#confirmation, #testConfirmation').prop('disabled', true);
				}
			})
			.change();

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
			.change();

		// trim spaces on submit
		jQuery('#scriptForm').submit(function() {
			jQuery(this).trimValues(['#name', '#command', '#description']);
		});
	});
</script>
