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
		this.form_id = form_id;
		this.form_tabs = form_tabs_id;

		//jQuery(".overlay-dialogue-body").on("scroll", function() {
		//	if (jQuery("#svg-graph-preview").length) {
		//		const $dialogue_body = jQuery(this);
		//		const $preview_container = jQuery(".<?//= ZBX_STYLE_SVG_GRAPH_PREVIEW ?>//");
		//
		//		if ($preview_container.offset().top < $dialogue_body.offset().top && $dialogue_body.height() > 400) {
		//			jQuery("#svg-graph-preview").css("top", $dialogue_body.offset().top - $preview_container.offset().top);
		//			jQuery(".graph-widget-config-tabs .ui-tabs-nav").css("top", $preview_container.height());
		//		}
		//		else {
		//			jQuery("#svg-graph-preview").css("top", 0);
		//			jQuery(".graph-widget-config-tabs .ui-tabs-nav").css("top", 0);
		//		}
		//	}
		//	else {
		//		jQuery(".overlay-dialogue-body").off("scroll");
		//	}
		//});

		//jQuery(`#${this.form_tabs}`).on("change", "input, z-select, .multiselect", () => this.onGraphConfigChange());
		//
		//
		//this.onGraphConfigChange();
		//
		//$(".overlay-dialogue").on("overlay-dialogue-resize", (event, size_new, size_old) => {
		//	if (jQuery("#svg-graph-preview").length) {
		//		if (size_new.width != size_old.width) {
		//			this.onGraphConfigChange();
		//		}
		//	} else {
		//		$(".overlay-dialogue").off("overlay-dialogue-resize");
		//	}
		//});

		document.querySelector('.js-add-item').addEventListener('click', () => this._selectItems());

		document
			.getElementById('dataset-menu')
			.addEventListener('click', this._addDataset);
	}



	_selectItems() {
		const overlay = PopUp("popup.generic", {
			srctbl: 'items',
			srcfld1: 'itemid',
			srcfld2: 'name',
			dstfrm: 'graphForm',
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

	_addDataset(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item pattern')) ?>,
						clickCallback: (event) => {
							//debugger;
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => ZABBIX.Dashboard.addNewDashboardPage()
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						clickCallback: () => ZABBIX.Dashboard.addNewDashboardPage()
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
};
