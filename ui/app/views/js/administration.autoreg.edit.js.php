<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */
?>

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
