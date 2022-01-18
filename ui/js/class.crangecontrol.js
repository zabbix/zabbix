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
 * JQuery class that creates input[type=range] UI element from given input[type=text] elements and adds some
 * interactivity for better user experience.
 */
jQuery(function ($) {
	"use strict";

	function generateRangeElements(datalist, options) {
		var value;

		for (value = options.min; value < options.max; value += options.step) {
			$('<option>', {value: value}).appendTo(datalist);
		}
	}

	var methods = {
		/**
		 * Initializes range control for passed input[type=text] elements.
		 *
		 * Following options should/must be defined in [data-options] attribute (json expected):
		 *  - step - specifies what is a step in specified min-max range (optional).
		 *  - min - specifies minimal value of range (mandatory).
		 *  - max - specifies maximal value of range (mandatory).
		 */
		init: function() {
			var tmpl = $('<div class="range-control">' +
					'<div>' +
						'<div class="range-control-track"></div>' +
						'<div class="range-control-progress"></div>' +
						'<datalist></datalist>' +
						'<div class="range-control-thumb"></div>' +
						'<input type="range">' +
					'</div>' +
				'</div>');

			return $(this).each(function(_, input) {
				var options = $(this).data('options'),
					datalistid = (new Date()).getTime().toString(34),
					$input = $(input),
					$control = tmpl.clone(),
					$range = $control.find('[type=range]'),
					$datalist = $control.find('datalist').attr('id', datalistid),
					$progress = $control.find('.range-control-progress'),
					$thumb = $control.find('.range-control-thumb'),
					updateHandler = function() {
						var value = $range.val(),
							shift = ((value - options.min) * 100 / (options.max - options.min));

						$input.val(value);
						$progress.css({width: shift + '%'});
						$thumb.css({left: shift + '%'});
					};

				generateRangeElements($datalist, options);

				$(this).removeAttr('data-options');

				$range
					.attr({
						'list': datalistid,
						'min': options.min,
						'max': options.max,
						'step': options.step,
						'value': $input.val()
					})
					.prop('disabled', $input.prop('disabled'))
					.change(updateHandler)
					.focus(function() {
						$control.addClass("range-control-focus");
						$(document).on("mousemove", updateHandler);
					})
					.blur(function() {
						$control.removeClass("range-control-focus");
						$(document).off("mousemove", updateHandler);
					})
					.change();

				if ($input.prop('disabled')) {
					$control.addClass('disabled');
				}

				$control
					.width(options.width)
					.insertBefore($input);

				$input
					.change(function() {$range.val(this.value); updateHandler();})
					.appendTo($control);
			});
		},
		disable: function() {
			var $input = $(this),
				$range = $input.parent().find('[type=range]');

			$input.parent().addClass('disabled');
			$input.prop('disabled', true);
			$range.prop('disabled', true);
		},
		enable: function() {
			var $input = $(this),
				$range = $input.parent().find('[type=range]');

			$input.parent().removeClass('disabled');
			$input.prop('disabled', false);
			$range.prop('disabled', false);
		}
	};

	$.fn.rangeControl = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
});
