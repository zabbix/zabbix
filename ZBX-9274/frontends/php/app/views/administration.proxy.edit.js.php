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
			jQuery(this).trimValues(['#host', '#ip', '#dns', '#port', '#description']);
		});

		// refresh field visibility on document load
		jQuery('#status').trigger('change');
	});
</script>
