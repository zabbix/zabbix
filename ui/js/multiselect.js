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


(function($) {
	var ZBX_STYLE_CLASS = 'multiselect-control';
	const MS_ACTION_POPUP = 0;
	const MS_ACTION_AUTOSUGGEST = 1;

	/**
	 * Multi select helper.
	 *
	 * @param string options['object_name']		backend data source
	 * @param object options['objectOptions']	parameters to be added the request URL (optional)
	 *
	 * @see jQuery.multiSelect()
	 */
	$.fn.multiSelectHelper = function(options) {
		options = $.extend({objectOptions: {}}, options);

		var curl = new Curl('jsrpc.php', false);
		curl.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		curl.setArgument('method', 'multiselect.get');
		curl.setArgument('object_name', options.object_name);

		for (var key in options.objectOptions) {
			curl.setArgument(key, options.objectOptions[key]);
		}

		options.url = curl.getUrl();

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
			var $obj = $(this).first(),
				ms = $obj.data('multiSelect');

			var data = [];
			for (var id in ms.values.selected) {
				var item = ms.values.selected[id];

				data.push({
					id: id,
					name: item.name,
					prefix: (typeof item.prefix === 'undefined') ? '' : item.prefix
				});
			}

			// Sort entries by name field.
			data.sort(function(a, b) {
				if (a.name === b.name) {
					return 0;
				}
				else {
					return (a.name < b.name) ? -1 : 1;
				}
			});

			return data;
		},

		/**
		 * Insert outside data
		 *
		 * @param {array} items           Multiselect value object.
		 * @param {bool}  trigger_change  (optional) Either to trigger element on-change event once data added. True by default.
		 *
		 * @return jQuery
		 */
		addData: function(items, trigger_change) {
			return this.each(function() {
				var $obj = $(this),
					ms = $obj.data('multiSelect');

				if (typeof trigger_change !== 'boolean') {
					trigger_change = true;
				}

				if (ms.options.selectedLimit == 1) {
					for (var id in ms.values.selected) {
						removeSelected($obj, id);
					}
				}

				for (var i = 0, l = items.length; i < l; i++) {
					addSelected($obj, items[i]);
				}

				trigger_change && $obj.trigger('change', ms);
			});
		},

		/**
		 * Enable multi select UI control.
		 *
		 * @return jQuery
		 */
		enable: function() {
			return this.each(function() {
				var $obj = $(this),
					ms = $obj.data('multiSelect');

				if (ms.options.disabled === true) {
					$obj.removeAttr('aria-disabled');
					$('.multiselect-list', $obj).removeClass('disabled');
					$('.multiselect-button > button', $obj.parent()).prop('disabled', false);
					$obj.append(makeMultiSelectInput($obj));

					ms.options.disabled = false;

					cleanSearch($obj);
				}
			});
		},

		/**
		 * Disable multi select UI control.
		 *
		 * @return jQuery
		 */
		disable: function() {
			return this.each(function() {
				var $obj = $(this),
					ms = $obj.data('multiSelect');

				if (ms.options.disabled === false) {
					$obj.attr('aria-disabled', true);
					$('.multiselect-list', $obj).addClass('disabled');
					$('.multiselect-button > button', $obj.parent()).prop('disabled', true);
					$('input[type="text"]', $obj).remove();

					ms.options.disabled = true;

					cleanSearch($obj);
				}
			});
		},

		/**
		 * Clean multi select object values.
		 *
		 * @return jQuery
		 */
		clean: function() {
			return this.each(function() {
				var $obj = $(this),
					ms = $obj.data('multiSelect');

				for (var id in ms.values.selected) {
					removeSelected($obj, id);
				}

				cleanSearch($obj);

				$obj.trigger('change', ms);
			});
		},

		/**
		 * Modify one or more multiselect options after multiselect object has been created.
		 *
		 * @return jQuery
		 */
		modify: function(options) {
			return this.each(function() {
				var $obj = $(this),
					ms = $obj.data('multiSelect');

				var addNew_modified = ('addNew' in options) && options['addNew'] != ms.options['addNew'];

				for (var key in ms.options) {
					if (key in options) {
						ms.options[key] = options[key];
					}
				}

				if (addNew_modified) {
					/*
					 * When modifying the "addNew" option, few things must be done:
					 *   1. Search input must be reset.
					 *   2. The already selected "(new)" items must be either hidden and disabled or shown and enabled.
					 *      Note: hidden and disabled items will not submit to the server.
					 *   3. The "change" trigger must fire.
					 */

					cleanSearch($obj);

					$('input[name*="[new]"]', $obj)
						.prop('disabled', !ms.options['addNew'])
						.each(function() {
							var id = $(this).val();
							$('.selected li[data-id]', $obj).each(function() {
								if ($(this).data('id') == id) {
									$(this).toggle(ms.options['addNew']);
								}
							});
						});

					$obj.trigger('change', ms);
				}
			});
		},

		/**
		 * Return option value.
		 *
		 * @return string
		 */
		getOption: function(key) {
			var ret = null;
			this.each(function() {
				var $obj = $(this);

				if ($obj.data('multiSelect') !== undefined) {
					ret = $obj.data('multiSelect').options[key];
				}
			});

			return ret;
		},

		/**
		 * @return HTMLElement
		 */
		getSelectButton: function() {
			var ret = null;

			this.each(function() {
				var $obj = $(this);

				if ($obj.data('multiSelect') !== undefined) {
					ret = $obj.data('multiSelect').select_button[0];

					return false;
				}
			});

			return ret;
		},

		/**
		 * @param array entries  IDs to mark disabled.
		 */
		setDisabledEntries: function (entries) {
			this.each(function() {
				const $obj = $(this);
				const ms_parameters = $obj.data('multiSelect');

				if (typeof ms_parameters === 'undefined') {
					return;
				}

				const link = new Curl(ms_parameters.options.url, false);
				link.setArgument('disabledids', entries);

				ms_parameters.options.url = link.getUrl();
				ms_parameters.options.popup.parameters.disableids = entries;

				$obj.data('multiSelect', ms_parameters);
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
	 * @param bool   options['data'][inaccessible]	(optional)
	 * @param bool   options['data'][disabled]		(optional)
	 * @param string options['placeholder']			set custom placeholder (optional)
	 * @param array  options['excludeids']			the list of excluded ids (optional)
	 * @param string options['defaultValue']		default value for input element (optional)
	 * @param bool   options['disabled']			turn on/off readonly state (optional)
	 * @param bool   options['addNew']				allow user to create new names (optional)
	 * @param int    options['selectedLimit']		how many items can be selected (optional)
	 * @param int    options['limit']				how many available items can be received from backend (optional)
	 * @param object options['popup']				popup data {parameters, width, height} (optional)
	 * @param string options['popup']['parameters']
	 * @param string options['popup']['filter_preselect_fields']
	 * @param int    options['popup']['width']
	 * @param int    options['popup']['height']
	 * @param object options['autosuggest']         autosuggest options (optional)
	 * @param object options['autosuggest']['filter_preselect_fields'] autosuggest preselect fields (optional)
	 * @param string options['autosuggest']['filter_preselect_fields']['hosts'] autosuggest host preselect field (optional)
	 * @param string options['styles']				additional style for multiselect wrapper HTML element (optional)
	 * @param string options['styles']['property']
	 * @param string options['styles']['value']
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(options) {
		// Call a public method.
		if (methods[options]) {
			return methods[options].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		var defaults = {
				url: '',
				name: '',
				labels: {
					'No matches found': t('No matches found'),
					'More matches found...': t('More matches found...'),
					'type here to search': t('type here to search'),
					'new': t('new'),
					'Select': t('Select')
				},
				placeholder: t('type here to search'),
				data: [],
				excludeids: [],
				addNew: false,
				defaultValue: null,
				disabled: false,
				selectedLimit: 0,
				limit: 20,
				popup: {},
				styles: {}
			};

		options = $.extend({}, defaults, options);

		return this.each(function() {
			var $obj = $(this);

			if ($obj.data('multiSelect') !== undefined) {
				return;
			}

			options.required_str = $obj.attr('aria-required') === undefined ? 'false' : $obj.attr('aria-required');
			$obj.removeAttr('aria-required');

			var ms = {
					options: options,
					values: {
						search: '',
						searches: {},
						searching: {},
						selected: {},
						available: {},
						available_div: $('<div>', {'class': 'multiselect-available'}),

						/*
						 * Indicates a false click on an available list, but not on some actual item.
						 * In such case the "focusout" event (IE) of the search input should not be processed.
						 */
						available_false_click: false
					},
					select_button: null
				};

			ms.values.available_div.on('mousedown', 'li', function() {
				/*
				 * Cancel event propagation if actual available item was clicked.
				 * As a result, the "focusout" event of the search input will not fire in all browsers.
				 */
				return false;
			});

			$obj.data('multiSelect', ms);

			$obj.wrap($('<div>', {
				'class': ZBX_STYLE_CLASS,
				css: ms.options.styles
			}));

			var $selected_div = $('<div>', {'class': 'selected'}).on('click', function() {
					/*
					 * Focus without options because here it don't matter.
					 * Click used instead focus because in patternselect listen only click.
					 */
					$('input[type="text"]', $obj).click().focus();
				}),
				$selected_ul = $('<ul>', {'class': 'multiselect-list'});

			$obj.append($selected_div.append($selected_ul));

			if (ms.options.disabled) {
				$obj.attr('aria-disabled', true);
				$selected_ul.addClass('disabled');
			}
			else {
				$obj.append(makeMultiSelectInput($obj));
			}

			$obj
				.on('mousedown', function(event) {
					if (isSearchFieldVisible($obj) && ms.options.selectedLimit != 1) {
						$obj.addClass('active');
						$('.selected li.selected', $obj).removeClass('selected');

						// Focus input only when not clicked on selected item.
						if (!$(event.target).parents('.multiselect-list').length) {
							$('input[type="text"]', $obj).focus();
						}
					}
				})
				.on('remove', function() {
					cleanSearch($obj);
				});

			if (empty(ms.options.data)) {
				addDefaultValue($obj);
			}
			else {
				$.each(ms.options.data, function(i, item) {
					addSelected($obj, item);
				});
			}

			if (ms.options.custom_select || ms.options.popup.parameters !== undefined) {
				ms.select_button = $('<button>', {
					type: 'button',
					'class': 'btn-grey',
					text: ms.options.labels['Select']
				});

				if (ms.options.disabled) {
					ms.select_button.prop('disabled', true);
				}

				if (ms.options.popup.parameters !== undefined) {
					ms.select_button.on('click', function(event) {
						var parameters = ms.options.popup.parameters;

						if (ms.options.popup.filter_preselect_fields) {
							parameters = jQuery.extend(parameters, getFilterPreselectField($obj, MS_ACTION_POPUP));
						}

						if (typeof parameters['disable_selected'] !== 'undefined' && parameters['disable_selected']) {
							parameters['disableids'] = Object.keys(ms.values.selected);
						}

						// Click used instead focus because in patternselect only click is listened for.
						$('input[type="text"]', $obj).click();

						return PopUp('popup.generic', parameters, {
							dialogue_class: 'modal-popup-generic',
							trigger_element: event.target
						});
					});
				}

				$obj.after($('<div>', {'class': 'multiselect-button'}).append(ms.select_button));
			}
		});
	};

	/**
	 * Get current value from preselect filter field.
	 *
	 * @param {jQuery} $obj    Multiselect instance.
	 * @param {int}    action  User action that caused preselection.
	 *
	 * @return {object}
	 */
	function getFilterPreselectField($obj, action) {
		const ms = $obj.data('multiSelect');
		const preselect_options = ms.options[(action == MS_ACTION_AUTOSUGGEST) ? 'autosuggest' : 'popup'] || null;
		const ret = {};

		if (!preselect_options) {
			return ret;
		}

		if (typeof preselect_options.filter_preselect_fields.hosts !== 'undefined') {
			const hosts = $('#' + preselect_options.filter_preselect_fields.hosts).multiSelect('getData');

			if (hosts.length != 0) {
				ret.hostid = hosts[0].id;
			}
		}

		if (typeof preselect_options.filter_preselect_fields.hostgroups !== 'undefined') {
			const host_groups = $('#' + preselect_options.filter_preselect_fields.hostgroups).multiSelect('getData');

			if (host_groups.length != 0) {
				ret.groupid = host_groups[0].id;
			}
		}

		if (typeof preselect_options.filter_preselect_fields.templategroups !== 'undefined') {
			const template_groups = $('#' + preselect_options.filter_preselect_fields.templategroups)
				.multiSelect('getData');

			if (template_groups.length != 0) {
				ret.templategroupid = template_groups[0].id;
			}
		}

		return ret;
	}

	function makeMultiSelectInput($obj) {
		var ms = $obj.data('multiSelect'),
			$label = $('label[for=' + $obj.attr('id') + '_ms]'),
			$aria_live = $('[aria-live]', $obj),
			$input = $('<input>', {
				'id': $label.length ? $label.attr('for') : null,
				'class': 'input',
				'type': 'text',
				'autocomplete': 'off',
				'placeholder': ms.options.placeholder,
				'aria-label': ($label.length ? $label.text() + '. ' : '') + ms.options.placeholder,
				'aria-required': ms.options.required_str
			})
				.on('keyup', function(e) {
					switch (e.which) {
						case KEY_ARROW_DOWN:
						case KEY_ARROW_LEFT:
						case KEY_ARROW_RIGHT:
						case KEY_ARROW_UP:
						case KEY_ESCAPE:
							return false;
					}

					clearSearchTimeout($obj);

					// Maximum results selected already?
					if (ms.options.selectedLimit != 0 && $('.selected li', $obj).length >= ms.options.selectedLimit) {
						return false;
					}

					var search = $input.val();

					if (search !== '') {
						search = search.trim();

						$('.selected li.selected', $obj).removeClass('selected');
					}

					if (search !== '') {
						var preselect_values = getFilterPreselectField($obj, MS_ACTION_AUTOSUGGEST),
							cache_key = search + JSON.stringify(preselect_values);

						/*
						 * Strategy:
						 * 1. Load the cached result set if such exists for the given term and show the list.
						 * 2. Skip anything if already expecting the result set to arrive for the given term.
						 * 3. Schedule result set retrieval for the given term otherwise.
						 */

						if (cache_key in ms.values.searches) {
							ms.values.search = search;
							ms.values.cache_key = cache_key;
							loadAvailable($obj);
							showAvailable($obj);
						}
						else if (!(cache_key in ms.values.searching)) {
							ms.values.searchTimeout = setTimeout(function() {
								ms.values.searching[cache_key] = true;

								$.ajax({
									url: ms.options.url + '&curtime=' + new CDate().getTime(),
									type: 'GET',
									dataType: 'json',
									cache: false,
									data: jQuery.extend({
										search: search,
										limit: getLimit($obj),
									}, preselect_values)
								})
									.then(function(response) {
										ms.values.searches[cache_key] = response.result;

										if (search === $input.val().trim()) {
											ms.values.search = search;
											ms.values.cache_key = cache_key;
											loadAvailable($obj);
											showAvailable($obj);
										}
									})
									.done(function() {
										delete ms.values.searching[cache_key];
									});

								delete ms.values.searchTimeout;
							}, 500);
						}
					}
					else {
						hideAvailable($obj);
					}
				})
				.on('keydown', function(e) {
					switch (e.which) {
						case KEY_TAB:
						case KEY_ESCAPE:
							var $available = ms.values.available_div;

							if ($available.parent().is(document.body)) {
								hideAvailable($obj);
								e.stopPropagation();
							}

							cleanSearchInput($obj);
							break;

						case KEY_ENTER:
							if ($input.val() !== '') {
								var $selected = $('li.suggest-hover', ms.values.available_div);

								if ($selected.length) {
									select($obj, $selected.data('id'));
									$aria_live.text(sprintf(t('Added, %1$s'), $selected.data('label')));
								}

								return false;
							}
							break;

						case KEY_ARROW_LEFT:
							if ($input.val() === '') {
								var $collection = $('.selected li', $obj);

								if ($collection.length) {
									var $prev = $collection.filter('.selected').removeClass('selected').prev();
									$prev = ($prev.length ? $prev : $collection.last()).addClass('selected');

									$aria_live.text(($prev.hasClass('disabled'))
										? sprintf(t('%1$s, read only'), $prev.data('label'))
										: $prev.data('label')
									);
								}
							}
							break;

						case KEY_ARROW_RIGHT:
							if ($input.val() === '') {
								var $collection = $('.selected li', $obj);

								if ($collection.length) {
									var $next = $collection.filter('.selected').removeClass('selected').next();
									$next = ($next.length ? $next : $collection.first()).addClass('selected');

									$aria_live.text(($next.hasClass('disabled'))
										? sprintf(t('%1$s, read only'), $next.data('label'))
										: $next.data('label')
									);
								}
							}
							break;

						case KEY_ARROW_UP:
						case KEY_ARROW_DOWN:
							var $collection = $('li', ms.values.available_div.filter(':visible')),
								$selected = $collection.filter('.suggest-hover').removeClass('suggest-hover');

							if ($selected.length) {
								$selected = (e.which == KEY_ARROW_UP)
									? ($selected.is(':first-child') ? $collection.last() : $selected.prev())
									: ($selected.is(':last-child') ? $collection.first() : $selected.next());

								$selected.addClass('suggest-hover');
								$aria_live.text($selected.data('label'));
							}

							scrollAvailable($obj);

							return false;

						case KEY_BACKSPACE:
						case KEY_DELETE:
							if ($input.val() === '') {
								var $selected = $('.selected li.selected', $obj);

								if ($selected.length) {
									var id = $selected.data('id'),
										item = ms.values.selected[id];

									if (typeof item.disabled === 'undefined' || !item.disabled) {
										var aria_text = sprintf(t('Removed, %1$s'), $selected.data('label'));

										$selected = (e.which == KEY_BACKSPACE)
											? ($selected.is(':first-child') ? $selected.next() : $selected.prev())
											: ($selected.is(':last-child') ? $selected.prev() : $selected.next());

										removeSelected($obj, id);

										$obj.trigger('change', ms);

										if ($selected.length) {
											var $collection = $('.selected li', $obj);
											$selected.addClass('selected');

											aria_text += ', ' + sprintf(
												($selected.hasClass('disabled'))
													? t('Selected, %1$s, read only, in position %2$d of %3$d')
													: t('Selected, %1$s in position %2$d of %3$d'),
												$selected.data('label'),
												$collection.index($selected) + 1,
												$collection.length
											);
										}

										$aria_live.text(aria_text);
									}
									else {
										$aria_live.text(t('Cannot be removed'));
									}
								}
								else if (e.which == KEY_BACKSPACE) {
									/*
									 * Pressing Backspace on empty input field should select last element in
									 * multiselect. For next Backspace press to be able to remove it.
									 */
									var $selected = $('.selected li:last-child', $obj).addClass('selected');
									$aria_live.text($selected.data('label'));
								}

								return false;
							}
							break;
					}
				})
				.on('focusin', function($event) {
					$obj.addClass('active');
				})
				.on('focusout', function($event) {
					if (ms.values.available_false_click) {
						ms.values.available_false_click = false;
						$('input[type="text"]', $obj)[0].focus({preventScroll:true});
					}
					else {
						$obj.removeClass('active');
						$('.selected li.selected', $obj).removeClass('selected');
						cleanSearchInput($obj);
						hideAvailable($obj);
					}
				});

		return $input;
	}

	function addDefaultValue($obj) {
		var ms = $obj.data('multiSelect');

		if (!empty(ms.options.defaultValue)) {
			$obj.append($('<input>', {
				type: 'hidden',
				name: ms.options.name,
				value: ms.options.defaultValue,
				'data-default': 1
			}));
		}
	}

	function removeDefaultValue($obj) {
		$('input[data-default="1"]', $obj).remove();
	}

	function select($obj, id) {
		var ms = $obj.data('multiSelect');

		addSelected($obj, ms.values.available[id]);

		if (isSearchFieldVisible($obj)) {
			$('input[type="text"]', $obj)[0].focus({preventScroll:true});
		}

		$obj.trigger('change', ms);
	}

	function addSelected($obj, item) {
		var ms = $obj.data('multiSelect');

		if (item.id in ms.values.selected) {
			return;
		}

		removeDefaultValue($obj);
		ms.values.selected[item.id] = item;

		var prefix = (item.prefix || ''),
			item_disabled = (typeof item.disabled !== 'undefined' && item.disabled);

		$obj.append($('<input>', {
			type: 'hidden',
			name: (ms.options.addNew && item.isNew) ? ms.options.name + '[new]' : ms.options.name,
			value: item.id,
			'data-name': item.name,
			'data-prefix': prefix
		}));

		var $li = $('<li>', {
				'data-id': item.id,
				'data-label': prefix + item.name
			})
				.append(
					$('<span>', {
						'class': 'subfilter-enabled'
					})
						.append($('<span>', {
							text: prefix + item.name,
							title: item.name
						}))
						.append($('<span>')
							.addClass('subfilter-disable-btn')
							.on('click', function() {
								if (!ms.options.disabled && !item_disabled) {
									removeSelected($obj, item.id);
									if (isSearchFieldVisible($obj)) {
										$('input[type="text"]', $obj)[0].focus({preventScroll:true});
									}

									$obj.trigger('change', ms);
								}
							})
						)
				)
				.on('click', function() {
					if (isSearchFieldVisible($obj) && ms.options.selectedLimit != 1) {
						$('.selected li.selected', $obj).removeClass('selected');
						$(this).addClass('selected');

						// preventScroll not work in IE.
						$('input[type="text"]', $obj)[0].focus({preventScroll: true});
					}
				});

		if (typeof item.inaccessible !== 'undefined' && item.inaccessible) {
			$li.addClass('inaccessible');
		}

		if (item_disabled) {
			$li.addClass('disabled');
		}

		$('.selected ul', $obj).append($li);

		cleanSearch($obj);
	}

	function removeSelected($obj, id) {
		var ms = $obj.data('multiSelect');

		$('.selected li[data-id]', $obj).each(function(){
			if ($(this).data('id') == id) {
				$(this).remove();
			}
		});
		$('input', $obj).each(function(){
			if ($(this).val() == id) {
				$(this).remove();
			}
		});

		delete ms.values.selected[id];

		if (!$('.selected li', $obj).length) {
			addDefaultValue($obj);
		}

		cleanSearch($obj);
	}

	function addAvailable($obj, item) {
		var ms = $obj.data('multiSelect'),
			is_new = item.isNew || false,
			prefix = item.prefix || '',
			$li = $('<li>', {
				'data-id': item.id,
				'data-label': prefix + item.name
			})
				.on('mouseenter', function() {
					$('li.suggest-hover', ms.values.available_div).removeClass('suggest-hover');
					$li.addClass('suggest-hover');
				})
				.on('click', function() {
					select($obj, item.id);
				});

		if (prefix !== '') {
			$li.append($('<span>', {'class': 'grey', text: prefix}));
		}

		// Highlight matched.
		if (ms.values.search !== item.name) {
			var text = item.name.toLowerCase(),
				search = ms.values.search.toLowerCase().replace(/[*]+/g, ''),
				start = 0,
				end = 0;

			while (search !== '' && text.indexOf(search, end) > -1) {
				end = text.indexOf(search, end);

				if (end > start) {
					$li.append(document.createTextNode(item.name.substring(start, end)));
				}

				$li.append($('<span>', {
					class: !is_new ? 'suggest-found' : '',
					text: item.name.substring(end, end + search.length)
				})).toggleClass('suggest-new', is_new);

				end += search.length;
				start = end;
			}

			if (end < item.name.length) {
				$li.append(document.createTextNode(item.name.substring(end, item.name.length)));
			}
		}
		else {
			$li.append($('<span>', {
				class: !is_new ? 'suggest-found' : '',
				text: item.name
			})).toggleClass('suggest-new', is_new);
		}

		$('ul', ms.values.available_div).append($li);
	}

	function loadAvailable($obj) {
		var ms = $obj.data('multiSelect'),
			data = ms.values.searches[ms.values.cache_key];

		cleanAvailable($obj);

		var addNew = false;

		if (ms.options.addNew && ms.values.search.length) {
			if (data.length || objectSize(ms.values.selected) > 0) {
				var names = {};

				// Check if value exists among available values.
				$.each(data, function(i, item) {
					if (item.name === ms.values.search) {
						names[item.name.toUpperCase()] = true;
					}
				});

				if (typeof names[ms.values.search.toUpperCase()] === 'undefined') {
					addNew = true;
				}

				// Check if value exists among selected values.
				if (!addNew && objectSize(ms.values.selected) > 0) {
					$.each(ms.values.selected, function(i, item) {
						if (typeof item.isNew === 'undefined') {
							names[item.name.toUpperCase()] = true;
						}
						else {
							names[item.id.toUpperCase()] = true;
						}
					});

					if (typeof names[ms.values.search.toUpperCase()] === 'undefined') {
						addNew = true;
					}
				}
			}
			else {
				addNew = true;
			}
		}

		var available_more = false;

		$.each(data, function(i, item) {
			if (ms.options.limit == 0 || objectSize(ms.values.available) < ms.options.limit) {
				if (typeof ms.values.available[item.id] === 'undefined'
						&& typeof ms.values.selected[item.id] === 'undefined'
						&& ms.options.excludeids.indexOf(item.id) === -1) {
					ms.values.available[item.id] = item;
				}
			}
			else {
				available_more = true;
			}
		});

		if (addNew) {
			ms.values.available[ms.values.search] = {
				id: ms.values.search,
				name: ms.values.search + ' (' + ms.options.labels['new'] + ')',
				isNew: true
			};
		}

		var found = 0,
			preselected = '';

		if (objectSize(ms.values.available) == 0) {
			var div = $('<div>', {
					'class': 'multiselect-matches',
					text: ms.options.labels['No matches found']
				})
					.on('click', function() {
						$('input[type="text"]', $obj)[0].focus({preventScroll:true});
					});

			ms.values.available_div.append(div);
		}
		else {
			ms.values.available_div.append($('<ul>', {
				'class': 'multiselect-suggest',
				'aria-hidden': true
			}));

			$.each(ms.values.available, function (i, item) {
				if (found == 0) {
					preselected = (item.prefix || '') + item.name;
				}
				addAvailable($obj, item);
				found++;
			});
		}

		if (found > 0) {
			$('[aria-live]', $obj).text(
				(available_more
					? sprintf(t('More than %1$d matches for %2$s found'), found, ms.values.search)
					: sprintf(t('%1$d matches for %2$s found'), found, ms.values.search)) +
				', ' + sprintf(t('%1$s preselected, use down,up arrow keys and enter to select'), preselected)
			);
		}
		else {
			$('[aria-live]', $obj).text(ms.options.labels['No matches found']);
		}

		if (available_more) {
			var div = $('<div>', {
					'class': 'multiselect-matches',
					text: ms.options.labels['More matches found...']
				})
					.on('click', function() {
						$('input[type="text"]', $obj)[0].focus({preventScroll:true});
					});

			ms.values.available_div.prepend(div);
		}
	}

	function showAvailable($obj) {
		var ms = $obj.data('multiSelect'),
			$available = ms.values.available_div;

		if ($available.parent().is(document.body)) {
			return;
		}

		$('.multiselect-available').not($available).each(function() {
			hideAvailable($(this).data('obj'));
		});

		$available.data('obj', $obj);

		// Will disconnect this handler in hideAvailable().
		var hide_handler = function() {
			hideAvailable($obj);
		};

		$available.data('hide_handler', hide_handler);

		$obj.parents().add(window).one('scroll', hide_handler);
		$(window).one('resize', hide_handler);

		// For auto-test purposes.
		$available.attr('data-opener', $obj.attr('id'));

		var obj_offset = $obj.offset(),
			obj_padding_y = $obj.outerHeight() - $obj.height(),
			// Subtract 1px for borders of the input and available container to overlap.
			available_top = obj_offset.top + $obj.height() + obj_padding_y / 2 - 1,
			available_left = obj_offset.left,
			available_width = $obj.width(),
			available_max_height = Math.max(50, Math.min(400,
				// Subtract 10px to make available box bottom clearly visible, for better usability.
				$(window).height() + $(window).scrollTop() - available_top - obj_padding_y - 10
			));

		if (objectSize(ms.values.available) > 0) {
			available_width_min = Math.max(available_width, 300);

			// Prevent less than 15% width difference for the available list and the input field.
			if ((available_width_min - available_width) / available_width > .15) {
				available_width = available_width_min;
			}

			available_left = Math.min(available_left, $(window).width() + $(window).scrollLeft() - available_width);
			if (available_left < 0) {
				available_width += available_left;
				available_left = 0;
			}
		}

		$available.css({
			'top': available_top,
			'left': available_left,
			'width': available_width,
			'max-height': available_max_height
		});

		$available.scrollTop(0);

		if (objectSize(ms.values.available) != 0) {
			// Remove selected item selected state.
			$('.selected li.selected', $obj).removeClass('selected');

			// Pre-select first available item.
			if ($('li', $available).length > 0) {
				$('li.suggest-hover', $available).removeClass('suggest-hover');
				$('li:first-child', $available).addClass('suggest-hover');
			}
		}

		$available.appendTo(document.body);
	}

	function hideAvailable($obj) {
		var ms = $obj.data('multiSelect'),
			$available = ms.values.available_div;

		clearSearchTimeout($obj);

		if (!$available.parent().is(document.body)) {
			return;
		}

		$available.detach();

		var hide_handler = $available.data('hide_handler');
		$obj.parents().add(window).off('scroll', hide_handler);
		$(window).off('resize', hide_handler);

		$available
			.removeData(['obj', 'hide_handler'])
			.removeAttr('data-opener');
	}

	function cleanAvailable($obj) {
		var ms = $obj.data('multiSelect');

		hideAvailable($obj);

		ms.values.available = {};
		ms.values.available_div.empty();
	}

	function scrollAvailable($obj) {
		var ms = $obj.data('multiSelect'),
			$available = ms.values.available_div,
			$selected = $available.find('li.suggest-hover');

		if ($selected.length > 0) {
			var	available_height = $available.height(),
				selected_top = 0,
				selected_height = $selected.outerHeight(true);

			if ($('.multiselect-matches', $available)) {
				selected_top += $('.multiselect-matches', $available).outerHeight(true);
			}

			$available.find('li').each(function() {
				var item = $(this);
				if (item.hasClass('suggest-hover')) {
					return false;
				}
				selected_top += item.outerHeight(true);
			});

			if (selected_top < $available.scrollTop()) {
				var prev = $selected.prev();

				$available.scrollTop((prev.length == 0) ? 0 : selected_top);
			}
			else if (selected_top + selected_height > $available.scrollTop() + available_height) {
				$available.scrollTop(selected_top - available_height + selected_height);
			}
		}
		else {
			$available.scrollTop(0);
		}
	}

	function cleanSearchInput($obj) {
		$('input[type="text"]', $obj).val('');

		clearSearchTimeout($obj);
	}

	function cleanSearchHistory($obj) {
		var ms = $obj.data('multiSelect');

		ms.values.cache_key = '';
		ms.values.search = '';
		ms.values.searches = {};
		ms.values.searching = {};
	}

	function clearSearchTimeout($obj) {
		var ms = $obj.data('multiSelect');

		if (ms.values.searchTimeout !== undefined) {
			clearTimeout(ms.values.searchTimeout);
			delete ms.values.searchTimeout;
		}
	}

	function cleanSearch($obj) {
		cleanAvailable($obj);
		cleanSearchInput($obj);
		cleanSearchHistory($obj);
		updateSearchFieldVisibility($obj);
	}

	function updateSearchFieldVisibility($obj) {
		var ms = $obj.data('multiSelect'),
			visible_now = !$obj.hasClass('search-disabled'),
			visible = !ms.options.disabled
				&& (ms.options.selectedLimit == 0 || $('.selected li', $obj).length < ms.options.selectedLimit);

		if (visible === visible_now) {
			return;
		}

		if (visible) {
			var $label = $('label[for=' + $obj.attr('id') + '_ms]');

			$obj.removeClass('search-disabled')
				.find('input[type="text"]')
				.attr({
					placeholder: ms.options.placeholder,
					'aria-label': ($label.length ? $label.text() + '. ' : '') + ms.options.placeholder,
					readonly: false
				});
		}
		else {
			$obj.addClass('search-disabled')
				.find('input[type="text"]')
				.attr({
					placeholder: '',
					'aria-label': '',
					readonly: true
				});
		}
	}

	function isSearchFieldVisible($obj) {
		return !$obj.hasClass('search-disabled');
	}

	function getLimit($obj) {
		var ms = $obj.data('multiSelect');

		return (ms.options.limit != 0)
			? ms.options.limit + objectSize(ms.values.selected) + ms.options.excludeids.length + 1
			: null;
	}
})(jQuery);
