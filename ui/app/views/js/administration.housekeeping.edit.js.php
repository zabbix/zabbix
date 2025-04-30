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
		const $form = jQuery('#housekeeping-form');

		$form.on('submit', () => {
			$form.trimValues(['#hk_events_trigger', '#hk_events_service', '#hk_events_internal', '#hk_events_discovery',
				'#hk_events_autoreg', '#hk_services', '#hk_sessions', '#hk_history', '#hk_trends'
			]);
		});

		$('#hk_events_mode').change(function() {
			$('#hk_events_trigger').prop('disabled', !this.checked);
			$('#hk_events_service').prop('disabled', !this.checked);
			$('#hk_events_internal').prop('disabled', !this.checked);
			$('#hk_events_discovery').prop('disabled', !this.checked);
			$('#hk_events_autoreg').prop('disabled', !this.checked);
		});

		$('#hk_services_mode').change(function() {
			$('#hk_services').prop('disabled', !this.checked);
		});

		$('#hk_sessions_mode').change(function() {
			$('#hk_sessions').prop('disabled', !this.checked);
		});

		$('#hk_history_global').change(function() {
			$('#hk_history').prop('disabled', !this.checked);
		});

		$('#hk_trends_global').change(function() {
			$('#hk_trends').prop('disabled', !this.checked);
		});

		$('#compression_status').change(function() {
			$('#compress_older').prop('disabled', !this.checked);
		});

		$('#hk_history_mode, #hk_history_global')
			.change(function() {
				$('.js-hk-history-warning').toggle(document.getElementById('hk_history_mode').checked
					&& !document.getElementById('hk_history_global').checked
				)
			})
			.trigger('change');

		$('#hk_trends_mode, #hk_trends_global')
			.change(function() {
				$('.js-hk-trends-warning').toggle(document.getElementById('hk_trends_mode').checked
					&& !document.getElementById('hk_trends_global').checked
				)
			})
			.trigger('change');

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

							// events and alerts
							$('#hk_events_mode')
								.prop('checked',
									<?= json_encode((bool) CSettingsSchema::getDefault('hk_events_mode')) ?>
								)
								.change();
							$('#hk_events_trigger').val("<?= CSettingsSchema::getDefault('hk_events_trigger') ?>");
							$('#hk_events_service').val("<?= CSettingsSchema::getDefault('hk_events_service') ?>");
							$('#hk_events_internal').val("<?= CSettingsSchema::getDefault('hk_events_internal') ?>");
							$('#hk_events_discovery').val("<?= CSettingsSchema::getDefault('hk_events_discovery') ?>");
							$('#hk_events_autoreg').val("<?= CSettingsSchema::getDefault('hk_events_autoreg') ?>");
							$('#hk_services_mode')
								.prop('checked',
									<?= json_encode((bool) CSettingsSchema::getDefault('hk_services_mode')) ?>
								)
								.change();
							$('#hk_services').val("<?= CSettingsSchema::getDefault('hk_services') ?>");

							// user sessions
							$('#hk_sessions_mode')
								.prop('checked',
									<?= json_encode((bool) CSettingsSchema::getDefault('hk_sessions_mode')) ?>
								)
								.change();
							$('#hk_sessions').val("<?= CSettingsSchema::getDefault('hk_sessions') ?>");

							// history
							$('#hk_history_mode').prop('checked',
								<?= json_encode((bool) CSettingsSchema::getDefault('hk_history_mode')) ?>
							);
							$('#hk_history_global')
								.prop('checked',
									<?= json_encode((bool) CSettingsSchema::getDefault('hk_history_global')) ?>
								)
								.change();
							$('#hk_history').val("<?= CSettingsSchema::getDefault('hk_history') ?>");

							// trends
							$('#hk_trends_mode').prop('checked',
								<?= json_encode((bool) CSettingsSchema::getDefault('hk_trends_mode')) ?>
							);
							$('#hk_trends_global')
								.prop('checked',
									<?= json_encode((bool) CSettingsSchema::getDefault('hk_trends_global')) ?>
								)
								.change();
							$('#hk_trends').val("<?= CSettingsSchema::getDefault('hk_trends') ?>");

							// history and trends compression
							$('#compression_status')
								.prop('checked',
									<?= json_encode((bool) CSettingsSchema::getDefault('compression_status')) ?>
								)
								.change();
							$('#compress_older').val("<?= CSettingsSchema::getDefault('compress_older') ?>");
						}
					}
				]
			}, this);
		});
	});
</script>
