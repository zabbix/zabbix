<script type="text/x-jquery-tmpl" id="delayFlexRow">
	<tr class="form_row">
		<td>
			<ul class="<?= ZBX_STYLE_RADIO_SEGMENTED ?>" id="delay_flex_#{rowNum}_type">
				<li>
					<input type="radio" id="delay_flex_#{rowNum}_type_0" name="delay_flex[#{rowNum}][type]" value="0" checked="checked">
					<label for="delay_flex_#{rowNum}_type_0"><?= _('Flexible') ?></label>
				</li><li>
					<input type="radio" id="delay_flex_#{rowNum}_type_1" name="delay_flex[#{rowNum}][type]" value="1">
					<label for="delay_flex_#{rowNum}_type_1"><?= _('Scheduling') ?></label>
				</li>
			</ul>
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_delay" name="delay_flex[#{rowNum}][delay]" maxlength="5" onchange="validateNumericBox(this, true, false);" placeholder="50" style="text-align: right;">
			<input type="text" id="delay_flex_#{rowNum}_schedule" name="delay_flex[#{rowNum}][schedule]" maxlength="255" placeholder="wd1-5h9-18" style="display: none;">
		</td>
		<td>
			<input type="text" id="delay_flex_#{rowNum}_period" name="delay_flex[#{rowNum}][period]" maxlength="255" placeholder="<?= ZBX_DEFAULT_INTERVAL ?>">
		</td>
		<td>
			<button type="button" id="delay_flex_#{rowNum}_remove" name="delay_flex[#{rowNum}][remove]" class="<?= ZBX_STYLE_BTN_LINK ?> element-table-remove"><?= _('Remove') ?></button>
		</td>
	</tr>
</script>
<script type="text/x-jquery-tmpl" id="preprocessing_steps_row">
	<?=
		(new CRow([
			(new CCol(
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CComboBox('preprocessing[#{rowNum}][type]', '', null, get_preprocessing_types())),
			(new CNumericBox('preprocessing[#{rowNum}][params][0]', '', 20, false, true))
				->setAttribute('placeholder', _('number')),
			(new CTextBox('preprocessing[#{rowNum}][params][1]'))
				->setAttribute('placeholder', _('output'))
				->addStyle('display: none;'),
			(new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('sortable')
			->toString()
	?>
	</script>
<script type="text/javascript">
	jQuery(function($) {
		$('#visible_type, #visible_interface').click(function() {
			// if no item type is selected, reset the interfaces to default
			if (!$('#visible_type').is(':checked')) {
				var itemInterfaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemInterfaceTypes[<?php echo CJs::encodeJson($data['initial_item_type']) ?>]);
			}
			else {
				$('#type').trigger('change');
			}
		});

		$('#type')
			.change(function() {
				// update the interface select with each item type change
				var itemInterfaceTypes = <?php echo CJs::encodeJson(itemTypeInterface()); ?>;
				organizeInterfaces(itemInterfaceTypes[parseInt(jQuery(this).val())]);
			})
			.trigger('change');

		$('#delayFlexTable').on('click', 'input[type="radio"]', function() {
			var rowNum = $(this).attr('id').split('_')[2];

			if ($(this).val() == <?= ITEM_DELAY_FLEX_TYPE_FLEXIBLE; ?>) {
				$('#delay_flex_' + rowNum + '_schedule').hide();
				$('#delay_flex_' + rowNum + '_delay').show();
				$('#delay_flex_' + rowNum + '_period').show();
			}
			else {
				$('#delay_flex_' + rowNum + '_delay').hide();
				$('#delay_flex_' + rowNum + '_period').hide();
				$('#delay_flex_' + rowNum + '_schedule').show();
			}
		});

		$('#delayFlexTable').dynamicRows({
			template: '#delayFlexRow'
		});

		var preproc_row_tpl = new Template($('#preprocessing_steps_row').html()),
			preprocessing = $('#preprocessing');

		preprocessing.sortable({
			disabled: (preprocessing.find('tr.sortable') < 2),
			items: 'tr.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			helper: function(e, ui) {
				ui.children().each(function() {
					var td = $(this);

					td.width(td.width());
				});

				return ui;
			},
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		preprocessing
			.delegate('.element-table-add', 'click', function() {
				var row = $(this).parent().parent();
				row.before(preproc_row_tpl.evaluate({rowNum: preprocessing.find('tr.sortable').length}));

				if (preprocessing.find('tr.sortable').length > 1) {
					preprocessing.sortable('enable');
				}
			})
			.delegate('.element-table-remove', 'click', function() {
				var row = $(this).parent().parent();
				row.remove();

				if (preprocessing.find('tr.sortable').length < 2) {
					preprocessing.sortable('disable');
				}
			})
			.delegate('select[name*="type"]', 'change', function() {
				var inputs = $(this).parent().parent().find('[name*="params"]');

				switch ($(this).val()) {
					case '<?= ZBX_PREPROC_MULTIPLIER ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', '<?= _('number') ?>')
							.attr('onchange', 'validateNumericBox(this, true, true);')
							.attr('style', 'text-align: right;');
						$(inputs[1]).hide();
						break;

					case '<?= ZBX_PREPROC_RTRIM ?>':
					case '<?= ZBX_PREPROC_LTRIM ?>':
					case '<?= ZBX_PREPROC_TRIM ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', '<?= _('list of characters') ?>')
							.removeAttr('onchange')
							.removeAttr('style');
						$(inputs[1]).hide();
						break;

					case '<?= ZBX_PREPROC_REGSUB ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', '<?= _('pattern') ?>')
							.removeAttr('onchange')
							.removeAttr('style');
						$(inputs[1]).show();
						break;

					case '<?= ZBX_PREPROC_BOOL2DEC ?>':
					case '<?= ZBX_PREPROC_OCT2DEC ?>':
					case '<?= ZBX_PREPROC_HEX2DEC ?>':
					case '<?= ZBX_PREPROC_DELTA_VALUE ?>':
					case '<?= ZBX_PREPROC_DELTA_SPEED ?>':
						$(inputs[0]).hide();
						$(inputs[1]).hide();
						break;
				}
			});
	});
</script>
