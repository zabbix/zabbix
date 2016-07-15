<script type="text/x-jquery-tmpl" id="tagRow">
<?=
	(new CRow([
		(new CTextBox('tags[#{rowNum}][tag]', '', false, 255))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAttribute('placeholder', _('tag')),
		(new CTextBox('tags[#{rowNum}][value]', '', false, 255))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
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

		$('input[name=recovery_mode]').change(function() {
			changeRecoveryMode();
		});

		function changeRecoveryMode() {
			// Used textarea selector by ID because there are ID duplicates on page.
			if ($('input[name=recovery_mode]:checked').val() == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>
					|| $('input[name=recovery_mode_name]:checked').val() == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>) {
				$('#recovery_expression_row, .recovery_expression_constructor_row').show();
				$('#expression_row').find('label').html('<?= _('Problem expression') ?>');
			}
			else {
				$('#recovery_expression_row, .recovery_expression_constructor_row').hide();
				$('#expression_row').find('label').html('<?= _('Expression') ?>');
			}
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
