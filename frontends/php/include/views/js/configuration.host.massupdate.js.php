<script type="text/javascript">
	jQuery(function() {
		jQuery('#mass_replace_tpls').on('change', function() {
			jQuery('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();

		jQuery('#inventory_mode').change(function() {
			jQuery('.formrow-inventory').toggle(jQuery(this).val() !== '<?php echo HOST_INVENTORY_DISABLED; ?>');
		}).change();

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert, #visible_tls_connect, #visible_tls_accept').change(function() {
			/*
			 * If visiblity for "Connections to host" is checked and certificate is selected or
			 * visiblity for "Connections from host" is checked and certificate is checked.
			 */
			if ((jQuery('#visible_tls_connect').is(':checked')
					&& jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>)
					|| (jQuery('#visible_tls_accept').is(':checked')
					&& jQuery('#tls_in_cert').is(':checked'))) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			/*
			 * If visiblity for "Connections to host" is checked and PSK is selected or
			 * visiblity for "Connections from host" is checked and PSK is checked.
			 */
			if ((jQuery('#visible_tls_accept').is(':checked')
					&& jQuery('#tls_in_psk').is(':checked'))
					|| (jQuery('#visible_tls_connect').is(':checked')
					&& jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_PSK ?>)) {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').hide();
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

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert, #visible_tls_connect, #visible_tls_accept').trigger('change');

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
