<script type="text/javascript">
	jQuery(document).ready(function() {
		// proxy mode: active or passive
		jQuery('#status').change(function() {
			if (jQuery(this).val() == <?= HOST_STATUS_PROXY_ACTIVE ?>) {
				jQuery('#ip').closest('li').hide();
				jQuery('#tls_in_none').closest('li').removeClass('hidden');
				jQuery('#tls_connect').closest('li').addClass('hidden');
			}
			else {
				jQuery('#ip').closest('li').show();
				jQuery('#tls_in_none').closest('li').addClass('hidden');
				jQuery('#tls_connect').closest('li').removeClass('hidden');
			}
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
			redirect(url.getUrl(), 'post', 'action');
		});

		jQuery('#tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			// If certificate is selected or checked.
			if (jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| jQuery('#tls_in_cert').is(':checked')) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			// If PSK is selected or checked.
			if (jQuery('#tls_connect').val() == <?= HOST_ENCRYPTION_PSK ?> || jQuery('#tls_in_psk').is(':checked')) {
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

		jQuery('#tls_connect').trigger('change');

		// Trim spaces on submit and depending on checkboxes, create a value for hidden field 'tls_accept'.
		jQuery('#proxyForm').submit(function() {
			jQuery('#host').val(jQuery.trim(jQuery('#host').val()));
			jQuery('#ip').val(jQuery.trim(jQuery('#ip').val()));
			jQuery('#dns').val(jQuery.trim(jQuery('#dns').val()));
			jQuery('#port').val(jQuery.trim(jQuery('#port').val()));
			jQuery('#description').val(jQuery.trim(jQuery('#description').val()));

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

		// Refresh field visibility on document load.
		jQuery('#status').trigger('change');
	});
</script>
