<script type="text/javascript">
jQuery(function ($) {
	var form = $("[name=\"form_auth\"]");

	form.submit(function () {
		return jQuery("[name=authentication_type]:checked").val() == <?= $this->data['db_authentication_type'] ?>
			|| confirm(<?= CJs::encodeJson(
					_('Switching authentication method will reset all except this session! Continue?')) ?>
				);
	});

	form.find("#http_auth_enabled").change(function () {
		$("input,select", ".http_auth").attr("disabled", !this.checked);
	});

	form.find("#ldap_configured").change(function () {
		$("[name^=ldap_],button[name=change_bind_password]")
			.not(this)
			.attr("disabled", !this.checked);
	});

	form.find("button#change_bind_password").click(function () {
		form.find("[name=action]")
			.val("<?= $this->data['action_passw_change'] ?>");

		submitFormWithParam("form_auth", "change_bind_password", "1");
	});
});
</script>
