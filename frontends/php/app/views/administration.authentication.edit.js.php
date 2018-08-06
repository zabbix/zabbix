<script type="text/javascript">
	jQuery(function ($) {
		var form = $('[name=form_auth]');

		form.submit(function () {
			return $('[name=authentication_type]:checked').val() == <?= $data['db_authentication_type'] ?>
					|| confirm(<?= CJs::encodeJson(
						_('Switching authentication method will reset all except this session! Continue?')
					) ?>);
		});

		form.find('#http_auth_enabled,#ldap_configured').change(function () {
			var fields = $(this).is('#http_auth_enabled')
				? form.find('[name^=http_]')
				: form.find('[name^=ldap_],button[name=change_bind_password]');

			fields
				.not(this)
				.attr('disabled', !this.checked)
				.filter('[name$=_case_sensitive]:not(:disabled)')
				.prop('checked', true);
		});

		form.find('button#change_bind_password').click(function () {
			form.find('[name=action]')
				.val('<?= $data['action_passw_change'] ?>');

			submitFormWithParam('form_auth', 'change_bind_password', '1');
		});
	});
</script>
