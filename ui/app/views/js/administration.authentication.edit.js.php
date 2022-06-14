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
		var $form = $('[name=form_auth]'),
			warn = true;

		$form.submit(function() {
			var proceed = !warn
				|| $('[name=authentication_type]:checked').val() == $('[name=db_authentication_type]').val()
				|| confirm(<?= json_encode(
					_('Switching authentication method will reset all except this session! Continue?')
				) ?>);
			warn = true;

			$form.trimValues(['#saml_idp_entityid', '#saml_sso_url', '#saml_slo_url', '#saml_username_attribute',
				'#saml_sp_entityid', '#saml_nameid_format'
			]);

			return proceed;
		});

		$form.find('#http_auth_enabled, #ldap_configured, #saml_auth_enabled').on('change', function() {
			var fields;

			if ($(this).is('#http_auth_enabled')) {
				fields = $form.find('[name^=http_]');
			}
			else if ($(this).is('#ldap_configured')) {
				fields = $form.find('[name^=ldap_],#bind-password-btn');
			}
			else {
				fields = $form.find('[name^=saml_]');
			}

			fields
				.not('[name=http_auth_enabled], [name=ldap_configured], [name=saml_auth_enabled]')
				.prop('disabled', !this.checked);
		});

		$form.find('#bind-password-btn').on('click', showPasswordField);

		$form.find('[name=ldap_test]').click(function() {
			warn = false;
		});
	});

	function showPasswordField(e) {
		const form_field = e.target.parentNode;
		const password_field = form_field.querySelector('[name="ldap_bind_password"]');

		password_field.disabled = false;
		password_field.classList.remove('<?= ZBX_STYLE_DISPLAY_NONE ?>');

		form_field.removeChild(e.target);

		document.getElementById('change_bind_password').value = 1;
	}
</script>
