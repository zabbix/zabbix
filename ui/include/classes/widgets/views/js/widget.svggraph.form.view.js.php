<?php declare(strict_types = 1);
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
?>


window.widget_svggraph_form = new class {

	init({form_id, form_tabs_id}) {
		colorPalette.setThemeColors(<?= json_encode(explode(',', getUserGraphTheme()['colorpalette'])) ?>);

		this.form_id = form_id;
		this.form_tabs = form_tabs_id;

		// jQuery(".overlay-dialogue-body").on("scroll", function() {
		// 	if (jQuery("#svg-graph-preview").length) {
		// 		const $dialogue_body = jQuery(this);
		// 		const $preview_container = jQuery(".<?//= ZBX_STYLE_SVG_GRAPH_PREVIEW ?>//");

		// 		if ($preview_container.offset().top < $dialogue_body.offset().top && $dialogue_body.height() > 400) {
		// 			jQuery("#svg-graph-preview").css("top", $dialogue_body.offset().top - $preview_container.offset().top);
		// 			jQuery(".graph-widget-config-tabs .ui-tabs-nav").css("top", $preview_container.height());
		// 		}
		// 		else {
		// 			jQuery("#svg-graph-preview").css("top", 0);
		// 			jQuery(".graph-widget-config-tabs .ui-tabs-nav").css("top", 0);
		// 		}
		// 	}
		// 	else {
		// 		jQuery(".overlay-dialogue-body").off("scroll");
		// 	}
		// });

		jQuery(`#${this.form_tabs}`).on("change", "input, z-select, .multiselect", () => this.onGraphConfigChange());

		this.onGraphConfigChange();

		$(".overlay-dialogue").on("overlay-dialogue-resize", (event, size_new, size_old) => {
			if (jQuery("#svg-graph-preview").length) {
				if (size_new.width != size_old.width) {
					this.onGraphConfigChange();
				}
			} else {
				$(".overlay-dialogue").off("overlay-dialogue-resize");
			}
		});


		// Initialize vertical accordion.
		jQuery("#data_sets")
			.on("focus", ".<?= CMultiSelect::ZBX_STYLE_CLASS ?> input.input, .js-click-expend, .color-picker-preview", function() {
				jQuery("#data_sets").zbx_vertical_accordion("expandNth",
					jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>").index()
				);
			})
			.on("collapse", function(event, data) {
				jQuery("textarea, .multiselect", data.section).scrollTop(0);
				jQuery(window).trigger("resize");
			})
			.on("expand", function() {
				jQuery(window).trigger("resize");
			})
			.zbx_vertical_accordion({handler: ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>"});

		// Initialize rangeControl UI elements.
		jQuery(".<?= CRangeControl::ZBX_STYLE_CLASS ?>", jQuery("#data_sets")).rangeControl();

		// Expand dataset when click in pattern fields.
		jQuery("#data_sets").on("click", ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED ?> .<?= CMultiSelect::ZBX_STYLE_CLASS ?>, .<?= ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED ?> .<?= ZBX_STYLE_BTN_GREY ?>", function(event) {
			jQuery("#data_sets").zbx_vertical_accordion("expandNth",
				jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>").index());

			jQuery(event.currentTarget).find("input.input").focus();
		});

		// Initialize pattern fields.
		jQuery(".multiselect", jQuery("#data_sets")).each(function() {
			jQuery(this).multiSelect(jQuery(this).data("params"));
		});

		// Initialize color-picker UI elements.
		jQuery(".<?= ZBX_STYLE_COLOR_PICKER ?> input").colorpicker({onUpdate: function(color){
			jQuery(".<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>", jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>"))
				.css("background-color", "#"+color);
		}, appendTo: ".overlay-dialogue-body"});

		this.initDataSetSortable();

		jQuery(".overlay-dialogue-body").on("change", "z-select[id$=\"aggregate_function\"]", (e) => {
			changeDataSetAggregateFunction(e.target);
		});

		this.rewriteNameLinks();

		document.getElementById('data_sets').addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-item')) {
				this._selectItems();
			}

			if (e.target.classList.contains('element-table-remove')) {
				this.removeSingleItem(e.target);
			}

			if (e.target.classList.contains('btn-remove')) {
				this.removeDataSet(e.target);
			}
		});

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => this._addDataset(1));

		document
			.getElementById('dataset-menu')
			.addEventListener('click', this._addDatasetMenu);
	}

	_selectItems() {
		const overlay = PopUp("popup.generic", {
			srctbl: 'items',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: this.form_id,
			numeric: 1,
			writeonly: 1,
			multiselect: 1,
			with_webitems: 1,
			real_hosts: 1
		});
		overlay.$dialogue[0].addEventListener('overlay.submit', (e) => {
			console.log(e.detail);
		});
	}

	_timePeriodTabInit() { // TODO: Time period tab - update controls enable status on 'Set custom time period' checkbox change
		jQuery("#time_from, #time_to, #time_from_calendar, #time_to_calendar")
			.prop("disabled", !jQuery(this).is(":checked"));
	}

	_axesTabInit() {
		widget_svggraph_form.onLeftYChange(); // TODO: on Left Y checkbox

		widget_svggraph_form.onRightYChange(); // TODO: on Right Y checkbox
	}

	_legendTabInit() { // TODO: on Show legend checkbox
		jQuery("[name=legend_lines]").rangeControl(
			jQuery(this).is(":checked") ? "enable" : "disable"
		);
	}

	_problemsTabInit() { // TODO: Problems tab - update controls enable status on 'Show problems' checkbox change
		var on = jQuery(this).is(":checked"),
			widget = jQuery(this).closest(".ui-widget");

		jQuery("#graph_item_problems, #problem_name, #problemhosts_select").prop("disabled", !on);
		jQuery("#problemhosts_").multiSelect(on ? "enable" : "disable");
		jQuery("[name^=\"severities[\"]", widget).prop("disabled", !on);
		jQuery("[name=\"evaltype\"]", widget).prop("disabled", !on);
		jQuery("input, button, z-select", jQuery("#tags_table_tags", widget)).prop("disabled", !on);
	}

	_addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item pattern')) ?>,
						clickCallback: () => {
							widget_svggraph_form._addDataset(1)
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => {
							widget_svggraph_form._addDataset(0)
						}
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						clickCallback: () => {
							// TODO: clone function
						}
					}
				]
			}
		];

		jQuery(e.target).menuPopup(menu, new jQuery.Event(e), {
			position: {
				of: e.target,
				my: 'left top',
				at: 'left bottom',
				within: '.wrapper'
			}
		});
	}

	_addDataset(type) {
		const row_numb = jQuery('#data_sets .list-accordion-item').length;
		const container = jQuery(".overlay-dialogue-body");

		let template = new Template(jQuery('#dataset-single-item-tmpl').html());

		if (type == 1) {
			template = new Template(jQuery('#dataset-pattern-item-tmpl').html());
		}

		jQuery("#data_sets").zbx_vertical_accordion("collapseAll");

		jQuery('#data_sets .list-accordion-foot').before(template.evaluate({rowNum: row_numb, color: colorPalette.getNextColor()}));

		container.scrollTop(Math.max(container.scrollTop(),
			jQuery("#widget-dialogue-form")[0].scrollHeight - container.height()
		));

		jQuery(".<?= ZBX_STYLE_COLOR_PICKER ?> input").colorpicker({onUpdate: function(color) {
			jQuery(".<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>",
					jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>")
			).css("background-color", "#"+color);
		}, appendTo: ".overlay-dialogue-body"});

		jQuery(".multiselect", jQuery("#data_sets")).each(function() {
			jQuery(this).multiSelect(jQuery(this).data("params"));
		});

		jQuery(".<?= CRangeControl::ZBX_STYLE_CLASS ?>", jQuery("#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>")).rangeControl();

		widget_svggraph_form.updateVariableOrder(jQuery("#data_sets"), ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>", "ds");
		widget_svggraph_form.onGraphConfigChange();

		widget_svggraph_form.initDataSetSortable();

		colorPalette.incrementNextColor();
	}

	removeDataSet(obj) {
		let i = 0;

		obj
			.closest('.list-accordion-item')
			.remove();

		[...document.querySelectorAll('#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')].map((elem) => {
			elem.dataset.set = i;

			if (elem.querySelector('.single-item-table')) {
				elem.querySelector('.single-item-table').dataset.set = i;
			}

			i++;
		});

		this.updateVariableOrder(jQuery("#data_sets"), ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>", "ds");
		this.recalculateSortOrder();
	}

	getDataSetNumber() {
		return jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]').data('set');
	}

	onLeftYChange() {
		const on = (!jQuery("#lefty").is(":disabled") && jQuery("#lefty").is(":checked"));

		if (jQuery("#lefty").is(":disabled") && !jQuery("#lefty").is(":checked")) {
			jQuery("#lefty").prop("checked", true);
		}

		jQuery("#lefty_min, #lefty_max, #lefty_units").prop("disabled", !on);

		jQuery("#lefty_static_units").prop("disabled",
			(!on || jQuery("#lefty_units").val() != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>));
	}

	onRightYChange() {
		const on = (!jQuery("#righty").is(":disabled") && jQuery("#righty").is(":checked"));

		if (jQuery("#righty").is(":disabled") && !jQuery("#righty").is(":checked")) {
			jQuery("#righty").prop("checked", true);
		}

		jQuery("#righty_min, #righty_max, #righty_units").prop("disabled", !on);

		jQuery("#righty_static_units").prop("disabled",
			(!on || jQuery("#righty_units").val() != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>));
	}

	onGraphConfigChange() {
		// Update graph preview.
		const $preview = jQuery("#svg-graph-preview");
		const $preview_container = $preview.parent();
		const preview_data = $preview_container.data();
		const $form = jQuery(`#${this.form_id}`);
		const url = new Curl("zabbix.php");
		const data = {
			uniqueid: 0,
			preview: 1,
			content_width: Math.floor($preview.width()),
			content_height: Math.floor($preview.height()) - 10
		};

		url.setArgument("action", "widget.svggraph.view");

		// Enable/disable fields for Y axis.
		if (this.id !== "lefty" && this.id !== "righty") {
			const axes_used = {<?= GRAPH_YAXIS_SIDE_LEFT ?>: 0, <?= GRAPH_YAXIS_SIDE_RIGHT ?>: 0};

			jQuery("[type=radio]", $form).each(function() {
				if (jQuery(this).attr("name").match(/ds\[\d+]\[axisy]/) && jQuery(this).is(":checked")) {
					axes_used[jQuery(this).val()]++;
				}
			});

			jQuery("[type=hidden]", $form).each(function() {
				if (jQuery(this).attr("name").match(/or\[\d+]\[axisy]/)) {
					axes_used[jQuery(this).val()]++;
				}
			});

			jQuery("#lefty").prop("disabled", !axes_used[<?= GRAPH_YAXIS_SIDE_LEFT ?>]);
			jQuery("#righty").prop("disabled", !axes_used[<?= GRAPH_YAXIS_SIDE_RIGHT ?>]);

			this.onLeftYChange();
			this.onRightYChange();
		}

		const form_fields = $form.serializeJSON();

		if ("ds" in form_fields) {
			for (var i in form_fields.ds) {
				form_fields.ds[i] = jQuery.extend({"hosts": [], "items": []}, form_fields.ds[i]);
			}
		}
		if ("or" in form_fields) {
			for (var i in form_fields.or) {
				form_fields.or[i] = jQuery.extend({"hosts": [], "items": []}, form_fields.or[i]);
			}
		}
		data.fields = JSON.stringify(form_fields);

		if (preview_data.xhr) {
			preview_data.xhr.abort();
		}

		if (preview_data.timeoutid) {
			clearTimeout(preview_data.timeoutid);
		}

		preview_data.timeoutid = setTimeout(function() {
			$preview_container.addClass("is-loading");
		}, 1000);

		preview_data.xhr = jQuery.ajax({
			url: url.getUrl(),
			method: "POST",
			contentType: "application/json",
			data: JSON.stringify(data),
			dataType: "json",
			success: function(r) {
				if (preview_data.timeoutid) {
					clearTimeout(preview_data.timeoutid);
				}
				$preview_container.removeClass("is-loading");

				$form.prev(".msg-bad").remove();
				if (typeof r.messages !== "undefined") {
					jQuery(r.messages).insertBefore($form);
				}
				if (typeof r.body !== "undefined") {
					$preview.html(jQuery(r.body)).attr("unselectable", "on").css("user-select", "none");
				}
			}
		});

		$preview_container.data(preview_data);
	}

	updateVariableOrder(obj, row_selector, var_prefix) {
		jQuery.each([10000, 0], function(index, value) {
			jQuery(row_selector, obj).each(function(i) {
				jQuery(".multiselect[data-params]", this).each(function() {
					const name = jQuery(this).multiSelect("getOption", "name");

					if (name !== null) {
						jQuery(this).multiSelect("modify", {
							name: name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, "$1" + (value + i) + "$2")
						});
					}
				});

				jQuery('[name^="' + var_prefix + '["]', this).filter(function() {
					return jQuery(this).attr("name").match(/[a-z]+\[\d+]\[[a-z_]+]/);
				}).each(function() {
					jQuery(this).attr("name",
						jQuery(this).attr("name").replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, "$1" + (value + i) + "$2")
					);
				});
			});
		});
	}

	initDataSetSortable() {
		// Initialize sorting.
		if (jQuery("#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>").length == 1) {
			jQuery("#data_sets .js-main-drag-icon").addClass("disabled");
		}
		else {
			jQuery("#data_sets .js-main-drag-icon").removeClass("disabled");
		}

		jQuery("#data_sets").sortable({
			disabled: jQuery("#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>").length < 2,
			items: ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>",
			containment: "parent",
			handle: ".js-main-drag-icon",
			tolerance: "pointer",
			scroll: false,
			cursor: "grabbing",
			opacity: 0.6,
			axis: "y",
			start: function() { // Workaround to fix wrong scrolling at initial sort.
				jQuery(this).sortable("refreshPositions");
			},
			stop: () => widget_svggraph_form.onGraphConfigChange(),
			update: function() {
				widget_svggraph_form.updateVariableOrder(jQuery("#data_sets"), ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>", "ds");
			}
		});
	}

	initSingleItemSortable() {
		const dataset_number = widget_svggraph_form.getDataSetNumber();

		if (jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row').length == 1) {
			jQuery('.single-item-table[data-set='+dataset_number+'] .<?= ZBX_STYLE_DRAG_ICON ?>').addClass("disabled");
		}
		else {
			jQuery('.single-item-table[data-set=' + dataset_number + '] .<?= ZBX_STYLE_DRAG_ICON ?>')
				.removeClass("disabled");
		}


		jQuery('.single-item-table[data-set='+dataset_number+']').sortable({
			disabled: jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row').length < 2,
			items: 'tbody .single-item-table-row',
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
	}

	rewriteNameLinks() {
		[...document.querySelectorAll('#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>[data-set]')].map((elem) => {
			const dataset_number = elem.dataset.set;
			const size = jQuery('.single-item-table-row', jQuery(elem)).length + 1;

			for (let i = 0; i < size; i++) {
				const parameters = {
					srctbl: "items",
					srcfld1: 'itemid',
					srcfld2: 'name',
					dstfrm: widget_svggraph_form.form_id,
					dstfld1: `items_${dataset_number}_${i}_input`,
					dstfld2: `items_${dataset_number}_${i}_name`,
					numeric: 1,
					writeonly: 1,
					multiselect: 1,
					with_webitems: 1,
					real_hosts: 1
				};

				$('#items_' + dataset_number + '_' + i + '_name').attr('onclick', 'PopUp("popup.generic", ' +
					JSON.stringify(parameters) + ',' +
					'{dialogue_class: "modal-popup-generic"});'
				);
			}
		});
	}

	removeSingleItem(obj) {
		const table_row = obj.closest('.single-item-table-row');

		table_row.remove();

		this.recalculateSortOrder();
	}

	recalculateSortOrder() {
		const dataset_number = widget_svggraph_form.getDataSetNumber();
		let i = 1;

		jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row').each(function () {
			const $obj = jQuery(this);

			$obj.data('number', i);

			jQuery('.color-picker input', $obj).attr('id', `items_${dataset_number}_${i}_color`);
			jQuery('.color-picker button', $obj).attr('id', `lbl_items_${dataset_number}_${i}_color`);

			jQuery('.table-col-name a', $obj).attr('id', `items_${dataset_number}_${i}_name`);

			jQuery('.table-col-action input', $obj).attr('id', `items_${dataset_number}_${i}_input`);

			jQuery('.table-col-no span', $obj).text(i + ':');

			i++;
		});

		widget_svggraph_form.rewriteNameLinks();
	}
};

