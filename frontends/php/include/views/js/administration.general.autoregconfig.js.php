<script type="text/javascript">
	jQuery(function($) {
		// Refresh field visibility on document load.
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			$('#tls_in_none').prop('checked', true);
		}
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			$('#tls_in_psk').prop('checked', true);
		}

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		$('#autoregconfigForm').submit(function() {
			var tls_accept = 0x00;

			if ($('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if ($('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}

			$('#tls_accept').val(tls_accept);
		});

		// PSK fields show/hide
		$('.checkbox-radio', $('#autoreg')).click(function() {

			if ($('#tls_in_psk').is(':checked')) {
				$('.tls_psk').show();
			}
			else {
				$('.tls_psk').hide().find('input').val('');
				if (!($('#tls_in_none').is(':checked'))) {
					$('#tls_in_none').prop('checked', true);
				}
			}
		});
		if (!$('#tls_in_psk').is(':checked')) {
			$('.tls_psk').hide();
		}

	});
</script>
