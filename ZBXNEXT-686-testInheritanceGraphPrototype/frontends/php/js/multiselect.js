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
	 * Multi select helper.
	 *
	 * @param objectName options['objectName']	backend data source
	 *
	 * @see jQuery.multiSelect()
	 */
	$.fn.multiSelectHelper = function(options) {
		// url
		options.url = new Curl('jsrpc.php');
		options.url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		options.url.setArgument('method', 'multiselect.get');
		options.url.setArgument('objectName', options.objectName);
		options.url = options.url.getUrl();

		// labels
		options.labels = {
			'No matches found': t('No matches found'),
			'More matches found...': t('More matches found...'),
			'type here to search': t('type here to search'),
			'new': t('new')
		};

		return this.each(function() {
			$(this).empty();
			$(this).multiSelect(options);
		});
	};

	/**
	 * Create multi select input element.
	 *
	 * @param string options['id']				multi select id in dom
	 * @param string options['url']				backend url
	 * @param string options['name']			input element name
	 * @param object options['labels']			translated labels
	 * @param object options['data']			preload data {id, name, prefix}
	 * @param string options['data'][id]
	 * @param string options['data'][name]
	 * @param string options['data'][prefix]
	 * @param array  options['ignored']			preload ignored {id: name}
	 * @param string options['defaultValue']	default value for input element
	 * @param bool   options['disabled']		turn on/off readonly state
	 * @param bool   options['addNew']			allow user to create new names
	 * @param int    options['selectedLimit']	how many items can be selected
	 * @param int    options['limit']			how many available items can be received from backend
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(options) {
		var defaults = {
			id: '',
			url: '',
			name: '',
			labels: {
				'No matches found': 'No matches found',
				'More matches found...': 'More matches found...',
				'type here to search': 'type here to search',
				'new': 'new'
			},
			data: [],
			ignored: [],
			addNew: false,
			defaultValue: null,
			disabled: false,
			selectedLimit: null,
			limit: 20
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
			/**
			 * Clean multi select object values.
			 */
			$.fn.multiSelect.clean = function(msId) {
				var ms = window.multiSelect[msId];

				for (var id in ms.values.selected) {
					removeSelected(id, ms.obj, ms.values, ms.options);
				}

				cleanAvailable(ms.obj, ms.values);
			};

			/**
			 * Get multi select selected data.
			 *
			 * @return array
			 */
			$.fn.multiSelect.getData = function() {
				var data = [];

				for (var id in values.selected) {
					data[data.length] = {
						id: id,
						name: $('input[value="' + id + '"]', obj).data('name'),
						prefix: $('input[value="' + id + '"]', obj).data('prefix')
					};
				}

				return data;
			};

			/**
			 * MultiSelect object.
			 */
			if (empty(window.multiSelect)) {
				window.multiSelect = {};
			}

			window.multiSelect[options.id] = {
				options: options,
				obj: $(this),
				jqxhr: null,
				values: {
					search: '',
					width: null,
					isWaiting: false,
					isAjaxLoaded: true,
					isMoreMatchesFound: false,
					isAvailableOpenned: false,
					selected: {},
					available: {},
					ignored: empty(options.ignored) ? [] : options.ignored
				}
			};

			var ms = window.multiSelect[options.id],
				obj = ms.obj,
				jqxhr = ms.jqxhr,
				values = ms.values;

			ms.values.width = parseInt(obj.css('width'));

			// search input
			if (!options.disabled) {
				var input = $('<input>', {
					'class': 'input',
					type: 'text',
					value: '',
					css: {width: values.width}
				})
				.on('keyup change', function(e) {
					if (e.which == KEY.ESCAPE) {
						cleanSearchInput(obj);
						return false;
					}

					if ($('.selected li', obj).length > 0
							&& $('.selected li', obj).length == options.selectedLimit) {
						setReadonly(obj);
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
					if (options.selectedLimit > 0) {
						if ($('.selected li', obj).length == 0) {
							$('.selected ul', obj).addClass('active');
						}
					}
					else {
						$('.selected ul', obj).addClass('active');
					}
				})
				.focusout(function() {
					$('.selected ul', obj).removeClass('active');
					cleanSearchInput(obj);
				});
				obj.append(input);
			}

			// selected
			var selectedDiv = $('<div>', {
				'class': 'selected'
			});
			var selectedUl = $('<ul>', {
				css: {width: values.width}
			});
			obj.append(selectedDiv.append(selectedUl));

			// available
			if (!options.disabled) {
				var available = $('<div>', {
					'class': 'available',
					css: {
						width: values.width + 1,
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
				obj.append(available)
				.focusout(function() {
					setTimeout(function() {
						if (!values.isAvailableOpenned && $('.available', obj).is(':visible')) {
							hideAvailable(obj);
						}
					}, 200);
				});
			}

			// preload data
			if (empty(options.data)) {
				setDefaultValue(obj, options);
				setPlaceholder(obj, options);
			}
			else {
				loadSelected(options.data, obj, values, options);
			}

			// resize
			resizeSelected(obj, values, options);
			resizeAvailable(obj);
		});
	};

	function setDefaultValue(obj, options) {
		if (!empty(options.defaultValue)) {
			obj.append($('<input>', {
				type: 'hidden',
				name: options.name,
				value: options.defaultValue,
				'data-default': 1
			}));
		}
	}

	function removeDefaultValue(obj, options) {
		if (!empty(options.defaultValue)) {
			$('input[data-default="1"]', obj).remove();
		}
	}

	function loadSelected(data, obj, values, options) {
		$.each(data, function(i, item) {
			addSelected(item, obj, values, options);
		});
	}

	function loadAvailable(data, obj, values, options) {
		cleanAvailable(obj, values);

		// add new
		if (options.addNew) {
			var value = values['search'].replace(/^\s+|\s+$/g, '');

			if (!empty(value)) {
				var addNew = false;

				if (!empty(data) || objectLength(values.selected) > 0) {
					// check if value exist in availables
					if (!empty(data)) {
						var names = {};

						$.each(data, function(i, item) {
							names[item.name.toUpperCase()] = true;
						});

						if (typeof(names[value.toUpperCase()]) == 'undefined') {
							addNew = true;
						}
					}

					// check if value exist in selected
					if (!addNew && objectLength(values.selected) > 0) {
						var names = {};

						$.each(values.selected, function(i, item) {
							if (typeof(item.isNew) == 'undefined') {
								names[item.name.toUpperCase()] = true;
							}
							else {
								names[item.id.toUpperCase()] = true;
							}
						});

						if (typeof(names[value.toUpperCase()]) == 'undefined') {
							addNew = true;
						}
					}
				}
				else {
					addNew = true;
				}

				if (addNew) {
					data[data.length] = {
						id: value,
						prefix: '',
						name: value + ' (' + options.labels['new'] + ')',
						isNew: true
					};
				}
			}
		}

		if (!empty(data)) {
			$.each(data, function(i, item) {
				addAvailable(item, obj, values, options);
			});
		}

		// write empty result label
		if (objectLength(values.available) == 0) {
			var div = $('<div>', {
				'class': 'label-empty-result',
				text: options.labels['No matches found']
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
				text: options.labels['More matches found...']
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
			removeDefaultValue(obj, options);

			values.selected[item.id] = item;

			// add hidden input
			obj.append($('<input>', {
				type: 'hidden',
				name: (options.addNew && item.isNew) ? options.name + '[new]' : options.name,
				value: item.id,
				'data-name': item.name,
				'data-prefix': item.prefix
			}));

			// add list item
			var li = $('<li>', {
				'data-id': item.id
			});

			var text = $('<span>', {
				'class': 'text',
				text: empty(item.prefix) ? item.name : item.prefix + item.name
			});

			if (options.disabled) {
				$('.selected ul', obj).append(li.append(text));
			}
			else {
				var arrow = $('<span>', {
					'class': 'arrow',
					'data-id': item.id
				})
				.click(function() {
					removeSelected($(this).data('id'), obj, values, options);
				});

				$('.selected ul', obj).append(li.append(text, arrow));
			}

			removePlaceholder(obj);

			// resize
			resizeSelected(obj, values, options);
			resizeAvailable(obj);

			// set readonly
			if (options.selectedLimit > 0 && $('.selected li', obj).length == options.selectedLimit) {
				setReadonly(obj);
			}
		}
	}

	function removeSelected(id, obj, values, options) {
		// remove
		$('.selected li[data-id="' + id + '"]', obj).remove();
		$('input[value="' + id + '"]', obj).remove();

		delete values.selected[id];

		// resize
		resizeSelected(obj, values, options);
		resizeAvailable(obj);

		// remove readonly
		if ($('.selected li', obj).length == 0) {
			setDefaultValue(obj, options);
			setPlaceholder(obj, options);

			if (options.selectedLimit > 0) {
				$('input[type="text"]', obj).prop('disabled', false);
			}
		}

		// clean
		cleanAvailable(obj, values);
		cleanLastSearch(obj);

		if (!$('input[type="text"]', obj).prop('disabled')) {
			$('input[type="text"]', obj).focus();
		}
	}

	function addAvailable(item, obj, values, options) {
		if (empty(options.limit) || (options.limit > 0 && $('.available li', obj).length < options.limit)) {
			if (typeof(values.available[item.id]) == 'undefined'
					&& typeof(values.selected[item.id]) == 'undefined'
					&& typeof(values.ignored[item.id]) == 'undefined') {
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

			if (!$('input[type="text"]', obj).prop('disabled')) {
				$('input[type="text"]', obj).focus();
			}
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
		var input = $('input[type="text"]', obj);

		if (!(IE && input.val() == input.attr('placeholder'))) {
			input.val('');
		}
	}

	function resizeSelected(obj, values, options) {
		// settings
		var settingTopPaddings = IE8 ? 1 : 0,
			settingTopPaddingsInit = 3,
			settingRightPaddings = 4,
			settingLeftPaddings = 13,
			settingMinimumWidth = 50,
			settingMinimumHeight = 12,
			settingNewLineTopPaddings = 6;

		// calculate
		var top = 0,
			left = 0,
			height = 0;

		if ($('.selected li', obj).length > 0) {
			var position = options.disabled
				? $('.selected li:last-child', obj).position()
				: $('.selected li:last-child .arrow', obj).position();

			top = position.top + settingTopPaddings - 1;
			left = position.left + settingLeftPaddings;
			height = $('.selected li:last-child', obj).height();
		}
		else {
			top = settingTopPaddingsInit + settingTopPaddings;
			height = 0;
		}

		if (SF) {
			top = top * 2;
		}

		if (left + settingMinimumWidth > values.width || height > settingMinimumHeight) {
			var topPaddings = (height > settingMinimumHeight) ? height / 2 : height;

			topPaddings += settingNewLineTopPaddings;

			if (SF) {
				topPaddings = topPaddings * 2;
			}

			top += topPaddings;
			left = 0;

			$('.selected ul', obj).css({
				'padding-bottom': topPaddings
			});
		}
		else {
			$('.selected ul', obj).css({
				'padding-bottom': 0
			});
		}

		if (IE) {
			$('input[type="text"]', obj).css({
				'padding-top': top,
				'padding-left': left
			});
		}
		else {
			$('input[type="text"]', obj).css({
				'padding-top': top,
				'padding-left': left,
				width: values.width - left - settingRightPaddings
			});
		}
	}

	function resizeAvailable(obj) {
		var selectedHeight = $('.selected', obj).height();

		$('.available', obj).css({
			top: (selectedHeight > 0) ? selectedHeight : 20
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

	function setReadonly(obj) {
		cleanSearchInput(obj);
		$('input[type="text"]', obj).prop('disabled', true);
		$('.selected ul', obj).removeClass('active');
	}

	function setPlaceholder(obj, options) {
		$('input[type="text"]', obj).attr('placeholder', options.labels['type here to search']);

		createPlaceholders();
	}

	function removePlaceholder(obj) {
		$('input[type="text"]', obj).removeAttr('placeholder');
	}

	function getLimit(values, options) {
		return (options.limit > 0)
			? options.limit + countMatches(values.selected, values.search) + countMatches(values.ignored, values.search) + 1
			: null;
	}

	function countMatches(data, search) {
		var count = 0;

		if (empty(data)) {
			return count;
		}

		for (var id in data) {
			var name = (typeof(data[id]) == 'object') ? data[id].name : data[id];

			if (name.substr(0, search.length).toUpperCase() == search.toUpperCase()) {
				count++;
			}
		}

		return count;
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