window.addPopupValues = (list) => {
	if (!isset('object', list) || list.object != 'itemid') {
		return false;
	}

	const dataset_number = widget_svggraph_form.getDataSetNumber();
	const tmpl = new Template(jQuery('#dataset-item-row-tmpl').html());

	for (let i = 0; i < list.values.length; i++) {
		const size = jQuery('.single-item-table[data-set='+dataset_number+'] .single-item-table-row').length + 1;
		const value = list.values[i];
		const name = value.name;
		const itemid = value.itemid;

		jQuery('.single-item-table[data-set='+dataset_number+'] tbody').append(tmpl.evaluate({
			dsNum: dataset_number,
			rowNum: size,
			name: name,
			itemid: itemid
		}));
		jQuery(`#items_${dataset_number}_${size}_color`).val(colorPalette.getNextColor());
		jQuery(`#items_${dataset_number}_${size}_color`).colorpicker();

		widget_svggraph_form.rewriteNameLinks();
		widget_svggraph_form.initSingleItemSortable();
	}
}

function changeDataSetDrawType(obj) {
	const row_num = obj.id.replace("ds_", "").replace("_type", "");

	switch (jQuery(":checked", jQuery(obj)).val()) {
		case "<?= SVG_GRAPH_TYPE_POINTS ?>":
			jQuery("#ds_" + row_num + "_width").rangeControl("disable");
			jQuery("#ds_" + row_num + "_pointsize").rangeControl("enable");
			jQuery("#ds_" + row_num + "_transparency").rangeControl("enable");
			jQuery("#ds_" + row_num + "_fill").rangeControl("disable");
			jQuery("#ds_" + row_num + "_missingdatafunc_0").prop("disabled", true);
			jQuery("#ds_" + row_num + "_missingdatafunc_1").prop("disabled", true);
			jQuery("#ds_" + row_num + "_missingdatafunc_2").prop("disabled", true);
			jQuery("#ds_" + row_num + "_missingdatafunc_3").prop("disabled", true);
			break;
		case "<?= SVG_GRAPH_TYPE_BAR ?>":
			jQuery("#ds_" + row_num + "_width").rangeControl("disable");
			jQuery("#ds_" + row_num + "_pointsize").rangeControl("disable");
			jQuery("#ds_" + row_num + "_transparency").rangeControl("enable");
			jQuery("#ds_" + row_num + "_fill").rangeControl("disable");
			jQuery("#ds_" + row_num + "_missingdatafunc_0").prop("disabled", true);
			jQuery("#ds_" + row_num + "_missingdatafunc_1").prop("disabled", true);
			jQuery("#ds_" + row_num + "_missingdatafunc_2").prop("disabled", true);
			jQuery("#ds_" + row_num + "_missingdatafunc_3").prop("disabled", true);
			break;
		default:
			jQuery("#ds_" + row_num + "_width").rangeControl("enable");
			jQuery("#ds_" + row_num + "_pointsize").rangeControl("disable");
			jQuery("#ds_" + row_num + "_transparency").rangeControl("enable");
			jQuery("#ds_" + row_num + "_fill").rangeControl("enable");
			jQuery("#ds_" + row_num + "_missingdatafunc_0").prop("disabled", false);
			jQuery("#ds_" + row_num + "_missingdatafunc_1").prop("disabled", false);
			jQuery("#ds_" + row_num + "_missingdatafunc_2").prop("disabled", false);
			jQuery("#ds_" + row_num + "_missingdatafunc_3").prop("disabled", false);
			break;
	}
};

function changeDataSetAggregateFunction(obj) {
	const row_num = obj.id.replace("ds_", "").replace("_aggregate_function", "");
	const no_aggregation = (jQuery(obj).val() == <?= AGGREGATE_NONE ?>);

	jQuery("#ds_" + row_num + "_aggregate_interval").prop("disabled", no_aggregation);
	jQuery("#ds_" + row_num + "_aggregate_grouping_0").prop("disabled", no_aggregation);
	jQuery("#ds_" + row_num + "_aggregate_grouping_1").prop("disabled", no_aggregation);
};
