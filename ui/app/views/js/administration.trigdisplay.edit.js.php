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


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	$(document).ready(function() {
		const $form = jQuery('#trigdisplay-form');

		$form.on('submit', () => {
			$form.trimValues(['#ok_period', '#blink_period', '#severity_name_0', '#severity_name_1', '#severity_name_2',
				'#severity_name_3', '#severity_name_4', '#severity_name_5'
			]);
		});

		$("input[name=custom_color]").on('change', function() {
			var checked = $(this).is(':checked');
			$(".js-event-color-picker").each(function() {
				this.disabled = !checked;
			});
		});

		$("#resetDefaults").click(function() {
			overlayDialogue({
				title: <?= json_encode(_('Reset confirmation')) ?>,
				content: $('<span>').text(<?= json_encode(_('Reset all fields to default values?')) ?>),
				buttons: [
					{
						title: <?= json_encode(_('Cancel')) ?>,
						cancel: true,
						class: '<?= ZBX_STYLE_BTN_ALT ?>',
						action: function() {}
					},
					{
						title: <?= json_encode(_('Reset defaults')) ?>,
						focused: true,
						action: function() {
							$('main')
								.prev('.msg-bad')
								.remove();

							var custom_color_enabled = <?= json_encode((bool) CSettingsSchema::getDefault('custom_color')) ?>;

							$('#custom_color')
								.prop('checked', custom_color_enabled)
								.change();

							// unacknowledged problem events
							const problem_unack_color = document.querySelector(
								`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="problem_unack_color"]`
							);

							problem_unack_color.color = '<?= CSettingsSchema::getDefault("problem_unack_color") ?>';
							problem_unack_color.disabled = !custom_color_enabled;

							$('#problem_unack_style').prop('checked',
								<?= json_encode((bool) CSettingsSchema::getDefault('problem_unack_style')) ?>
							);

							// acknowledged problem events
							const problem_ack_color = document.querySelector(
								`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="problem_ack_color"]`
							);

							problem_ack_color.color = '<?= CSettingsSchema::getDefault("problem_ack_color") ?>';
							problem_ack_color.disabled = !custom_color_enabled;

							$('#problem_ack_style').prop('checked',
								<?= json_encode((bool) CSettingsSchema::getDefault('problem_ack_style')) ?>
							);

							// unacknowledged resolved events
							const ok_unack_color = document.querySelector(
								`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="ok_unack_color"]`
							);

							ok_unack_color.color = '<?= CSettingsSchema::getDefault("ok_unack_color") ?>';
							ok_unack_color.disabled = !custom_color_enabled;

							$('#ok_unack_style').prop('checked',
								<?= json_encode((bool) CSettingsSchema::getDefault('ok_unack_style')) ?>
							);

							// acknowledged resolved events
							const ok_ack_color = document.querySelector(
								`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="ok_ack_color"]`
							);

							ok_ack_color.color = '<?= CSettingsSchema::getDefault("ok_ack_color") ?>';
							ok_ack_color.disabled = !custom_color_enabled;

							$('#ok_ack_style').prop('checked',
								<?= json_encode((bool) CSettingsSchema::getDefault('ok_ack_style')) ?>
							);

							$('#ok_period').val("<?= CSettingsSchema::getDefault('ok_period') ?>");
							$('#blink_period').val("<?= CSettingsSchema::getDefault('blink_period') ?>");

							$('#severity_name_0').val("<?= CSettingsSchema::getDefault('severity_name_0') ?>");
							$('#severity_name_1').val("<?= CSettingsSchema::getDefault('severity_name_1') ?>");
							$('#severity_name_2').val("<?= CSettingsSchema::getDefault('severity_name_2') ?>");
							$('#severity_name_3').val("<?= CSettingsSchema::getDefault('severity_name_3') ?>");
							$('#severity_name_4').val("<?= CSettingsSchema::getDefault('severity_name_4') ?>");
							$('#severity_name_5').val("<?= CSettingsSchema::getDefault('severity_name_5') ?>");

							document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="severity_color_0"]`)
								.color = '<?= CSettingsSchema::getDefault("severity_color_0") ?>';

							document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="severity_color_1"]`)
								.color = '<?= CSettingsSchema::getDefault("severity_color_1") ?>';

							document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="severity_color_2"]`)
								.color = '<?= CSettingsSchema::getDefault("severity_color_2") ?>';

							document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="severity_color_3"]`)
								.color = '<?= CSettingsSchema::getDefault("severity_color_3") ?>';

							document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="severity_color_4"]`)
								.color = '<?= CSettingsSchema::getDefault("severity_color_4") ?>';

							document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="severity_color_5"]`)
								.color = '<?= CSettingsSchema::getDefault("severity_color_5") ?>';
						}
					}
				]
			}, {
				position: Overlay.prototype.POSITION_CENTER,
				trigger_element: this
			});
		});
	});
</script>
