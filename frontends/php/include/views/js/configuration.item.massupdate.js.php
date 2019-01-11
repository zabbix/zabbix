<script type="text/x-jquery-tmpl" id="custom_intervals_row">
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
			<input type="text" id="delay_flex_#{rowNum}_delay" name="delay_flex[#{rowNum}][delay]" maxlength="255" placeholder="<?= ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT ?>">
			<input type="text" id="delay_flex_#{rowNum}_schedule" name="delay_flex[#{rowNum}][schedule]" maxlength="255" placeholder="<?= ZBX_ITEM_SCHEDULING_DEFAULT ?>" style="display: none;">
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
	<?php
		$preproc_types_cbbox = new CComboBox('preprocessing[#{rowNum}][type]', '');

		foreach (get_preprocessing_types() as $group) {
			$cb_group = new COptGroup($group['label']);

			foreach ($group['types'] as $type => $label) {
				$cb_group->addItem(new CComboItem($type, $label));
			}

			$preproc_types_cbbox->addItem($cb_group);
		}

		echo (new CRow([
			(new CCol(
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			$preproc_types_cbbox,
			(new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', _('number')),
			(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))->setAttribute('placeholder', _('output')),
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

		$('#custom_intervals').on('click', 'input[type="radio"]', function() {
			var rowNum = $(this).attr('id').split('_')[2];

			if ($(this).val() == <?= ITEM_DELAY_FLEXIBLE; ?>) {
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

		$('#custom_intervals').dynamicRows({
			template: '#custom_intervals_row'
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
			.on('click', '.element-table-add', function() {
				var row = $(this).parent().parent();
				row.before(preproc_row_tpl.evaluate({rowNum: preprocessing.find('tr.sortable').length}));

				if (preprocessing.find('tr.sortable').length > 1) {
					preprocessing.sortable('enable');
				}
			})
			.on('click', '.element-table-remove', function() {
				var row = $(this).parent().parent();
				row.remove();

				if (preprocessing.find('tr.sortable').length < 2) {
					preprocessing.sortable('disable');
				}
			})
			.on('change', 'select[name*="type"]', function() {
				var inputs = $(this).parent().parent().find('[name*="params"]');

				switch ($(this).val()) {
					case '<?= ZBX_PREPROC_MULTIPLIER ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', <?= CJs::encodeJson(_('number')) ?>);
						$(inputs[1]).hide();
						break;

					case '<?= ZBX_PREPROC_RTRIM ?>':
					case '<?= ZBX_PREPROC_LTRIM ?>':
					case '<?= ZBX_PREPROC_TRIM ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', <?= CJs::encodeJson(_('list of characters')) ?>);
						$(inputs[1]).hide();
						break;

					case '<?= ZBX_PREPROC_XPATH ?>':
					case '<?= ZBX_PREPROC_JSONPATH ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', <?= CJs::encodeJson(_('path')) ?>);
						$(inputs[1]).hide();
						break;

					case '<?= ZBX_PREPROC_REGSUB ?>':
						$(inputs[0])
							.show()
							.attr('placeholder', <?= CJs::encodeJson(_('pattern')) ?>);
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

			var $ = jQuery,
				editableTable = function (elm, tmpl, tmpl_defaults) {
					var table,
						row_template,
						row_default_values,
						insert_point,
						rows = 0,
						table_row_class = 'editable_table_row';

					table = $(elm);
					insert_point = table.find('tbody tr[data-insert-point]');
					row_template = new Template($(tmpl).html());
					row_default_values = tmpl_defaults;

					table.sortable({
						disabled: true,
						items: 'tbody tr.sortable',
						axis: 'y',
						containment: 'parent',
						cursor: 'move',
						handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
						tolerance: 'pointer',
						opacity: 0.6,
						helper: function(e, ui) {
							ui.children('td').each(function() {
								$(this).width($(this).width());
							});

							return ui;
						},
						start: function(e, ui) {
							// Fix placeholder not to change height while object is being dragged.
							$(ui.placeholder).height($(ui.helper).height());
						}
					});

					table.on('click', '[data-row-action]', function (e) {
						e.preventDefault();

						switch ($(e.currentTarget).data('row-action')) {
							case 'remove_row' :
								rows -= 1;
								table.sortable('option', 'disabled', rows < 2);

								$(e.currentTarget).closest('.'+table_row_class).remove();
								break;

							case 'add_row' :
								var row_data = $(e.currentTarget).data('values'),
									new_row = addRow($.extend({index: rows + 1}, row_data||{}));

								if (!row_data) {
									new_row.find('[type="text"]').val('');
								}
								break;
						}
					});

					function addRow(values) {
						rows += 1;
						table.sortable('option', 'disabled', rows < 2);

						return $(row_template.evaluate(values))
							.addClass(table_row_class)
							.addClass('sortable')
							.data('row-values', values)
							.insertBefore(insert_point);
					}

					function addRows(rows_values) {
						$.each(rows_values, function(index, values) {
							addRow($.extend({"index": index}, values));
						});
					}

					return {
						addRow: function(values) {
							return addRow(values);
						},
						addRows: function(rows_values) {
							addRows(rows_values);
							return table;
						},
						clearTable: function() {
							table.find('.'+table_row_class).remove();
							return table;
						}
					}
				};

		$('[data-sortable-pairs-table]').each(function() {
			var t = $(this),
				table = t.find('table'),
				data = JSON.parse(t.find('[type="text/json"]').text()),
				template = t.find('[type="text/x-jquery-tmpl"]'),
				et = new editableTable(table, template);

			et.addRows(data);

			if (t.data('sortable-pairs-table') != 1) {
				table.sortable('option', 'disabled', true);
			}

			t.data('editableTable', et);
		});
	});
</script>
