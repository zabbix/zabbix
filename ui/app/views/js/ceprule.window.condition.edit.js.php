<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

window.ceprule_window_condition_edit_popup = new class {

	/** @type {HTMLFormElement} */
	form_element;

	/** @type {CForm} */
	form;

	/** @type {Overlay} */
	#overlay;

	/** @type {Object} */
	#templates;

	/** @type {Array} */
	#live_nodes = [];

	/** @type {DocumentFragment} */
	#template;

	init({rules, window_condition, overlay}) {
		this.#overlay = overlay;
		this.form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		this.#templates = Object.fromEntries([...this.form_element.querySelectorAll('template[for-type]')]
			.map(template => [template.getAttribute('for-type'), template.content]));

		this.#setValues(window_condition);
		this.#initActions();
		window['ceprule-window-condition-type'].dispatchEvent(new Event('change'));
	}

	#initActions() {
		window['ceprule-window-condition-type'].addEventListener('change', (e) => this.#handleTypeChanged(e.target.value));
	}

	#setValues(window_condition) {
		[...this.#live_nodes, ...Object.values(this.#templates)]
			.reduce((carry, fragment) => [...fragment.querySelectorAll('[name]'), ...carry], [])
			.map((node) => {
				if (node.type === 'radio') {
					node.checked = node.value === window_condition[node.name];
				}
				else {
					node.value = window_condition[node.name] ?? '';
				}
			});

		this.form_element.querySelector('[name="type"]').value = window_condition.type;
		this.form_element.querySelector('[name="formulaid"]').value = window_condition.formulaid;
	}

	#handleTypeChanged(type) {
		const content = this.#templates[type];
		const form_grid = this.form_element.querySelector('.form-grid');

		this.#live_nodes.forEach(node => {
			this.#template.append(node);
		});
		this.#live_nodes = [];
		Array.from(content.children).forEach(node => {
			form_grid.appendChild(node);
			this.#live_nodes.push(node);
		});
		this.#template = content;
		this.form.discoverAllFields();
	}
};
