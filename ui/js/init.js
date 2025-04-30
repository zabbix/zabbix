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


/**
 * An object that is used to namespace objects, allows to retrieve and write objects via arbitrary path.
 */

window.ZABBIX = Object.create({

	/**
	 * @param {string} path  Dot separated path. Each segment is used as object key.
	 * @param {mixed} value  Optional value to be written into path only if path held undefined before.
	 *
	 * @return {mixed}  Value underlying the path is returned.
	 */
	namespace: function(path, value) {
		return path.split('.').reduce(function(obj, pt, idx, src) {
			var last = (idx + 1 == src.length);

			if (typeof obj[pt] === 'undefined') {
				obj[pt] = last ? value : {};
			}

			return obj[pt];
		}, this);
	},

	/**
	 * Logs user out, also, handles side effects before that.
	 */
	logout: function(csrf_token) {
		let ls = this.namespace('instances.localStorage');
		ls && ls.destruct();

		redirect(`index.php?reconnect=1&${CSRF_TOKEN_NAME}=${csrf_token}`, 'post', CSRF_TOKEN_NAME, true);
	}
});

document.addEventListener('click', e => {
	const element = e.target;

	if (element.matches('input[type="radio"][readonly], input[type="checkbox"][readonly]')) {
		e.preventDefault();
	}
});

