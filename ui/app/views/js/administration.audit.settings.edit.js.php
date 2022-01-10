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
		var $form = $('form#audit-settings');

		$form.on('submit', function() {
			$form.trimValues(['#hk_audit']);
		});

		$('#hk_audit_mode').change(function() {
			$('#hk_audit').prop('disabled', !this.checked);
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

							$('#auditlog_enabled')
								.prop('checked',
									<?= (DB::getDefault('config', 'auditlog_enabled') == 1) ? 'true' : 'false' ?>
								)
								.change();
							$('#hk_audit_mode')
								.prop('checked',
									<?= (DB::getDefault('config', 'hk_audit_mode') == 1) ? 'true' : 'false' ?>
								)
								.change();
							$('#hk_audit').val("<?= DB::getDefault('config', 'hk_audit') ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
