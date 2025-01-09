<?php declare(strict_types = 0);
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
 * @var CView $this
 */
?>


$(() => {
	$.valHooks.input = {
		get: function(elem) {
			return elem.value;
		},
		set: function(elem, value) {
			var tmp = elem.value;
				elem.value = value;

			if ('item_description' === elem.id && tmp !== value) {
				reloadPopup(elem.form, 'popup.triggerexpr');
			}
		}
	};

	$('#function-select').on('change', (e) => {
		var form = e.target.closest('form'),
			function_name_parts = form.elements.function_select.value.split('_');

		form.elements.function.value = function_name_parts[1];

		reloadPopup(form, 'popup.triggerexpr');
	});
});
