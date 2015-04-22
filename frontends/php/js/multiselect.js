/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	 * @param string options['objectName']		backend data source
	 * @param object options['objectOptions']	parameters to be added the request URL (optional)
	 *
	 * @see jQuery.multiSelect()
	 */
	$.fn.multiSelectHelper = function(options) {
		options = $.extend({objectOptions: {}}, options);

		// url
		options.url = new Curl('jsrpc.php');
		options.url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		options.url.setArgument('method', 'multiselect.get');
		options.url.setArgument('objectName', options.objectName);

		for (var key in options.objectOptions) {
			options.url.setArgument(key, options.objectOptions[key]);
		}

		options.url = options.url.getUrl();

		// labels
		options.labels = {
			'No matches found': t('No matches found'),
			'More matches found...': t('More matches found...'),
			'type here to search': t('type here to search'),
			'new': t('new'),
			'Select': t('Select')
		};

		return this.each(function() {
			$(this).empty().multiSelect(options);
		});
	};

	/*
	 * Multiselect methods
	 */
	var methods = {
		/**
		 * Get multi select selected data.
		 *
		 * @return array    array of multiselect value objects
		 */
		getData: function() {
			var ms = this.first().data('multiSelect');

			var data = [];
			for (var id in ms.values.selected) {
				var item = ms.values.selected[id];

				data[data.length] = {
					id: id,
					name: item.name,
					prefix: item.prefix === 'undefined' ? '' : item.prefix
				};
			}

			return data;
		},

		/**
		 * Rezise multiselect selected text
		 *
		 * @return jQuery
		 */
		resize: function() {
			return this.each(function() {
				var obj = $(this);
				var ms = $(this).data('multiSelect');

				resizeAllSelectedTexts(obj, ms.options, ms.values);
			});
		},

		/**
		 * Insert outside data
		 *
		 * @param object    multiselect value object
		 *
		 * @return jQuery
		 */
		addData: function(item) {
			return this.each(function() {
				var obj = $(this);
				var ms = $(this).data('multiSelect');

				// clean input if selectedLimit == 1
				if (ms.options.selectedLimit == 1) {
					for (var id in ms.values.selected) {
						removeSelected(id, obj, ms.values, ms.options);
					}

					cleanAvailable(item, ms.values);
				}
				addSelected(item, obj, ms.values, ms.options);
			});
		},

		/**
		 * Clean multi select object values.
		 *
		 * @return jQuery
		 */
		clean: function() {
			return this.each(function() {
				var obj = $(this);
				var ms = $(this).data('multiSelect');

				for (var id in ms.values.selected) {
					removeSelected(id, obj, ms.values, ms.options);
				}

				cleanAvailable(obj, ms.values);
			});
		}
	};

	/**
	 * Create multi select input element.
	 *
	 * @param string options['url']					backend url
	 * @param string options['name']				input element name
	 * @param object options['labels']				translated labels (optional)
	 * @param object options['data']				preload data {id, name, prefix} (optional)
	 * @param string options['data'][id]
	 * @param string options['data'][name]
	 * @param string options['data'][prefix]		(optional)
	 * @param array  options['ignored']				preload ignored {id: name} (optional)
	 * @param string options['defaultValue']		default value for input element (optional)
	 * @param bool   options['disabled']			turn on/off readonly state (optional)
	 * @param bool   options['addNew']				allow user to create new names (optional)
	 * @param int    options['selectedLimit']		how many items can be selected (optional)
	 * @param int    options['limit']				how many available items can be received from backend (optional)
	 * @param object options['popup']				popup data {parameters, width, height, buttonClass} (optional)
	 * @param string options['popup']['parameters']
	 * @param int    options['popup']['width']
	 * @param int    options['popup']['height']
	 * @param string options['popup']['buttonClass'](optional)
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(options) {
		// call a public method
		if (methods[options]) {
			return methods[options].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		var defaults = {
			url: '',
			name: '',
			labels: {
				'No matches found': 'No matches found',
				'More matches found...': 'More matches found...',
				'type here to search': 'type here to search',
				'new': 'new',
				'Select': 'Select'
			},
			data: [],
			ignored: {},
			addNew: false,
			defaultValue: null,
			disabled: false,
			selectedLimit: 0,
			limit: 20,
			popup: []
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
			var obj = $(this);

			var ms = {
				options: options,
				values: {
					search: '',
					width: parseInt(obj.css('width')),
					isWaiting: false,
					isAjaxLoaded: true,
					isMoreMatchesFound: false,
					isAvailableOpened: false,
					selected: {},
					available: {},
					ignored: empty(options.ignored) ? {} : options.ignored
				}
			};

			// store the configuration in the elements data
			obj.data('multiSelect', ms);

			var values = ms.values;

			// add wrap
			obj.wrap(jQuery('<div>', {
				'class': 'multiselect-wrapper'
			}));

			// search input
			if (!options.disabled) {
				var input = $('<input>', {
					'class': 'input',
					type: 'text'
				})
				.on('keyup change', function(e) {
					if (e.which == KEY.ESCAPE) {
						cleanSearchInput(obj);
						return false;
					}

					if (options.selectedLimit != 0 && $('.selected li', obj).length >= options.selectedLimit) {
						setReadonly(obj);
						return false;
					}

					var search = input.val();
					if (!empty(search)) {
						if (input.data('lastSearch') != search) {
							if (!values.isWaiting) {
								values.isWaiting = true;

								var jqxhr = null;
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
							var available_selected = $('.available li.suggest-hover', obj);

							if (available_selected.length > 0) {
								select(available_selected.data('id'), obj, values, options);
							}

							if (!empty(input.val())) {
								// stop form submit
								cancelEvent(e);
								return false;
							}
							break;

						case KEY.BACKSPACE:
							if (empty(input.val())) {
								var selected = $('.selected li.selected', obj);

								if (selected.length > 0) {
									prev = selected.prev();

									removeSelected(selected.data('id'), obj, values, options);

									if (prev.length > 0) {
										prev.addClass('selected');
									}
									else {
										$('.selected li:first-child', obj).addClass('selected');
									}
								}
								else if ($('.selected li', obj).length > 0) {
									$('.selected li:last-child', obj).addClass('selected');
								}

								cancelEvent(e);
								return false;
							}
							break;

						case KEY.DELETE:
							if (empty(input.val())) {
								var selected = $('.selected li.selected', obj);

								if (selected.length > 0) {
									var next = selected.next();

									removeSelected(selected.data('id'), obj, values, options);

									if (next.length > 0) {
										next.addClass('selected');
									}
									else {
										$('.selected li:last-child', obj).addClass('selected');
									}
								}

								cancelEvent(e);
								return false;
							}
							break;

						case KEY.ARROW_LEFT:
							if (empty(input.val())) {
								if ($('.selected li.selected', obj).length > 0) {
									var prev = $('.selected li.selected', obj).removeClass('selected').prev();

									if (prev.length > 0) {
										prev.addClass('selected');
									}
									else {
										$('.selected li:first-child', obj).addClass('selected');
									}
								}
								else if ($('.selected li', obj).length > 0) {
									$('.selected li:last-child', obj).addClass('selected');
								}
							}
							break;

						case KEY.ARROW_RIGHT:
							if ($('.selected li.selected', obj).length > 0) {
								var next = $('.selected li.selected', obj).removeClass('selected').next();

								if (next.length > 0) {
									next.addClass('selected');
								}
							}
							break;

						case KEY.ARROW_UP:
							if ($('.available', obj).is(':visible') && $('.available li', obj).length > 0) {
								if ($('.available li.suggest-hover', obj).length > 0) {
									var prev = $('.available li.suggest-hover', obj).removeClass('suggest-hover').prev();

									if (prev.length > 0) {
										prev.addClass('suggest-hover');
									}
									else {
										$('.available li:last-child', obj).addClass('suggest-hover');
									}

									scrollAvailable(obj);
								}
								else {
									$('.available li:last-child', obj).addClass('suggest-hover');
								}
							}
							break;

						case KEY.ARROW_DOWN:
							if ($('.available', obj).is(':visible') && $('.available li', obj).length > 0) {
								if ($('.available li.suggest-hover', obj).length > 0) {
									var next = $('.available li.suggest-hover', obj).removeClass('suggest-hover').next();

									if (next.length > 0) {
										next.addClass('suggest-hover');
									}
									else {
										$('.available li:first-child', obj).addClass('suggest-hover');
									}

									scrollAvailable(obj);
								}
								else {
									$('.available li:first-child', obj).addClass('suggest-hover');
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
					if (options.selectedLimit == 0 || $('.selected li', obj).length < options.selectedLimit) {
						$(obj).addClass('active');
					}
				})
				.focusout(function() {
					$(obj).removeClass('active');
					cleanSearchInput(obj);
				});
				obj.append(input);
			}

			// selected
			var selected_div = $('<div>', {
				'class': 'selected'
			});
			var sdelected_ul = $('<ul>', {
				'class': 'multiselect-list',
				css: {
					width: values.width
				}
			});
			if (options.disabled) {
				sdelected_ul.addClass('disabled');
			}
			obj.append(selected_div.append(sdelected_ul));

			// available
			if (!options.disabled) {
				var available = $('<div>', {
					'class': 'available',
					css: { display: 'none' }
				});

				// multi select
				obj.append(available)
				.focusout(function() {
					setTimeout(function() {
						if (!values.isAvailableOpened && $('.available', obj).is(':visible')) {
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
			resize(obj, values, options);

			cleanLastSearch(obj);

			// draw popup link
			if (options.popup.parameters != null) {
				var urlParameters = options.popup.parameters;

				if (options.ignored) {
					$.each(options.ignored, function(i, value) {
						urlParameters = urlParameters + '&excludeids[]=' + i;
					});
				}

				var popupButton = $('<button>', {
					type: 'button',
					'class': options.popup.buttonClass ? options.popup.buttonClass : 'btn-alt',
					text: options.labels['Select']
				});

				if (options.disabled) {
					popupButton.attr('disabled', true);
				}
				else {
					popupButton.click(function() {
						return PopUp('popup.php?' + urlParameters, options.popup.width, options.popup.height);
					});
				}

				obj.parent().append($('<div class="multiselect-button"></div>').append(popupButton));
			}

			// IE browsers use default width
			if (IE) {
				options.defaultWidth = $('input[type="text"]', obj).width();
			}
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
				if (options.limit != 0 && objectLength(values.available) < options.limit) {
					if (typeof values.available[item.id] === 'undefined'
							&& typeof values.selected[item.id] === 'undefined'
							&& typeof values.ignored[item.id] === 'undefined') {
						values.available[item.id] = item;
					}
				}
				else {
					values.isMoreMatchesFound = true;
				}
			});
		}

		// write empty result label
		if (objectLength(values.available) == 0) {
			var div = $('<div>', {
				'class': 'multiselect-matches',
				text: options.labels['No matches found']
			})
			.click(function() {
				$('input[type="text"]', obj).focus();
			});

			$('.available', obj).append(div);
		}
		else {
			$('.available', obj)
				.append($('<ul>', {
					'class': 'multiselect-suggest'
				}))
				.mouseenter(function() {
					values.isAvailableOpened = true;
				})
				.mouseleave(function() {
					values.isAvailableOpened = false;
				});

			$.each(values.available, function(i, item) {
				addAvailable(item, obj, values, options);
			});
		}


		// write more matches found label
		if (values.isMoreMatchesFound) {
			var div = $('<div>', {
				'class': 'multiselect-matches',
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
				'class': 'subfilter-enabled',
				text: empty(item.prefix) ? item.name : item.prefix + item.name
			});

			$('.selected ul', obj).append(li.append(text));

			resizeSelectedText(li, text, obj, options);

			var close_btn = $('<span>', {
				'class': 'subfilter-disable-btn',
				'text': '×'
			});

			if (!options.disabled) {
				close_btn.click(function() {
					removeSelected(item.id, obj, values, options);
				});
			}

			text.append(close_btn);

			removePlaceholder(obj);

			// resize
			resize(obj, values, options);

			// set readonly
			if (options.selectedLimit != 0 && $('.selected li', obj).length >= options.selectedLimit) {
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
		resize(obj, values, options);

		// remove readonly
		if ($('.selected li', obj).length == 0) {
			setDefaultValue(obj, options);
			setPlaceholder(obj, options);
		}

		if (options.selectedLimit == 0 || $('.selected li', obj).length < options.selectedLimit) {
			$('input[type="text"]', obj).prop('disabled', false);
		}

		// clean
		cleanAvailable(obj, values);
		cleanLastSearch(obj);

		if (!$('input[type="text"]', obj).prop('disabled')) {
			$('input[type="text"]', obj).focus();
		}
	}

	function addAvailable(item, obj, values, options) {
		var li = $('<li>', {
			'data-id': item.id
		})
		.click(function() {
			select(item.id, obj, values, options);
		})
		.hover(function() {
			$('.available li.suggest-hover', obj).removeClass('suggest-hover');
			li.addClass('suggest-hover');
		});

		if (!empty(item.prefix)) {
			li.append($('<span>', {
				'class': 'grey',
				text: item.prefix
			}));
		}

		// highlight matched
		var text = item.name.toLowerCase(),
			search = values.search.toLowerCase(),
			start = 0,
			end = 0,
			searchLength = search.length;

		while (text.indexOf(search, end) > -1) {
			end = text.indexOf(search, end);

			if (end > start) {
				li.append(
					item.name.substring(start, end)
				);
			}

			li.append($('<b>', {
				text: item.name.substring(end, end + searchLength)
			}));

			end += searchLength;
			start = end;
		}

		if (end < item.name.length) {
			li.append(
				item.name.substring(end, item.name.length)
			);
		}

		$('.available ul', obj).append(li);
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
		var available = $('.available', obj),
			available_paddings = available.outerWidth() - available.width();

		available.css({
			'width': obj.outerWidth() - available_paddings,
			'left': -1
		});

		available.fadeIn(0);

		if (objectLength(values.available) != 0) {
			// remove selected item selected state
			if ($('.selected li.selected', obj).length > 0) {
				$('.selected li.selected', obj).removeClass('selected');
			}

			// pre-select first available
			if ($('li', available).length > 0) {
				if ($('li.suggest-hover', available).length > 0) {
					$('li.suggest-hover', available).removeClass('suggest-hover');
				}
				$('li:first-child', available).addClass('suggest-hover');
			}
		}
	}

	function hideAvailable(obj) {
		$('.available', obj).fadeOut(0);
	}

	function cleanAvailable(obj, values) {
		$('.multiselect-matches', obj).remove();
		$('.available ul', obj).remove();
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

	function resize(obj, values, options) {
		if (!options.selectedLimit || $('.selected li', obj).length < options.selectedLimit) {
			resizeSelected(obj, values, options);
		}
	}

	function resizeSelected(obj, values, options) {
		if (options.disabled) {
			if ($('.selected li', obj).length) {
				var item = $('.selected li', obj),
					item_margins = item.outerHeight(true) - item.height();

				$('.selected ul', obj).css({
					'padding-bottom': item_margins
				});
			}
		}
		else {
			var input_padding_top = 0,
				input = $('input[type="text"]', obj),
				obj_paddings = obj.innerWidth() - obj.width(),
				input_paddings = input.innerWidth() - input.width();

			if ($('.selected li', obj).length > 0) {
				var lastItem = $('.selected li:last-child', obj),
					position = lastItem.position();

				input_padding_top = position.top + lastItem.outerHeight(true);
			}

			$('.selected ul', obj).css({
				'padding-bottom': input.height()
			});

			input.css({
				'width': obj.width() + obj_paddings - input_paddings,
				'padding-top': input_padding_top
			});

			if (IE) {
				// hack to fix inline-block container resizing and poke input element value to trigger reflow
				if (IE8) {
					$('.multiselect-wrapper').addClass('ie8fix-inline').removeClass('ie8fix-inline');
				}
				var currentInputVal = input.val();
				input.val(' ').val(currentInputVal);
			}
		}
	}

	function resizeSelectedText(item, text, obj, options) {
		var text_paddings = text.innerWidth() - text.width(),
			item_margins = item.outerWidth(true) - item.width(),
			max_width = $('.selected ul', obj).width() - item_margins - text_paddings;

		if (text.width() > max_width) {
			var t = text.text();
			var l = t.length;

			do {
				text.text(t.substring(0, --l) + '...');
			}
			while (text.width() > max_width)
		}
	}

	function resizeAllSelectedTexts(obj, options, values) {
		$('.selected li', obj).each(function() {
			var item = $(this),
				id = item.data('id'),
				text = $(item.children()[0]),
				t = empty(values.selected[id].prefix)
					? values.selected[id].name
					: values.selected[id].prefix + values.selected[id].name;

			// rewrite previous text to original
			text.text(t);

			resizeSelectedText(item, text, obj, options);
		});
	}

	function scrollAvailable(obj) {
		var hover = $('.available li.suggest-hover', obj);

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
		$(obj).removeClass('active');

		var item = $('.selected li', obj),
			item_margins = item.outerHeight(true) - item.height();

		$('.selected ul', obj).css({
			'padding-bottom': item_margins
		});
	}

	function setPlaceholder(obj, options) {
		$('input[type="text"]', obj).attr('placeholder', options.labels['type here to search']);

		createPlaceholders();
	}

	function removePlaceholder(obj) {
		$('input[type="text"]', obj)
			.removeAttr('placeholder')
//			.removeClass('placeholder')
			.val('');
	}

	function getLimit(values, options) {
		return (options.limit != 0)
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
