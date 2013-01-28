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
	'use strict';

	$.fn.listbox = function(options) {
		var chosenObj = this;
		chosenObj.chosen();

		var url = new Curl('jsrpc.php');
		url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		url.setArgument('method', 'chosen.get');
		url.setArgument('objectName', options.objectName);
		url = url.getUrl();

		var jqxhrs = [];

		return this.each(function() {
			var selectObj = $(this),
				containerObj = $('#' + selectObj.attr('id') + '_chzn');

			$('.search-field input', containerObj).bind('keyup', function() {
				var inputObj = $(this),
					value = inputObj.val();

				if (empty(value) || inputObj.data('lastValue') == value) {
					return;
				}

				inputObj.data('lastValue', value);

				$('.no-results', containerObj).text(locale['Looking for'] + " '" + value + "'");

				window.setTimeout(function() {
					if (!empty(jqxhrs[selectObj.attr('id')])) {
						jqxhrs[selectObj.attr('id')].abort();
					}

					jqxhrs[selectObj.attr('id')] = $.ajax({
						url: url,
						type: 'GET',
						dataType: 'json',
						data: {search: value}
					})
					.success(function(data) {
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

						chosenObj.trigger('liszt:updated');
					});
				}, 500);
			});
		});
	};
});
