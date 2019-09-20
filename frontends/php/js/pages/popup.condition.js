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

	// Delete active class from form elements.
	var elements = document.querySelectorAll('.condition-column.condition-column-active');
	for (var i in elements) {
		if (elements.hasOwnProperty(i)) {
			elements[i].classList.remove('condition-column-active');
		}
	}

	// Add active class to selected form elements.
	var elements = document.querySelectorAll('.condition-column[data-type=\'' + type + '\']');
	for (var i in elements) {
		if (elements.hasOwnProperty(i)) {
			elements[i].classList.add('condition-column-active');
		}
	}
}

function conditionPopupSubmit(form_name) {
	var select_element = document.querySelector('#new_condition_type')
		|| document.querySelector('#new_condition_conditiontype');
	var type = select_element.value;
	var form_element = document.forms['popup.condition'];
	var form_elements = form_element.querySelectorAll('.condition-column[data-type=\'' + type + '\']');
	var n = 0;

	create_var(form_name, select_element.name, select_element.value, false);

	for (var i in form_elements) {
		if (form_elements.hasOwnProperty(i)) {
			var inputs = form_elements[i].querySelectorAll('textarea, select, input');
			for (var j in inputs) {
				if (inputs.hasOwnProperty(j) && inputs[j].name.substring(0, 13) === 'new_condition') {
					// create_var can't add many inputs with same names.
					var name_len = inputs[j].name.length;
					if (inputs[j].name.substring(name_len - 2) === '[]') {
						inputs[j].name = inputs[j].name.substring(0, name_len - 2) + '[' + (n++) + ']';
					}

					create_var(form_name, inputs[j].name, inputs[j].value, false);
				}
			}
		}
	}

	submitFormWithParam(form_name, 'add_condition', '1');
}
