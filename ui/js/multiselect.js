/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


(function($) {
	var ZBX_STYLE_CLASS = 'multiselect-control';
	const MS_ACTION_POPUP = 0;
	const MS_ACTION_AUTOSUGGEST = 1;

	const FILTER_PRESELECT_ACCEPT_ID = 'id';

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

		var curl = new Curl('jsrpc.php');
		curl.setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);
		curl.setArgument('method', 'multiselect.get');
		curl.setArgument('object_name', options.object_name);

		for (var key in options.objectOptions) {
			curl.setArgument(key, options.objectOptions[key]);
		}

		options.url = curl.getUrl();

		return this.each(function() {
			$(this).empty();
			this.dataset.params = JSON.stringify(options);
			$(this).multiSelect();
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
		 * @param {array}   items           Multiselect value object.
		 * @param {boolean} trigger_change  (optional) Either to trigger element on-change event once data added. True by default.
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

				$obj.trigger('before-add', ms);

				for (var i = 0, l = items.length; i < l; i++) {
					addSelected($obj, items[i]);
				}

				trigger_change && $obj.trigger('change', ms);
			});
		},

		getSearch: function() {
			let search = '';

			this.each(function() {
				const ms = $(this).data('multiSelect');

				search = ms.values.search.toLowerCase().replace(/[*]+/g, '')
			});

			return search;
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

				$obj.removeAttr('aria-disabled');
				$('.multiselect-list', $obj).removeClass('disabled');
				$('.multiselect-button', $obj.parent()).prop('disabled', false);
				$('.multiselect-optional-select-button', $obj.parent()).prop('disabled', false);
				$('input', $obj).prop('disabled', false);

				if (ms.options.disabled === true) {
					ms.options.disabled = false;
					$obj.append(makeMultiSelectInput($obj));

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

				$obj.attr('aria-disabled', true);
				$('.multiselect-list', $obj).addClass('disabled');
				$('.multiselect-button', $obj.parent()).prop('disabled', true);
				$('.multiselect-optional-select-button', $obj.parent()).prop('disabled', true);
				$('input', $obj).prop('disabled', true);

				if (ms.options.disabled === false) {
					ms.options.disabled = true;
					$('input[type="text"]', $obj).remove();

					cleanSearch($obj);
				}
			});
		},

		/**
		 * Remove select object value.
		 *
		 * @param {string} id
		 *
		 * @return jQuery
		 */
		removeSelected: function(id) {
			return this.each(function() {
				var $obj = $(this),
					ms = $obj.data('multiSelect');

				removeSelected($obj, id);

				cleanSearch($obj);

				$obj.trigger('change', ms);
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

				cleanSearch($obj);

				if (addNew_modified) {
					/*
					 * When modifying the "addNew" option, few things must be done:
					 *   1. Search input must be reset.
					 *   2. The already selected "(new)" items must be either hidden and disabled or shown and enabled.
					 *      Note: hidden and disabled items will not submit to the server.
					 *   3. The "change" trigger must fire.
					 */

					$('input[name*="[new]"]', $obj)
						.prop('disabled', !ms.options['addNew'])
						.each(function() {
							var id = $(this).val();
							$('.selected li[data-id]', $obj).each(function() {
								if (this.dataset.id === id) {
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
					if ($obj.data('multiSelect').select_button !== null) {
						ret = $obj.data('multiSelect').select_button[0];
					}

					return false;
				}
			});

			return ret;
		},

		/**
		 * @param array entries  IDs to mark disabled.
		 */
		setDisabledEntries: function(entries) {
			this.each(function() {
				const $obj = $(this);
				const ms = $obj.data('multiSelect');

				if (ms?.options.popup.parameters !== undefined) {
					ms.options.popup.parameters.disableids = entries;
				}
			});
		},

		addOptionalSelect: function(label, callback) {
			this.each(function() {
				const $obj = $(this);

				if ($obj.data('multiSelect') !== undefined) {
					addOptionalSelect($obj, label, callback);

					return false;
				}
			});
		},

		openSelectPopup: function(event_target) {
			this.each(function() {
				const $obj = $(this);
				const ms = $obj.data('multiSelect');

				if (ms !== undefined && ms.options.popup.parameters !== undefined) {
					openSelectPopup($obj, event_target);

					return false;
				}
			});
		},

		setCustomSuggestList: function(callback) {
			this.each(function() {
				const $obj = $(this);
				const ms = $obj.data('multiSelect');

				ms.options.custom_suggest_list = callback;
			});
		},

		setSuggestListModifier: function(callback) {
			this.each(function() {
				const $obj = $(this);
				const ms = $obj.data('multiSelect');

				ms.options.suggest_list_modifier = callback;
			});
		},

		customSuggestSelectHandler: function(callback) {
			this.each(function() {
				const $obj = $(this);
				const ms = $obj.data('multiSelect');

				ms.options.custom_suggest_select_handler = callback;
			});
		}
	};

	/**
	 * Initialize and interact with multi select input element.
	 *
	 * Function can either accept a method from supported methods or expects the multiselect element to contain
	 * a 'data-params' attribute with the following possible properties:
	 *     string url                   backend url
	 *     string name                  input element name
	 *     string multiselect_id        multiselect wrapper id (optional)
	 *     object labels                translated labels (optional)
	 *     array  data                  preload data {id, name, prefix} (optional)
	 *     string data[][id]
	 *     string data[][name]
	 *     string data[][prefix]        (optional)
	 *     bool   data[][inaccessible]  (optional)
	 *     bool   data[][disabled]      (optional)
	 *     string placeholder           set custom placeholder (optional)
	 *     string defaultValue          default value for input element (optional)
	 *     bool   disabled              turn on/off disabled state (optional)
	 *     bool   readonly              turn on/off readonly state (optional)
	 *     bool   hidden                hide element (optional)
	 *     bool   addNew                allow user to create new names (optional)
	 *     int    selectedLimit         how many items can be selected (optional)
	 *     int    limit                 how many available items can be received from backend (optional)
	 *     object popup                 popup data {parameters, width, height} (optional)
	 *     string popup[parameters]
	 *     string popup[filter_preselect]
	 *     string popup[filter_preselect][id]
	 *     string popup[filter_preselect][submit_as]
	 *     object popup[filter_preselect][submit_parameters]
	 *     bool   popup[filter_preselect][multiple]
	 *     int    popup[width]
	 *     int    popup[height]
	 *     object autosuggest           autosuggest options (optional)
	 *     object autosuggest[filter_preselect]
	 *     string autosuggest[filter_preselect][id]
	 *     string autosuggest[filter_preselect][submit_as]
	 *     object autosuggest[filter_preselect][submit_parameters]
	 *     bool   autosuggest[filter_preselect][multiple]
	 *     string styles                additional style for multiselect wrapper HTML element (optional)
	 *     string styles[property]
	 *     string styles[value]
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(method) {
		if (method !== undefined) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		var defaults = {
			url: '',
			name: '',
			multiselect_id: '',
			object_labels: {object: '', objects: ''},
			labels: {
				'No matches found': t('No matches found'),
				'More matches found...': t('More matches found...'),
				'type here to search': t('type here to search'),
				'new': t('new'),
				'Select': t('Select')
			},
			placeholder: t('type here to search'),
			data: [],
			addNew: false,
			defaultValue: null,
			custom_select: false,
			custom_suggest_list: null,
			suggest_list_modifier: null,
			custom_suggest_select_handler: null,
			disabled: false,
			readonly: false,
			selectedLimit: 0,
			limit: 20,
			popup: {},
			styles: {}
		};

		return this.each(function() {
			var $obj = $(this);

			if ($obj.data('multiSelect') !== undefined) {
				return;
			}

			const options = $.extend({}, defaults, JSON.parse(this.dataset.params));

			options.required_str = $obj.attr('aria-required') === undefined ? 'false' : $obj.attr('aria-required');
			$obj.removeAttr('aria-required');

			var ms = {
				options: options,
				values: {
					search: '',
					searches: {},
					searching: {},
					selected: {},
					available: new Map(),
					available_div: $('<div>', {'class': 'multiselect-available'}),

					/*
					 * Indicates a false click on an available list, but not on some actual item.
					 * In such case the "focusout" event (IE) of the search input should not be processed.
					 */
					available_false_click: false
				},
				select_button: null,
				optional_select_menu: []
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
				css: ms.options.styles,
				id: ms.options.multiselect_id !== '' ? ms.options.multiselect_id : null
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

			if (ms.options.disabled || ms.options.readonly) {
				if (ms.options.disabled) {
					$obj.attr('aria-disabled', true);
					$selected_ul.addClass('disabled');
				}
				else {
					$obj.attr('aria-readonly', true);
					$selected_ul.attr('aria-readonly', true);
				}
			}
			else {
				$obj.append(makeMultiSelectInput($obj));
			}

			$obj
				.on('mousedown', function(event) {
					if (isSearchFieldVisible($obj) && ms.options.selectedLimit != 1 && !ms.options.readonly) {
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
					'class': `${ZBX_STYLE_BTN_GREY} multiselect-button`,
					text: ms.options.labels['Select']
				});
			}

			if (ms.select_button !== null) {
				if (ms.options.popup.parameters !== undefined) {
					ms.select_button.on('click', (e) => openSelectPopup($obj, e.target));
				}

				if (ms.options.disabled || ms.options.readonly) {
					ms.select_button.prop('disabled', true);
				}

				addSelectButton($obj, ms.select_button);
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
	function getFilterPreselect($obj, action) {
		const ms = $obj.data('multiSelect');
		const options_key = action == MS_ACTION_AUTOSUGGEST ? 'autosuggest' : 'popup';

		if (!(options_key in ms.options) || !('filter_preselect' in ms.options[options_key])) {
			return {};
		}

		const filter_preselect = ms.options[options_key].filter_preselect;
		const data = $('#' + filter_preselect.id).multiSelect('getData');

		const accept = filter_preselect.accept ?? null;

		const ret_data = [];

		for (const item of data) {
			if (accept === null || (accept === FILTER_PRESELECT_ACCEPT_ID && /^[0-9]+$/g.test(item.id))) {
				ret_data.push(item.id);
			}
		}

		if (ret_data.length === 0) {
			return {};
		}

		let ret = {
			[filter_preselect.submit_as]: filter_preselect.multiple ? ret_data : ret_data[0]
		};

		if ('submit_parameters' in filter_preselect) {
			ret = {...ret, ...filter_preselect.submit_parameters};
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
						var preselect_values = getFilterPreselect($obj, MS_ACTION_AUTOSUGGEST),
							cache_key = search + JSON.stringify(preselect_values);

						/*
						 * Strategy:
						 * 1. Load the cached result set if such exists for the given term and show the list.
						 * 2. Skip anything if already expecting the result set to arrive for the given term.
						 * 3. Schedule result set retrieval for the given term otherwise.
						 */

						if (ms.options.custom_suggest_list !== null) {
							ms.values.search = search;
							ms.values.cache_key = cache_key;
							ms.values.searches[cache_key] = ms.options.custom_suggest_list();
							loadAvailable($obj);
							showAvailable($obj);
						}
						else if (cache_key in ms.values.searches) {
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
										limit: ms.options.limit !== 0
											? ms.options.limit + getSkipSearchIds($obj).length + 1
											: undefined
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
									select($obj, $selected[0].dataset.id);
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
							var $collection = $('li[data-id]', ms.values.available_div.filter(':visible')),
								$selected = $collection.filter('.suggest-hover');

							if ($selected.length) {
								$selected.removeClass('suggest-hover');

								$selected = (e.which == KEY_ARROW_UP)
									? ($selected.is($collection.first()) ? $collection.last() : $selected.prevAll('[data-id]').first())
									: ($selected.is($collection.last()) ? $collection.first() : $selected.nextAll('[data-id]').first());

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
									const id = $selected[0].dataset.id;
									const item = ms.values.selected[id];

									if (item.disabled === undefined || !item.disabled) {
										let aria_text = sprintf(t('Removed, %1$s'), $selected.data('label'));

										$selected = (e.which == KEY_BACKSPACE)
											? ($selected.is(':first-child')
												? $selected.next('[data-id]')
												: $selected.prev('[data-id]')
											)
											: ($selected.is(':last-child')
												? $selected.prev('[data-id]')
												: $selected.next('[data-id]')
											);

										removeSelected($obj, id);

										$obj.trigger('change', ms);

										if ($selected.length) {
											var $collection = $('.selected li[data-id]', $obj);
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
				.on('focusin', function() {
					$obj.addClass('active');
				})
				.on('focusout', function() {
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

		if (ms.options.custom_suggest_select_handler !== null) {
			ms.options.custom_suggest_select_handler(ms.values.available.get(id.toString()));
		}
		else {
			addSelected($obj, ms.values.available.get(id.toString()));
		}

		if (isSearchFieldVisible($obj)) {
			$('input[type="text"]', $obj)[0].focus({preventScroll:true});
		}

		$obj.trigger('change', ms);
	}

	function addSelectButton($obj, $button) {
		let $container = $obj.siblings('.btn-split');

		if (!$container.length) {
			$container = $('<ul>', {'class': 'btn-split'});
			$obj.after($container);
		}

		$container.append($('<li>').append($button));
	}

	function addOptionalSelect($obj, label, callback) {
		const ms = $obj.data('multiSelect');

		if (!ms.optional_select_menu.length) {
			addSelectButton($obj, $('<button>', {
				type: 'button',
				class: `${ZBX_STYLE_BTN_GREY} ${ZBX_ICON_CHEVRON_DOWN_SMALL} multiselect-optional-select-button`
			}).on('click', function(event) {
				jQuery(event.target).menuPopup(
					[{items: ms.optional_select_menu}],
					new jQuery.Event(event), {
						position: {of: event.target, my: 'left top', at: 'left bottom', within: '.wrapper'}
					}
				);
			}));
		}

		ms.optional_select_menu.push({label, clickCallback: callback});
	}

	function openSelectPopup($obj, open_trigger_element) {
		const ms = $obj.data('multiSelect');

		let parameters = ms.options.popup.parameters;

		if (ms.options.popup.filter_preselect) {
			parameters = jQuery.extend(parameters, getFilterPreselect($obj, MS_ACTION_POPUP));
		}

		if (parameters['disable_selected'] !== undefined && parameters['disable_selected']) {
			parameters['disableids'] = Object.keys(ms.values.selected);
		}

		// Click used instead focus because in pattern select only click is listened for.
		$('input[type="text"]', $obj).click();

		PopUp('popup.generic', parameters, {
			dialogue_class: 'modal-popup-generic',
			trigger_element: open_trigger_element
		});
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
							.addClass([ZBX_STYLE_BTN_ICON, ZBX_ICON_REMOVE_SMALLER])
							.on('click', function() {
								if (!ms.options.disabled && !item_disabled && !ms.options.readonly) {
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
					if (isSearchFieldVisible($obj) && ms.options.selectedLimit != 1 && !ms.options.readonly) {
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
		const ms = $obj.data('multiSelect');

		$obj.trigger('before-remove', ms);

		$('.multiselect-list [data-id]', $obj).each(function() {
			if (this.dataset.id == id) {
				$(this).remove();
			}
		});
		$('input[type="hidden"]', $obj).each(function() {
			if ($(this).val() == id) {
				$(this).remove();
			}
		});

		delete ms.values.selected[id];

		if (!Object.keys(ms.values.selected).length) {
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
				'data-label': prefix + item.name,
				'data-source': item.source
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

		const skip_search_ids = getSkipSearchIds($obj);

		let available_more = false;

		if (ms.options.custom_suggest_list !== null) {
			ms.values.available = data;
		}
		else {
			$.each(data, function(i, item) {
				if (ms.options.limit == 0 || ms.values.available.size < ms.options.limit) {
					if (!skip_search_ids.includes(item.id)) {
						ms.values.available.set(item.id, item);
					}
				}
				else {
					available_more = true;
				}
			});
		}

		if (addNew) {
			ms.values.available.set(ms.values.search, {
				id: ms.values.search,
				name: ms.values.search + ' (' + ms.options.labels['new'] + ')',
				isNew: true
			});
		}

		var found = 0,
			preselected = '';

		if (ms.options.suggest_list_modifier !== null) {
			ms.values.available = ms.options.suggest_list_modifier(ms.values.available);
		}

		if (ms.values.available.size === 0) {
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

			for (const item of ms.values.available.values()) {
				if ('group_label' in item) {
					$('ul', ms.values.available_div)
						.addClass('multiselect-suggest-grouped')
						.append(
							$('<li>', {class: 'suggest-group'}).text(item.group_label)
						);
				}
				else {
					if (found === 0) {
						preselected = (item.prefix || '') + item.name;
					}
					addAvailable($obj, item);
					found++;
				}
			}
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

		if (ms.values.available.size > 0) {
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

		if (ms.values.available.size !== 0) {
			// Remove selected item selected state.
			$('.selected li.selected', $obj).removeClass('selected');

			// Pre-select first available item.
			if ($('li[data-id]', $available).length > 0) {
				$('li.suggest-hover', $available).removeClass('suggest-hover');
				$('li[data-id]', $available).first().addClass('suggest-hover');
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
		const ms = $obj.data('multiSelect');

		hideAvailable($obj);

		ms.values.available.clear();
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

			if ($('.multiselect-matches', $available).length > 0) {
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

	function getSkipSearchIds($obj) {
		const ms = $obj.data('multiSelect');

		return [...new Set([
			...Object.keys(ms.values.selected),
			...(ms.options.popup.parameters?.excludeids || []),
			...(ms.options.popup.parameters?.disableids || [])
		])];
	}
})(jQuery);
