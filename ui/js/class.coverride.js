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
 * JQuery class that creates an override UI control - button that shows menu with available override options and pill
 * buttons on which user can change selected option. Used in graph widget configuration window.
 */
jQuery(function ($) {
	"use strict";

	function createOverrideElement($override, option, value) {
		var close = $('<button>', {'type': 'button'})
				.on('click', function(e) {
					$override.overrides('removeOverride', $override, option);
					e.stopPropagation();
					e.preventDefault();
				})
				.addClass('subfilter-disable-btn'),
			opt = $override.data('options'),
			field_name = opt.makeName(option, opt.getId($override));

		if (option === 'color') {
			const id = field_name.replace(/\]/g, '_').replace(/\[/g, '_');
			const input = $('<input>', {'name': field_name, 'type': 'hidden', 'id': id}).val(value);

			return $('<div>')
				.addClass('color-picker')
				.append(input)
				.append(close);
		}
		else if (option === 'timeshift') {
			return $('<div>')
				.append($('<input>', {
						'name': field_name,
						'maxlength': 10,
						'type': 'text',
						'placeholder': t('S_TIME_SHIFT')
					})
					.val(value)
				)
				.append(close);
		}
		else {
			var visible_name = option,
				visible_value = value;

			if (typeof opt.captions[option] !== 'undefined') {
				visible_name = opt.captions[option];
			}
			if (typeof opt.captions[option + value] !== 'undefined') {
				visible_value = opt.captions[option + value];
			}

			var content = [
				$('<span>', {'data-option': option}).text(visible_name + ': ' + visible_value),
				$('<input>').attr({'name': field_name, 'type': 'hidden'}).val(value)
			];

			return $('<div>')
				.append(content)
				.append(close);
		}
	}

	function getMenu($obj, options, option_to_edit, trigger_elmnt) {
		var sections = [],
			menu = [],
			option_to_edit = option_to_edit || null;

		var appendMenuItem = function(menu, name, items, opt) {
			if (items.length > 0) {
				var item = items.shift();

				if (typeof menu[item] === 'undefined') {
					menu[item] = {items: {}};
				}

				appendMenuItem(menu[item].items, name, items, opt);
			}
			else {
				menu[name] = {
					data: opt,
					items: {}
				};
			}
		};

		var getMenuPopupItems = function($obj, tree, trigger_elmnt) {
			var items = [],
				data,
				item;

			if (objectSize(tree) > 0) {
				for (var name in tree) {
					data = tree[name];

					if (typeof data === 'object') {
						item = {label: name};

						if (typeof data.items !== 'undefined' && objectSize(data.items) > 0) {
							item.items = getMenuPopupItems($obj, data.items, trigger_elmnt);
						}

						if (typeof data.data !== 'undefined') {
							item.data = data.data;

							item.clickCallback = function(e) {
								var args = [$obj];
								$(this).data('args').forEach(function(a) {args.push(a)});
								methods[$(this).data('callback')].apply($obj, args);

								// Remove menu only after .data() has been read from <a>.
								$(this).closest('.menu-popup-top').menuPopup('close', null, true);

								cancelEvent(e);
							};
						}

						items[items.length] = item;
					}
				}
			}

			return items;
		};

		$(options.sections).each(function(i, section) {
			menu = [];
			$(section['options']).each(function(i, opt) {
				if (option_to_edit === null || option_to_edit === opt.args[0]) {
					var items = splitPath(opt.name),
						name = (items.length > 0) ? items.pop() : opt.name;

					appendMenuItem(menu, name, items, opt);
				}
			});

			if (option_to_edit) {
				var key = Object.keys(menu)[0];
				sections.push({
					label: key,
					items: getMenuPopupItems($obj, menu[key].items, trigger_elmnt)
				});
			}
			else {
				sections.push({
					label: section['name'],
					items: getMenuPopupItems($obj, menu, trigger_elmnt)
				});
			}
		});

		return sections;
	}

	var methods = {
		/**
		 * Create control for override option configuration.
		 *
		 * Supported options:
		 * - add		- UI element to click on to open override options menu.
		 * - override   - selector for single override set. Mandatory if getId() uses it.
		 * - overridesList - selector for overrides list. Mandatory if getId() uses it.
		 * - options	- selector of UI elements for already specified overrides.
		 * - menu		- JSon for override options that appears in context menu.
		 * - getId	    - Function returns unique identifier of override used for override option name.
		 * - makeName	- Function creates pattern matching name for input field that stores value of override option.
		 * - makeOption	- Function extracts given string and returns override option from it.
		 * - onUpdate	- Function called when override values changes.
		 *
		 * @param options
		 */
		init: function(options) {
			options = $.extend({}, {
				options: 'input[type=hidden]',
				add: null,
				menu: {},
				// Argument row_id is needed even if it is not used. Function is assumed to be overwritten.
				makeName: function(option, row_id) {
					return option;
				},
				makeOption: function(name) {
					return name;
				},
				onUpdate: function() {
					return true;
				},
				getId: function($override) {
					var opt = $override.data('options');
					return $(opt.overridesList + ' ' + opt.override).index($override.closest(opt.override));
				}
			}, options);

			this.each(function() {
				var $override = $(this);
				if (typeof $override.data('options') !== 'undefined') {
					return;
				}

				$override.data('options', $.extend({}, options));

				$(options.options, $override).each(function() {
					var opt = options.makeOption($(this).attr('name')),
						elmnt = createOverrideElement($override, opt, $(this).val());

					$(elmnt).insertBefore($(this));
					$(this).remove();

					if (opt === 'color') {
						$(elmnt).find('input').colorpicker();
					}
				});

				$override.on('click', '[data-option]', function(e) {
					var obj = $(this);
					obj.menuPopup(getMenu($override, options['menu'], obj.data('option'), obj), e);
					return false;
				});

				$(options['add'], $override).on('click keydown', function(e) {
					var obj = $(this);

					if (e.type === 'keydown') {
						if (e.which != 13) {
							return;
						}

						e.preventDefault();
						e.target = this;
					}

					obj.menuPopup(getMenu($override, options['menu'], null, obj), e);
					return false;
				});
			});
		},

		/**
		 * Method:
		 *  - adds new override option (UI element) of type {option} and value {value} for given $override;
		 *  - changes if specified option of type {option} is already set for given $override.
		 *
		 * @param object $override       Object of current override.
		 * @param string option          String of override option to set (e.g. color, type etc).
		 * @param string value           Value of option. Can be NULL for options 'color' and 'timeshift'.
		 */
		addOverride: function($override, option, value) {
			var opt = $override.data('options');
			if ($('[name="' + opt['makeName'](option, opt.getId($override)) + '"]', $override).length > 0) {
				methods.updateOverride($override, option, value);
			}
			else {
				var elmnt = createOverrideElement($override, option, value);
				$('<li>')
					.append(elmnt)
					.insertBefore($('li:last', $override));

				if (option === 'color') {
					$(elmnt).find('input').colorpicker();
				}
			}

			// Call on-select callback.
			opt['onUpdate']();
		},

		/**
		 * Update existing override option in given $override.
		 *
		 * See methods.addOverride for argument description.
		 */
		updateOverride: function($override, option, value) {
			var opt = $override.data('options'),
				field_name = opt['makeName'](option, opt.getId($override));
			$('[name="' + field_name + '"]', $override).val(value);

			switch (option) {
				case 'timeshift':
				case 'color':
					break;

				default:
					var visible_name = (typeof opt.captions[option] !== 'undefined') ? opt.captions[option] : option,
						visible_value = (typeof opt.captions[option + value] !== 'undefined')
							? opt.captions[option + value]
							: value;
					$('span', $('[name="' + field_name + '"]', $override).parent())
						.text(visible_name + ': ' + visible_value);
					break;
			}
		},

		/**
		 * Removes existing override option from given $override.
		 *
		 * @param object $override       Object of current override.
		 * @param string option          Override option that need to be removed.
		 */
		removeOverride: function($override, option) {
			var opt = $override.data('options');
			$('[name="'+opt['makeName'](option, opt.getId($override))+'"]', $(this)).closest('li').remove();
			opt['onUpdate']();
		}
	};

	$.fn.overrides = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		return methods.init.apply(this, arguments);
	};
});