jQuery(function($) {

	$.propHooks.disabled = {
		set: function (el, val) {
			if (el.disabled !== val) {
				el.disabled = val;
				$(el).trigger(val ? 'disable' : 'enable');
			}
		}
	};

	var $search = $('#search');

	if ($search.length) {
		createSuggest('search');

		var $search_icon = $search.siblings('.js-search');

		$search.on('keyup', function() {
			$search_icon.prop('disabled', $.trim($search.val()) === '');
		});

		$search.closest('form').on('submit', function() {
			return ($.trim($search.val()) !== '');
		});

		$search_icon.on('click', function() {
			if ($('.sidebar').is('.is-compact:not(.is-opened)')) {
				$search.focus();

				return false;
			}
		});
	}

	function uncheckedHandler($checkbox) {
		var $hidden = $checkbox.prev('input[type=hidden][name="' + $checkbox.prop('name') + '"]');

		if ($checkbox.is(':checked') || $checkbox.prop('disabled')) {
			$hidden.remove();
		}
		else if (!$hidden.length) {
			$('<input>', {'type': 'hidden', 'name': $checkbox.prop('name')})
				.val($checkbox.attr('unchecked-value'))
				.insertBefore($checkbox);
		}
	}

	$('input[unchecked-value]').each(function() {
		var $this = $(this);

		uncheckedHandler($this);
		$this.on('change enable disable', function () {
			uncheckedHandler($(this))
		})
	});

	function showMenuPopup($obj, data, event, options) {
		var sections;

		switch (data.type) {
			case 'history':
				sections = getMenuPopupHistory(data);
				break;

			case 'host':
				sections = getMenuPopupHost(data, $obj);
				break;

			case 'map_element_submap':
				sections = getMenuPopupMapElementSubmap(data);
				break;

			case 'map_element_group':
				sections = getMenuPopupMapElementGroup(data);
				break;

			case 'map_element_trigger':
				sections = getMenuPopupMapElementTrigger(data);
				break;

			case 'map_element_image':
				sections = getMenuPopupMapElementImage(data);
				break;

			case 'trigger':
				sections = getMenuPopupTrigger(data, $obj);
				break;

			case 'trigger_macro':
				sections = getMenuPopupTriggerMacro(data);
				break;

			case 'dashboard':
				sections = getMenuPopupDashboard(data, $obj);
				break;

			case 'item':
				sections = getMenuPopupItem(data);
				break;

			case 'item_prototype':
				sections = getMenuPopupItemPrototype(data);
				break;

			case 'dropdown':
				sections = getMenuPopupDropdown(data, $obj);
				break;

			case 'submenu':
				sections = getMenuPopupSubmenu(data);
				break;

			case 'drule':
				sections = getMenuPopupDRule(data);
				break;

			default:
				return;
		}

		$obj.menuPopup(sections, event, options);
	}

	/**
	 * Create preloader elements for the menu popup.
	 */
	function createMenuPopupPreloader() {
		return $('<div>', {
			'id': 'menu-popup-preloader',
			'class': 'is-loading menu-popup-preloader'
		})
			.appendTo($('body'))
			.on('click', function(e) {
				e.stopPropagation();
			})
			.hide();
	}

	/**
	 * Event handler for the preloader elements destroy.
	 */
	function menuPopupPreloaderCloseHandler(event) {
		overlayPreloaderDestroy(event.data.id);
	}

	/**
	 * Is request to a server required to process and update the data passed to the popup menu?
	 *
	 * @param string type  A menu popup type.
	 *
	 * @returns boolean
	 */
	function isServerRequestRequired(type) {
		switch (type) {
			case 'dashboard':
			case 'dropdown':
			case 'submenu':
				return false;

			default:
				return true;
		}
	}

	/**
	 * Make a default position object for the menu popup, based on it's type.
	 *
	 * @param object $obj   Menu popup opener object.
	 * @param object data   Menu popup data object.
	 * @param object event  Original opener event.
	 */
	function makeDefaultPosition($obj, data, event) {
		switch (data.type) {
			case 'dropdown':
				return {
					of: $obj,
					my: 'left top',
					at: 'left bottom'
				};

			case 'submenu':
				return {
					of: $obj,
					my: 'left top',
					at: 'left bottom+10'
				};

			default:
				// Should match the default algorithm used in $.menuPopup().
				return {
					of: (['click', 'mouseup', 'mousedown'].includes(event.type) && event.originalEvent.detail)
						? event
						: event.target,
					my: 'left top',
					at: 'left bottom',
					using: (pos, data) => {
						const wrapper = document.querySelector('.wrapper');
						const menu = data.element.element[0];
						const wrapper_rect = document.querySelector('.wrapper').getBoundingClientRect();
						const margin_right = Math.max(WRAPPER_PADDING_RIGHT, wrapper_rect.width - wrapper.clientWidth);
						const max_left = wrapper_rect.right - menu.offsetWidth - margin_right;

						pos.left = Math.max(wrapper_rect.left, Math.min(max_left, pos.left));

						menu.style.left = `${pos.left}px`;
						menu.style.top = `${pos.top}px`;
					}
				};
		}
	}

	/**
	 * Build menu popup for given elements.
	 */
	$(document).on('keydown click', '[data-menu-popup]', function(event) {
		var $obj = $(this),
			data = $obj.data('menu-popup');

		if (event.type === 'keydown' && event.which != 13) {
			return;
		}

		// Manually trigger event for menuPopupPreloaderCloseHandler call for the previous preloader.
		if ($('#menu-popup-preloader').length) {
			$(document).trigger('click');
		}

		// Close other action menus and prevent focus jumping before opening a new popup.
		$('.menu-popup-top').menuPopup('close', null, false);

		// Create options object based on original options.
		var options = $.extend({
			position: makeDefaultPosition($obj, data, event)
		}, data.options || {});

		if (isServerRequestRequired(data.type)) {
			if (data.type === 'trigger') {
				// Add additional IDs from checkboxes and pass them to popup menu.
				data.data.ids = Object.keys(chkbxRange.getSelectedIds());
			}

			var url = new Curl('zabbix.php');

			url.setArgument('action', 'menu.popup');
			url.setArgument('type', data.type);

			var xhr = $.ajax({
					url: url.getUrl(),
					method: 'POST',
					data: {
						data: data.data
					},
					dataType: 'json'
				});

			var	$preloader = createMenuPopupPreloader();

			setTimeout(function() {
				$preloader.fadeIn(200).position(options.position);
			}, 500);

			addToOverlaysStack($preloader.prop('id'), event.target, 'preloader', xhr);

			xhr.done(function(resp) {
				overlayPreloaderDestroy($preloader.prop('id'));

				if ('error' in resp) {
					clearMessages();

					const message_box = makeMessageBox('bad', resp.error.messages, resp.error.title);

					addMessage(message_box);

					return;
				}

				showMenuPopup($obj, jQuery.extend({context: data.context}, resp.data), event, options);
			});

			$(document)
				.off('click', menuPopupPreloaderCloseHandler)
				.on('click', {id: $preloader.prop('id')}, menuPopupPreloaderCloseHandler);
		}
		else {
			showMenuPopup($obj, jQuery.extend({type: data.type}, data.data), event, options);
		}

		return false;
	});

	/**
	 * add.popup event
	 *
	 * Call multiselect method 'addData' if parent was multiselect, execute addPopupValues function
	 * or just update input field value
	 *
	 * @param object data
	 * @param string data.object   object name
	 * @param array  data.values   values
	 * @param string data.parentId parent id
	 */
	$(document).on('add.popup', function(e, data) {
		// multiselect check
		if ($('#' + data.parentId).hasClass('multiselect')) {
			var items = [];
			for (var i = 0; i < data.values.length; i++) {
				if (typeof data.values[i].id !== 'undefined') {
					var item = {
						'id': data.values[i].id,
						'name': data.values[i].name,
						'query': data.values[i].query
					};

					if (typeof data.values[i].prefix !== 'undefined') {
						item.prefix = data.values[i].prefix;
					}
					items.push(item);
				}
			}

			$('#' + data.parentId).multiSelect('addData', items);
		}
		else if (typeof window.addPopupValues !== 'undefined') {
			// execute function if they exist
			window.addPopupValues(data);
		}
		else if (typeof view.addPopupValues !== 'undefined') {
			view.addPopupValues(data);
		}
		else {
			$('#' + data.parentId).val(data.values[0].name);
		}
	});

	// redirect buttons
	$('button[data-url]').click(function() {
		var button = $(this);
		var confirmation = button.attr('data-confirmation');

		if (typeof confirmation === 'undefined' || (typeof confirmation !== 'undefined' && confirm(confirmation))) {
			if (button.attr('data-post')) {
				return redirect(button.attr('data-url'), 'post', CSRF_TOKEN_NAME, true);
			}

			window.location = button.attr('data-url');
		}
	});

	// Initialize hintBox event handlers.
	hintBox.bindEvents();

	// Simulate Safari behaviour when text in a text field with autofocus becomes selected after page has loaded.
	if (!SF) {
		$('input[type=text][autofocus=autofocus]').filter(':visible').select();
	}

	/**
	 * @param {boolean} preserve_state  Preserve current state of the debug button.
	 *
	 * @returns {boolean} false
	 */
	function debug_click_handler(preserve_state) {
		var $button = $(this),
			visible = sessionStorage.getItem('debug-info-visible') === '1';

		if (preserve_state !== true) {
			visible = !visible;

			sessionStorage.setItem('debug-info-visible', visible ? '1' : '0');
		}

		$button.text(visible ? t('Hide debug') : t('Debug'));

		var style = $button.data('debug-info-style');
		if (style) {
			style.sheet.deleteRule(0);
		}
		else {
			style = document.createElement('style');
			$button.data('debug-info-style', style);
			document.head.appendChild(style);
		}

		// ZBX_STYLE_DEBUG_OUTPUT
		style.sheet.insertRule('.debug-output { display: ' + (visible ? 'block' : 'none') + '; }', 0);

		if (preserve_state !== true) {
			$.publish('debug.click', {
				visible: visible
			});
		}

		return false;
	}

	// Initialize ZBX_STYLE_BTN_DEBUG debug button and debug info state.
	$('.btn-debug').each(function(index, button) {
		$(button)
			.on('click', debug_click_handler)
			.addClass('visible');

		debug_click_handler.call(button, true);
	});
});
