<script type="text/x-jquery-tmpl" id="itemTpl">
<tr id="items_#{number}" class="sortable">
	<!-- icon + hidden -->
	<td>
		<span class="ui-icon ui-icon-arrowthick-2-n-s move"></span>
		<input type="hidden" id="items_#{number}_gitemid" name="items[#{number}][gitemid]" value="#{gitemid}">
		<input type="hidden" id="items_#{number}_graphid" name="items[#{number}][graphid]" value="#{graphid}">
		<input type="hidden" id="items_#{number}_itemid" name="items[#{number}][itemid]" value="#{itemid}">
		<input type="hidden" id="items_#{number}_sortorder" name="items[#{number}][sortorder]" value="#{sortorder}">
		<input type="hidden" id="items_#{number}_flags" name="items[#{number}][flags]" value="#{flags}">
		<?php if ($this->data['graphtype'] != GRAPH_TYPE_PIE && $this->data['graphtype'] != GRAPH_TYPE_EXPLODED): ?>
			<input type="hidden" id="items_#{number}_type" name="items[#{number}][type]" value="<?php echo GRAPH_ITEM_SIMPLE; ?>">
		<?php endif; ?>
	</td>

	<!-- row number -->
	<td>
		<span id="items_#{number}_number" class="items_number">#{number_nr}:</span>
	</td>

	<!-- name -->
	<td>
		<span id="items_#{number}_name" class="link" onclick="">#{name}</span>
	</td>

	<!-- type -->
	<?php if ($this->data['graphtype'] == GRAPH_TYPE_PIE || $this->data['graphtype'] == GRAPH_TYPE_EXPLODED): ?>
		<td>
			<select id="items_#{number}_type" name="items[#{number}][type]" class="input select">
				<option value="<?php echo GRAPH_ITEM_SIMPLE; ?>"><?php echo _('Simple'); ?></option>
				<option value="<?php echo GRAPH_ITEM_SUM; ?>"><?php echo _('Graph sum'); ?></option>
			</select>
		</td>
	<?php endif; ?>

	<!-- function -->
	<td>
		<select id="items_#{number}_calc_fnc" name="items[#{number}][calc_fnc]" class="input select">
		<?php if ($this->data['graphtype'] == GRAPH_TYPE_PIE || $this->data['graphtype'] == GRAPH_TYPE_EXPLODED): ?>
			<option value="<?php echo CALC_FNC_MIN; ?>"><?php echo _('min'); ?></option>
			<option value="<?php echo CALC_FNC_AVG; ?>"><?php echo _('avg'); ?></option>
			<option value="<?php echo CALC_FNC_MAX; ?>"><?php echo _('max'); ?></option>
			<option value="<?php echo CALC_FNC_LST; ?>"><?php echo _('last'); ?></option>
		<?php else: ?>
			<?php if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL): ?>
				<option value="<?php echo CALC_FNC_ALL; ?>"><?php echo _('all'); ?></option>
			<?php endif; ?>
				<option value="<?php echo CALC_FNC_MIN; ?>"><?php echo _('min'); ?></option>
				<option value="<?php echo CALC_FNC_AVG; ?>"><?php echo _('avg'); ?></option>
				<option value="<?php echo CALC_FNC_MAX; ?>"><?php echo _('max'); ?></option>
		<?php endif; ?>
		</select>
	</td>

	<!-- drawtype -->
	<?php if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL): ?>
		<td>
			<select id="items_#{number}_drawtype" name="items[#{number}][drawtype]" class="input select">
			<?php foreach (graph_item_drawtypes() as $drawtype): ?>
				<option value="<?php echo $drawtype; ?>"><?php echo graph_item_drawtype2str($drawtype); ?></option>
			<?php endforeach; ?>
			</select>
		</td>
	<?php else: ?>
		<input type="hidden" id="items_#{number}_drawtype" name="items[#{number}][drawtype]" value="#{drawtype}">
	<?php endif; ?>

	<!-- yaxisside -->
	<?php if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL || $this->data['graphtype'] == GRAPH_TYPE_STACKED): ?>
		<td>
			<select id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" class="input select">
				<option value="<?php echo GRAPH_YAXIS_SIDE_LEFT; ?>"><?php echo _('Left'); ?></option>
				<option value="<?php echo GRAPH_YAXIS_SIDE_RIGHT; ?>"><?php echo _('Right'); ?></option>
			</select>
		</td>
	<?php else: ?>
		<input type="hidden" id="items_#{number}_yaxisside" name="items[#{number}][yaxisside]" value="#{yaxisside}">
	<?php endif; ?>

	<!-- color -->
	<td>
		<input type="text" id="items_#{number}_color" name="items[#{number}][color]" class="input text colorpicker"
			onchange="javascript: set_color_by_name('items_#{number}_color', this.value);" maxlength="6" size="7" value="">
		<div id="lbl_items_#{number}_color" name="lbl_items[#{number}][color]" title="#" class="pointer colorpickerLabel"
			onclick="javascript: show_color_picker('items_#{number}_color');">&nbsp;&nbsp;&nbsp;</div>
	</td>

	<!-- remove button -->
	<td>
		<input type="button" class="input link_menu" id="items_#{number}_remove" data-remove="#{number}" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="removeItem(this);" />
	</td>
