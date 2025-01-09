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
?>


<script type="text/javascript">
	const view = new class {

		init({default_inventory_mode, iframe_sandboxing_enabled, iframe_sandboxing_exceptions, login_attempts,
				login_block, snmptrap_logging, uri_valid_schemes, url, validate_uri_schemes, vault_provider,
				x_frame_options}) {
			const $form = jQuery('#miscconfig-form');

			$('#validate_uri_schemes').change(function() {
				$('#uri_valid_schemes').prop('disabled', !this.checked);
			});

			$('#x_frame_header_enabled').change(function() {
				$('#x_frame_options').prop('disabled', !this.checked);
			});

			$('#iframe_sandboxing_enabled').change(function() {
				$('#iframe_sandboxing_exceptions').prop('disabled', !this.checked);
			});

			$form.on('submit', () => {
				$form.trimValues(['#url', '#login_block', '#uri_valid_schemes', '#x_frame_options',
					'#iframe_sandboxing_exceptions'
				]);
			});

			$("#resetDefaults").click(function() {
				overlayDialogue({
					'title': <?= json_encode(_('Reset confirmation')) ?>,
					'class': 'position-middle',
					'content': $('<span>').text(<?= json_encode(_('Reset all fields to default values?')) ?>),
					'buttons': [
						{
							'title': <?= json_encode(_('Cancel')) ?>,
							'cancel': true,
							'class': '<?= ZBX_STYLE_BTN_ALT ?>',
							'action': function() {}
						},
						{
							'title': <?= json_encode(_('Reset defaults')) ?>,
							'focused': true,
							'action': function() {
								$('main')
									.prev('.msg-bad')
									.remove();

								$('#url').val(url);
								$('#discovery_groupid').multiSelect('clean');
								$(`#default_inventory_mode input[value=${default_inventory_mode}]`)
									.prop('checked', true);
								$('#alert_usrgrpid').multiSelect('clean');
								$('#snmptrap_logging').prop('checked', snmptrap_logging == 0 ? 'false' : 'true');

								// Authorization.
								$('#login_attempts').val(login_attempts);
								$('#login_block').val(login_block);

								// Storage of secrets.
								$(`#vault_provider input[value=${vault_provider}]`).prop('checked', true);

								// Security.
								$('#validate_uri_schemes')
									.prop('checked', validate_uri_schemes == 0 ? 'false' : 'true')
									.change();
								$('#uri_valid_schemes').val(uri_valid_schemes);
								$('#x_frame_header_enabled')
									.prop('checked',
										<?= DB::getDefault('config', 'x_frame_options') === 'null' ? 'false' : 'true' ?>
									)
									.change();
								$('#x_frame_options').val(x_frame_options);
								$('#iframe_sandboxing_enabled')
									.prop('checked', iframe_sandboxing_enabled == 0 ? 'false' : 'true')
									.change();
								$('#iframe_sandboxing_exceptions').val(iframe_sandboxing_exceptions);
							}
						}
					]
				}, this);
			});
		}
	}
</script>
