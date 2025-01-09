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


class ZSelect extends HTMLElement {

	constructor() {
		super();

		this._options_map = new Map();

		this._option_template = "#{label}";
		this._selected_option_template = "#{label}";

		this._highlighted_index = -1;
		this._preselected_index = -1;

		this._expanded = false;
		this._list_hovered = false;

		this._button = document.createElement('button');
		this._input = document.createElement('input');
		this._list = document.createElement('ul');

		this._events = {};

		this._is_connected = false;
	}

	connectedCallback() {
		this._is_connected = true;

		this.init();
		this.registerEvents();
	}

	disconnectedCallback() {
		this.unregisterEvents();
	}

	/**
	 * @return {array}
	 */
	static get observedAttributes() {
		return ['name', 'value', 'disabled', 'readonly', 'width'];
	}

	attributeChangedCallback(name, old_value, new_value) {
		switch (name) {
			case 'name':
				this._input.name = new_value;
				break;

			case 'value':
				if (!this._is_connected || this._input.value !== new_value) {
					const option = this.getOptionByValue(new_value);

					this._highlight(option ? option._index : -1);
					this._preselect(this._highlighted_index);

					if (option && !option.disabled) {
						this._input.value = option.value;
						this.dispatchEvent(new Event('change', {bubbles: true}));
					}
					else {
						this._input.value = null;
					}
				}
				break;

			case 'disabled':
				this._button.disabled = (new_value !== null);
				this._input.disabled = (new_value !== null);
				break;

			case 'readonly':
				this._button.readOnly = (new_value !== null);
				this._input.readOnly = (new_value !== null);
				break;

			case 'width':
				if (new_value === 'auto') {
					this.style.width = '100%';
				}
				else if (new_value !== null) {
					this.style.width = `${new_value}px`;
				}
				else {
					this.style.width = '';
				}
				break;
		}
	}

	init() {
		this._button.type = 'button';
		this._button.classList.add('focusable');
		this.appendChild(this._button);

		this._input.type = 'hidden';
		this.appendChild(this._input);

		this._list.classList.add('list');
		this.appendChild(this._list);

		if (this.hasAttribute('focusable-element-id')) {
			this._button.id = this.getAttribute('focusable-element-id');
			this.removeAttribute('focusable-element-id');
		}

		if (this.hasAttribute('option-template')) {
			this._option_template = this.getAttribute('option-template');
			this.removeAttribute('option-template');
		}

		if (this.hasAttribute('selected-option-template')) {
			this._selected_option_template = this.getAttribute('selected-option-template');
			this.removeAttribute('selected-option-template');
		}

		if (this.hasAttribute('data-options')) {
			const options = JSON.parse(this.getAttribute('data-options'));

			for (const option of options) {
				option.options instanceof Array
					? this.addOptionGroup(option)
					: this.addOption(option);
			}

			this.removeAttribute('data-options');
		}

		if (!this.hasAttribute('width')) {
			this.setAttribute('width', this._listWidth());
		}

		this._preselect(this._highlighted_index >= 0 ? this._highlighted_index : this._first(this._highlighted_index));
		this._input.value = this.getValueByIndex(this._preselected_index);
	}

	getOptions() {
		return [...this._options_map.values()];
	}

	getOptionByIndex(index) {
		return this.getOptions()[index] || null;
	}

	getOptionByValue(value) {
		return this._options_map.get(value.toString()) || null;
	}

	getValueByIndex(index) {
		const option = this.getOptionByIndex(index);

		return option ? option.value : null;
	}

	addOption({value, label, extra, class_name, is_disabled}, container, template) {
		value = value.toString();

		if (this._options_map.has(value)) {
			throw new Error('Duplicate option value: ' + value);
		}

		const option = {value, label, extra, class_name, is_disabled, template};
		const li = document.createElement('li');

		li._index = this._options_map.size;
		li.setAttribute('value', value);
		li.setAttribute('title', label.trim());
		li.innerHTML = new Template(template || this._option_template).evaluate(
			Object.assign({label: label.trim()}, extra || {})
		);
		class_name && li.classList.add(class_name);
		is_disabled && li.setAttribute('disabled', 'disabled');

		this._options_map.set(value, Object.defineProperties(option, {
			_node: {
				get: () => li
			},
			_index: {
				get: () => li._index
			},
			value: {
				get: () => value
			},
			disabled: {
				get: () => option.is_disabled === true,
				set: (is_disabled) => {
					option.is_disabled = is_disabled;
					li.toggleAttribute('disabled', is_disabled);
				}
			},
			hidden: {
				set: (is_hidden) => {
					option.disabled = is_hidden;
					is_hidden ? li.setAttribute('disabled', 'disabled') : li.removeAttribute('disabled');
					li.style.display = is_hidden ? 'none' : '';
				}
			}
		}));

		// Should accept both integer and string.
		if ((this.getAttribute('value') || 0) == value) {
			this._highlighted_index = li._index;
		}

		(container || this._list).appendChild(li);
	}

