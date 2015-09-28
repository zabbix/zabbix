<script type="text/javascript">
	jQuery(document).ready(function() {
		// proxy mode: active or passive
		jQuery('#status').change(function() {
			if (jQuery(this).val() == <?= HOST_STATUS_PROXY_ACTIVE ?>) {
				jQuery('#ip').closest('li').hide();
			}
			else {
				jQuery('#ip').closest('li').show();
			}

			toggleEncryptionFields();
		});

		// clone button, special processing because of list of hosts
		jQuery('#clone').click(function() {
			var url = new Curl('zabbix.php?action=proxy.edit');
			url.setArgument('host', jQuery('#host').val());
			url.setArgument('status', jQuery('#status').val());
			url.setArgument('description', jQuery('#description').val());
			url.setArgument('ip', jQuery('#ip').val());
			url.setArgument('dns', jQuery('#dns').val());
			url.setArgument('useip', jQuery('input[name=useip]:checked').val());
			url.setArgument('port', jQuery('#port').val());
			url.setArgument('tls_connect', jQuery('#tls_connect').val());
			url.setArgument('tls_psk_identity', jQuery('#tls_psk_identity').val());
			url.setArgument('tls_psk', jQuery('#tls_psk').val());
			url.setArgument('tls_issuer', jQuery('#tls_issuer').val());
			url.setArgument('tls_subject', jQuery('#tls_subject').val());
			url.setArgument('tls_accept', getTlsAccept());
			redirect(url.getUrl(), 'post', 'action');
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

		jQuery('#tls_connect').trigger('change');

		// Trim spaces on submit and depending on checkboxes, create a value for hidden field 'tls_accept'.
		jQuery('#proxyForm').submit(function() {
			jQuery(this).trimValues(['#host', '#ip', '#dns', '#port', '#description']);
			jQuery('#tls_accept').val(getTlsAccept());
		});

		// Refresh field visibility on document load.
		jQuery('#status').trigger('change');

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			displayAdditionalEncryptionFields();
		});

		/**
		 * Enabling or disabling connections to/from proxy based on proxy mode:
		 *  if proxy is active, then disabled "Connections to proxy" field and enabled "Connections from proxy";
		 *  if proxy is passive, then enabled "Connections to proxy" field and disabled "Connections from proxy".
		 */
		function toggleEncryptionFields() {
			if (jQuery('#status').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>) {
				jQuery('#tls_connect').prop('disabled', true);
				jQuery('#tls_in_none, #tls_in_psk, #tls_in_cert').prop('disabled', false);
			}
			else {
				jQuery('#tls_connect').prop('disabled', false);
				jQuery('#tls_in_none, #tls_in_psk, #tls_in_cert').prop('disabled', true);
			}

			displayAdditionalEncryptionFields();
		}

		/**
		 * Show/hide they based on connections to/from proxy fields and enabling or disabling additional encryption
		 * fields based on proxy mode:
		 *  if selected or checked certificate then show "Issuer" and "Subject" fields;
		 *  if selected or checked PSK then show "PSK identity" and "PSK" fields;
		 *  if selected or checked certificate, but it disabled based on proxy status, then "Issuer" and "Subject"
		 *   fields will be disabled;
		 *  if selected or checked PSK, but it disabled based on proxy status, then "PSK identity" and "PSK"
		 *   fields will be disabled;
		 */
		function displayAdditionalEncryptionFields() {
			// If certificate is selected or checked.
			if (jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| jQuery('#tls_in_cert').is(':checked')) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			if ((jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					&& jQuery('#status').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>)
					|| (jQuery('#tls_in_cert').is(':checked'))
					&& jQuery('#status').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>) {
				jQuery('#tls_issuer, #tls_subject').prop('disabled', false);
			}
			else {
				jQuery('#tls_issuer, #tls_subject').prop('disabled', true);
			}

			// If PSK is selected or checked.
			if (jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_PSK ?> || jQuery('#tls_in_psk').is(':checked')) {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').hide();
			}

			if ((jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_PSK ?>
					&& jQuery('#status').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>)
					|| (jQuery('#tls_in_psk').is(':checked'))
					&& jQuery('#status').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>) {
				jQuery('#tls_psk, #tls_psk_identity').prop('disabled', false);
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').prop('disabled', true);
			}
		}

		/**
		 * Get tls_accept value.
		 *
		 * @return int
		 */
		function getTlsAccept() {
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

			return tls_accept;
		}
	});
</script>
