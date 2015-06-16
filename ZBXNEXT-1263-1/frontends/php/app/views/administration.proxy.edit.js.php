<script type="text/javascript">
	jQuery(document).ready(function() {
		// proxy mode: active or passive
		jQuery('#status').change(function() {
			var active = (jQuery(this).val() == 5);
			if (active) {
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

		jQuery('#status, #tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			var active = (jQuery('#status').val() == 5);
				is_certificate = (jQuery('#tls_connect').val() == 4) || jQuery('#tls_in_cert').is(":checked"),
				is_psk = (jQuery('#tls_connect').val() == 2) || jQuery('#tls_in_psk').is(":checked");

			if (is_certificate) {
				jQuery('#tls_issuer, #tls_subject').closest('li').show();
			}
			else {
				jQuery('#tls_issuer, #tls_subject').closest('li').hide();
			}

			if (is_psk) {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').show();
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').closest('li').hide();
			}
		});

		// refresh field visibility on document load
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			jQuery('#tls_in_none').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			jQuery('#tls_in_psk').prop('checked', true);
		}
		if ((jQuery('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			jQuery('#tls_in_cert').prop('checked', true);
		}

		jQuery('#tls_connect, #tls_psk_out').trigger('change');

		// trim spaces on sumbit
		jQuery('#proxyForm').submit(function() {
			var tls_accept = 0x00;

			if (jQuery('#tls_in_none').is(":checked")) {
				tls_accept |= 0x01;
			}
			if (jQuery('#tls_in_psk').is(":checked")) {
				tls_accept |= 0x02;
			}
			if (jQuery('#tls_in_cert').is(":checked")) {
				tls_accept |= 0x04;
			}

			jQuery('#tls_accept').val(tls_accept);
		});
	});
</script>
