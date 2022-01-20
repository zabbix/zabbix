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

$default_inventory_mode = DB::getDefault('config', 'default_inventory_mode');
?>

<script type="text/javascript">
	$(document).ready(function() {
		$('#validate_uri_schemes').change(function() {
			$('#uri_valid_schemes').prop('disabled', !this.checked);
		});

		$('#iframe_sandboxing_enabled').change(function() {
			$('#iframe_sandboxing_exceptions').prop('disabled', !this.checked);
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

							$('#url').val("<?= DB::getDefault('config', 'url') ?>");
							$('#discovery_groupid').multiSelect('clean');
							$('#default_inventory_mode input[value=<?= $default_inventory_mode ?>]')
								.prop('checked', true);
							$('#alert_usrgrpid').multiSelect('clean');
							$('#snmptrap_logging').prop('checked',
								<?= (DB::getDefault('config', 'snmptrap_logging') == 0) ? 'false' : 'true' ?>
							);

							// authorization
							$('#login_attempts').val("<?= DB::getDefault('config', 'login_attempts') ?>");
							$('#login_block').val("<?= DB::getDefault('config', 'login_block') ?>");

							// security
							$('#validate_uri_schemes')
								.prop('checked',
									<?= (DB::getDefault('config', 'validate_uri_schemes') == 0) ? 'false' : 'true' ?>
								)
								.change();
							$('#uri_valid_schemes').val("<?= DB::getDefault('config', 'uri_valid_schemes') ?>");
							$('#x_frame_options').val("<?= DB::getDefault('config', 'x_frame_options') ?>");
							$('#iframe_sandboxing_enabled')
								.prop('checked',
									<?= (DB::getDefault('config', 'iframe_sandboxing_enabled') == 0) ? 'false' : 'true' ?>
								)
								.change();
							$('#iframe_sandboxing_exceptions').val(
								"<?= DB::getDefault('config', 'iframe_sandboxing_exceptions') ?>"
							);

							// communication with Zabbix server
							$('#socket_timeout').val("<?= DB::getDefault('config', 'socket_timeout') ?>");
							$('#connect_timeout').val("<?= DB::getDefault('config', 'connect_timeout') ?>");
							$('#media_type_test_timeout').val(
								"<?= DB::getDefault('config', 'media_type_test_timeout') ?>"
							);
							$('#script_timeout').val("<?= DB::getDefault('config', 'script_timeout') ?>");
							$('#item_test_timeout').val("<?= DB::getDefault('config', 'item_test_timeout') ?>");
							$('#report_test_timeout').val("<?= DB::getDefault('config', 'report_test_timeout') ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