	addOptionGroup({label, option_template, options}) {
		const li = document.createElement('li');
		const ul = document.createElement('ul');

		li.setAttribute('optgroup', label);
		li.appendChild(ul);

		for (const option of options) {
			this.addOption(option, ul, option_template);
		}

		this._list.appendChild(li);
	}

	focus() {
		this._button.focus();
	}

	get name() {
		return this._input.name;
	}

	set name(name) {
		this.setAttribute('name', name);
	}

	get options() {
		return this.getOptions();
	}

	get value() {
		return !this._is_connected
			? this.getAttribute('value')
			: this._input.value;
	}

	set value(value) {
		this.setAttribute('value', value);
	}

	get disabled() {
		return this._input.disabled;
	}

	set disabled(is_disabled) {
		this.toggleAttribute('disabled', is_disabled);
	}

	get readOnly() {
		return this._input.readOnly;
	}

	set readOnly(is_readonly) {
		this.toggleAttribute('readonly', is_readonly);
	}

	get selectedIndex() {
		return this._preselected_index;
	}

	set selectedIndex(index) {
		this._change(index);
	}

	_expand() {
		const {
			width: button_width,
			height: button_height,
			y: button_y,
			left: button_left,
			right: button_right
		} = this._button.getBoundingClientRect();
		const {height: document_height} = document.body.getBoundingClientRect();

		if (button_y + button_height < 0 && document_height - button_y < 0) {
			return;
		}

		this._expanded = true;
		this.classList.add('is-expanded');

		const list_max_height = 362;
		const offset_top = 4;
		const offset_bottom = 38;
		const list_height = Math.min(this._list.scrollHeight, list_max_height);
		const space_below = document_height - button_y - button_height;
		const space_above = button_y;

		this._list.style.left = `${button_left}px`;
		this._list.style.minWidth = `${button_width}px`;
		this._list.style.maxHeight = '';

		if (space_below - list_height > offset_bottom || space_below > space_above) {
			this._list.classList.remove('fall-upwards');
			this._list.style.top = `${button_y + button_height}px`;
			this._list.style.bottom = '';

			if (space_below < list_height) {
				this._list.style.maxHeight = `${space_below - offset_bottom}px`;
			}
		}
		else {
			this._list.classList.add('fall-upwards');
			this._list.style.top = '';
			this._list.style.bottom = `${document_height - button_y}px`;

			if (space_above < list_height) {
				this._list.style.maxHeight = `${space_above - offset_top}px`;
			}
		}

		const screen_width = window.innerWidth;
		const list_right_edge = this._list.offsetLeft + this._list.offsetWidth;

		if (list_right_edge > screen_width) {
			this._list.style.left = '';
			this._list.style.right = `${screen_width - button_right}px`;
		}

		this._highlight(this._preselected_index);

		document.addEventListener('wheel', this._events.document_wheel);
		setTimeout(() => document.querySelector('.wrapper').addEventListener('scroll', this._events.document_wheel));
	}

	_collapse() {
		this._expanded = false;
		this.classList.remove('is-expanded');

		document.removeEventListener('wheel', this._events.document_wheel);
		document.querySelector('.wrapper').removeEventListener('scroll', this._events.document_wheel);
	}

	_highlight(index) {
		const old_option = this.getOptionByIndex(this._highlighted_index);
		const new_option = this.getOptionByIndex(index);

		if (old_option) {
			old_option._node.classList.remove('hover');
		}

		if (new_option && !new_option.disabled) {
			new_option._node.classList.add('hover');

			this._expanded && new_option._node.scrollIntoView({block: 'nearest'});
			this._highlighted_index = index;
		}
		else {
			this._highlighted_index = -1;
		}
	}

