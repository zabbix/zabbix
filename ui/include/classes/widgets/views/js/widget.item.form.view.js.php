<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

window.widget_item_form = {

	init() {
		$(".<?= ZBX_STYLE_COLOR_PICKER ?> input", $(".overlay-dialogue-body"))
			.colorpicker({appendTo: ".overlay-dialogue-body", use_default: true,
				onUpdate: this.events.setIndicatorColor
			});

		const $show = $('input[id^="show_"]', "#widget-dialogue-form").not("#show_header");

		$("#adv_conf").on("change", function() {
			$show.trigger("change");

			$("#bg-color-row")
				.toggle(this.checked)
				.find("input")
				.prop("disabled", !this.checked);
		});

		// Prevent unchecking last "Show" checkbox.
		$show.on("click", function() {
			return $show.filter(":checked").length > 0;
		});

		$show.on("change", function() {
			const adv_conf_checked = $("#adv_conf").prop("checked");

			switch($(this).val()) {
				case "<?= WIDGET_ITEM_SHOW_DESCRIPTION ?>":
					$("#description-row")
						.toggle(adv_conf_checked && this.checked)
						.find("input, textarea")
						.prop("disabled", !adv_conf_checked || !this.checked);
					break;

				case "<?= WIDGET_ITEM_SHOW_VALUE ?>":
					$("#value-row")
						.toggle(adv_conf_checked && this.checked)
						.find("input")
						.prop("disabled", !adv_conf_checked || !this.checked);
					break;

				case "<?= WIDGET_ITEM_SHOW_TIME ?>":
					$("#time-row")
						.toggle(adv_conf_checked && this.checked)
						.find("input")
						.prop("disabled", !adv_conf_checked || !this.checked);
					break;

				case "<?= WIDGET_ITEM_SHOW_CHANGE_INDICATOR ?>":
					$("#change-indicator-row")
						.toggle(adv_conf_checked && this.checked)
						.find("input")
						.prop("disabled", !adv_conf_checked || !this.checked);
					break;
			}
		});

		$("#adv_conf").trigger("change");

		$("#units_show").on("change", function() {
			$("#units, #units_pos, #units_size, #units_bold, #units_color").prop("disabled", !this.checked);
		});

		$("#units_show").trigger("change");
	},

	events: {
		setIndicatorColor(color) {
			const indicator_ids = {
				up_color: 'change-indicator-up',
				down_color: 'change-indicator-down',
				updown_color: 'change-indicator-updown'
			};

			if (this.name in indicator_ids) {
				const indicator = document.getElementById(indicator_ids[this.name]);

				indicator.querySelector("polygon").style.fill = (color !== "") ? "#" + color : "";
			}
		}
	}
};
