<script type="text/javascript">
	jQuery(document).ready(function() {
		// proxy mode: active or passive
		jQuery('#status').change(function() {
			var active = (jQuery(this).val() == 5);
			if (active) {
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
			jQuery('#host').val(jQuery.trim(jQuery('#host').val()));
			jQuery('#ip').val(jQuery.trim(jQuery('#ip').val()));
			jQuery('#dns').val(jQuery.trim(jQuery('#dns').val()));
			jQuery('#port').val(jQuery.trim(jQuery('#port').val()));
			jQuery('#description').val(jQuery.trim(jQuery('#description').val()));
		});
	});
</script>
