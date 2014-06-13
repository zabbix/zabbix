/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

				if (ms.options.data.length) {
					resizeAllSelectedTexts(obj, ms.options, ms.values);
				}
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

				// clean input if selectedLimit = 1
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
			selectedLimit: null,
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
					isAvailableOpenned: false,
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
			resize(obj, values, options);

			// draw popup link
			if (options.popup.parameters != null) {
				var popupBlock = $('<div>', {
					'class': 'select-popup'
				});

				var urlParameters = options.popup.parameters;

				if (options.ignored) {
					$.each(options.ignored, function(i, value) {
						urlParameters = urlParameters + '&excludeids[]=' + i;
					});
				}

				var popupButton = $('<input>', {
					type: 'button',
					'class': options.popup.buttonClass ? options.popup.buttonClass : 'input link_menu select-popup',
					value: options.labels['Select']
				});

				if (options.disabled) {
					popupButton.attr('disabled', true);
				}
				else {
					popupButton.click(function() {
						return PopUp('popup.php?' + urlParameters, options.popup.width, options.popup.height);
					});
				}

				obj.parent().append(popupButton);
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

			$('.selected ul', obj).append(li.append(text));

			resizeSelectedText(li, text, obj, options);

			if (!options.disabled) {
				var arrow = $('<span>', {
					'class': 'arrow',
					'data-id': item.id
				})
				.click(function() {
					removeSelected(item.id, obj, values, options);
				});

				$('.selected ul', obj).append(li.append(arrow));
			}

			removePlaceholder(obj);

			// resize
			resize(obj, values, options);

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
		resize(obj, values, options);

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
			if (typeof values.available[item.id] === 'undefined'
					&& typeof values.selected[item.id] === 'undefined'
					&& typeof values.ignored[item.id] === 'undefined') {
				values.available[item.id] = item;

				var prefix = $('<span>', {
					'class': 'prefix',
					text: item.prefix
				});

				var li = $('<li>', {
					'data-id': item.id
				})
				.click(function() {
					select(item.id, obj, values, options);
				})
				.hover(function() {
					$('.available li.hover', obj).removeClass('hover');
					li.addClass('hover');
				})
				.append(prefix);

				// highlight matched
				var text = item.name.toLowerCase(),
					search = values.search.toLowerCase(),
					start = 0,
					end = 0,
					searchLength = search.length;

				while (text.indexOf(search, end) > -1) {
					end = text.indexOf(search, end);

					if (end > start) {
						li.append($('<span>', {
							text: item.name.substring(start, end)
						}));
					}

					li.append($('<span>', {
						'class': 'matched',
						text: item.name.substring(end, end + searchLength)
					}));

					end += searchLength;
					start = end;
				}

				if (end < item.name.length) {
					li.append($('<span>', {
						text: item.name.substring(end, item.name.length)
					}));
				}

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

	function resize(obj, values, options) {
		if (!options.selectedLimit || $('.selected li', obj).length < options.selectedLimit) {
			resizeSelected(obj, values, options);
			resizeAvailable(obj);
		}
	}

	function resizeSelected(obj, values, options) {
		// settings
		var settingTopPaddingsEmpty = IE8 ? 1 : 0,
			settingTopPaddingsExist = IE8 ? 1 : 2,
			settingTopPaddingsInit = 3,
			settingRightPaddings = 4,
			settingMinimumWidth = 50,
			settingMinimumHeight = 12,
			settingNewLineTopPaddings = 6;

		// calculate
		var top = 0,
			left = 0,
			height = 0;

		if ($('.selected li', obj).length > 0) {
			var lastItem = $('.selected li:last-child', obj),
				position = lastItem.position();

			top = position.top + settingTopPaddingsExist;
			left = position.left + lastItem.width();
			height = $('.selected li:last-child', obj).height();
		}
		else {
			top = settingTopPaddingsInit + settingTopPaddingsEmpty;
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

			// IE8 hack to fix inline-block container resizing
			if (IE8) {
				$('.multiselect-wrapper').addClass('ie8fix-inline').removeClass('ie8fix-inline');
			}
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

	function resizeSelectedText(item, text, obj, options) {
		// settings
		var settingLineHeight = 15,
			settingArrowSpace = options.disabled ? 22 : 32,
			settingPaddings = 3,
			settingTextMax = 510;

		// calculate
		var maxWidth = $('.selected ul', obj).width() - settingArrowSpace;

		if (text.width() > maxWidth || text.height() > settingLineHeight) {
			var i = 0;

			while (text.width() > maxWidth || text.height() > settingLineHeight) {
				var t = text.text();

				text.text(t.substring(0, t.length - 1));

				i++;

				if (i > settingTextMax) {
					break;
				}
			}

			text.text(text.text() + '...');

			item.css('width', $('.selected ul', obj).width() - settingPaddings);
		}
		else {
			item.css('width', '');

			if (!options.disabled) {
				text.css('padding-right', 16);
			}
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
