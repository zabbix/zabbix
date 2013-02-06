/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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


jQuery(function($) {

	$.fn.multiSelect = function(options) {
		var url = new Curl('jsrpc.php');
		url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		url.setArgument('method', 'multiselect.get');
		url.setArgument('objectName', options.objectName);
		url = url.getUrl();

		return this.each(function() {
			var obj = $(this),
				objId = obj.attr('id'),
				isWaiting = false,
				jqxhr = null,
				values = {
					name: options.name,
					width: parseInt(obj.css('width')),
					selected: [],
					available: []
				};

			// search input
			var input = $('<input>', {
				type: 'text',
				value: '',
				css: {width: values.width}
			})
			.keyup(function(e) {
				var search = input.val();

				if (!empty(search) && input.data('lastSearch') != search) {
					input.data('lastSearch', search);

					if (!isWaiting) {
						isWaiting = true;

						window.setTimeout(function() {
							isWaiting = false;

							if (!empty(jqxhr)) {
								jqxhr.abort();
							}

							jqxhr = $.ajax({
								url: url + '&curtime=' + new CDate().getTime(),
								type: 'GET',
								dataType: 'json',
								data: {search: search},
								success: function(data) {
									loadAvailable(obj, values, data);
								}
							});
						}, 500);
					}
				}
			})
			.keypress(function(e) {
				if (e.which == 13) {
					cancelEvent(e);
				}
			});
			obj.append($('<div>', {style: 'position: relative;'}).append(input));

			// selected
			obj.append($('<div>', {
				'class': 'selected',
				css: {width: values.width}
			}).append($('<ul>')));

			// available
			var available = $('<div>', {
				'class': 'available',
				css: {
					width: values.width - 2,
					display: 'none'
				}
			})
			.append($('<ul>'))
			.focusout(function () {
				hideAvailable(obj);
			});
			obj.append($('<div>', {style: 'position: relative;'}).append(available));

			// preload data
			if (!empty(options.data)) {
				loadSelected(obj, values, options.data);
				loadAvailable(obj, values, {result: options.data});
			}
		});

		function loadSelected(obj, values, data) {
			$.each(data, function(i, item) {
				addSelected(obj, values, item);
			});
		};

		function addSelected(obj, values, item) {
			if (typeof(values.selected[item.id]) == 'undefined') {
				values.selected[item.id] = item.id;

				// add hidden input
				obj.append($('<input>', {
					type: 'hidden',
					name: values.name,
					value: item.id
				}));

				// add list item
				var li = $('<li>', {
					'data-id': item.id,
					'data-name': item.name
				});

				var text = $('<span>', {
					'class': 'text',
					text: empty(item.prefix) ? item.name : item.prefix + item.name
				});

				var arrow = $('<span>', {
					'class': 'arrow',
					'data-id': item.id
				})
				.click(function() {
					removeSelected(obj, values, $(this).data('id'));
				});

				$('.selected ul', obj).append(li.append(text, arrow));

				// resize
				resizeSelected(obj, values);
			}
		};

		function removeSelected(obj, values, id) {
			$('.selected li[data-id="' + id + '"]', obj).remove();
			$('input[value="' + id + '"]', obj).remove();
			values.selected[id] = 'undefined';

			// resize
			resizeSelected(obj, values);
		};

		function resizeSelected(obj, values) {
			var position = $('.selected li:last-child .arrow', obj).position(),
				top = position.top,
				left = position.left + 20,
				height = $('.selected li:last-child', obj).height();

			if (left > values.width) {
				top += (height / 2);
				left = 0;

				$('.selected ul', obj).css({
					'padding-bottom': height / 2
				});
			}
			else {
				$('.selected ul', obj).css({
					'padding-bottom': 0
				});
			}

			$('input[type="text"]', obj).css({
				'padding-top': top,
				'padding-left': left,
				width: values.width - left
			});
		};

		function loadAvailable(obj, values, data) {
			if (!empty(data.result)) {
				$.each(data.result, function(i, item) {
					addAvailable(obj, values, item);
				});

				showAvailable(obj);
			}
			else {
			}
		};

		function addAvailable(obj, values, item) {
			if (typeof(values.available[item.id]) == 'undefined') {
				values.available[item.id] = item.id;

				// add list item
				var li = $('<li>', {
					'data-id': item.id,
					'data-name': item.name,
					text: empty(item.prefix) ? item.name : item.prefix + item.name
				})
				.click(function() {
					removeAvailable(obj, values, $(this).data('id'));
				});

				$('.available ul', obj).append(li);
			}
		};

		function showAvailable(obj) {
			$('.selected ul', obj).css({
				'-webkit-box-shadow': '0 0 5px rgba(0, 0, 0, 0.3)',
				'-moz-box-shadow': '0 0 5px rgba(0, 0, 0, 0.3)',
				'box-shadow': '0 0 5px rgba(0, 0, 0, 0.3)',
				border: '1px solid #5897FB'
			});
			$('.available', obj).fadeIn(100);
		};

		function hideAvailable(obj) {
			$('.selected ul', obj).css({
				'-webkit-box-shadow': 'none',
				'-moz-box-shadow': 'none',
				'box-shadow': 'none',
				border: '1px solid #AAAAAA'
			});

			$('.available', obj).fadeOut(50);
		};
	};
});
