<script type="text/x-jquery-tmpl" id="tagRow">
<?=
	(new CRow([
		(new CTextBox('tags[#{rowNum}][tag]', '', false, 255))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag')),
		(new CTextBox('tags[#{rowNum}][value]', '', false, 255))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('value')),
		(new CCol(
			(new CButton('tags[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	]))
		->addClass('form_row')
		->toString()
?>
</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Refresh field visibility on document load.
		changeRecoveryMode();
		changeCorrelationMode();

		$('input[name=recovery_mode]').change(function() {
			changeRecoveryMode();
		});

		$('input[name=correlation_mode]').change(function() {
			changeCorrelationMode();
		});

		function changeRecoveryMode() {
			var	recovery_mode = $('input[name=recovery_mode]:checked').val();

			$('#expression_row').find('label').text(
				(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					? '<?= _('Problem expression') ?>'
					: '<?= _('Expression') ?>'
			);
			$('.recovery_expression_constructor_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>);
			$('#correlation_mode_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>
				);

			changeCorrelationMode();
		}

		function changeCorrelationMode() {
			var	recovery_mode = $('input[name=recovery_mode]:checked').val(),
				correlation_mode = $('input[name=correlation_mode]:checked').val();

			$('#correlation_tag_row')
				.toggle((recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					&& correlation_mode == <?= ZBX_TRIGGER_CORRELATION_TAG ?>
				);
		}

		$('#tbl_tags').dynamicRows({
			template: '#tagRow'
		});
	});

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		if (!isset('object', list)) {
			return false;
		}

		if (list.object == 'deptrigger') {
			for (var i = 0; i < list.values.length; i++) {
				create_var('triggersForm', 'new_dependency[' + i + ']', list.values[i].triggerid, false);
			}

			// return to the same form after it has been submitted
			jQuery('#action').val(<?php echo CJs::encodeJson(getRequest('action')) ?>);

			create_var('triggersForm', 'add_dependency', 1, true);
		}
	}

	function removeDependency(triggerid) {
		jQuery('#dependency_' + triggerid).remove();
		jQuery('#dependencies_' + triggerid).remove();
	}
</script>
