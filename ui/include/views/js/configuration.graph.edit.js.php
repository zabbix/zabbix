<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="tmpl-item-row-<?= GRAPH_TYPE_NORMAL ?>">
	<tr id="items_#{number}" class="graph-item">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?= GRAPH_ITEM_SIMPLE ?>">
			<input type="hidden" id="items_#{number}_calc_fnc" name="items[#{number}][calc_fnc]" value="#{calc_fnc}">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
		</td>

		<!-- row number -->
		<td>
			<span class="<?= ZBX_STYLE_LIST_NUMBERED_ITEM ?>">:</span>
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
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_ALL => _('all'),
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max')
					]))
					->setReadonly($readonly)
			?>
		</td>

		<!-- drawtype -->
		<td>
			<?= (new CSelect('items[#{number}][drawtype]'))
					->setValue('#{drawtype}')
					->addOptions(CSelect::createOptionsFromArray($graph_item_drawtypes))
					->setReadonly($readonly)
			?>
		</td>

		<!-- yaxisside -->
		<td>
			<?= (new CSelect('items[#{number}][yaxisside]'))
					->setValue('#{yaxisside}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_YAXIS_SIDE_LEFT => _('Left'),
						GRAPH_YAXIS_SIDE_RIGHT => _('Right')
					]))
					->setReadonly($readonly)
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
	<tr id="items_#{number}" class="graph-item">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?= GRAPH_ITEM_SIMPLE ?>">
			<input type="hidden" id="items_#{number}_calc_fnc" name="items[#{number}][calc_fnc]" value="#{calc_fnc}">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
		</td>

		<!-- row number -->
		<td>
			<span class="<?= ZBX_STYLE_LIST_NUMBERED_ITEM ?>">:</span>
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
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max')
					]))
					->setReadonly($readonly)
			?>
		</td>

		<!-- yaxisside -->
		<td>
			<?= (new CSelect('items[#{number}][yaxisside]'))
					->setValue('#{yaxisside}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_YAXIS_SIDE_LEFT => _('Left'),
						GRAPH_YAXIS_SIDE_RIGHT => _('Right')
					]))
					->setReadonly($readonly)
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
	<tr id="items_#{number}" class="graph-item">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="#{type}">
			<input type="hidden" id="items_#{number}_calc_fnc" name="items[#{number}][calc_fnc]" value="#{calc_fnc}">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="<?= GRAPH_ITEM_DRAWTYPE_LINE ?>">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="<?= GRAPH_YAXIS_SIDE_LEFT ?>">
		</td>

		<!-- row number -->
		<td>
			<span class="<?= ZBX_STYLE_LIST_NUMBERED_ITEM ?>">:</span>
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
			<?= (new CSelect('items[#{number}][type]'))
					->setValue('#{type}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_ITEM_SIMPLE =>_('Simple'),
						GRAPH_ITEM_SUM =>_('Graph sum')
					]))
					->setReadonly($readonly)
			?>
		</td>

		<!-- function -->
		<td>
			<?= (new CSelect('items[#{number}][calc_fnc]'))
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max'),
						CALC_FNC_LST => _('last')
					]))
					->setReadonly($readonly)
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
	<tr id="items_#{number}" class="graph-item">
		<!-- icon + hidden -->
		<?php if ($readonly): ?>
			<td>
		<?php else: ?>
			<td class="<?= ZBX_STYLE_TD_DRAG_ICON ?>">
				<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<?php endif ?>
			<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
			<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
			<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
			<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="#{type}">
			<input type="hidden" id="items_#{number}_calc_fnc" name="items[#{number}][calc_fnc]" value="#{calc_fnc}">
			<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="<?= GRAPH_ITEM_DRAWTYPE_LINE ?>">
			<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="<?= GRAPH_YAXIS_SIDE_LEFT ?>">
		</td>

		<!-- row number -->
		<td>
			<span class="<?= ZBX_STYLE_LIST_NUMBERED_ITEM ?>">:</span>
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
			<?= (new CSelect('items[#{number}][type]'))
					->setValue('#{type}')
					->addOptions(CSelect::createOptionsFromArray([
						GRAPH_ITEM_SIMPLE => _('Simple'),
						GRAPH_ITEM_SUM => _('Graph sum')
					]))
					->setReadonly($readonly)
			?>
		</td>

		<!-- function -->
		<td>
			<?= (new CSelect('items[#{number}][calc_fnc]'))
					->setValue('#{calc_fnc}')
					->addOptions(CSelect::createOptionsFromArray([
						CALC_FNC_MIN => _('min'),
						CALC_FNC_AVG => _('avg'),
						CALC_FNC_MAX => _('max'),
						CALC_FNC_LST => _('last')
					]))
					->setReadonly($readonly)
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
		context: null,
		parent_discoveryid: null,

		init({form_name, theme_colors, graphs, items, context, parent_discoveryid}) {
			this.form_name = form_name;
			colorPalette.setThemeColors(theme_colors);
			this.graphs = graphs;
			this.context = context;
			this.is_discovery = parent_discoveryid !== null;

			items.forEach((item, i) => {
				item.number = i;
				item.name = item.host + '<?= NAME_DELIMITER ?>' + item.name;

				this.loadItem(item);
			});

			$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
				const $panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

				if ($panel.attr('id') === 'previewTab') {
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
					src.setArgument('resolve_macros', this.context === 'template' ? 0 : 1);

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

						if ($('#ymin_type').val() == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>) {
							const ymin_item_data = $('#ymin_itemid').multiSelect('getData');

							if (ymin_item_data.length) {
								src.setArgument('ymin_itemid', ymin_item_data[0]['id']);
							}
						}

						if ($('#ymax_type').val() == <?= GRAPH_YAXIS_TYPE_ITEM_VALUE ?>) {
							const ymax_item_data = $('#ymax_itemid').multiSelect('getData');

							if (ymax_item_data.length) {
								src.setArgument('ymax_itemid', ymax_item_data[0]['id']);
							}
						}

						src.setArgument('showworkperiod', $('#show_work_period').is(':checked') ? 1 : 0);
						src.setArgument('showtriggers', $('#show_triggers').is(':checked') ? 1 : 0);
					}

					$('#itemsTable tbody tr.graph-item').each((i, node) => {
						const short_fmt = [];

						$(node).find('*[name]').each((_, input) => {
							if (!$.isEmptyObject(input) && input.name != null) {
								const regex = /items\[\d+\]\[([a-zA-Z0-9\-\_\.]+)\]/;
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
				const size = $('#itemsTable tbody tr.graph-item').length;

				for (let i = 0; i < size; i++) {
					$('#items_' + i + '_color')
						.removeAttr('onchange')
						.prop('readonly', true);
					$('#lbl_items_' + i + '_color')
						.removeAttr('onclick')
						.prop('readonly', true);
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

			new CSortable(document.querySelector('#itemsTable tbody'), {
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.graphs.readonly
			})
				.on(CSortable.EVENT_SORT, this.recalculateSortOrder);

			!this.graphs.readonly && this.rewriteNameLinks();

			this.initPopupListeners();
		},

		loadItem(item) {
			const itemTpl = new Template($('#tmpl-item-row-' + this.graphs.graphtype).html());
			const $row = $(itemTpl.evaluate(item));

			$('#itemButtonsRow').before($row);
			$row.find('.<?= ZBX_STYLE_COLOR_PICKER ?> input').colorpicker();

			!this.graphs.readonly && this.rewriteNameLinks();
		},

		/**
		 * @see init.js add.popup event
		 */
		addPopupValues(list) {
			if (!isset('object', list) || list.object != 'itemid') {
				return false;
			}

			const form = document.getElementsByName(this.form_name)[0];
			const itemTpl = new Template($('#tmpl-item-row-' + this.graphs.graphtype).html());

			for (let i = 0; i < list.values.length; i++) {
				const used_colors = [];

				for (const color of form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
					if (color.value !== '') {
						used_colors.push(color.value);
					}
				}

				const number = $('#itemsTable tbody tr.graph-item').length;
				const item = {
					number: number,
					gitemid: null,
					itemid: list.values[i].itemid,
					calc_fnc: null,
					drawtype: 0,
					yaxisside: 0,
					sortorder: number,
					flags: (typeof list.values[i].flags === 'undefined') ? 0 : list.values[i].flags,
					color: colorPalette.getNextColor(used_colors),
					name: list.values[i].name
				};
				const $row = $(itemTpl.evaluate(item));

				$('#itemButtonsRow').before($row);
				$row.find('#items_' + number + '_calc_fnc').val('<?= CALC_FNC_AVG ?>');
				$(`#items_${number}_color`).colorpicker();
			}

			!this.graphs.readonly && this.rewriteNameLinks();
		},

		getOnlyHostParam() {
			return this.graphs.is_template
				? {only_hostid: this.graphs.hostid}
				: {real_hosts: '1', hostid: this.graphs.hostid};
		},

		rewriteNameLinks() {
			const size = $('#itemsTable tbody tr.graph-item').length;

			for (let i = 0; i < size; i++) {
				const parameters = {
					srcfld1: 'itemid',
					srcfld2: 'name',
					dstfrm: this.form_name,
					dstfld1: 'items_' + i + '_itemid',
					dstfld2: 'items_' + i + '_name',
					numeric: 1,
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
					'{dialogue_class: "modal-popup-generic", trigger_element: this.parentNode});'
				);
			}
		},

		removeItem(obj) {
			const number = $(obj).data('remove');

			$('#items_' + number).find('*').remove();
			$('#items_' + number).remove();

			this.recalculateSortOrder();
		},

		recalculateSortOrder() {
			let i = 0;

			// Rewrite IDs, set "tmp" prefix.
			$('#itemsTable tbody tr.graph-item').find('*[id]').each(function() {
				const $obj = $(this);

				$obj.attr('id', 'tmp' + $obj.attr('id'));
			});

			$('#itemsTable tbody tr.graph-item').each(function() {
				const $obj = $(this);

				$obj.attr('id', 'tmp' + $obj.attr('id'));
			});

			for (const [index, row] of document.querySelectorAll('#itemsTable tbody tr.graph-item').entries()) {
				row.id = row.id.substring(3).replace(/\d+/, `${index}`);

				row.querySelectorAll('[id]').forEach(element => {
					element.id = element.id.substring(3).replace(/\d+/, `${index}`);

					if (element.id.includes('sortorder')) {
						element.value = index;
					}
				});

				row.querySelectorAll('[name]').forEach(element => {
					element.name = element.name.replace(/\d+/, `${index}`);
				});
			}

			$('#itemsTable tbody tr.graph-item').each(function() {
				// Set remove number.
				$('#items_' + i + '_remove').data('remove', i);

				i++;
			});

			!view.graphs.readonly && view.rewriteNameLinks();
		},

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: ({data, event}) => {
					if (data.submit.success.action === 'delete') {
						const url = new URL(this.is_discovery ? 'host_discovery.php' : 'graphs.php', location.href);

						url.searchParams.set('context', this.context);

						event.setRedirectUrl(url.href);
					}
					else {
						this.refresh();
					}
				}
			});
		}
	};
</script>
