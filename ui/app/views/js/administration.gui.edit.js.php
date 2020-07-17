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
?>

<script type="text/javascript">
	$(document).ready(function() {
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
							$('#default_lang').val("<?= $schema['fields']['default_lang']['default'] ?>");
							$('#default_theme').val("<?= $schema['fields']['default_theme']['default'] ?>");
							$('#search_limit').val("<?= $schema['fields']['search_limit']['default'] ?>");
							$('#max_in_table').val("<?= $schema['fields']['max_in_table']['default'] ?>");
							$('#server_check_interval').prop('checked',
								<?= ($schema['fields']['server_check_interval']['default'] == 0) ? 'false' : 'true' ?>
							);
							$('#work_period').val("<?= $schema['fields']['work_period']['default'] ?>");
							$('#show_technical_errors').prop('checked',
								<?= ($schema['fields']['show_technical_errors']['default'] == 0) ? 'false' : 'true' ?>
							);
							$('#history_period').val("<?= $schema['fields']['history_period']['default'] ?>");
							$('#period_default').val("<?= $schema['fields']['period_default']['default'] ?>");
							$('#max_period').val("<?= $schema['fields']['max_period']['default'] ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
