<?php

include dirname(__FILE__).'/common.item.edit.js.php';

$this->data['valueTypeVisibility'] = [];
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_units');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_trends');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_LOG, 'logtimefmt');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_LOG, 'row_logtimefmt');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'valuemapid');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_valuemap');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'valuemap_name');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_STR, 'row_inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_TEXT, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_TEXT, 'row_inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_FLOAT, 'row_inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'inventory_link');
zbx_subarray_push($this->data['valueTypeVisibility'], ITEM_VALUE_TYPE_UINT64, 'row_inventory_link');
?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		var editableTable = function (elm, tmpl, tmpl_defaults) {
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
			}).disableSelection();

			table.on('click', '[data-row-action]', function (e) {
				e.preventDefault();

				switch ($(e.currentTarget).data('row-action')) {
					case 'remove_row' :
						if (rows > 1) {
							rows -= 1;
							table.sortable('option', 'disabled', rows < 2);

							$(e.currentTarget).closest('.'+table_row_class).remove();
						}
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

			t.data('editableTable', et);
		});

		$('[data-action="parse_url"]').click(function() {
			var url = $(this).siblings('[name="url"]'),
				table = $('#query_fields_pairs').data('editableTable'),
				pos = url.val().indexOf('?');

			if (pos != -1) {
				var host = url.val().substring(0, pos),
					query = url.val().substring(pos + 1),
					parsed = [];

				$.each(query.split('&'), function(i, pair) {
					pair = pair.split('=', 2);
					parsed.push({'key': pair[0], 'value': pair[1]});
				});

				url.val(host);
				table.clearTable()
				table.addRows(parsed);
			}
		});

		function typeChangeHandler() {
			// selected item type
			var type = parseInt($('#type').val()),
				asterisk = '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>';

			$('#keyButton').prop('disabled',
				type != <?= ITEM_TYPE_ZABBIX ?>
					&& type != <?= ITEM_TYPE_ZABBIX_ACTIVE ?>
					&& type != <?= ITEM_TYPE_SIMPLE ?>
					&& type != <?= ITEM_TYPE_INTERNAL ?>
					&& type != <?= ITEM_TYPE_AGGREGATE ?>
					&& type != <?= ITEM_TYPE_DB_MONITOR ?>
					&& type != <?= ITEM_TYPE_SNMPTRAP ?>
					&& type != <?= ITEM_TYPE_JMX ?>
			)

			if ((type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>)) {
				$('label[for=username]').addClass(asterisk);
				$('input[name=username]').attr('aria-required', 'true');
			}
			else {
				$('label[for=username]').removeClass(asterisk);
				$('input[name=username]').removeAttr('aria-required');
			}
		}

		// field switchers
		<?php
		if (!empty($this->data['valueTypeVisibility'])) { ?>
			var valueTypeSwitcher = new CViewSwitcher('value_type', 'change',
				<?= zbx_jsvalue($this->data['valueTypeVisibility'], true) ?>);
		<?php } ?>

		$('#type').change(function() {
				typeChangeHandler();
			})
			.trigger('change');

		// Whenever non-numeric type is changed back to numeric type, set the default value in "trends" field.
		$('#value_type').on('focus', function () {
			old_value = $(this).val();
		}).change(function() {
			var new_value = $(this).val(),
				trends = $('#trends');

			if ((old_value == <?= ITEM_VALUE_TYPE_STR ?> || old_value == <?= ITEM_VALUE_TYPE_LOG ?>
					|| old_value == <?= ITEM_VALUE_TYPE_TEXT ?>)
					&& ((new_value == <?= ITEM_VALUE_TYPE_FLOAT ?>
					|| new_value == <?= ITEM_VALUE_TYPE_UINT64 ?>)
					&& trends.val() == 0)) {
				trends.val('<?= $this->data['trends_default'] ?>');
			}
		});
	});
</script>
