<script type="text/javascript">
	jQuery(function($) {
		var $tls_psk = $('.tls_psk', $('#autoreg-form'));

		// Refresh field visibility on document load.
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			$('#tls_in_none').prop('checked', true);
		}
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			$('#tls_in_psk').prop('checked', true);
		}
		else {
			$tls_psk.hide();
		}

		// Show/hide PSK fields.
		$('#tls_in_psk').on('click', function() {
			$tls_psk.toggle($(this).is(':checked'));
		});

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		$('#autoreg-form').on('submit', function() {
			var tls_accept = 0x00;

			if ($('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if ($('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}
			else {
				$('#tls_psk_identity, #tls_psk').val('');
			}

			$('#tls_accept').val(tls_accept);
		});
	});
</script>
