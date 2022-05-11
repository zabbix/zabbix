<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	jQuery(function($) {
		var	editableTable = function(elm, tmpl) {
			var table = $(elm),
				row_template = new Template($(tmpl).html()),
				insert_point = table.find('tbody tr[data-insert-point]'),
				row_index = 0,
				table_row_class = 'editable_table_row';

			table.sortable({
				disabled: true,
				items: 'tbody tr.sortable',
				axis: 'y',
				containment: 'parent',
				cursor: 'grabbing',
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
				},
				update: function() {
					let i = 0;

					table.find('.' + table_row_class).each(function() {
						$(this).find('[name*="sortorder"]').val(i++);
					});
				}
			});

			table.on('click', '[data-row-action]', function(e) {
				e.preventDefault();

				switch ($(e.currentTarget).data('row-action')) {
					case 'remove_row':
						removeRow($(e.currentTarget).closest('.' + table_row_class));
						break;

					case 'add_row':
						var row_data = $(e.currentTarget).data('values'),
							new_row = addRow(row_data || {});

						if (!row_data) {
							new_row.find('[type="text"]').val('');
						}
						break;
				}
			});

			/**
			 * Enable or disable table rows sorting according to rows count. At least 2 rows should exists to be able
			 * sort rows using drag and drop.
			 */
			function setSortableState() {
				const allow_sort = (table.find('.' + table_row_class).length < 2);

				table.sortable('option', 'disabled', allow_sort);
			}

			/**
			 * Add table row. Returns new added row DOM node.
			 *
			 * @param {object} values  Object with data for added row.
			 *
			 * @return {object}
			 */
			function addRow(values) {
				row_index += 1;

				if (typeof values.sortorder === 'undefined') {
					let row_count = table.find('.' + table_row_class).length;

					values.sortorder = row_count++;
				}

				values.index = row_index;

				var new_row = $(row_template.evaluate(values))
					.addClass(table_row_class)
					.addClass('sortable')
					.data('values', values)
					.insertBefore(insert_point);

				setSortableState();

				return new_row;
			}

			/**
			 * Add multiple rows to table.
			 *
			 * @param {array} rows_values  Array of objects for every added row.
			 */
			function addRows(rows_values) {
				$.each(rows_values, function(index, values) {
					addRow(values);
				});
			}

			/**
			 * Remove table row.
			 *
			 * @param {object} row_node  Table row DOM node to be removed.
			 */
			function removeRow(row_node) {
				row_node.remove();
				setSortableState();

				const $rows = table.find('.' + table_row_class);

				if ($rows.length > 0) {
					let i = 0;

					$rows.each(function() {
						$(this).find('[name*="sortorder"]').val(i++);
					});
				}
			}

			return {
				addRow: function(values) {
					return addRow(values);
				},
				addRows: function(rows_values) {
					addRows(rows_values);
					return table;
				},
				removeRow: function(row_node) {
					removeRow(row_node);
				},
				getTableRows: function() {
					return table.find('.' + table_row_class);
				}
			};
		};

		var $data_sortable_table = $('[data-sortable-pairs-table]');

		if ($data_sortable_table.length === 0) {
			const headers_elem = document.querySelector('#headers_pairs');

			if (!headers_elem) {
				return false;
			}

			let obj = headers_elem;
			if (headers_elem.tagName === 'SPAN') {
				obj = headers_elem.originalObject;
			}

			$data_sortable_table = $(obj);
		}

		$data_sortable_table.each(function() {
			var t = $(this),
				table = t.find('table'),
				data = JSON.parse(t.find('[type="text/json"]').text()),
				template = t.find('[type="text/x-jquery-tmpl"]'),
				container = new editableTable(table, template);

			container.addRows(data);

			if (t.data('sortable-pairs-table') != 1) {
				table.sortable('option', 'disabled', true);
			}

			t.data('editableTable', container);
		});
	});
</script>
