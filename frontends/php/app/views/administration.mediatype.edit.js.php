<script type="text/javascript">
	jQuery(document).ready(function() {
		// type of media
		jQuery('#type').change(function() {
			switch (jQuery(this).val()) {
				case '<?php echo MEDIA_TYPE_EMAIL; ?>':
					jQuery('#smtp_server').closest('li').css('display', '').removeClass('hidden');
					jQuery('#smtp_helo').closest('li').css('display', '').removeClass('hidden');
					jQuery('#smtp_email').closest('li').css('display', '').removeClass('hidden');
					jQuery('#exec_path').closest('li').css('display', 'none');
					jQuery('#gsm_modem').closest('li').css('display', 'none');
					jQuery('#jabber_username').closest('li').css('display', 'none');
					jQuery('#eztext_username').closest('li').css('display', 'none');
					jQuery('#eztext_limit').closest('li').css('display', 'none');
					jQuery('#eztext_link').css('display', 'none');
					jQuery('#passwd').closest('li').css('display', 'none');
					break;
				case '<?php echo MEDIA_TYPE_EXEC; ?>':
					jQuery('#smtp_server').closest('li').css('display', 'none');
					jQuery('#smtp_helo').closest('li').css('display', 'none');
					jQuery('#smtp_email').closest('li').css('display', 'none');
					jQuery('#exec_path').closest('li').css('display', '').removeClass('hidden');
					jQuery('#gsm_modem').closest('li').css('display', 'none');
					jQuery('#jabber_username').closest('li').css('display', 'none');
					jQuery('#eztext_username').closest('li').css('display', 'none');
					jQuery('#eztext_limit').closest('li').css('display', 'none');
					jQuery('#eztext_link').css('display', 'none');
					jQuery('#passwd').closest('li').css('display', 'none');
					break;
				case '<?php echo MEDIA_TYPE_SMS; ?>':
					jQuery('#smtp_server').closest('li').css('display', 'none');
					jQuery('#smtp_helo').closest('li').css('display', 'none');
					jQuery('#smtp_email').closest('li').css('display', 'none');
					jQuery('#exec_path').closest('li').css('display', 'none');
					jQuery('#gsm_modem').closest('li').css('display', '').removeClass('hidden');
					jQuery('#jabber_username').closest('li').css('display', 'none');
					jQuery('#eztext_username').closest('li').css('display', 'none');
					jQuery('#eztext_limit').closest('li').css('display', 'none');
					jQuery('#eztext_link').css('display', 'none');
					jQuery('#passwd').closest('li').css('display', 'none');
					break;
				case '<?php echo MEDIA_TYPE_JABBER; ?>':
					jQuery('#smtp_server').closest('li').css('display', 'none');
					jQuery('#smtp_helo').closest('li').css('display', 'none');
					jQuery('#smtp_email').closest('li').css('display', 'none');
					jQuery('#exec_path').closest('li').css('display', 'none');
					jQuery('#gsm_modem').closest('li').css('display', 'none');
					jQuery('#jabber_username').closest('li').css('display', '').removeClass('hidden');
					jQuery('#eztext_username').closest('li').css('display', 'none');
					jQuery('#eztext_limit').closest('li').css('display', 'none');
					jQuery('#eztext_link').css('display', 'none');
					jQuery('#passwd').closest('li').css('display', '').removeClass('hidden');
					break;
				case '<?php echo MEDIA_TYPE_EZ_TEXTING; ?>':
					jQuery('#smtp_server').closest('li').css('display', 'none');
					jQuery('#smtp_helo').closest('li').css('display', 'none');
					jQuery('#smtp_email').closest('li').css('display', 'none');
					jQuery('#exec_path').closest('li').css('display', 'none');
					jQuery('#gsm_modem').closest('li').css('display', 'none');
					jQuery('#jabber_username').closest('li').css('display', 'none');
					jQuery('#eztext_username').closest('li').css('display', '').removeClass('hidden');
					jQuery('#eztext_limit').closest('li').css('display', '').removeClass('hidden');
					jQuery('#eztext_link').css('display', '').removeClass('hidden');
					jQuery('#passwd').closest('li').css('display', '').removeClass('hidden');
					break;
			}
		});

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#mediatypeid, #delete, #clone').remove();
			jQuery('#update span').text(<?php echo CJs::encodeJson(_('Add')); ?>);
			jQuery('#update').val('mediatype.create').attr({id: 'add'});
			jQuery('#cancel').addClass('ui-corner-left');
			jQuery('#description').focus();
		});

		// refresh field visibility on document load
		jQuery('#type').trigger('change');
	});
</script>
