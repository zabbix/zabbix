/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
	 * @param string options['url']
	 * @param string options['name']
	 * @param int    options['limit']
	 * @param bool   options['disabled']
	 * @param object options['labels']
	 * @param object options['data']
	 * @param string options['data'][id]
	 * @param string options['data'][name]
	 * @param string options['data'][prefix]
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(options) {
		var defaults = {
			url: '',
			name: '',
			limit: null,
			data: {},
			disabled: false,
			labels: {
				emptyResult: 'No matches found',
				moreMatchesFound: 'More matches found...'
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
				jqxhr = null,
				values = {
					search: '',
					width: parseInt(obj.css('width')),
					isWaiting: false,
					isAjaxLoaded: true,
					isMoreMatchesFound: false,
					isAvailableOpenned: false,
					selected: {},
					available: {}
				};

			// search input
			if (!options.disabled) {
				var input = $('<input>', {
					type: 'text',
					value: '',
					css: {width: values.width}
				})
				.on('keyup change', function(e) {
					if (e.which == KEY.ESCAPE) {
						cleanSearchInput(obj);
						return false;
					}

					var search = input.val();

					if (!empty(search)) {
						if (input.data('lastSearch') != search) {
							if (!values.isWaiting) {
								values.isWaiting = true;

								window.setTimeout(function() {
									values.isWaiting = false;

									var search = input.val();

									// re-check search after delay
									if (!empty(search) && input.data('lastSearch') != search) {
										values.search = search;

										input.data('lastSearch', values.search);

										if (!empty(jqxhr)) {
											jqxhr.abort();
										}

										values.isAjaxLoaded = false;

										jqxhr = $.ajax({
											url: options.url + '&curtime=' + new CDate().getTime(),
											type: 'GET',
											dataType: 'json',
											cache: false,
											data: {
												search: values.search,
												limit: getLimit(values, options)
											},
											success: function(data) {
												values.isAjaxLoaded = true;

												loadAvailable(data.result, obj, values, options);
											}
										});
									}
								}, 500);
							}
						}
						else {
							if ($('.available', obj).is(':hidden')) {
								showAvailable(obj, values);
							}
						}
					}
					else {
						hideAvailable(obj);
					}
				})
				.on('keypress keydown', function(e) {
					switch (e.which) {
						case KEY.ENTER:
							var availableActive = $('.available li.hover', obj);

							if (availableActive.length > 0) {
								select(availableActive.data('id'), obj, values, options);
							}

							// stop form submit
							cancelEvent(e);
							return false;

						case KEY.BACKSPACE:
						case KEY.ARROW_LEFT:
						case KEY.DELETE:
							if (empty(input.val())) {
								if (e.which == KEY.DELETE && $('.selected li.pressed', obj).length == 0) {
									return;
								}

								if ($('.selected li', obj).length > 0) {
									if ($('.selected li.pressed', obj).length > 0) {
										if (e.which == KEY.BACKSPACE || e.which == KEY.DELETE) {
											removeSelected($('.selected li.pressed', obj).data('id'), obj, values, options);
										}
										else {
											var prev = $('.selected li.pressed', obj).removeClass('pressed').prev();

											if (prev.length > 0) {
												prev.addClass('pressed');
											}
											else {
												$('.selected li:first-child', obj).addClass('pressed');
											}
										}
									}
									else {
										$('.selected li:last-child', obj).addClass('pressed');
									}

									hideAvailable(obj);
								}
							}
							break;

						case KEY.ARROW_RIGHT:
							if ($('.selected li.pressed', obj).length > 0) {
								var next = $('.selected li.pressed', obj).removeClass('pressed').next();

								if (next.length > 0) {
									next.addClass('pressed');
								}
							}
							break;

						case KEY.ARROW_UP:
							if ($('.available', obj).is(':visible') && $('.available li', obj).length > 0) {
								if ($('.available li.hover', obj).length > 0) {
									var prev = $('.available li.hover', obj).removeClass('hover').prev();

									if (prev.length > 0) {
										prev.addClass('hover');
									}
									else {
										$('.available li:last-child', obj).addClass('hover');
									}

									scrollAvailable(obj);
								}
								else {
									$('.available li:last-child', obj).addClass('hover');
								}
							}
							break;

						case KEY.ARROW_DOWN:
							if ($('.available', obj).is(':visible') && $('.available li', obj).length > 0) {
								if ($('.available li.hover', obj).length > 0) {
									var next = $('.available li.hover', obj).removeClass('hover').next();

									if (next.length > 0) {
										next.addClass('hover');
									}
									else {
										$('.available li:first-child', obj).addClass('hover');
									}

									scrollAvailable(obj);
								}
								else {
									$('.available li:first-child', obj).addClass('hover');
								}
							}
							break;

						case KEY.TAB:
						case KEY.ESCAPE:
							hideAvailable(obj);
							cleanSearchInput(obj);
							break;
					}
				})
				.focusin(function() {
					$('.selected ul', obj).addClass('active');
				})
				.focusout(function() {
					$('.selected ul', obj).removeClass('active');
					cleanSearchInput(obj);
				});
				obj.append($('<div>', {style: 'position: relative;'}).append(input));
			}

			// selected
			obj.append($('<div>', {
				'class': 'selected',
				css: {width: values.width}
			}).append($('<ul>')));

			// available
			if (!options.disabled) {
				var available = $('<div>', {
					'class': 'available',
					css: {
						width: values.width - 2,
						display: 'none'
					}
				})
				.append($('<ul>'))
				.mouseenter(function() {
					values.isAvailableOpenned = true;
				})
				.mouseleave(function() {
					values.isAvailableOpenned = false;
				});

				// multi select
				obj.append($('<div>', {style: 'position: relative;'}).append(available))
				.focusout(function() {
					setTimeout(function() {
						if (!values.isAvailableOpenned && $('.available', obj).is(':visible')) {
							hideAvailable(obj);
						}
					}, 200);
				});
			}

			// preload data
			if (!empty(options.data)) {
				loadSelected(options.data, obj, values, options);
			}
			else {
				// resize
				resizeSelected(obj, values);
			}
		});
	};

	function loadSelected(data, obj, values, options) {
		$.each(data, function(i, item) {
			addSelected(item, obj, values, options);
		});
	}

	function loadAvailable(data, obj, values, options) {
		cleanAvailable(obj, values);

		if (!empty(data)) {
			$.each(data, function(i, item) {
				addAvailable(item, obj, values, options);
			});
		}

		// write empty result label
		if (objectLength(values.available) == 0) {
			var div = $('<div>', {
				'class': 'label-empty-result',
				text: options.labels.emptyResult
			})
			.click(function() {
				$('input[type="text"]', obj).focus();
			});

			$('.available', obj).append(div);
		}

		// write more matches found label
		if (values.isMoreMatchesFound) {
			var div = $('<div>', {
				'class': 'label-more-matches-found',
				text: options.labels.moreMatchesFound
			})
			.click(function() {
				$('input[type="text"]', obj).focus();
			});

			$('.available', obj).prepend(div);
		}

		showAvailable(obj, values);
	}

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
				'data-id': item.id
			});

			var text = $('<span>', {
				'class': 'text',
				text: empty(item.prefix) ? item.name : item.prefix + item.name
			});

			if (!options.disabled) {
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
			else {
				$('.selected ul', obj).append(li.append(text));
			}
		}
	}

	function removeSelected(id, obj, values, options) {
		// remove
		$('.selected li[data-id="' + id + '"]', obj).remove();
		$('input[value="' + id + '"]', obj).remove();

		delete values.selected[id];

		// resize
		resizeSelected(obj, values);

		// clean
		cleanAvailable(obj, values);
		cleanLastSearch(obj);
		$('input[type="text"]', obj).focus();
	}

	function addAvailable(item, obj, values, options) {
		if (empty(options.limit) || (options.limit > 0 && $('.available li', obj).length < options.limit)) {
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
					select($(this).data('id'), obj, values, options);
				})
				.hover(function() {
					$('.available li.hover', obj).removeClass('hover');
					li.addClass('hover');
				})
				.append(prefix, matchedText, unMatchedText);

				$('.available ul', obj).append(li);
			}
		}
		else {
			values.isMoreMatchesFound = true;
		}
	}

	function select(id, obj, values, options) {
		if (values.isAjaxLoaded && !values.isWaiting) {
			addSelected(values.available[id], obj, values, options);
			hideAvailable(obj);
			cleanAvailable(obj, values);
			cleanLastSearch(obj);

			$('input[type="text"]', obj).focus();
		}
	}

	function showAvailable(obj, values) {
		if ($('.label-empty-result', obj).length > 0 || objectLength(values.available) > 0) {
			$('.selected ul', obj).addClass('active');
			$('.available', obj).fadeIn(0);

			// remove selected item pressed state
			$('.selected li.pressed', obj).removeClass('pressed');

			// pre-select first available
			$('.available li.hover', obj).removeClass('hover');
			$('.available li:first-child', obj).addClass('hover');
		}
	}

	function hideAvailable(obj) {
		$('.available', obj).fadeOut(0);
	}

	function cleanAvailable(obj, values) {
		$('.label-empty-result', obj).remove();
		$('.label-more-matches-found', obj).remove();
		$('.available li', obj).remove();
		values.available = {};
		values.isMoreMatchesFound = false;
	}

	function cleanLastSearch(obj) {
		var input = $('input[type="text"]', obj);

		input.data('lastSearch', '');
		input.val('');
	}

	function cleanSearchInput(obj) {
		$('input[type="text"]', obj).val('');
	}

	function resizeSelected(obj, values) {
		// settings
		var searchInputMinWidth = 50,
			searchInputLeftPaddings = 4,
			searchInputRightPaddings = 4,
			searchInputTopPaddings = IE8 ? 4 : 0;

		// calculate
		var top, left, height;

		if ($('.selected li', obj).length > 0) {
			var position = $('.selected li:last-child .arrow', obj).position();
			top = position.top + searchInputTopPaddings;
			left = position.left + 20;
			height = $('.selected li:last-child', obj).height();
		}
		else {
			top = 3 + searchInputTopPaddings;
			left = searchInputLeftPaddings;
			height = 0;
		}

		if (SF) {
			top = top * 2;
		}

		if (left + searchInputMinWidth > values.width) {
			var topPaddings = (height > 20) ? height / 2 : height;

			if (SF) {
				topPaddings = topPaddings * 2;
			}

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
			width: values.width - left - searchInputRightPaddings
		});
	}

	function scrollAvailable(obj) {
		var hover = $('.available li.hover', obj);

		if (hover.length > 0) {
			var available = $('.available ul', obj),
				offset = hover.position().top;

			if (offset < 0 || offset > available.height()) {
				if (offset < 0) {
					offset = 0;
				}

				available.animate({scrollTop: offset}, 300);
			}
		}
	}

	function getLimit(values, options) {
		return (options.limit > 0)
			? options.limit + objectLength(values.selected) + 1
			: null;
	}

	function objectLength(obj) {
		var length = 0;

		for (var key in obj) {
			if (obj.hasOwnProperty(key)) {
				length++;
			}
		}

		return length;
	}
});
