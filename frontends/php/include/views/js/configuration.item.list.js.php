<script type="text/javascript">
	jQuery(function() {
		// disable the status filter when using the state filter
		jQuery('#filter_state').change(function(event, saveValue) {
			var stateObj = jQuery(this),
				statusObj = jQuery('#filter_status'),
				saveValue = (saveValue === undefined) ? true : saveValue;

			if (stateObj.val() == -1) {
				// restore the last remembered status filter value
				if (statusObj.prop('disabled') && statusObj.data('last-value') !== undefined) {
					statusObj.val(statusObj.data('last-value'));
				}
				statusObj.prop('disabled', false);
			}
			else {
				// remember the last status filter value
				if (!statusObj.prop('disabled') && saveValue) {
					statusObj.data('last-value', statusObj.val());
				}
				statusObj.prop('disabled', true).val(<?php echo ITEM_STATUS_ACTIVE ?>);
			}
		})
		.trigger('change', false);
	});
</script>

<script type="text/x-jquery-tmpl" id="tmpl_expressions_list_row">
<?=
	(new CRow([
		(new CCol([
			(new CDiv())
				->addClass(ZBX_STYLE_DRAG_ICON)
				->addStyle('top: 0px;'),
			(new CSpan())->addClass('ui-icon ui-icon-arrowthick-2-n-s move '.ZBX_STYLE_TD_DRAG_ICON)
		]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
		(new CDiv('#{expression}'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
		new CDiv('#{type_label}'),
		(new CCol([
			(new CVar('expressions[][value]', '#{expression}')),
			(new CVar('expressions[][type]', '#{type}')),
			(new CButton(null, _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
		]))->addClass(ZBX_STYLE_NOWRAP)
	]))
		->addClass('sortable form_row')
?>
</script>

<script type="text/x-jquery-tmpl" id="tmpl_expressions_part_list_row">
<?=
	(new CRow([
		(new CDiv('#{keyword}'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
		new CDiv('#{type_label}'),
		(new CCol([
			(new CVar('keys[][value]', '#{keyword}')),
			(new CVar('keys[][type]', '#{type_label}')),
			(new CButton(null, _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
		]))->addClass(ZBX_STYLE_NOWRAP)
	]))
		->addClass('form_row')
?>
</script>
