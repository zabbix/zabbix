<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$schema = DB::getSchema('config');
$default_inventory_mode = $schema['fields']['default_inventory_mode']['default'];
?>

<script type="text/javascript">
	$(document).ready(function() {
		$('#validate_uri_schemes').change(function() {
			$('#uri_valid_schemes').prop('disabled', !this.checked);
		});

		$("#resetDefaults").click(function() {
			overlayDialogue({
				'title': <?= json_encode(_('Reset confirmation')) ?>,
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
							$('#refresh_unsupported').val("<?= $schema['fields']['refresh_unsupported']['default'] ?>");
							$('#discovery_groupid').multiSelect('clean');
							$('#default_inventory_mode input[value=<?= $default_inventory_mode ?>]')
								.prop('checked', true);
							$('#alert_usrgrpid').multiSelect('clean');
							$('#snmptrap_logging').prop('checked',
								<?= ($schema['fields']['snmptrap_logging']['default'] == 0) ? 'false' : 'true' ?>
							);
							$('#login_attempts').val("<?= $schema['fields']['login_attempts']['default'] ?>");
							$('#login_block').val("<?= $schema['fields']['login_block']['default'] ?>");
							$('#validate_uri_schemes').prop('checked',
								<?= ($schema['fields']['validate_uri_schemes']['default'] == 0) ? 'false' : 'true' ?>
							);
							$('#uri_valid_schemes').val("<?= $schema['fields']['uri_valid_schemes']['default'] ?>");
							$('#x_frame_options').val("<?= $schema['fields']['x_frame_options']['default'] ?>");
							$('#socket_timeout').val("<?= $schema['fields']['socket_timeout']['default'] ?>");
							$('#connect_timeout').val("<?= $schema['fields']['connect_timeout']['default'] ?>");
							$('#media_type_test_timeout').val("<?= $schema['fields']['media_type_test_timeout']['default'] ?>");
							$('#script_timeout').val("<?= $schema['fields']['script_timeout']['default'] ?>");
							$('#item_test_timeout').val("<?= $schema['fields']['item_test_timeout']['default'] ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
