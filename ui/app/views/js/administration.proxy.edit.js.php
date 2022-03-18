<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	jQuery(function($) {
		// proxy mode: active or passive
		$('#status').change(function() {
			$('#ip').closest('li').toggle($('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>);
			$('#proxy_address')
				.closest('li')
				.toggle($('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>);

			toggleEncryptionFields();
		});

		$(':radio[name=useip]').change(function() {
			$(':text[name=ip],:text[name=dns]')
				.removeAttr('aria-required')
				.filter(($(this).val() == <?= INTERFACE_USE_IP ?>) ? '[name=ip]' : '[name=dns]')
				.attr('aria-required', 'true');
		});

		// clone button, special processing because of list of hosts
		$('#clone').click(function() {
			var url = new Curl('zabbix.php?action=proxy.edit');
			url.setArgument('host', $('#host').val());
			url.setArgument('status', $('input[name=status]:checked').val());
			url.setArgument('proxy_address', $('#proxy_address').val());
			url.setArgument('description', $('#description').val());
			url.setArgument('ip', $('#ip').val());
			url.setArgument('dns', $('#dns').val());
			url.setArgument('useip', $('input[name=useip]:checked').val());
			url.setArgument('port', $('#port').val());
			url.setArgument('tls_connect', $('input[name=tls_connect]:checked').val());
			url.setArgument('tls_psk_identity', $('#tls_psk_identity').val());
			url.setArgument('tls_psk', $('#tls_psk').val());
			url.setArgument('psk_edit_mode', $('#psk_edit_mode').val());
			url.setArgument('tls_issuer', $('#tls_issuer').val());
			url.setArgument('tls_subject', $('#tls_subject').val());
			url.setArgument('tls_accept', getTlsAccept());
			url.setArgument('clone_proxyid', $('#proxyid').val());
			redirect(url.getUrl(), 'post', 'action');
		});

		$('#change_psk').click(function() {
			let input = document.createElement('input');

			input.setAttribute('type', 'hidden');
			input.setAttribute('name', 'action');
			input.setAttribute('value', 'proxy.edit');
			document.forms['proxy-form'].appendChild(input);

			submitFormWithParam('proxy-form', 'psk_edit_mode', '1');
		});

		// Refresh field visibility on document load.
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			$('#tls_in_none').prop('checked', true);
		}

		/*
		 * Calling 'click' would reverse the property that was just set, and calling a manual 'click' even is just a not
		 * good idea for checkboxes. For some reason jQuery .trigger('change') doesn't work on checkboxes. The event
		 * listener in class.tab-indicators.js is not registering a change to it, so this is a workaround. So after the
		 * property has changed by script, the tab indicator should update.
		 */
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			$('#tls_in_psk').prop('checked', true);

			const event = new Event('change');

			document.querySelector('[name=tls_in_psk]').dispatchEvent(event);
		}

		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			$('#tls_in_cert').prop('checked', true);

			const event = new Event('change');

			document.querySelector('[name=tls_in_cert]').dispatchEvent(event);
		}

		$('input[name=tls_connect]').trigger('change');

		// Trim spaces on submit and depending on checkboxes, create a value for hidden field 'tls_accept'.
		$('#proxy-form').submit(function() {
			$(this).trimValues([
				'#host', '#ip', '#dns', '#port', '#description', '#tls_psk_identity', '#tls_psk', '#tls_issuer',
				'#tls_subject', '#proxy_address'
			]);
			$('#tls_accept').val(getTlsAccept());
		});

		// Refresh field visibility on document load.
		$('#status,[name=useip]:checked').trigger('change');

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
				$('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').show();
			}
			else {
				$('#tls_psk, #tls_psk_identity, .tls_psk').closest('li').hide();
			}

			if (($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					&& $('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>)
					|| ($('#tls_in_psk').is(':checked')
					&& $('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_ACTIVE ?>)) {
				$('#tls_psk, #tls_psk_identity, #change_psk').prop('disabled', false);
			}
			else {
				$('#tls_psk, #tls_psk_identity, #change_psk').prop('disabled', true);
			}
		}

		/**
		 * Get tls_accept value.
		 *
		 * @return int
		 */
		function getTlsAccept() {
			var tls_accept = 0x00;

			if ($('input[name=status]:checked').val() == <?= HOST_STATUS_PROXY_PASSIVE ?>) {
				return <?= HOST_ENCRYPTION_NONE ?>;
			}

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
