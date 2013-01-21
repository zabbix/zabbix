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


(function($) {
	return $.fn.zbxChosen = function(settings) {
		var url = new Curl('jsrpc.php');
		url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		url.setArgument('method', 'chosen.get');
		url.setArgument('objectName', settings.objectName);

		var obj = this,
			xhr = null,
			options = $.extend({startCount: 1, delay: 500, data: {}, url: url.getUrl()}, settings);

		this.chosen();

		return this.each(function() {
			return $(this).next('.chzn-container').find('.search-field > input, .chzn-search > input').bind('keyup', function() {
				var elem = $(this),
					untrimmedValue = $(this).attr('value'),
					value = $.trim($(this).attr('value')),
					success;

				obj.next('.chzn-container').find('.no-results').text((value.length < options.startCount)
					? locale['Keep typing...']
					: locale['Looking for'] + (" '" + value + "'")
				);

				if (elem.data('prevVal') === value) {
					return false;
				}

				elem.data('prevVal', value);

				if (this.timer) {
					clearTimeout(this.timer);
				}
				if (value.length < options.startCount) {
					return false;
				}

				options.data['search'] = value;

				success = options.success;

				options.success = function(data) {
					if (empty(data)) {
						return;
					}

					var listboxValues = [];

					obj.find('option').each(function() {
						return $(this).is(':selected')
							? listboxValues.push($(this).val() + '-' + $(this).text())
							: $(this).remove();
					});

					$.each(data, function(i, item) {
						if ($.inArray(item.value + '-' + item.text, listboxValues) === -1) {
							return $('<option />').attr('value', item.value).html(item.text).appendTo(obj);
						}
					});

					obj.data().chosen.no_results_clear();

					if (Object.keys(data).length) {
						obj.trigger('liszt:updated');
					}
					else {
						obj.data().chosen.no_results(elem.attr('value'));
					}

					if (success != null) {
						success(data);
					}

					return elem.attr('value', untrimmedValue);
				};

				return this.timer = setTimeout(function() {
					if (xhr) {
						xhr.abort();
					}

					return xhr = $.ajax($.extend({type: 'GET', dataType: 'json'}, options));
				}, options.delay);
			});
		});
	};
})(jQuery);
