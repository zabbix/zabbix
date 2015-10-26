<script type="text/javascript">
	jQuery(function($) {
		$('#mass_replace_tpls').on('change', function() {
			$('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();

		$('#inventory_mode').change(function() {
			$('.formrow-inventory').toggle($(this).val() !== '<?php echo HOST_INVENTORY_DISABLED; ?>');
		}).change();

		$('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| $('#tls_in_cert').is(':checked')) {
				$('#tls_issuer, #tls_subject').closest('tr').show();
			}
			else {
				$('#tls_issuer, #tls_subject').closest('tr').hide();
			}

			// If PSK is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| $('#tls_in_psk').is(':checked')) {
				$('#tls_psk, #tls_psk_identity').closest('tr').show();
			}
			else {
				$('#tls_psk, #tls_psk_identity').closest('tr').hide();
			}
		});

		$('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| $('#tls_in_cert').is(':checked')) {
				$('#tls_issuer, #tls_subject').closest('tr').show();
			}
			else {
				$('#tls_issuer, #tls_subject').closest('tr').hide();
			}

			// If PSK is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| $('#tls_in_psk').is(':checked')) {
				$('#tls_psk, #tls_psk_identity').closest('tr').show();
			}
			else {
				$('#tls_psk, #tls_psk_identity').closest('tr').hide();
			}
		});

		// Refresh field visibility on document load.
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			$('#tls_in_none').prop('checked', true);
		}
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			$('#tls_in_psk').prop('checked', true);
		}
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			$('#tls_in_cert').prop('checked', true);
		}

		$('input[name=tls_connect]').trigger('change');

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		$('#hostForm').submit(function() {
			var tls_accept = 0x00;

			if ($('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if ($('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}
			if ($('#tls_in_cert').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
			}

			$('#tls_accept').val(tls_accept);
		});
	});
</script>
