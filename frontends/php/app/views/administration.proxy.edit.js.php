<script type="text/javascript">
	jQuery(document).ready(function() {
		// proxy mode: active or passive
		jQuery('#status').change(function() {
			var active = (jQuery(this).val() == 5);
			if (active) {
				jQuery('#ip').closest('li').addClass('hidden');
				jQuery('#tls_in_none').closest('li').removeClass('hidden');
				jQuery('#tls_connect').closest('li').addClass('hidden');
			}
			else {
				jQuery('#ip').closest('li').removeClass('hidden');
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

		// trim spaces on sumbit
		jQuery('#proxyForm').submit(function() {
			jQuery('#host').val(jQuery.trim(jQuery('#host').val()));
			jQuery('#ip').val(jQuery.trim(jQuery('#ip').val()));
			jQuery('#dns').val(jQuery.trim(jQuery('#dns').val()));
			jQuery('#port').val(jQuery.trim(jQuery('#port').val()));
			jQuery('#description').val(jQuery.trim(jQuery('#description').val()));

			var tls_accept = 0;

			if (jQuery('#tls_in_none').is(":checked")) {
				tls_accept += 1;
			}
			if (jQuery('#tls_in_psk').is(":checked")) {
				tls_accept += 2;
			}
			if (jQuery('#tls_in_cert').is(":checked")) {
				tls_accept += 4;
			}

			jQuery('#tls_accept').val(tls_accept);
		});

		jQuery('#status, #tls_connect, #tls_in_psk, #tls_in_cert').change(function() {
			var active = (jQuery('#status').val() == 5);
				is_certificate = (!active && jQuery('#tls_connect').val() == 4) || (active && jQuery('#tls_in_cert').is(":checked")),
				is_psk = (!active && jQuery('#tls_connect').val() == 2) || (active && jQuery('#tls_in_psk').is(":checked"));

			if (is_certificate) {
				jQuery('#tls_issuer, #tls_subject').prop('disabled', false);
			}
			else {
				jQuery('#tls_issuer, #tls_subject').prop('disabled', true);
			}

			if (is_psk) {
				jQuery('#tls_psk, #tls_psk_identity').prop('disabled', false);
			}
			else {
				jQuery('#tls_psk, #tls_psk_identity').prop('disabled', true);
			}
		});

		// refresh field visibility on document load
		jQuery('#status, #tls_connect, #tls_psk_out').trigger('change');
	});
</script>
