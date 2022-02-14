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

<script type="text/x-jquery-tmpl" id="tmpl-item-row-<?= GRAPH_TYPE_NORMAL ?>">
	<tr id="items_#{number}" class="sortable">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
				<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?= GRAPH_ITEM_SIMPLE ?>">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
		</td>

		<!-- row number -->
		<td>
			<span id="items_#{number}_number" class="items_number">#{number_nr}:</span>
		</td>

		<!-- name -->
		<td>
			<?php if ($readonly): ?>
				<span id="items_#{number}_name">#{name}</span>
			<?php else: ?>
				<a href="javascript:void(0)"><span id="items_#{number}_name">#{name}</span></a>
			<?php endif ?>
		</td>

		<!-- function -->
		<td>
			<?= (new CSelect('items[#{number}][calc_fnc]'))
					->setId('items_#{number}_calc_fnc')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_ALL =>_('all'),
						CALC_FNC_MIN =>_('min'),
						CALC_FNC_AVG =>_('avg'),
						CALC_FNC_MAX =>_('max')
					]))
			?>
		</td>

		<!-- drawtype -->
		<td>
			<?= (new CSelect('items[#{number}][drawtype]'))
					->addOptions(CSelect::createOptionsFromArray($graph_item_drawtypes))
			?>
		</td>

		<!-- yaxisside -->
		<td>
			<?= (new CSelect('items[#{number}][yaxisside]'))
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_YAXIS_SIDE_LEFT =>_('Left'),
						GRAPH_YAXIS_SIDE_RIGHT =>_('Right')
					]))
			?>
		</td>

		<td>
			<?= (new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			?>
		</td>

		<?php if (!$readonly): ?>
			<td class="<?= ZBX_STYLE_NOWRAP ?>">
				<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" id="items_#{number}_remove" data-remove="#{number}" onclick="view.removeItem(this);"><?= _('Remove') ?></button>
			</td>
		<?php endif ?>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="tmpl-item-row-<?= GRAPH_TYPE_STACKED ?>">
	<tr id="items_#{number}" class="sortable">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
				<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?= GRAPH_ITEM_SIMPLE ?>">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
		</td>

		<!-- row number -->
		<td>
			<span id="items_#{number}_number" class="items_number">#{number_nr}:</span>
		</td>

		<!-- name -->
		<td>
			<?php if ($readonly): ?>
				<span id="items_#{number}_name">#{name}</span>
			<?php else: ?>
				<a href="javascript:void(0)"><span id="items_#{number}_name">#{name}</span></a>
			<?php endif ?>
		</td>

		<!-- function -->
		<td>
			<?= (new CSelect('items[#{number}][calc_fnc]'))
				->setId('items_#{number}_calc_fnc')
				->addOptions(CSelect::createOptionsFromArray([
					CALC_FNC_MIN =>_('min'),
					CALC_FNC_AVG =>_('avg'),
					CALC_FNC_MAX =>_('max')
				]))
			?>
		</td>

		<!-- yaxisside -->
		<td>
			<?= (new CSelect('items[#{number}][yaxisside]'))->addOptions(CSelect::createOptionsFromArray([
					GRAPH_YAXIS_SIDE_LEFT =>_('Left'),
					GRAPH_YAXIS_SIDE_RIGHT =>_('Right')
				]))
			?>
		</td>

		<td>
			<?= (new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			?>
		</td>

		<?php if (!$readonly): ?>
			<td class="<?= ZBX_STYLE_NOWRAP ?>">
				<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" id="items_#{number}_remove" data-remove="#{number}" onclick="view.removeItem(this);"><?= _('Remove') ?></button>
			</td>
		<?php endif ?>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="tmpl-item-row-<?= GRAPH_TYPE_PIE ?>">
	<tr id="items_#{number}" class="sortable">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
				<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?= GRAPH_ITEM_SIMPLE ?>">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
		</td>

		<!-- row number -->
		<td>
			<span id="items_#{number}_number" class="items_number">#{number_nr}:</span>
		</td>

		<!-- name -->
		<td>
			<?php if ($readonly): ?>
				<span id="items_#{number}_name">#{name}</span>
			<?php else: ?>
				<a href="javascript:void(0)"><span id="items_#{number}_name">#{name}</span></a>
			<?php endif ?>
		</td>

		<!-- type -->
		<td>
			<?= (new CSelect('items[#{number}][type]'))->addOptions(CSelect::createOptionsFromArray([
					GRAPH_ITEM_SIMPLE =>_('Simple'),
					GRAPH_ITEM_SUM =>_('Graph sum')
				]))
			?>
		</td>

		<!-- function -->
		<td>
			<?= (new CSelect('items[#{number}][calc_fnc]'))
				->setId('items_#{number}_calc_fnc')
				->addOptions(CSelect::createOptionsFromArray([
					CALC_FNC_MIN =>_('min'),
					CALC_FNC_AVG =>_('avg'),
					CALC_FNC_MAX =>_('max'),
					CALC_FNC_LST =>_('last')
				]))
			?>
		</td>

		<td>
			<?= (new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			?>
		</td>

		<?php if (!$readonly): ?>
			<td class="<?= ZBX_STYLE_NOWRAP ?>">
				<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" id="items_#{number}_remove" data-remove="#{number}" onclick="view.removeItem(this);"><?= _('Remove') ?></button>
			</td>
		<?php endif ?>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="tmpl-item-row-<?= GRAPH_TYPE_EXPLODED ?>">
	<tr id="items_#{number}" class="sortable">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
				<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?= GRAPH_ITEM_SIMPLE ?>">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
		</td>

		<!-- row number -->
		<td>
			<span id="items_#{number}_number" class="items_number">#{number_nr}:</span>
		</td>

		<!-- name -->
		<td>
			<?php if ($readonly): ?>
				<span id="items_#{number}_name">#{name}</span>
			<?php else: ?>
				<a href="javascript:void(0)"><span id="items_#{number}_name">#{name}</span></a>
			<?php endif ?>
		</td>

		<!-- type -->
		<td>
			<?= (new CSelect('items[#{number}][type]'))->addOptions(CSelect::createOptionsFromArray([
					GRAPH_ITEM_SIMPLE =>_('Simple'),
					GRAPH_ITEM_SUM =>_('Graph sum')
				]))
			?>
		</td>

		<!-- function -->
		<td>
			<?= (new CSelect('items[#{number}][calc_fnc]'))
				->setId('items_#{number}_calc_fnc')
				->addOptions(CSelect::createOptionsFromArray([
					CALC_FNC_MIN =>_('min'),
					CALC_FNC_AVG =>_('avg'),
					CALC_FNC_MAX =>_('max'),
					CALC_FNC_LST =>_('last')
				]))
			?>
		</td>

		<td>
			<?= (new CColor('items[#{number}][color]', '#{color}', 'items_#{number}_color'))
					->appendColorPickerJs(false)
			?>
		</td>

		<?php if (!$readonly): ?>
			<td class="<?= ZBX_STYLE_NOWRAP ?>">
				<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" id="items_#{number}_remove" data-remove="#{number}" onclick="view.removeItem(this);"><?= _('Remove') ?></button>
			</td>
		<?php endif ?>
	</tr>
</script>

<script>
	const view = {
		form_name: null,
		graphs: null,

		init({form_name, theme_colors, graphs, items}) {
			this.form_name = form_name;
			colorPalette.setThemeColors(theme_colors);
			this.graphs = graphs;

			for (let i = 0; i < items.length; i++) {
				const name = items[i].host + '<?= NAME_DELIMITER ?>' + items[i].name;

				this.loadItem(i, items[i].gitemid, items[i].itemid, name, items[i].type, items[i].calc_fnc,
					items[i].drawtype, items[i].yaxisside, items[i].color, items[i].flags
				);
			}

			$('#tabs').on('tabsactivate', (event, ui) => {
				if (ui.newPanel.attr('id') === 'previewTab') {
					const $preview_chart = $('#previewChart');
					const src = new Curl('chart3.php');

					if ($preview_chart.find('.is-loading').length) {
						return false;
					}

					src.setArgument('period', '3600');
					src.setArgument('name', $('#name').val());
					src.setArgument('width', $('#width').val());
					src.setArgument('height', $('#height').val());
					src.setArgument('graphtype', $('#graphtype').val());
					src.setArgument('legend', $('#show_legend').is(':checked') ? 1 : 0);

					if (this.graphs.graphtype == <?= GRAPH_TYPE_PIE ?>
							|| this.graphs.graphtype == <?= GRAPH_TYPE_EXPLODED ?>) {
						src.setPath('chart7.php');
						src.setArgument('graph3d', $('#show_3d').is(':checked') ? 1 : 0);
					}
					else {
						if (this.graphs.graphtype == <?= GRAPH_TYPE_NORMAL ?>) {
							src.setArgument('percent_left', $('#percent_left').val());
							src.setArgument('percent_right', $('#percent_right').val());
						}
						src.setArgument('ymin_type', $('#ymin_type').val());
						src.setArgument('ymax_type', $('#ymax_type').val());
						src.setArgument('yaxismin', $('#yaxismin').val());
						src.setArgument('yaxismax', $('#yaxismax').val());
						src.setArgument('ymin_itemid', $('#ymin_itemid').val());
						src.setArgument('ymax_itemid', $('#ymax_itemid').val());
						src.setArgument('showworkperiod', $('#show_work_period').is(':checked') ? 1 : 0);
						src.setArgument('showtriggers', $('#show_triggers').is(':checked') ? 1 : 0);
					}

					$('#itemsTable tr.sortable').each((i, node) => {
						const short_fmt = [];

						$(node).find('*[name]').each((_, input) => {
							if (!$.isEmptyObject(input) && input.name != null) {
								const regex = /items\[[\d+]\]\[([a-zA-Z0-9\-\_\.]+)\]/;
								const name = input.name.match(regex);

								short_fmt.push((name[1]).substr(0, 2) + ':' + input.value);
							}
						});

						src.setArgument('i[' + i + ']', short_fmt.join(','));
					});

					const $image = $('img', $preview_chart);

					if ($image.length != 0) {
						$image.remove();
					}

					$preview_chart.append($('<div>', {css: {'position': 'relative', 'min-height': '50px'}})
						.addClass('is-loading'));

					$('<img>')
						.attr('src', src.getUrl())
						.on('load', function() {
							$preview_chart.html($(this));
						});
				}
			});

			if (this.graphs.readonly) {
				$('#itemsTable').sortable({disabled: true}).find('input').prop('readonly', true);
				$('z-select', '#itemsTable').prop('disabled', true);

				const size = $('#itemsTable tr.sortable').length;

				for (let i = 0; i < size; i++) {
					$('#items_' + i + '_color').removeAttr('onchange');
					$('#lbl_items_' + i + '_color').removeAttr('onclick');
				}
			}

			// Y axis min clean unused fields.
			$('#ymin_type').change(function() {
				switch ($(this).val()) {
					case '<?= GRAPH_YAXIS_TYPE_CALCULATED ?>':
						$('#yaxismin').val('');
						$('#ymin_name').val('');
						$('#ymin_itemid').val('0');
						break;

					case '<?= GRAPH_YAXIS_TYPE_FIXED ?>':
						$('#ymin_name').val('');
						$('#ymin_itemid').val('0');
						break;

					default:
						$('#yaxismin').val('');
				}

				$('form[name="' + view.form_name + '"]').submit();
			});

			// Y axis max clean unused fields.
			$('#ymax_type').change(function() {
				switch ($(this).val()) {
					case '<?= GRAPH_YAXIS_TYPE_CALCULATED ?>':
						$('#yaxismax').val('');
						$('#ymax_name').val('');
						$('#ymax_itemid').val('0');
						break;

					case '<?= GRAPH_YAXIS_TYPE_FIXED ?>':
						$('#ymax_name').val('');
						$('#ymax_itemid').val('0');
						break;

					default:
						$('#yaxismax').val('');
				}

				$('form[name="' + view.form_name + '"]').submit();
			});

			$('#graphtype').change(() => {
				$('form[name="' + view.form_name + '"]').submit();
			});

			!this.graphs.readonly && this.initSortable();
		},

		loadItem(number, gitemid, itemid, name, type, calc_fnc, drawtype, yaxisside, color, flags) {
			const item = {
				number: number,
				number_nr: number + 1,
				gitemid: gitemid,
				itemid: itemid,
				calc_fnc: calc_fnc,
				color: color,
				sortorder: number,
				flags: flags,
				name: name
			};
			const itemTpl = new Template($('#tmpl-item-row-' + this.graphs.graphtype).html());
			const $row = $(itemTpl.evaluate(item));

			$row.find('#items_' + number + '_type').val(type);
			$row.find('#items_' + number + '_drawtype').val(drawtype);
			$row.find('#items_' + number + '_yaxisside').val(yaxisside);

			const $calc_fnc = $row.find('#items_' + number + '_calc_fnc');

			$calc_fnc.val(calc_fnc);

			if ($calc_fnc[0].selectedIndex < 0) {
				$calc_fnc[0].selectedIndex = 0;
			}

			$('#itemButtonsRow').before($row);
			$row.find('.<?= ZBX_STYLE_COLOR_PICKER ?> input').colorpicker();

			colorPalette.incrementNextColor();

			!this.graphs.readonly && this.rewriteNameLinks();
		},

		/**
		 * @see init.js add.popup event
		 */
		addPopupValues(list) {
			if (!isset('object', list) || list.object != 'itemid') {
				return false;
			}

			const itemTpl = new Template($('#tmpl-item-row-' + this.graphs.graphtype).html());

			for (let i = 0; i < list.values.length; i++) {
				const number = $('#itemsTable tr.sortable').length;
				const item = {
					number: number,
					number_nr: number + 1,
					gitemid: null,
					itemid: list.values[i].itemid,
					calc_fnc: null,
					drawtype: 0,
					yaxisside: 0,
					sortorder: number,
					flags: (typeof list.values[i].flags === 'undefined') ? 0 : list.values[i].flags,
					color: colorPalette.getNextColor(),
					name: list.values[i].name
				};
				const $row = $(itemTpl.evaluate(item));

				$('#itemButtonsRow').before($row);
				$row.find('#items_' + number + '_calc_fnc').val('<?= CALC_FNC_AVG ?>');
				$(`#items_${number}_color`).colorpicker();
			}

			if (!this.graphs.readonly) {
				this.activateSortable();
				this.rewriteNameLinks();
			}
		},

		getOnlyHostParam() {
			return this.graphs.is_template
				? {only_hostid: this.graphs.hostid}
				: {real_hosts: '1', hostid: this.graphs.hostid};
		},

		rewriteNameLinks() {
			const size = $('#itemsTable tr.sortable').length;

			for (let i = 0; i < size; i++) {
				const parameters = {
					srcfld1: 'itemid',
					srcfld2: 'name',
					dstfrm: this.form_name,
					dstfld1: 'items_' + i + '_itemid',
					dstfld2: 'items_' + i + '_name',
					numeric: 1,
					with_webitems: 1,
					writeonly: 1
				};

				if ($('#items_' + i + '_flags').val() == <?= ZBX_FLAG_DISCOVERY_PROTOTYPE ?>) {
					parameters['srctbl'] = 'item_prototypes',
					parameters['srcfld3'] = 'flags',
					parameters['dstfld3'] = 'items_' + i + '_flags',
					parameters['parent_discoveryid'] = this.graphs.parent_discoveryid;
				}
				else {
					parameters['srctbl'] = 'items';
				}

				if (this.graphs.normal_only !== '') {
					parameters['normal_only'] = '1';
				}

				if (!this.graphs.parent_discoveryid && this.graphs.hostid) {
					parameters['hostid'] = this.graphs.hostid;
				}

				$('#items_' + i + '_name').attr('onclick', 'PopUp("popup.generic", ' +
					'$.extend(' + JSON.stringify(parameters) + ', view.getOnlyHostParam()),' +
					'{dialogue_class: "modal-popup-generic", trigger_element: this});'
				);
			}
		},

		removeItem(obj) {
			const number = $(obj).data('remove');

			$('#items_' + number).find('*').remove();
			$('#items_' + number).remove();

			this.recalculateSortOrder();
			!this.graphs.readonly && this.activateSortable();
		},

		recalculateSortOrder() {
			let i = 0;

			// Rewrite IDs, set "tmp" prefix.
			$('#itemsTable tr.sortable').find('*[id]').each(function() {
				const $obj = $(this);

				$obj.attr('id', 'tmp' + $obj.attr('id'));
			});

			$('#itemsTable tr.sortable').each(function() {
				const $obj = $(this);

				$obj.attr('id', 'tmp' + $obj.attr('id'));
			});

			// Rewrite IDs to new order.
			$('#itemsTable tr.sortable').each(function() {
				const $obj = $(this);

				// Rewrite IDs in input fields.
				$obj.find('*[id]').each(function() {
					const $obj = $(this);
					const id = $obj.attr('id').substring(3);
					const part1 = id.substring(0, id.indexOf('items_') + 5);
					let part2 = id.substring(id.indexOf('items_') + 6);

					part2 = part2.substring(part2.indexOf('_') + 1);

					$obj.attr('id', part1 + '_' + i + '_' + part2);

					// Set sortorder.
					if (part2 === 'sortorder') {
						$obj.val(i);
					}
				});

				// Rewrite IDs in <tr>.
				const id = $obj.attr('id').substring(3);
				const part1 = id.substring(0, id.indexOf('items_') + 5);

				$obj.attr('id', part1 + '_' + i);

				i++;
			});

			i = 0;

			$('#itemsTable tr.sortable').each(function() {
				// Set row number.
				$('.items_number', this).text((i + 1) + ':');

				// Set remove number.
				$('#items_' + i + '_remove').data('remove', i);

				i++;
			});

			!view.graphs.readonly && view.rewriteNameLinks();
		},

		initSortable() {
			$('#itemsTable').sortable({
				disabled: ($('#itemsTable tr.sortable').length < 2),
				items: 'tbody tr.sortable',
				axis: 'y',
				containment: 'parent',
				cursor: 'grabbing',
				handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				tolerance: 'pointer',
				opacity: 0.6,
				update: this.recalculateSortOrder,
				helper: (e, ui) => {
					for (const td of ui.find('>td')) {
						const $td = $(td);
						$td.attr('width', $td.width())
					}

					// When dragging element on safari, it jumps out of the table.
					if (SF) {
						// Move back draggable element to proper position.
						ui.css('left', (ui.offset().left - 2) + 'px');
					}

					return ui;
				},
				stop: (e, ui) => {
					ui.item.find('>td').removeAttr('width');
				},
				start: (e, ui) => {
					$(ui.placeholder).height($(ui.helper).height());
				}
			});
		},

		activateSortable() {
			$('#itemsTable').sortable({disabled: ($('#itemsTable tr.sortable').length < 2)});
		},

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		refresh() {
			const url = new Curl('', false);
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php', false);
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};
</script>
