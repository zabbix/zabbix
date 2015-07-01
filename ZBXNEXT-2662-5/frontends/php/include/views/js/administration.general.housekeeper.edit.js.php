<?php
$schema = DB::getSchema('config');
?>

<?=
	// dialog background
	(new CDiv())
		->addClass(ZBX_STYLE_OVERLAY_BG)
		->addStyle('display: none')
		->toString()
?>
<?=
	// dialog
	(new CDiv([
		(new CSpan())
			->addClass(ZBX_STYLE_OVERLAY_CLOSE_BTN)
			->onClick('javascript: closeResetDialog();'),
		(new CDiv(
			(new CTag('h4', true, _('Reset confirmation')))
		))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD),
		(new CDiv(
			_('Reset all fields to default values?')
		))->addClass(ZBX_STYLE_OVERLAY_DIALOGUE_BODY),
		(new CDiv([
			(new CButton(null, _('Cancel')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->onClick('javascript: closeResetDialog();'),
			(new CButton(null, _('Reset defaults')))
				->onClick('javascript: resetDefaults();')
		]))->addClass(ZBX_STYLE_OVERLAY_DIALOGUE_FOOTER)
	]))
		->addClass(ZBX_STYLE_OVERLAY_DIALOGUE)
		->addStyle('display: none;')
		->addStyle('min-width: '.ZBX_HOST_MODAL_DIALOG_MIN_WIDTH.'px;')
		->setId('dialog')
		->toString()
?>

<script type="text/javascript">
	function openResetDialog() {
		var width = jQuery('#dialog').outerWidth(),
			height = jQuery('#dialog').outerHeight();

		jQuery('.<?= ZBX_STYLE_OVERLAY_BG ?>').show();

		jQuery('#dialog')
			.css({
				'left': jQuery(window).width() / 2 - width / 2,
				'top': jQuery(window).height() / 2 - height / 2
			})
			.show();

		jQuery('#dialog .<?= ZBX_STYLE_OVERLAY_DIALOGUE_FOOTER ?> button:last-child').focus();
	}

	function closeResetDialog() {
		jQuery('.<?= ZBX_STYLE_OVERLAY_BG ?>, #dialog').hide();
	}

	function resetDefaults() {
		// events and alerts
		<?php if ($schema['fields']['hk_events_mode']['default'] == 1): ?>
			jQuery('#hk_events_mode').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_events_mode').prop('checked', false);
		<?php endif ?>

		jQuery('#hk_events_mode').trigger('change');

		jQuery('#hk_events_trigger').val("<?= $schema['fields']['hk_events_trigger']['default'] ?>");
		jQuery('#hk_events_internal').val("<?= $schema['fields']['hk_events_internal']['default'] ?>");
		jQuery('#hk_events_discovery').val("<?= $schema['fields']['hk_events_discovery']['default'] ?>");
		jQuery('#hk_events_autoreg').val("<?= $schema['fields']['hk_events_autoreg']['default'] ?>");

		// IT services
		<?php if ($schema['fields']['hk_services_mode']['default'] == 1): ?>
			jQuery('#hk_services_mode').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_services_mode').prop('checked', false);
		<?php endif ?>

		jQuery('#hk_services_mode').trigger('change');

		jQuery('#hk_services').val("<?= $schema['fields']['hk_services']['default'] ?>");

		// audit
		<?php if ($schema['fields']['hk_audit_mode']['default'] == 1): ?>
			jQuery('#hk_audit_mode').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_audit_mode').prop('checked', false);
		<?php endif ?>

		jQuery('#hk_audit_mode').trigger('change');

		jQuery('#hk_audit').val("<?= $schema['fields']['hk_audit']['default'] ?>");

		// user sessions
		<?php if ($schema['fields']['hk_sessions_mode']['default'] == 1): ?>
			jQuery('#hk_sessions_mode').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_sessions_mode').prop('checked', false);
		<?php endif ?>

		jQuery('#hk_sessions_mode').trigger('change');

		jQuery('#hk_sessions').val("<?= $schema['fields']['hk_sessions']['default'] ?>");

		// history
		<?php if ($schema['fields']['hk_history_mode']['default'] == 1): ?>
			jQuery('#hk_history_mode').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_history_mode').prop('checked', false);
		<?php endif ?>

		<?php if ($schema['fields']['hk_history_global']['default'] == 1): ?>
			jQuery('#hk_history_global').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_history_global').prop('checked', false);
		<?php endif ?>

		jQuery('#hk_history_global').trigger('change');

		jQuery('#hk_history').val("<?= $schema['fields']['hk_history']['default'] ?>");

		// trends
		<?php if ($schema['fields']['hk_trends_mode']['default'] == 1): ?>
			jQuery('#hk_trends_mode').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_trends_mode').prop('checked', false);
		<?php endif ?>

		<?php if ($schema['fields']['hk_trends_global']['default'] == 1): ?>
			jQuery('#hk_trends_global').prop('checked', true);
		<?php else: ?>
			jQuery('#hk_trends_global').prop('checked', false);
		<?php endif ?>

		jQuery('#hk_trends_global').trigger('change');

		jQuery('#hk_trends').val("<?= $schema['fields']['hk_trends']['default'] ?>");

		closeResetDialog();
	}

	jQuery(function() {
		jQuery('#hk_events_mode').change(function() {
			jQuery('#hk_events_trigger').prop('disabled', !this.checked);
			jQuery('#hk_events_internal').prop('disabled', !this.checked);
			jQuery('#hk_events_discovery').prop('disabled', !this.checked);
			jQuery('#hk_events_autoreg').prop('disabled', !this.checked);
		});

		jQuery('#hk_services_mode').change(function() {
			jQuery('#hk_services').prop('disabled', !this.checked);
		});

		jQuery('#hk_audit_mode').change(function() {
			jQuery('#hk_audit').prop('disabled', !this.checked);
		});

		jQuery('#hk_sessions_mode').change(function() {
			jQuery('#hk_sessions').prop('disabled', !this.checked);
		});

		jQuery('#hk_history_global').change(function() {
			jQuery('#hk_history').prop('disabled', !this.checked);
		});

		jQuery('#hk_trends_global').change(function() {
			jQuery('#hk_trends').prop('disabled', !this.checked);
		});

		jQuery("#resetDefaults").click(function() {
			openResetDialog();
		});
	});
</script>
