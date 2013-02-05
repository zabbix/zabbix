/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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

	$.fn.multiSelect = function(options) {
		var url = new Curl('jsrpc.php');
		url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		url.setArgument('method', 'multiselect.get');
		url.setArgument('objectName', options.objectName);
		url = url.getUrl();

		var jqxhrs = [],
			isWaiting = [];

		return this.each(function() {
			var obj = $(this),
				objId = obj.attr('id'),
				width = (parseInt(obj.css('width')) - 2) + 'px';

			// input
			var input = $('<input>', {
				type: 'text',
				value: '',
				css: {width: width},
				'data-lastValue': ''
			})
			.keyup(function(e) {
				var value = input.val();

				if (!empty(value) && input.data('lastValue') != value) {
					input.data('lastValue', value);

					if (!isWaiting[objId]) {
						isWaiting[objId] = true;

						window.setTimeout(function() {
							isWaiting[objId] = false;

							if (!empty(jqxhrs[objId])) {
								jqxhrs[objId].abort();
							}

							jqxhrs[objId] = $.ajax({
								url: url + '&curtime=' + new CDate().getTime(),
								type: 'GET',
								dataType: 'json',
								data: {search: value},
								success: function(data) {
									loadData(obj, options, data);
								}
							});
						}, 500);
					}
				}
			})
			.keypress(function(e) {
				if (e.which == 13) {
					cancelEvent(e);
				}
			});
			obj.append(input);

			// list
			obj.append($('<div>', {
				'class': 'list',
				css: {width: width}
			}).append($('<ul>')));

			// preload data
			if (!empty(options.data)) {
				loadData(obj, options, {result: options.data});
			}

			// load ajax data
			/*var loadData = function(data) {
				if (empty(data['result'])) {
					return;
				}

				var values = [];

				$('option', selectObj).each(function() {
					if ($(this).is(':selected')) {
						values.push($(this).val());
					}
					else {
						$(this).remove();
					}
				});

				$.each(data['result'], function(i, item) {
					if (typeof(values[item.value]) == 'undefined') {
						$('<option />', {value: item.value, text: item.text}).appendTo(selectObj);
					}
				});
			};*/

			/* preload data
			$('.search-field input', containerObj).focus(function() {
				$.ajax({
					url: url,
					type: 'GET',
					dataType: 'json',
					data: {limit: 10},
					success: success
				});
				jQuery(this).off('focus');
			});*/
		});

		function loadData(obj, options, data) {
			$.each(data.result, function(i, item) {
				obj.append($('<input>', {
					type: 'hidden',
					name: options.name,
					value: item.id
				}));

				$('ul', obj).each(function() {
					$(this).append($('<li>', {
						'data-id': item.id,
						'data-name': item.name
					})
					.append($('<span>', {text: item.prefix + item.name})));
				});
			});
		};
	};
});