	_preselect(index) {
		const option = this.getOptionByIndex(index);

		if (option) {
			this._button.innerHTML = new Template(this._selected_option_template).evaluate(
				Object.assign({label: option.label.trim()}, option.extra || {})
			);
			this._input.disabled = this.hasAttribute('disabled');
		}
		else {
			this._button.innerText = '';
			this._input.disabled = true;
		}

		this._preselected_index = index;
		this._highlighted_index = index;
	}

	_change(index) {
		const option = this.getOptionByIndex(index);

		if (option !== null) {
			this.value = option.value;
		}
	}

	_prev(current_index) {
		const options = this.getOptions();

		for (let index = current_index - 1; index >= 0; index--) {
			if (!options[index].disabled) {
				return index;
			}
		}

		return current_index;
	}

	_next(current_index) {
		const options = this.getOptions();

		for (let index = current_index + 1; index < this._options_map.size; index++) {
			if (!options[index].disabled) {
				return index;
			}
		}

		return current_index;
	}

	_prevPage(current_index) {
		let index = current_index;

		for (let i = 0; i < (this._expanded ? 14 : 3); i++) {
			index = this._prev(index);
		}

		return index;
	}

	_nextPage(current_index) {
		let index = current_index;

		for (let i = 0; i < (this._expanded ? 14 : 3); i++) {
			index = this._next(index);
		}

		return index;
	}

	_first(current_index) {
		const option = this.getOptionByIndex(this._next(-1));

		return option ? option._index : current_index;
	}

	_last(current_index) {
		const option = this.getOptionByIndex(this._prev(this._options_map.size));

		return option ? option._index : current_index;
	}

	_search(char) {
		const options = this.getOptions();
		const size = this._options_map.size;
		let start = this._highlighted_index - size;
		const end = start + size;

		while (start++ < end) {
			const index = (start + size) % size;
			const {label, disabled} = options[index];

			if (!disabled && label[0].toLowerCase() === char.toLowerCase()) {
				return index;
			}
		}

		return null;
	}

	_listWidth() {
		const container = document.createElement('div');
		const list = this._list.cloneNode(true);

		container.classList.add('z-select', 'is-expanded');
		container.style.position = 'fixed';
		container.style.left = '-9999px';
		container.appendChild(list);

		document.body.appendChild(container);

		const width = Math.ceil(list.scrollWidth) + 34;  // 34 = scrollbar + padding + border width

		container.remove();

		return Math.min(width, 453);
	}

	_isVisible() {
		const {bottom, top} = this.getBoundingClientRect();
		return !(bottom < 0 || top - Math.max(document.documentElement.clientHeight, window.innerHeight) >= 0);
	}

	_closestIndex(node) {
		while (node !== null && node._index === undefined) {
			node = node.parentNode.closest('li');
		}

		return node ? node._index : null;
	}

