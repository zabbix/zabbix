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
 * JQuery class that creates an override UI control - button that shows menu with available override options and pill
 * buttons on which user can change selected option. Used in graph widget configuration window.
 */
jQuery(function ($) {
	"use strict";

	function createOverrideElement($override, option, value) {
		var close = $('<button></button>', {'type': 'button'})
				.on('click', function(e) {
					$override.overrides('removeOverride', $override, option);
					e.stopPropagation();
					e.preventDefault();
				})
				.addClass('remove-btn'),
			opt = $override.data('options'),
			field_name = opt.makeName(option);

		if (option === 'color') {
			var id = field_name.replace(/\]/g, '_').replace(/\[/g, '_'),
				input = $('<input>')
					.attr({'name': field_name, 'type': 'text', 'maxlength': 6, 'id': id, 'placeholder': t('color')})
					.val(value);

			return $('<div></div>')
				.addClass('input-color-picker')
				.append(input)
				.append(close);
		}
		else if (option === 'timeshift') {
			return $('<div></div>')
				.append($('<input>', {
						'name': field_name,
						'maxlength': 10,
						'type': 'text',
						'placeholder': t('time shift')
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
				$('<span></span>', {'data-option': option}).text(visible_name + ': ' + visible_value),
				$('<input>').attr({'name': field_name, 'type': 'hidden'}).val(value)
			];

			return $('<div></div>')
				.append(content)
				.append(close);
		}
	};

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
								$(this).closest('.action-menu-top').menuPopup('close', null, true);

								var args = [$obj];
								$(this).data('args').each(function(a) {args.push(a)});
								methods[$(this).data('callback')].apply($obj, args);

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
				sections.push({
					label: Object.keys(menu)[0],
					items: getMenuPopupItems($obj, Object.values(menu)[0].items, trigger_elmnt)
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
	};

	var methods = {
		/**
		 * Create control for override option configuration.
		 *
		 * Supported options:
		 * - add		- UI element to click on to open override options menu.
		 * - options	- selector of UI elements for already specified overrides.
		 * - menu		- JSon for override options that appears in context menu.
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
				makeName: function(option) {
					return option;
				},
				makeOption: function(name) {
					return name;
				},
				onUpdate: function() {
					return true;
				}
			}, options);

			this.each(function() {
				var override = $(this);
				if (typeof override.data('options') !== 'undefined') {
					return;
				}

				var row_id = $(options['add'], override).data('row');
				override.data('options', $.extend({}, {rowId: row_id}, options));

				$(options.options, override).each(function() {
					var opt = options.makeOption($(this).attr('name')),
						elmnt = createOverrideElement(override, opt, $(this).val());
					$(elmnt).insertBefore($(this));
					$(this).remove();

					if (opt === 'color') {
						$(elmnt).find('input').colorpicker();
					}
				});

				$(override).on('click', '[data-option]', function(e) {
					var obj = $(this);

					obj.menuPopup(getMenu(override, options['menu'], obj.data('option'), obj), e);
					return false;
				});

				$(options['add'], override).on('click keydown', function(e) {
					var obj = $(this);

					if (e.type === 'keydown') {
						if (e.which != 13) {
							return;
						}

						e.preventDefault();
						e.target = this;
					}

					obj.menuPopup(getMenu(override, options['menu'], null, obj), e);
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
		 * @param string option          String of ovverride option to set (e.g. color, type etc).
		 * @param string value           Value of option. Can be NULL for options 'color' and 'timeshift'.
		 */
		addOverride: function($override, option, value) {
			if ($('[name="'+$override.data('options')['makeName'](option)+'"]', $override).length > 0) {
				methods.updateOverride($override, option, value);
			}
			else {
				var elmnt = createOverrideElement($override, option, value);
				$('<li></li>')
					.append(elmnt)
					.insertBefore($('li:last', $override));

				if (option === 'color') {
					$(elmnt).find('input').colorpicker();
				}
			}

			// Call on-select callback.
			$override.data('options')['onUpdate']();
		},

		/**
		 * Update existing override option in given $override.
		 *
		 * See methods.addOverride for argument description.
		 */
		updateOverride: function($override, option, value) {
			var field_name = $override.data('options')['makeName'](option);
			$('[name="'+field_name+'"]', $override).val(value);

			switch (option) {
				case 'timeshift':
				case 'color':
					break;

				default:
					var o = $override.data('options');
					var visible_name = (typeof o.captions[option] !== 'undefined') ? o.captions[option] : option;
					var visible_value = (typeof o.captions[option+value] !== 'undefined') ? o.captions[option+value] : value;
					$('span', $('[name="'+field_name+'"]', $override).parent()).text(visible_name+': '+visible_value);
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
			$('[name="'+$override.data('options')['makeName'](option)+'"]', $(this)).closest('li').remove();
			$override.data('options')['onUpdate']();
		}
	};

	$.fn.overrides = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else {
			return methods.init.apply(this, arguments);
		}
	};
});
