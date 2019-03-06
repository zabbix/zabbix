/*
 ** Zabbix
 ** Copyright (C) 2001-2019 Zabbix SIA
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

	var $search = $('#search');

	if ($search.length) {
		createSuggest('search');

		$search.keyup(function() {
			$search
				.siblings('button')
				.attr('disabled', ($.trim($search.val()) === '') ? true : null);
		}).closest('form').submit(function() {
			if ($.trim($search.val()) === '') {
				return false;
			}
		});
	}

	if (IE) {
		setTimeout(function () { $('[autofocus]').focus(); }, 10);
	}

	/**
	 * Change combobox color according selected option.
	 */
	$('select').each(function() {
		var comboBox = $(this),
			changeClass = function(obj) {
				if (obj.find('option.red:selected').length > 0) {
					obj.addClass('red');
				}
				else {
					obj.removeClass('red');
				}
			};

		comboBox.change(function() {
			changeClass($(this));
		});

		changeClass(comboBox);
	});

	function showMenuPopup(obj, data, event) {
		switch (data.type) {
			case 'history':
				data = getMenuPopupHistory(data);
				break;

			case 'host':
				data = getMenuPopupHost(data, obj);
				break;

			case 'map_element_submap':
				data = getMenuPopupMapElementSubmap(data);
				break;

			case 'map_element_group':
				data = getMenuPopupMapElementGroup(data);
				break;

			case 'map_element_trigger':
				data = getMenuPopupMapElementTrigger(data);
				break;

			case 'map_element_image':
				data = getMenuPopupMapElementImage(data);
				break;

			case 'refresh':
				data = getMenuPopupRefresh(data, obj);
				break;

			case 'trigger':
				data = getMenuPopupTrigger(data, obj);
				break;

			case 'trigger_macro':
				data = getMenuPopupTriggerMacro(data);
				break;

			case 'dashboard':
				data = getMenuPopupDashboard(data, obj);
				break;

			case 'item':
				data = getMenuPopupItem(data, obj);
				break;

			case 'item_prototype':
				data = getMenuPopupItemPrototype(data);
				break;
		}

		obj.menuPopup(data, event);
	}

	/**
	 * Build menu popup for given elements.
	 */
	$(document).on('keydown click', '[data-menu-popup]', function(event) {
		var obj = $(this),
			data = obj.data('menu-popup');

		if (event.type === 'keydown') {
			if (event.which != 13) {
				return;
			}

			event.preventDefault();
			event.target = this;
		}

		var	url = new Curl('zabbix.php'),
			ajax_data = {
				data: data.data
			};

		url.setArgument('action', 'menu.popup');
		url.setArgument('type', data.type);

		$.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: ajax_data,
			dataType: 'json',
			success: function(resp) {
				showMenuPopup(obj, resp.data, event);
			},
			error: function() {
			}
		});

		return false;
	});

	/*
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
			for (var i = 0; i < data.values.length; i++) {
				if (typeof data.values[i].id !== 'undefined') {
					var item = {
						'id': data.values[i].id,
						'name': data.values[i].name
					};

					if (typeof data.values[i].prefix !== 'undefined') {
						item.prefix = data.values[i].prefix;
					}

					$('#' + data.parentId).multiSelect('addData', item);
				}
			}
		}
		else if ($('[name="'+data.parentId+'"]').hasClass('patternselect')) {
			/**
			 * Pattern select allows to enter multiple comma or newline separated values in same editable field. Values
			 * passed to add.popup should be appended at the end of existing value string.
			 *
			 * values_arr is used to catch duplicates.
			 * values_str is used to store user's original syntax.
			 */
			var values_str = $('[name="'+data.parentId+'"]').val(),
				values_arr = values_str.split(/[,|\n]+/).map(function(str) {return str.trim()});

			data.values.forEach(function(val) {
				if (values_arr.indexOf(val[data.object]) == -1) {
					if (values_str !== '') {
						values_str += ', ';
					}
					values_str += val[data.object];
				}
			});

			$('[name="'+data.parentId+'"]')
				.val(values_str)
				.trigger('change');
		}
		else if (typeof addPopupValues !== 'undefined') {
			// execute function if they exist
			addPopupValues(data);
		}
		else {
			$('#' + data.parentId).val(data.values[0].name);
		}
	});

	// redirect buttons
	$('button[data-url]').click(function() {
		var button = $(this);
		var confirmation = button.data('confirmation');

		if (typeof confirmation === 'undefined' || (typeof confirmation !== 'undefined' && confirm(confirmation))) {
			window.location = button.data('url');
		}
	});

	// Initialize hintBox event handlers.
	hintBox.bindEvents();
});