	registerEvents() {
		this._events = {
			button_mousedown: (e) => {
				if (this._button.readOnly) {
					return;
				}

				// Safari fix - event needs to be prevented, else blur event is fired on this button.
				e.preventDefault();
				// Safari fix - a click button on label would not focus button element.
				document.activeElement !== this._button && this._button.focus();

				if (e.which === 1) {
					if (this._expanded) {
						this._change(this._preselected_index);
						this._collapse();
					}
					else {
						this._expand();
					}
				}
			},

			button_keydown: (e) => {
				if (this._button.readOnly) {
					return;
				}

				!this._isVisible() && this.scrollIntoView({block: 'nearest'})

				if (e.which !== KEY_SPACE && !e.metaKey && !e.ctrlKey && e.key.length === 1) {
					const index = this._search(e.key);
					if (index !== null) {
						this._highlight(index);
						this._preselect(this._highlighted_index);
						!this._expanded && this._change(this._preselected_index);
					}

					return;
				}

				switch (e.which) {
					case KEY_ARROW_UP:
					case KEY_ARROW_DOWN:
					case KEY_PAGE_UP:
					case KEY_PAGE_DOWN:
					case KEY_HOME:
					case KEY_END:
						e.preventDefault();
						e.stopPropagation();

						let new_index, scroll = 0;
						switch (e.which) {
							case KEY_ARROW_UP:
								new_index = this._prev(this._highlighted_index);
								scroll = -48;
								break;

							case KEY_ARROW_DOWN:
								new_index = this._next(this._highlighted_index);
								scroll = 48;
								break;

							case KEY_PAGE_UP:
								new_index = this._prevPage(this._highlighted_index);
								break;

							case KEY_PAGE_DOWN:
								new_index = this._nextPage(this._highlighted_index);
								break;

							case KEY_HOME:
								new_index = this._first(this._highlighted_index);
								break;

							case KEY_END:
								new_index = this._last(this._highlighted_index);
								break;
						}

						if (scroll !== 0 && this._highlighted_index === new_index) {
							this._list.scrollTop += scroll;
						}
						else {
							this._highlight(new_index);
							this._preselect(this._highlighted_index);
							!this._expanded && this._change(this._preselected_index);
						}
						break;

					case KEY_ENTER:
						if (this._expanded) {
							this._preselect(this._highlighted_index);
							this._change(this._preselected_index);
							this._collapse();
						}
						else {
							this._isVisible() && this._expand();
						}
						break;

					case KEY_TAB:
						if (this._expanded) {
							e.preventDefault();
							this._preselect(this._highlighted_index);
							this._change(this._preselected_index);
							this._collapse();
						}
						break;

					case KEY_SPACE:
						!this._expanded && this._isVisible() && this._expand();
						break;

					case KEY_ESCAPE:
						this._expanded && e.stopPropagation();
						this._change(this._preselected_index);
						this._collapse();
						break;
				}
			},

			button_click: () => {
				// Safari fix - a click button on label would not focus button element.
				document.activeElement !== this._button && this._button.focus();
			},

			focus: (e) => {
				this._button.focus();
			},

			button_blur: () => {
				if (this._button.readOnly) {
					return;
				}

				this._change(this._preselected_index);
				this._collapse();
			},

			list_mouseenter: () => {
				this._list_hovered = true;
			},

			list_mouseleave: () => {
				this._list_hovered = false;
			},

			list_mousedown: (e) => {
				e.preventDefault();
			},

			list_mouseup: (e) => {
				const option = this.getOptionByIndex(this._closestIndex(e.target));

				if (option && !option.disabled) {
					this._change(option._index);
					this._collapse();
				}

				e.preventDefault();
			},

			list_mousemove: (e) => {
				const option = this.getOptionByIndex(this._closestIndex(e.target));

				if (option && this._highlighted_index !== option._index) {
					!option.disabled && this._highlight(option._index);
				}
			},

			document_wheel: () => {
				if (!this._list_hovered) {
					this._change(this._preselected_index);
					this._collapse();
				}
			},

			window_resize: () => {
				this._change(this._preselected_index);
				this._collapse();
			}
		}

		this._button.addEventListener('click', this._events.button_click);
		this._button.addEventListener('mousedown', this._events.button_mousedown);
		this._button.addEventListener('keydown', this._events.button_keydown);
		this._button.addEventListener('blur', this._events.button_blur);

		this._list.addEventListener('mouseenter', this._events.list_mouseenter);
		this._list.addEventListener('mouseleave', this._events.list_mouseleave);
		this._list.addEventListener('mousedown', this._events.list_mousedown);
		this._list.addEventListener('mouseup', this._events.list_mouseup);
		this._list.addEventListener('mousemove', this._events.list_mousemove);

		this.addEventListener('focus', this._events.focus);

		window.addEventListener('resize', this._events.window_resize);
	}

	unregisterEvents() {
		this._button.removeEventListener('click', this._events.button_click);
		this._button.removeEventListener('mousedown', this._events.button_mousedown);
		this._button.removeEventListener('keydown', this._events.button_keydown);
		this._button.removeEventListener('blur', this._events.button_blur);

		this._list.removeEventListener('mouseenter', this._events.list_mouseenter);
		this._list.removeEventListener('mouseleave', this._events.list_mouseleave);
		this._list.removeEventListener('mousedown', this._events.list_mousedown);
		this._list.removeEventListener('mouseup', this._events.list_mouseup);
		this._list.removeEventListener('mousemove', this._events.list_mousemove);

		this.removeEventListener('focus', this._events.focus);

		window.removeEventListener('resize', this._events.window_resize);
	}
}

customElements.define('z-select', ZSelect);