</tr>
</script>
<script type="text/javascript">
	function loadItem(number, gitemid, graphid, itemid, name, type, calc_fnc, drawtype, yaxisside, color, flags) {
		var item = {
			number: number,
			number_nr: number + 1,
			gitemid: gitemid,
			graphid: graphid,
			itemid: itemid,
			type: type,
			calc_fnc: calc_fnc,
			drawtype: drawtype,
			yaxisside: yaxisside,
			color: color,
			sortorder: number,
			flags: flags,
			name: name
		};

		var itemTpl = new Template(jQuery('#itemTpl').html());
		jQuery('#itemButtonsRow').before(itemTpl.evaluate(item));
		jQuery('#items_' + number + '_type').val(type);
		jQuery('#items_' + number + '_calc_fnc').val(calc_fnc);
		jQuery('#items_' + number + '_drawtype').val(drawtype);
		jQuery('#items_' + number + '_yaxisside').val(yaxisside);
		jQuery('#items_' + number + '_color').val(color);
		jQuery('#lbl_items_' + number + '_color').attr('title', '#' + color);
		jQuery('#lbl_items_' + number + '_color').css('background-color', '#' + color);

		incrementNextColor();
		rewriteNameLinks();
	}

	function addPopupValues(list) {
		if (!isset('object', list) || list.object != 'itemid') {
			return false;
		}

		for (var i = 0; i < list.values.length; i++) {
			var number = jQuery('#itemsTable tr.sortable').length,
				item = {
				number: number,
				number_nr: number + 1,
				gitemid: null,
				graphid: <?php echo $this->data['graphid']; ?>,
				itemid: list.values[i].itemid,
				type: null,
				calc_fnc: null,
				drawtype: 0,
				yaxisside: 0,
				sortorder: number,
				flags: (typeof list.values[i].flags === 'undefined') ? 0 : list.values[i].flags,
				color: getNextColor(1),
				name: list.values[i].name
			};

			var itemTpl = new Template(jQuery('#itemTpl').html());
			jQuery('#itemButtonsRow').before(itemTpl.evaluate(item));
			jQuery('#items_' + item['number'] + '_calc_fnc').val(<?php echo CALC_FNC_AVG; ?>);
			jQuery('#items_' + item['number'] + '_color').val(item['color']);
			jQuery('#lbl_items_' + item['number'] + '_color').attr('title', '#' + item['color']);
			jQuery('#lbl_items_' + item['number'] + '_color').css('background-color', '#' + item['color']);
		}

		activateSortable();
		rewriteNameLinks();
	}

	function getOnlyHostParam() {
		<?php if ($this->data['is_template']): ?>
			return '&only_hostid=<?php echo $this->data['hostid']; ?>';
		<?php else: ?>
			return '&real_hosts=1';
		<?php endif ?>
	}

	function rewriteNameLinks() {
		var size = jQuery('#itemsTable tr.sortable').length;

		for (var i = 0; i < size; i++) {
			var nameLink = 'PopUp("popup.php?writeonly=1&dstfrm=graphForm'
				+ '&dstfld1=items_' + i + '_itemid&dstfld2=items_' + i + '_name'
				+ (jQuery('#items_' + i + '_flags').val() == <?php echo ZBX_FLAG_DISCOVERY_PROTOTYPE; ?>
					? '&srctbl=prototypes&parent_discoveryid=<?php echo $this->data['parent_discoveryid']; ?>'
						+ '&srcfld3=flags&dstfld3=items_' + i + '_flags'
					: '&srctbl=items')
				+ '<?php echo !empty($this->data['normal_only']) ? '&normal_only=1' : ''; ?>'
				+ '&srcfld1=itemid&srcfld2=name" + getOnlyHostParam(), 800, 600)';
			jQuery('#items_' + i + '_name').attr('onclick', nameLink);
		}
	}

	function removeItem(obj) {
		var number = jQuery(obj).data('remove');

		jQuery('#items_' + number).find('*').remove();
		jQuery('#items_' + number).remove();

		recalculateSortOrder();
		activateSortable();
	}

	function recalculateSortOrder() {
		var i = 0;

		// rewrite ids, set "tmp" prefix
		jQuery('#itemsTable tr.sortable').find('*[id]').each(function() {
			var obj = jQuery(this);

			obj.attr('id', 'tmp' + obj.attr('id'));
		});

		jQuery('#itemsTable tr.sortable').each(function() {
			var obj = jQuery(this);

			obj.attr('id', 'tmp' + obj.attr('id'));
		});

		// rewrite ids to new order
		jQuery('#itemsTable tr.sortable').each(function() {
			var obj = jQuery(this);

			// rewrite ids in input fields
			obj.find('*[id]').each(function() {
				var obj = jQuery(this),
					id = obj.attr('id').substring(3),
					part1 = id.substring(0, id.indexOf('items_') + 5),
					part2 = id.substring(id.indexOf('items_') + 6);

				part2 = part2.substring(part2.indexOf('_') + 1);

				obj.attr('id', part1 + '_' + i + '_' + part2);
				obj.attr('name', part1 + '[' + i + '][' + part2 + ']');

				// set sortorder
				if (part2 === 'sortorder') {
					obj.val(i);
				}

				// rewrite color action
				if (part1.substring(0, 3) === 'lbl') {
					obj.attr('onclick', 'javascript: show_color_picker("items_' + i + '_color");');
				}
				else if (part2 === 'color') {
					obj.attr('onchange', 'javascript: set_color_by_name("items_' + i + '_color", this.value);');
				}
			});

			// rewrite ids in <tr>
			var id = obj.attr('id').substring(3),
				part1 = id.substring(0, id.indexOf('items_') + 5);

			obj.attr('id', part1 + '_' + i);

			i++;
		});

		i = 0;

		jQuery('#itemsTable tr.sortable').each(function() {
			// set row number
			jQuery('.items_number', this).text((i + 1) + ':');

			// set remove number
			jQuery('#items_' + i + '_remove').data('remove', i);

			i++;
		});

		rewriteNameLinks();
	}

	function initSortable() {
		var itemsTable = jQuery('#itemsTable'),
			itemsTableWidth = itemsTable.width(),
			itemsTableColumns = jQuery('#itemsTable .header td'),
			itemsTableColumnWidths = [];

		itemsTableColumns.each(function() {
			itemsTableColumnWidths[itemsTableColumnWidths.length] = jQuery(this).width();
		});

		itemsTable.sortable({
			disabled: (jQuery('#itemsTable tr.sortable').length < 2),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			handle: 'span.ui-icon-arrowthick-2-n-s',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			create: function() {
				// force not to change table width
				itemsTable.width(itemsTableWidth);
			},
			helper: function(e, ui) {
				ui.children().each(function(i) {
					var td = jQuery(this);

					td.width(itemsTableColumnWidths[i]);
				});

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				itemsTableColumns.each(function(i) {
					jQuery(this).width(itemsTableColumnWidths[i]);
				});

				return ui;
			},
			start: function(e, ui) {
				jQuery(ui.placeholder).height(jQuery(ui.helper).height());
			}
		});
	}

	function activateSortable() {
		jQuery('#itemsTable').sortable({disabled: (jQuery('#itemsTable tr.sortable').length < 2)});
	}

	jQuery(function($) {
		$('#tab_previewTab').click(function() {
			var name = 'chart3.php';
			var src = '&name=' + encodeURIComponent($('#name').val())
						+ '&width=' + $('#width').val()
						+ '&height=' + $('#height').val()
						+ '&graphtype=' + $('#graphtype').val()
						+ '&legend=' + ($('#show_legend').is(':checked') ? 1 : 0);

			<?php if ($this->data['graphtype'] == GRAPH_TYPE_PIE || $this->data['graphtype'] == GRAPH_TYPE_EXPLODED): ?>
				name = 'chart7.php';
				src += '&graph3d=' + ($('#show_3d').is(':checked') ? 1 : 0);

			<?php else: ?>
				<?php if ($this->data['graphtype'] == GRAPH_TYPE_NORMAL): ?>
					src += '&percent_left=' + $('#percent_left').val()
							+ '&percent_right=' + $('#percent_right').val();
				<?php endif; ?>

				src += '&ymin_type=' + $('#ymin_type').val()
							+ '&ymax_type=' + $('#ymax_type').val()
							+ '&yaxismin=' + $('#yaxismin').val()
							+ '&yaxismax=' + $('#yaxismax').val()
							+ '&ymin_itemid=' + $('#ymin_itemid').val()
							+ '&ymax_itemid=' + $('#ymax_itemid').val()
							+ '&showworkperiod=' + ($('#show_work_period').is(':checked') ? 1 : 0)
							+ '&showtriggers=' + ($('#show_triggers').is(':checked') ? 1 : 0);
			<?php endif; ?>

			$('#itemsTable tr.sortable').find('*[name]').each(function(index, value) {
				if (!$.isEmptyObject(value) && value.name != null) {
					src += '&' + value.name + '=' + value.value;
				}
			});

			$('#previewTab img')
				.attr('src', 'styles/themes/<?php echo getUserTheme(CWebUser::$data); ?>/images/preloader.gif')
				.width(80)
				.height(12);

			$('<img />').attr('src', name + '?period=3600' + src).load(function() {
				$('#previewChar img').remove();
				$('#previewChar').append($(this));
			});
		});

		<?php if (!empty($this->data['templateid'])): ?>
			$('#graphTab input, #graphTab select').each(function() {
				$(this).attr('disabled', 'disabled');
				$('#itemsTable').sortable({disabled: true});
			});

			var size = $('#itemsTable tr.sortable').length;

			for (var i = 0; i < size; i++) {
				$('.ui-icon').attr('class', 'ui-icon ui-icon-arrowthick-2-n-s state-disabled');
				$('#items_' + i + '_name').removeAttr('onclick');
				$('#items_' + i + '_name').removeAttr('class');
				$('#items_' + i + '_color').removeAttr('onchange');
				$('#lbl_items_' + i + '_color').removeAttr('onclick');
				$('#lbl_items_' + i + '_color').attr('class', 'colorpickerLabel');
			}
		<?php endif; ?>

		// Y axis min clean unused fields
		$('#ymin_type').change(function() {
			switch ($(this).val()) {
				case '<?php echo GRAPH_YAXIS_TYPE_CALCULATED; ?>':
					$('#yaxismin').val('');
					$('#ymin_name').val('');
					$('#ymin_itemid').val('0');
					break;

				case '<?php echo GRAPH_YAXIS_TYPE_FIXED; ?>':
					$('#ymin_name').val('');
					$('#ymin_itemid').val('0');
					break;

				default:
					$('#yaxismin').val('');
			}

			$('form[name="graphForm"]').submit();
		});

		// Y axis max clean unused fields
		$('#ymax_type').change(function() {
			switch ($(this).val()) {
				case '<?php echo GRAPH_YAXIS_TYPE_CALCULATED; ?>':
					$('#yaxismax').val('');
					$('#ymax_name').val('');
					$('#ymax_itemid').val('0');
					break;

				case '<?php echo GRAPH_YAXIS_TYPE_FIXED; ?>':
					$('#ymax_name').val('');
					$('#ymax_itemid').val('0');
					break;

				default:
					$('#yaxismax').val('');
			}

			$('form[name="graphForm"]').submit();
		});

		initSortable();
	});
</script>
