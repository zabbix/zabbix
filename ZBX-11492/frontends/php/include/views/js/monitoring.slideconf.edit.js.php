<script type="text/x-jquery-tmpl" id="screenRowTPL">
<tr class="sortable" id="slides_#{rowId}">
	<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
		<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<input id="slides_#{rowId}_screenid" name="slides[#{rowId}][screenid]" type="hidden" value="#{screenid}" />
		<input id="slides_#{rowId}_slideid" name="slides[#{rowId}][slideid]" type="hidden" value="" />
	</td>
	<td>
		<span class="rowNum" id="current_slide_#{rowId}">#{rowNum}</span>
	</td>
	<td>#{name}</td>
	<td>
		<input type="text" id="slides_#{rowId}_delay" name="slides[#{rowId}][delay]" placeholder="<?= CHtml::encode(_('default')); ?>" value="" maxlength="5" onchange="validateNumericBox(this, true, false);" style="text-align: right; width: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>px">
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" id="remove_#{rowId}" remove_slide="#{rowId}" onclick="removeSlide(this);"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
	<?= (new CRow([
			new CCol([
				(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
				(new CSpan('#{name}')),
			]),
			new CCol(
				(new CTag('ul', false, [
					new CTag('li', false, [
						(new CInput('radio', 'userGroups[#{usrgrpid}][permission]', PERM_READ))
							->setId('user_group_#{usrgrpid}_permission_'.PERM_READ),
						(new CTag('label', false, _('Read-only')))
							->setAttribute('for', 'user_group_#{usrgrpid}_permission_'.PERM_READ)
					]),
					new CTag('li', false, [
						(new CInput('radio', 'userGroups[#{usrgrpid}][permission]', PERM_READ_WRITE))
							->setId('user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE),
						(new CTag('label', false, _('Read-write')))
							->setAttribute('for', 'user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE)
					])
				]))->addClass('radio-segmented')
			),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('removeUserGroupShares("#{usrgrpid}");')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('user_group_shares_#{usrgrpid}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="user_row_tpl">
	<?= (new CRow([
			new CCol([
				(new CTextBox('users[#{id}][userid]', '#{id}'))->setAttribute('type', 'hidden'),
				(new CSpan('#{name}')),
			]),
			new CCol(
				(new CTag('ul', false, [
					new CTag('li', false, [
						(new CInput('radio', 'users[#{id}][permission]', PERM_READ))
							->setId('user_#{id}_permission_'.PERM_READ),
						(new CTag('label', false, _('Read-only')))
							->setAttribute('for', 'user_#{id}_permission_'.PERM_READ)
					]),
					new CTag('li', false, [
						(new CInput('radio', 'users[#{id}][permission]', PERM_READ_WRITE))
							->setId('user_#{id}_permission_'.PERM_READ_WRITE),
						(new CTag('label', false, _('Read-write')))
							->setAttribute('for', 'user_#{id}_permission_'.PERM_READ_WRITE)
					])
				]))->addClass('radio-segmented')
			),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('removeUserShares("#{id}");')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('user_shares_#{id}')
			->toString()
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#clone').click(function() {
			$('#slideshowid, #delete, #clone, #inaccessible_user').remove();
			$('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});

			$('#form').val('clone');

			// Switch to first tab so multiselect is visible and only then add data and resize.
			$('#slideTab').trigger('click');

			$('#multiselect_userid_wrapper').show();

			// Set current user as owner.
			$('#userid').multiSelect('addData', {
				'id': $('#current_user_userid').val(),
				'name': $('#current_user_fullname').val()
			});

			$('#name').focus();
		});
	});

	function removeSlide(obj) {
		var step = obj.getAttribute('remove_slide');

		jQuery('#slides_' + step).remove();
		jQuery('#slides_' + step + '_slideid').remove();
		jQuery('#slides_' + step + '_screenid').remove();
		jQuery('#slides_' + step + '_delay').remove();

		if (jQuery('#slideTable tr.sortable').length <= 1) {
			jQuery('#slideTable').sortable('disable');
		}

		recalculateSortOrder();
	}

	function recalculateSortOrder() {
		var i = 0;

		jQuery('#slideTable tr.sortable .rowNum').each(function() {
			var newStep = (i == 0) ? '0' : i,
				currentStep = jQuery(this).closest('tr').attr('id').split('_')[1];

			// rewrite ids to temp
			jQuery('#remove_' + currentStep).attr('id', 'tmp_remove_' + newStep);
			jQuery('#slides_' + currentStep).attr('id', 'tmp_slides_' + newStep);
			jQuery('#slides_' + currentStep + '_slideid').attr('id', 'tmp_slides_' + newStep + '_slideid');
			jQuery('#slides_' + currentStep + '_screenid').attr('id', 'tmp_slides_' + newStep + '_screenid');
			jQuery('#slides_' + currentStep + '_delay').attr('id', 'tmp_slides_' + newStep + '_delay');
			jQuery('#current_slide_' + currentStep).attr('id', 'tmp_current_slide_' + newStep);

			// set order number
			jQuery(this)
				.attr('new_slide', i)
				.text((i + 1) + ':');
			i++;
		});

		// rewrite ids in new order
		for (var n = 0; n < i; n++) {
			var newStep = jQuery('#tmp_current_slide_' + n).attr('new_slide');
			jQuery('#tmp_current_slide_' + n).removeAttr('new_slide');

			jQuery('#tmp_remove_' + n).attr('id', 'remove_' + newStep);
			jQuery('#tmp_slides_' + n).attr('id', 'slides_' + newStep);
			jQuery('#tmp_slides_' + n + '_slideid').attr('id', 'slides_' + newStep + '_slideid');
			jQuery('#tmp_slides_' + n + '_screenid').attr('id', 'slides_' + newStep + '_screenid');
			jQuery('#tmp_slides_' + n + '_delay').attr('id', 'slides_' + newStep + '_delay');

			jQuery('#slides_' + newStep + '_slideid').attr('name', 'slides[' + newStep + '][slideid]');
			jQuery('#slides_' + newStep + '_screenid').attr('name', 'slides[' + newStep + '][screenid]');
			jQuery('#slides_' + newStep + '_delay').attr('name', 'slides[' + newStep + '][delay]');
			jQuery('#remove_' + newStep)
				.attr('remove_slide', newStep)
				.attr('name', 'remove_' + newStep);

			// set new slide order position
			jQuery('#tmp_current_slide_' + n).attr('id', 'current_slide_' + newStep);
		}
	}

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var initSize = jQuery('#slideTable tr.sortable .rowNum').length,
			defaultDelay = jQuery('#delay').val(),
			i,
			value,
			tpl,
			container;

		for (i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}

			value = list.values[i];

			switch (list.object) {
				case 'screenid':
					value['rowId'] = jQuery('#slideTable tr.sortable .rowNum').length;
					value['rowNum'] = value['rowId'] + 1;
					value['rowDelay'] = defaultDelay;

					tpl = new Template(jQuery('#screenRowTPL').html());
					jQuery('#screenListFooter').before(tpl.evaluate(value));
					break;

				case 'usrgrpid':
					if (jQuery('#user_group_shares_' + value.usrgrpid).length) {
						continue;
					}

					if (typeof value.permission === 'undefined') {
						if (jQuery('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
							value.permission = <?= PERM_READ ?>;
						}
						else {
							value.permission = <?= PERM_READ_WRITE ?>;
						}
					}

					tpl = new Template(jQuery('#user_group_row_tpl').html());

					container = jQuery('#user_group_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_group_' + value.usrgrpid + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;

				case 'userid':
					if (jQuery('#user_shares_' + value.id).length) {
						continue;
					}

					if (typeof value.permission === 'undefined') {
						if (jQuery('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
							value.permission = <?= PERM_READ ?>;
						}
						else {
							value.permission = <?= PERM_READ_WRITE ?>;
						}
					}

					tpl = new Template(jQuery('#user_row_tpl').html());

					container = jQuery('#user_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_' + value.id + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;
			}
		}

		if (initSize < 2) {
			initSortable();
		}
	}

	function initSortable() {
		var slideTable = jQuery('#slideTable'),
			slideTableWidth = slideTable.width(),
			slideTableColumns = jQuery('#slideTable .header td'),
			slideTableColumnWidths = [];

		slideTableColumns.each(function() {
			slideTableColumnWidths[slideTableColumnWidths.length] = jQuery(this).width();
		});

		slideTable.sortable({
			disabled: (slideTable.find('tr.sortable').length < 2),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			create: function () {
				// force not to change table width
				slideTable.width(slideTableWidth);
			},
			helper: function(e, ui) {
				ui.children().each(function(i) {
					var td = jQuery(this);

					td.width(slideTableColumnWidths[i]);
				});

				// when dragging element on safari, it jumps out of the table on IE it moves about 4 pixels to right
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 4) + 'px');
				}

				slideTableColumns.each(function(i) {
					jQuery(this).width(slideTableColumnWidths[i]);
				});

				return ui;
			},
			start: function(e, ui) {
				jQuery(ui.placeholder).height(jQuery(ui.helper).height());
			}
		});
	}

	function removeUserGroupShares(usrgrpid) {
		jQuery('#user_group_shares_' + usrgrpid).remove();
	}

	function removeUserShares(userid) {
		jQuery('#user_shares_' + userid).remove();
	}

	jQuery(function() {
		initSortable();
	});
</script>
