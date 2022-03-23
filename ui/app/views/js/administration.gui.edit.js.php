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
	$(document).ready(function() {
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

							$('#default_lang').val("<?= DB::getDefault('config', 'default_lang') ?>");
							$('#default_timezone').val("<?= DB::getDefault('config', 'default_timezone') ?>");
							$('#default_theme').val("<?= DB::getDefault('config', 'default_theme') ?>");
							$('#search_limit').val("<?= DB::getDefault('config', 'search_limit') ?>");
							$('#max_overview_table_size').val(
								"<?= DB::getDefault('config', 'max_overview_table_size') ?>"
							);
							$('#max_in_table').val("<?= DB::getDefault('config', 'max_in_table') ?>");
							$('#server_check_interval').prop('checked', <?=
								json_encode((bool) DB::getDefault('config', 'server_check_interval'))
							?>);
							$('#work_period').val("<?= DB::getDefault('config', 'work_period') ?>");
							$('#show_technical_errors').prop('checked', <?=
								json_encode((bool) DB::getDefault('config', 'show_technical_errors'))
							?>);
							$('#history_period').val("<?= DB::getDefault('config', 'history_period') ?>");
							$('#period_default').val("<?= DB::getDefault('config', 'period_default') ?>");
							$('#max_period').val("<?= DB::getDefault('config', 'max_period') ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
