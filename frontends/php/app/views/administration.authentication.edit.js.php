<script type="text/javascript">
	jQuery(function ($) {
		var form = $('[name=form_auth]');

		form.submit(function () {
			return $('[name=authentication_type]:checked').val() == <?= $data['db_authentication_type'] ?>
					|| confirm(<?= CJs::encodeJson(
						_('Switching authentication method will reset all except this session! Continue?')
					) ?>);
		});

		form.find('#http_auth_enabled').change(function () {
			$('input,select', '.http_auth').attr('disabled', !this.checked);
		});

		form.find('#ldap_configured').change(function () {
			$('[name^=ldap_],button[name=change_bind_password]')
				.not(this)
				.attr('disabled', !this.checked);
		});

		form.find('button#change_bind_password').click(function () {
			form.find('[name=action]')
				.val('<?= $data['action_passw_change'] ?>');

			submitFormWithParam('form_auth', 'change_bind_password', '1');
		});

		form.find('#http_auth_enabled,#ldap_configured').change(function () {
			var checkbox = $('#login_case_sensitive'),
				was_disabled = checkbox.is(':disabled');

			checkbox.attr('disabled', !$('#http_auth_enabled:checked,#ldap_configured:checked').length);

			if (was_disabled && !checkbox.is(':disabled')) {
				checkbox.prop('checked', true);
			}
		});
	});
</script>
