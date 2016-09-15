<script type="text/javascript">
	jQuery(function($) {
		// proxy mode: active or passive
		$('#status').change(function() {
			$('#ip').closest('li').toggle($('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>);

			toggleEncryptionFields();
		});

		// clone button, special processing because of list of hosts
		$('#clone').click(function() {
			var url = new Curl('zabbix.php?action=proxy.edit');
			url.setArgument('host', $('#host').val());
			url.setArgument('status', $('input[name=status]:checked').val());
			url.setArgument('description', $('#description').val());
			url.setArgument('ip', $('#ip').val());
			url.setArgument('dns', $('#dns').val());
			url.setArgument('useip', $('input[name=useip]:checked').val());
			url.setArgument('port', $('#port').val());
			url.setArgument('tls_connect', $('input[name=tls_connect]:checked').val());
			url.setArgument('tls_psk_identity', $('#tls_psk_identity').val());
			url.setArgument('tls_psk', $('#tls_psk').val());
			url.setArgument('tls_issuer', $('#tls_issuer').val());
			url.setArgument('tls_subject', $('#tls_subject').val());
			url.setArgument('tls_accept', getTlsAccept());
			redirect(url.getUrl(), 'post', 'action');
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

		// Trim spaces on submit and depending on checkboxes, create a value for hidden field 'tls_accept'.
		$('#proxyForm').submit(function() {
			$(this).trimValues([
				'#host', '#ip', '#dns', '#port', '#description', '#tls_psk_identity', '#tls_psk', '#tls_issuer',
				'#tls_subject'
			]);
			$('#tls_accept').val(getTlsAccept());
		});

		// Refresh field visibility on document load.
		$('#status').trigger('change');

		$('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			displayAdditionalEncryptionFields();
		});

		/**
		 * Enabling or disabling connections to/from proxy based on proxy mode:
		 *  if proxy is active, disable "Connections to proxy" field and enable "Connections from proxy";
		 *  if proxy is active, "Connections to proxy" field is disabled and "Connections from proxy" is enabled.
		 */
		function toggleEncryptionFields() {
			if ($('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>) {
				$('input[name=tls_connect]').prop('disabled', true);
				$('#tls_in_none, #tls_in_psk, #tls_in_cert').prop('disabled', false);
			}
			else {
				$('input[name=tls_connect]').prop('disabled', false);
				$('#tls_in_none, #tls_in_psk, #tls_in_cert').prop('disabled', true);
			}

			displayAdditionalEncryptionFields();
		}

		/**
		 * Show/hide them based on connections to/from proxy fields and enabling or disabling additional encryption
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
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| $('#tls_in_cert').is(':checked')) {
				$('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				$('#tls_issuer, #tls_subject').closest('li').hide();
			}

			if (($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					&& $('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>)
					|| ($('#tls_in_cert').is(':checked')
					&& $('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>)) {
				$('#tls_issuer, #tls_subject').prop('disabled', false);
			}
			else {
				$('#tls_issuer, #tls_subject').prop('disabled', true);
			}

			// If PSK is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| $('#tls_in_psk').is(':checked')) {
				$('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				$('#tls_psk, #tls_psk_identity').closest('li').hide();
			}

			if (($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					&& $('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>)
					|| ($('#tls_in_psk').is(':checked')
					&& $('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>)) {
				$('#tls_psk, #tls_psk_identity').prop('disabled', false);
			}
			else {
				$('#tls_psk, #tls_psk_identity').prop('disabled', true);
			}
		}

		/**
		 * Get tls_accept value.
		 *
		 * @return int
		 */
		function getTlsAccept() {
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

			return tls_accept;
		}
	});
</script>
