<?php
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


ob_start(); ?>

(function() {
	if (typeof NodeList.prototype.forEach === "function") {
		return false;
	}
	NodeList.prototype.forEach = Array.prototype.forEach;
})();

function conditionFormSelector() {
	var select_element = document.querySelector('#new_condition_type')
		|| document.querySelector('#new_condition_conditiontype');
	var type = select_element.value;

	// Reset form.
	document.forms['popup.condition'].reset();

	// Set correct select value.
	select_element.value = type;

	// Clear multiselect.
	jQuery('.condition-container .multiselect').multiSelect('clean');

	// Clear textareaflexible.
	jQuery('.condition-container .textarea-flexible').textareaFlexible('clean');

	document
		.querySelectorAll('.condition-column.condition-column-active')
		.forEach(function(elem) {
			elem.classList.remove('condition-column-active');
		});

	document
		.querySelectorAll('.condition-column[data-type=\'' + type + '\']')
		.forEach(function(elem) {
			elem.classList.add('condition-column-active');
		});
}

function conditionPopupSubmit(form_name) {
	var select_element = document.querySelector('#new_condition_type')
		|| document.querySelector('#new_condition_conditiontype');
	var type = select_element.value;
	var form_element = document.forms['popup.condition'];
	var form_elements = form_element.querySelectorAll('.condition-column[data-type=\'' + type + '\']');
	var n = 0;

	create_var(form_name, select_element.name, select_element.value, false);

	form_elements
		.forEach(function(elem) {
			elem
				.querySelectorAll('textarea, select, input')
				.forEach(function(input) {
					// create_var can't add many inputs with same names.
					var name_length = input.name.length;
					if (input.name.substring(name_length - 2) == '[]') {
						input.name = input.name.substring(0, name_length - 2) + '[' + (n++) + ']';
					}

					create_var(form_name, input.name, input.value, false);
				});
		});

	submitFormWithParam(form_name, 'add_condition', '1');
}

(function() {
	jQuery('.condition-container .textarea-flexible').textareaFlexible();
})();

<?php return ob_get_clean(); ?>
