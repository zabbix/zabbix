<script type="text/javascript">
	jQuery(function() {
		var tls_connect = jQuery('#tls_connect').val(),
			tls_in_psk = jQuery('#tls_in_psk').is(':checked'),
			tls_in_cert = jQuery('#tls_in_cert').is(':checked');

		jQuery('#mass_replace_tpls').on('change', function() {
			jQuery('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();

		jQuery('#inventory_mode').change(function() {
			jQuery('.formrow-inventory').toggle(jQuery(this).val() !== '<?php echo HOST_INVENTORY_DISABLED; ?>');
		}).change();

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			tls_connect = jQuery('#tls_connect').val();
			tls_in_psk = jQuery('#tls_in_psk').is(':checked');
			tls_in_cert = jQuery('#tls_in_cert').is(':checked');

			toggleEncryptionFields();
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

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').trigger('change');

		jQuery('#visible_tls_connect, #visible_tls_accept').change(function() {
			toggleEncryptionFields();
		});

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

		function toggleEncryptionFields() {
			if ((jQuery('#visible_tls_connect').is(':checked') && tls_connect == <?= HOST_ENCRYPTION_CERTIFICATE ?>)
					|| (jQuery('#visible_tls_accept').is(':checked') && tls_in_cert)) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			if ((jQuery('#visible_tls_accept').is(':checked') && tls_in_psk)
					|| (jQuery('#visible_tls_connect').is(':checked') && tls_connect == <?= HOST_ENCRYPTION_PSK ?>)) {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').hide();
			}
		}
	});
</script>
