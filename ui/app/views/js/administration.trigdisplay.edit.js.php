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
		var $form = $('form');
		$form.on('submit', function() {
			$form.trimValues(['#severity_name_0', '#severity_name_1', '#severity_name_2', '#severity_name_3',
				'#severity_name_4', '#severity_name_5'
			]);
		});

		$("input[name=custom_color]").on('change', function() {
			var checked = $(this).is(':checked');
			$(".js-event-color-picker").each(function() {
				var $field = $(this);
				$field.toggleClass('<?= ZBX_STYLE_DISABLED ?>', !checked);
				$("input, input+button", $field).prop('disabled', !checked);
			});
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

							var custom_color_enabled = <?=
								(DB::getDefault('config', 'custom_color') == EVENT_CUSTOM_COLOR_ENABLED)
									? 'true'
									: 'false'
								?>;

							$('#custom_color')
								.prop('checked', custom_color_enabled)
								.change();

							// unacknowledged problem events
							$('#problem_unack_color')
								.val("<?= DB::getDefault('config', 'problem_unack_color') ?>")
								.prop('disabled', !custom_color_enabled)
								.change();
							$('#problem_unack_style').prop('checked',
								<?= (DB::getDefault('config', 'problem_unack_style') == 0) ? 'false' : 'true' ?>
							);

							// acknowledged problem events
							$('#problem_ack_color')
								.val("<?= DB::getDefault('config', 'problem_ack_color') ?>")
								.prop('disabled', !custom_color_enabled)
								.change();
							$('#problem_ack_style').prop('checked',
								<?= (DB::getDefault('config', 'problem_ack_style') == 0) ? 'false' : 'true' ?>
							);

							// unacknowledged resolved events
							$('#ok_unack_color')
								.val("<?= DB::getDefault('config', 'ok_unack_color') ?>")
								.prop('disabled', !custom_color_enabled)
								.change();
							$('#ok_unack_style').prop('checked',
								<?= (DB::getDefault('config', 'ok_unack_style') == 0) ? 'false' : 'true' ?>
							);

							// acknowledged resolved events
							$('#ok_ack_color')
								.val("<?= DB::getDefault('config', 'ok_ack_color') ?>")
								.prop('disabled', !custom_color_enabled)
								.change();
							$('#ok_ack_style').prop('checked',
								<?= (DB::getDefault('config', 'ok_ack_style') == 0) ? 'false' : 'true' ?>
							);

							$('#ok_period').val("<?= DB::getDefault('config', 'ok_period') ?>");
							$('#blink_period').val("<?= DB::getDefault('config', 'blink_period') ?>");

							$('#severity_name_0').val("<?= DB::getDefault('config', 'severity_name_0') ?>");
							$('#severity_name_1').val("<?= DB::getDefault('config', 'severity_name_1') ?>");
							$('#severity_name_2').val("<?= DB::getDefault('config', 'severity_name_2') ?>");
							$('#severity_name_3').val("<?= DB::getDefault('config', 'severity_name_3') ?>");
							$('#severity_name_4').val("<?= DB::getDefault('config', 'severity_name_4') ?>");
							$('#severity_name_5').val("<?= DB::getDefault('config', 'severity_name_5') ?>");
							$('#severity_color_0')
								.val("<?= DB::getDefault('config', 'severity_color_0') ?>")
								.change();
							$('#severity_color_1')
								.val("<?= DB::getDefault('config', 'severity_color_1') ?>")
								.change();
							$('#severity_color_2')
								.val("<?= DB::getDefault('config', 'severity_color_2') ?>")
								.change();
							$('#severity_color_3')
								.val("<?= DB::getDefault('config', 'severity_color_3') ?>")
								.change();
							$('#severity_color_4')
								.val("<?= DB::getDefault('config', 'severity_color_4') ?>")
								.change();
							$('#severity_color_5')
								.val("<?= DB::getDefault('config', 'severity_color_5') ?>")
								.change();
						}
					}
				]
			}, this);
		});
	});
</script>
