<script type="text/javascript">
	jQuery(function() {
		jQuery('#mass_replace_tpls').on('change', function() {
			jQuery('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();

		jQuery('#inventory_mode').change(function() {
			jQuery('.formrow-inventory').toggle(jQuery(this).val() !== '<?php echo HOST_INVENTORY_DISABLED; ?>');
		}).change();

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| jQuery('#tls_in_cert').is(':checked')) {
				jQuery('#tls_issuer, #tls_subject').closest('tr').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('tr').hide();
			}

			// If PSK is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| jQuery('#tls_in_psk').is(':checked')) {
				jQuery('#tls_psk, #tls_psk_identity').closest('tr').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('tr').hide();
			}
		});

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| jQuery('#tls_in_cert').is(':checked')) {
				jQuery('#tls_issuer, #tls_subject').closest('tr').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('tr').hide();
			}

			// If PSK is selected or checked.
			if (jQuery('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| jQuery('#tls_in_psk').is(':checked')) {
				jQuery('#tls_psk, #tls_psk_identity').closest('tr').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('tr').hide();
			}
		});

		// Refresh field visibility on document load.
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			jQuery('#tls_in_none').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			jQuery('#tls_in_psk').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			jQuery('#tls_in_cert').prop('checked', true);
		}

		jQuery('input[name=tls_connect]').trigger('change');

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		jQuery('#hostForm').submit(function() {
			var tls_accept = 0x00;

			if (jQuery('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if (jQuery('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}
			if (jQuery('#tls_in_cert').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
			}

			jQuery('#tls_accept').val(tls_accept);
		});
	});
</script>
