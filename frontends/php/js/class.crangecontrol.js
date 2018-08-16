/*
 ** Zabbix
 ** Copyright (C) 2001-2018 Zabbix SIA
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

	function getRange(start, end, step) {
		return (new Array(Math.round((end - start) / step))).join(0).split(0).map(function(_, i) {
			return start + i * step;
		});
	}

	function generateRangeElements(elm, range) {
		$.map(range, function(value) {
			$('<option/>').attr('value', value).appendTo(elm);
		});
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
					'<div class="range-control-track"/>' +
					'<div class="range-control-progress"/>' +
					'<datalist/>' +
					'<div class="range-control-thumb"/>' +
					'<input type="range"/>' +
				'</div>');

			return $(this).each(function(_, input) {
				var options = $(this).data('options'),
					listid = (new Date()).getTime().toString(34),
					$input = $(input),
					$control = tmpl.clone(),
					$range = $control.find('[type=range]'),
					step = options.step || 1,
					min = options.min,
					max = options.max,
					range = getRange(min, max, step),
					interval = Math.ceil((max - min) / step) * step,
					$datalist = $control.find('datalist').attr('id', listid),
					$progress = $control.find('.range-control-progress'),
					$thumb = $control.find('.range-control-thumb'),
					updateHandler = function() {
						var value = $range.val(),
							shift = ((value - min) * 100 / interval);

						$input.val(value);
						$progress.css({width: shift + '%'});
						$thumb.css({left: shift + '%'});
					};

				if (range) {
					generateRangeElements($datalist, range);
				}

				$(this).removeAttr('data-options');

				$range
					.attr({
						'list': listid,
						'min': min,
						'max': max,
						'step': step,
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

				$input
					.change(function() {$range.val(this.value); updateHandler();})
					.before($control);
			});
		},
		disable: function() {
			var $input = $(this),
				$range = $input.prev('.range-control').find('[type=range]');

			$input.prev('.range-control').addClass('disabled');
			$input.prop('disabled', true);
			$range.prop('disabled', true);
		},
		enable: function() {
			var $input = $(this),
				$range = $input.prev('.range-control').find('[type=range]');

			$input.prev('.range-control').removeClass('disabled');
			$input.prop('disabled', false);
			$range.prop('disabled', false);
		}
	};

	$.fn.rangeControl = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else {
			return methods.init.apply(this, arguments);
		}
	};
});
