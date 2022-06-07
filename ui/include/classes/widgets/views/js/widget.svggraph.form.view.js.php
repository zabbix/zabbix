<?php declare(strict_types = 0);
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

		this.overlay_body = jQuery(".overlay-dialogue-body");
		this.form_id = form_id;
		this.form_tabs = form_tabs_id;

		this.dataset_wrapper = document.getElementById('data_sets');

		this.overlay_body.on("scroll", () => {
			const $preview_container = jQuery(".<?= ZBX_STYLE_SVG_GRAPH_PREVIEW ?>");

			if ($preview_container.offset().top < this.overlay_body.offset().top && this.overlay_body.height() > 400) {
				jQuery("#svg-graph-preview").css("top", this.overlay_body.offset().top - $preview_container.offset().top);
				jQuery(".graph-widget-config-tabs .ui-tabs-nav").css("top", $preview_container.height());
			}
			else {
				jQuery("#svg-graph-preview").css("top", 0);
				jQuery(".graph-widget-config-tabs .ui-tabs-nav").css("top", 0);
			}
		});

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
		jQuery(this.dataset_wrapper)
			.on("focus", ".<?= CMultiSelect::ZBX_STYLE_CLASS ?> input.input, .js-click-expend, .color-picker-preview", function() {
				jQuery("#data_sets").zbx_vertical_accordion("expandNth",
					jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>").index()
				);
			})
			.on("collapse", function(event, data) {
				jQuery("textarea, .multiselect", data.section).scrollTop(0);
				jQuery(window).trigger("resize");
				const dataset = data.section[0];


				if (dataset.dataset.type == '0') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'block';
					}
				}
			})
			.on("expand", function(event, data) {
				jQuery(window).trigger("resize");
				const dataset = data.section[0];

				if (dataset.dataset.type == '0') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'none';
					}
				}
			})
			.zbx_vertical_accordion({handler: ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>"});

		// Initialize rangeControl UI elements.
		jQuery(".<?= CRangeControl::ZBX_STYLE_CLASS ?>", jQuery(this.dataset_wrapper)).rangeControl();

		// Expand dataset when click in pattern fields.
		jQuery(this.dataset_wrapper).on("click", ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED ?> .<?= CMultiSelect::ZBX_STYLE_CLASS ?>, .<?= ZBX_STYLE_LIST_ACCORDION_ITEM_CLOSED ?> .<?= ZBX_STYLE_BTN_GREY ?>", function(event) {
			jQuery(this.dataset_wrapper).zbx_vertical_accordion("expandNth",
				jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>").index());

			jQuery(event.currentTarget).find("input.input").focus();
		});

		// Initialize pattern fields.
		jQuery(".multiselect", jQuery(this.dataset_wrapper)).each(function() {
			jQuery(this).multiSelect(jQuery(this).data("params"));
		});

		for (const colorpicker of jQuery(".<?= ZBX_STYLE_COLOR_PICKER ?> input")) {
			$(colorpicker).colorpicker({
				onUpdate: function(color) {
					jQuery(".<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>",
						jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>")
					).css("background-color", `#${color}`);
				},
				appendTo: ".overlay-dialogue-body"
			});

			colorPalette.incrementNextColor();
		}

		this.initDataSetSortable();

		this.overlay_body.on("change", "z-select[id$=\"aggregate_function\"]", (e) => {
			widget_svggraph_form.changeDataSetAggregateFunction(e.target);
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

		this.initSingleItemSortable();

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => this._addDataset(1));

		document
			.getElementById('dataset-menu')
			.addEventListener('click', this._addDatasetMenu);

		this._displayingOptionsTabInit();
		this._timePeriodTabInit();
		this._axesTabInit();
		this._legendTabInit();
		this._problemsTabInit();
	}

	_selectItems() {
		PopUp("popup.generic", {
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
	}

	_displayingOptionsTabInit() {
		document.getElementById('percentile_left')
			.addEventListener('click', (e) => {
				document.getElementById('percentile_left_value').disabled = !e.target.checked;
			});
		document.getElementById('percentile_right')
			.addEventListener('click', (e) => {
				document.getElementById('percentile_right_value').disabled = !e.target.checked;
			});
	}

	_timePeriodTabInit() {
		document.getElementById('graph_time')
			.addEventListener('click', (e) => {
				document.getElementById('time_from').disabled = !e.target.checked;
				document.getElementById('time_to').disabled = !e.target.checked;
				document.getElementById('time_from_calendar').disabled = !e.target.checked;
				document.getElementById('time_to_calendar').disabled = !e.target.checked;
			});
	}

	_axesTabInit() {
		this.onLeftYChange();

		this.onRightYChange();
	}

	_legendTabInit() {
		document.getElementById('legend')
			.addEventListener('click', (e) => {
				jQuery('#legend_lines').rangeControl(
					e.target.checked ? 'enable' : 'disable'
				);
				if (!e.target.checked) {
					jQuery('#legend_columns').rangeControl('disable');
				}
				else {
					if (!document.getElementById('legend_statistic').checked) {
						jQuery('#legend_columns').rangeControl('enable');
					}
				}
				document.getElementById('legend_statistic').disabled = !e.target.checked;
			});

		document.getElementById('legend_statistic')
			.addEventListener('click', (e) => {
				jQuery('#legend_columns').rangeControl(
					!e.target.checked ? 'enable' : 'disable'
				);
			});
	}

	_problemsTabInit() {
		const widget = document.getElementById('problems');

		document.getElementById('show_problems')
			.addEventListener('click', (e) => {
				jQuery('#graph_item_problems, #problem_name, #problemhosts_select').prop('disabled', !e.target.checked);
				jQuery('#problemhosts_').multiSelect(e.target.checked ? 'enable' : 'disable');
				jQuery("[name^='severities[']", jQuery(widget)).prop('disabled', !e.target.checked);
				jQuery("[name='evaltype']", jQuery(widget)).prop('disabled', !e.target.checked);
				jQuery('input, button, z-select', jQuery('#tags_table_tags', jQuery(widget))).prop('disabled', !e.target.checked);
			});
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
							widget_svggraph_form.clone();
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

		let template = new Template(jQuery('#dataset-single-item-tmpl').html());

		if (type == 1) {
			template = new Template(jQuery('#dataset-pattern-item-tmpl').html());
		}

		jQuery(this.dataset_wrapper).zbx_vertical_accordion("collapseAll");

		jQuery('#data_sets .list-accordion-foot').before(
			template.evaluate({rowNum: row_numb, color: colorPalette.getNextColor()})
		);

		this.overlay_body.scrollTop(Math.max(this.overlay_body.scrollTop(),
			jQuery("#widget-dialogue-form")[0].scrollHeight - this.overlay_body.height()
		));

		jQuery(".<?= ZBX_STYLE_COLOR_PICKER ?> input").colorpicker({onUpdate: function(color) {
			jQuery(".<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>",
					jQuery(this).closest(".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>")
			).css("background-color", "#"+color);
		}, appendTo: ".overlay-dialogue-body"});

		jQuery(".multiselect", jQuery(this.dataset_wrapper)).each(function() {
			jQuery(this).multiSelect(jQuery(this).data("params"));
		});

		jQuery(".<?= CRangeControl::ZBX_STYLE_CLASS ?>",
			jQuery("#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>")
		).rangeControl();

		this.updateVariableOrder(jQuery(this.dataset_wrapper), ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>", "ds");
		this.onGraphConfigChange();

		this.initDataSetSortable();
	}

	removeDataSet(obj) {
		obj
			.closest('.list-accordion-item')
			.remove();

		this.recalculateDataSetAttribute();

		this.updateVariableOrder(jQuery(this.dataset_wrapper), ".<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>", "ds");
		this.recalculateSortOrder();
	}

	recalculateDataSetAttribute() {
		let i = 0;

		[...this.dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')].map((elem) => {
			elem.dataset.set = i;

			if (elem.querySelector('.single-item-table')) {
				elem.querySelector('.single-item-table').dataset.set = i;
			}

			i++;
		});
	}

	getDataSetNumber() {
		if (jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]').length) {
			return jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]').data('set');
		}

		return jQuery('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>[data-set]:last').data('set');
	}

	onLeftYChange() {
		const on = (!jQuery("#lefty").is(":disabled") && jQuery("#lefty").is(":checked"));

		if (jQuery("#lefty").is(":disabled") && !jQuery("#lefty").is(":checked")) {
			jQuery("#lefty").prop("checked", true);
		}

		jQuery("#lefty_min, #lefty_max, #lefty_units").prop("disabled", !on);

		jQuery("#lefty_static_units").prop("disabled",
			(!on || jQuery("#lefty_units").val() != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>));

		jQuery("#percentile_left").prop("disabled", !on);
	}

	onRightYChange() {
		const on = (!jQuery("#righty").is(":disabled") && jQuery("#righty").is(":checked"));

		if (jQuery("#righty").is(":disabled") && !jQuery("#righty").is(":checked")) {
			jQuery("#righty").prop("checked", true);
		}

		jQuery("#righty_min, #righty_max, #righty_units").prop("disabled", !on);

		jQuery("#righty_static_units").prop("disabled",
			(!on || jQuery("#righty_units").val() != <?= SVG_GRAPH_AXIS_UNITS_STATIC ?>));


		jQuery("#percentile_right").prop("disabled", !on);
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
				if ("error" in r) {
					const message_box = makeMessageBox("bad", r.error.messages, r.error.title);
					message_box.insertBefore($form);
				}
				if (typeof r.body !== "undefined") {
					$preview.html(jQuery(r.body)).attr("unselectable", "on").css("user-select", "none");
				}
			}
		});

		$preview_container.data(preview_data);
	}

	updateVariableOrder(obj, row_selector, var_prefix) {
		jQuery.each([10000, 0], function(_, value) {
			jQuery(row_selector, obj).each(function(i) {
				jQuery(".multiselect[data-params]", this).each(function() {
					const name = jQuery(this).multiSelect("getOption", "name");

					if (name !== null) {
						jQuery(this).multiSelect("modify", {
							name: name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, "$1" + (value + i) + "$2")
						});
					}
				});

				jQuery('[name^="' + var_prefix + '["]', this)
					.filter(function () {
						return jQuery(this).attr("name").match(/[a-z]+\[\d+]\[[a-z_]+]/);
					})
					.each(function () {
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
		const dataset_number = this.getDataSetNumber();

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
		[...document.querySelectorAll('#data_sets .<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>[data-set]')].map((element) => {
			const dataset_number = element.dataset.set;
			const size = jQuery('.single-item-table-row', jQuery(element)).length + 1;

			for (let i = 0; i < size; i++) {
				$('#items_' + dataset_number + '_' + i + '_name').off('click').on('click', () => {
					let ids = [];
					for (let i = 0; i < size; i++) {
						ids.push($('#items_' + dataset_number + '_' + i + '_input').val());
					}

					PopUp("popup.generic", {
						srctbl: "items",
						srcfld1: 'itemid',
						srcfld2: 'name',
						dstfrm: widget_svggraph_form.form_id,
						dstfld1: `items_${dataset_number}_${i}_input`,
						dstfld2: `items_${dataset_number}_${i}_name`,
						numeric: 1,
						writeonly: 1,
						with_webitems: 1,
						real_hosts: 1,
						dialogue_class: "modal-popup-generic",
						excludeids: ids
					});
				});
			}
		});
	}

	removeSingleItem(obj) {
		const table_row = obj.closest('.single-item-table-row');

		table_row.remove();

		this.recalculateSortOrder();
	}

	recalculateSortOrder() {
		const dataset_number = widget_svggraph_form.getDataSetNumber(); // When function added as eventlistener `this` scope not working.
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

	clone() {
		let dataset_elem = this.dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');
		if (!dataset_elem) {
			dataset_elem = Array.from(this.dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?> ')).pop();
		}

		const dataset_number = this.getDataSetNumber();
		const dataset_type = dataset_elem.dataset.type;
		const inputs = dataset_elem.querySelectorAll('input[name^=ds]');

		this._addDataset(dataset_type);

		const cloned_dataset = this.dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');
		const cloned_number = cloned_dataset.dataset.set;

		if (dataset_type == 0) {
			const list = {
				object: 'itemid',
				values: []
			};

			[...dataset_elem.querySelectorAll('.single-item-table-row')].map((elem) => {
				const itemid = elem.querySelector("[name^='ds[" + dataset_number + "][itemids]").value;
				const name = elem.querySelector('.table-col-name a').textContent;

				list.values.push({
					itemid: itemid,
					name: name
				});
			});

			window.addPopupValues(list);
		}
		else {
			const host_pattern_data = jQuery(dataset_elem.querySelector('.js-hosts-multiselect')).multiSelect('getData')
			const items_pattern_data = jQuery(dataset_elem.querySelector('.js-items-multiselect')).multiSelect('getData')

			jQuery(cloned_dataset.querySelector('.js-hosts-multiselect')).multiSelect('addData', host_pattern_data);
			jQuery(cloned_dataset.querySelector('.js-items-multiselect')).multiSelect('addData', items_pattern_data);
		}

		[...inputs].map((elem) => {
			const name = elem.name;
			const type = elem.type;
			const value = elem.value;

			const cloned_name = name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, "$1" + (cloned_number) + "$2");

			if (type === 'text') {
				cloned_dataset.querySelector("[name='" + cloned_name + "']").value = value;

				if (elem.classList.contains('<?= CRangeControl::ZBX_STYLE_CLASS ?>')) {
					// Fire change event to redraw range input.
					cloned_dataset.querySelector("[name='" + cloned_name + "']").dispatchEvent(new Event('change'));
				}
			}
			else if (type === 'checkbox' || type === 'radio') {
				if (elem.checked) {
					// Click to fire events.
					cloned_dataset.querySelector("[name='" + cloned_name + "'][value='" + value + "']").click();
				}
			}
			else if (cloned_dataset.querySelector("z-select[name='" + cloned_name + "']")) {
				cloned_dataset.querySelector("[name='" + cloned_name + "']").value = value;
			}
		});

		this.onGraphConfigChange();
		this.rewriteNameLinks();
	}


	changeDataSetDrawType(obj) {
		const row_num = obj.id.replace("ds_", "").replace("_type", "");
		const approximation_select = document.getElementById('ds_' + row_num + '_approximation');

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
				jQuery("#ds_" + row_num + "_stacked").prop("disabled", true);

				approximation_select.getOptionByValue(<?= APPROXIMATION_ALL ?>).disabled = true;
				if (approximation_select.value == <?= APPROXIMATION_ALL ?>) {
					approximation_select.value = <?= APPROXIMATION_AVG ?>;
				}
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
				jQuery("#ds_" + row_num + "_missingdatafunc_3").prop("disabled", true);
				jQuery("#ds_" + row_num + "_stacked").prop("disabled", true);

				approximation_select.getOptionByValue(<?= APPROXIMATION_ALL ?>).disabled = true;
				if (approximation_select.value == <?= APPROXIMATION_ALL ?>) {
					approximation_select.value = <?= APPROXIMATION_AVG ?>;
				}
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
				jQuery("#ds_" + row_num + "_stacked").prop("disabled", false);

				approximation_select.getOptionByValue(<?= APPROXIMATION_ALL ?>).disabled = false;

				if (jQuery(":checked", jQuery(obj)).val() == "<?= SVG_GRAPH_TYPE_STAIRCASE ?>") {
					jQuery("#ds_" + row_num + "_stacked").prop("disabled", true);

					approximation_select.getOptionByValue(<?= APPROXIMATION_ALL ?>).disabled = true;
					if (approximation_select.value == <?= APPROXIMATION_ALL ?>) {
						approximation_select.value = <?= APPROXIMATION_AVG ?>;
					}
				}
				break;
		}
	}

	changeDataSetAggregateFunction(obj) {
		const row_num = obj.id.replace("ds_", "").replace("_aggregate_function", "");
		const no_aggregation = (jQuery(obj).val() == <?= AGGREGATE_NONE ?>);

		jQuery("#ds_" + row_num + "_aggregate_interval").prop("disabled", no_aggregation);
		jQuery("#ds_" + row_num + "_aggregate_grouping_0").prop("disabled", no_aggregation);
		jQuery("#ds_" + row_num + "_aggregate_grouping_1").prop("disabled", no_aggregation);
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

		if (jQuery('.single-item-table[data-set=' + dataset_number + '] .single-item-table-row input[value=' + itemid + ']').length) {
			continue;
		}

		jQuery('.single-item-table[data-set='+dataset_number+'] tbody').append(tmpl.evaluate({
			dsNum: dataset_number,
			rowNum: size,
			name: name,
			itemid: itemid
		}));
		jQuery(`#items_${dataset_number}_${size}_color`).val(colorPalette.getNextColor());
		jQuery(`#items_${dataset_number}_${size}_color`).colorpicker();
	}

	widget_svggraph_form.rewriteNameLinks();
	widget_svggraph_form.initSingleItemSortable();
}
