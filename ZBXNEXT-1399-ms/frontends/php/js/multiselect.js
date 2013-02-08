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

	/**
	 * Create multi select input element.
	 *
	 * @param string $options['url']
	 * @param string $options['name']
	 * @param bool   $options['disabled']
	 * @param object $options['labels']
	 * @param object $options['data']
	 * @param string $options['data'][id]
	 * @param string $options['data'][name]
	 * @param string $options['data'][prefix]
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(options) {
		var defaults = {
			url: '',
			name: '',
			data: {},
			disabled: false,
			labels: {
				emptyResult: 'No matches found'
			}
		};
		options = $.extend({}, defaults, options);

		var KEY = {
			ARROW_DOWN: 40,
			ARROW_LEFT: 37,
			ARROW_RIGHT: 39,
			ARROW_UP: 38,
			BACKSPACE: 8,
			DELETE: 46,
			ENTER: 13,
			ESCAPE: 27,
			TAB: 9
		};

		return this.each(function() {
			var obj = $(this),
				objId = obj.attr('id'),
				isWaiting = false,
				jqxhr = null,
				values = {
					search: '',
					width: parseInt(obj.css('width')),
					selected: {},
					available: {},
					availableIds: {} // for sorting
				};

			// search input
			var input = $('<input>', {
				type: 'text',
				value: '',
				css: {width: values.width}
			})
			.keyup(function(e) {
				if (!isWaiting) {
					isWaiting = true;

					window.setTimeout(function() {
						isWaiting = false;

						values.search = input.val();

						if (!empty(values.search)) {
							if (input.data('lastSearch') != values.search) {
								input.data('lastSearch', values.search);

								if (!empty(jqxhr)) {
									jqxhr.abort();
								}

								jqxhr = $.ajax({
									url: options.url + '&curtime=' + new CDate().getTime(),
									type: 'GET',
									dataType: 'json',
									data: {search: values.search},
									success: function(data) {
										loadAvailable(data.result, obj, values, options);
									}
								});
							}
						}
						else {
							input.data('lastSearch', '');
							cleanAvailable(obj, values);
							hideAvailable(obj);
						}
					}, 500);
				}
			})
			.bind('keypress, keydown', function (e) {
				switch (e.which) {
					case KEY.ENTER:
						// break form submit
						cancelEvent(e);

						var availableActive = $('.available li.hover', obj).get(0);

						if (availableActive) {
							var id = $(availableActive).data('id');

							addSelected(values.available[id], obj, values, options);
							removeAvailable(id, obj, values);
						}
						break;

					case KEY.BACKSPACE:
					case KEY.ARROW_LEFT:
					case KEY.DELETE:
						if (empty(input.val())) {
							if ($('.selected li', obj).length > 0) {
								var lastItem = $('.selected li:last-child', obj);

								if (lastItem.hasClass('pressed')) {
									if (e.which == KEY.BACKSPACE || e.which == KEY.DELETE) {
										removeSelected(lastItem.data('id'), obj, values, options);
									}
								}
								else {
									$('.selected li:last-child', obj).addClass('pressed');
								}

								cancelEvent(e);
							}
						}
						break;

					case KEY.ARROW_RIGHT:
						// remove selected item pressed state
						if (empty(input.val())) {
							$('.selected li:last-child', obj).removeClass('pressed');
						}
						break;

					case KEY.ARROW_UP:
						showAvailable(obj, values);

						// move hover
						if ($('.available li.hover', obj).length > 0) {
							$('.available li.hover', obj).removeClass('hover').prev().addClass('hover');

							scrollAvailable(obj);
						}
						else {
							$('.available li:last-child', obj).addClass('hover');
						}
						break;

					case KEY.ARROW_DOWN:
						showAvailable(obj, values);

						// move hover
						if ($('.available li.hover', obj).length > 0) {
							$('.available li.hover', obj).removeClass('hover').next().addClass('hover');

							scrollAvailable(obj);
						}
						else {
							$('.available li:first-child', obj).addClass('hover');
						}

						break;

					case KEY.TAB:
					case KEY.ESCAPE:
						hideAvailable(obj);
						break;
				}
			})
			.click(function () {
				showAvailable(obj, values);
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

			// multi select
			obj.append($('<div>', {style: 'position: relative;'}).append(available))
			.focusout(function () {
				setTimeout(function() {
					if ($('.available', obj).is(':visible')) {
						hideAvailable(obj);
					}
				}, 200);
			});

			// preload data
			if (!empty(options.data)) {
				loadSelected(options.data, obj, values, options);
			}
		});

		function loadSelected(data, obj, values, options) {
			$.each(data, function(i, item) {
				addSelected(item, obj, values, options);
			});
		};

		function loadAvailable(data, obj, values, options) {
			cleanAvailable(obj, values);

			if (!empty(data)) {
				$.each(data, function(i, item) {
					addAvailable(item, obj, values, options);
				});
			}

			if (objectLength(values.available) == 0) {
				$('.available', obj).append($('<div>', {
					'class': 'label-empty-result',
					text: options.labels.emptyResult
				}));
			}

			showAvailable(obj, values);
		};

		function addSelected(item, obj, values, options) {
			if (typeof(values.selected[item.id]) == 'undefined') {
				values.selected[item.id] = item;

				// add hidden input
				obj.append($('<input>', {
					type: 'hidden',
					name: options.name,
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
					removeSelected($(this).data('id'), obj, values, options);
				});

				$('.selected ul', obj).append(li.append(text, arrow));

				// resize
				resizeSelected(obj, values);
			}
		};

		function removeSelected(id, obj, values, options) {
			var item = values.selected[id];

			$('.selected li[data-id="' + id + '"]', obj).remove();
			$('input[value="' + id + '"]', obj).remove();

			delete values.selected[id];

			resizeSelected(obj, values);

			// try return value to available
			if (typeof(values.availableIds[id] != 'undefined')) {
				addAvailable(item, obj, values, options);
			}
		};

		function addAvailable(item, obj, values, options) {
			if (typeof(values.available[item.id]) == 'undefined' && typeof(values.selected[item.id]) == 'undefined') {
				values.available[item.id] = item;

				var prefix = $('<span>', {
					'class': 'prefix',
					text: item.prefix
				});

				var matchedText = $('<span>', {
					'class': 'matched',
					text: item.name.substr(0, values.search.length)
				});

				var unMatchedText = $('<span>', {
					text: item.name.substr(values.search.length, item.name.length)
				});

				var li = $('<li>', {
					'data-id': item.id
				})
				.click(function() {
					var id = $(this).data('id');

					addSelected(values.available[id], obj, values, options);
					removeAvailable(id, obj, values);
				})
				.hover(
					function() {
						$('.available li.hover', obj).removeClass('hover');
						li.addClass('hover');
					},
					function() {
						$('.available li.hover', obj).removeClass('hover');
					}
				)
				.append(prefix, matchedText, unMatchedText);

				// insert item
				if (typeof(values.availableIds[item.id]) == 'undefined') {
					values.availableIds[item.id] = 'isPresented';

					$('.available ul', obj).append(li);
				}
				else {
					var insertAfterId = null;

					for (var id in values.availableIds) {
						if (values.availableIds[id] == 'isPresented') {
							insertAfterId = id;
						}

						if (item.id == id) {
							break;
						}
					}

					if (empty(insertAfterId)) {
						$('.available ul', obj).prepend(li);
					}
					else {
						$('.available li[data-id="' + insertAfterId + '"]', obj).after(li);
					}
				}
			}
		};

		function removeAvailable(id, obj, values) {
			$('.available li[data-id="' + id + '"]', obj).remove();

			values.availableIds[id] = 'isRemoved';
			delete values.available[id];

			if ($('.available li', obj).length == 0) {
				hideAvailable(obj);
			}
		};

		function showAvailable(obj, values) {
			if ($('.label-empty-result', obj).length > 0 || objectLength(values.available) > 0) {
				$('.selected ul', obj).addClass('active');
				$('.available', obj).fadeIn(0);

				// remove selected item pressed state
				$('.selected li:last-child', obj).removeClass('pressed');
			}
		};

		function hideAvailable(obj) {
			$('.selected ul', obj).removeClass('active');
			$('.available', obj).fadeOut(0);
		};

		function cleanAvailable(obj, values) {
			$('.label-empty-result', obj).remove();
			$('.available li', obj).remove();
			values.available = {};
			values.availableIds = {};
		};

		function resizeSelected(obj, values) {
			// settings
			var searchInputMinWidth = 50,
				searchInputLeftPaddings = 4,
				searchInputTopPaddings = IE8 ? 7 : 0;

			// calculate
			var top, left, height;

			if ($('.selected li', obj).length > 0) {
				var position = $('.selected li:last-child .arrow', obj).position();
				top = position.top - 1 + searchInputTopPaddings;
				left = position.left + 20;
				height = $('.selected li:last-child', obj).height();
			}
			else {
				top = 2 + searchInputTopPaddings;
				left = searchInputLeftPaddings;
				height = 0;
			}

			if (left + searchInputMinWidth > values.width) {
				var topPaddings = (height > 20) ? height / 2 : height;

				top += topPaddings;
				left = searchInputLeftPaddings;

				$('.selected ul', obj).css({
					'padding-bottom': topPaddings
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

		function scrollAvailable(obj) {
			var hover = $('.available li.hover', obj);

			if (hover.length > 0) {
				var available = $('.available ul', obj),
					offset = hover.position().top;

				if (offset < 0 || offset > available.height()) {
					if (offset < 0) {
						offset = available.scrollTop() + offset;
					}

					available.animate({ scrollTop: offset }, 300);
				}
			}
		}

		function objectLength(obj) {
			var count = 0;

			for (var key in obj) {
				if (obj.hasOwnProperty(key)) {
					count++;
				}
			}

			return count;
		};
	};
});
