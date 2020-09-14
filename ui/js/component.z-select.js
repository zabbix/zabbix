class ZSelect extends HTMLElement {

	constructor() {
		super();

		this._options_map = new Map();

		this._option_template = "#{label}";

		this._preselected_index = 0;
		this._staged_index = 0;
		this._value = null;

		this._expanded = false;
		this._list_hovered = false;

		this._button = document.createElement('button');
		this._input = document.createElement('input');
		this._list = document.createElement('ul');

		this.onchange = () => {};
	}

	connectedCallback() {
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
		return ['name', 'value', 'disabled', 'readonly', 'data-options', 'option-template', 'focusable-element-id',
			'width', 'onchange'];
	}

	attributeChangedCallback(name, _old_value, new_value) {
		switch (name) {
			case 'name':
				this._input.setAttribute('name', new_value);
				break;

			case 'value':
				if (this._value != new_value) {
					this._value = new_value;
					if (this._options_map.has(new_value)) {
						this._highlight(this._options_map.get(new_value)._index);
						this._preSelect();
						this._change();
					}
				}
				break;

			case 'disabled':
				this._button.toggleAttribute('disabled', new_value !== null);
				this._input.toggleAttribute('disabled', new_value !== null);
				break;

			case 'readonly':
				this._button.toggleAttribute('readonly', new_value !== null);
				this._input.toggleAttribute('readonly', new_value !== null);
				break;

			case 'data-options':
				if (new_value !== null) {
					const options = JSON.parse(new_value);

					for (const option of options) {
						option.options instanceof Array
							? this.addOptionGroup(option)
							: this.addOption(option);
					}

					this.removeAttribute('data-options');
				}
				break;

			case 'option-template':
				if (new_value !== null) {
					this._option_template = new_value;
					this.removeAttribute('option-template');
				}

				break

			case 'focusable-element-id':
				if (new_value !== null) {
					this._button.setAttribute('id', new_value);
					this.removeAttribute('focusable-element-id');
				}

				break;

			case 'width':
				this._button.style.width = `${new_value}px`;
				this._list.style.width = `${new_value}px`;
				break;

			case 'onchange':
				this.onchange = new Function('e', new_value);
				break;
		}
	}

	init() {
		this._button.setAttribute('type', 'button');
		this.appendChild(this._button);

		this._input.setAttribute('type', 'hidden');
		this.appendChild(this._input);

		this._list.classList.add('list');
		this.appendChild(this._list);

		if (!this.hasAttribute('width')) {
			const container = document.createElement('div');
			const list = this._list.cloneNode(true);

			container.classList.add('z-select', 'is-expanded');
			container.style.position = 'fixed';
			container.style.left = '-9999px';
			container.appendChild(list);

			document.body.appendChild(container);

			this.setAttribute('width', Math.ceil(list.scrollWidth) + 24);  // 24 = padding + border width

			container.remove();
		}

		this._highlight(this._preselected_index);
		this._preSelect();
		this._input.setAttribute('value', this._value);
	}

	getOptionByIndex(index) {
		return this.getOptions()[index];
	}

	getOptionByValue(value) {
		return this._options_map.has(value) ? this._options_map.get(value) : null;
	}

	getOptions() {
		return [...this._options_map.values()];
	}

	addOption({value, label, label_extra, class_name, is_disabled}, container, template) {
		if (this._options_map.has(value)) {
			throw new Error('Duplicate option value: ' + value);
		}

		const option = {value, label, label_extra, class_name, is_disabled, template};
		const li = document.createElement('li');

		li._index = this._options_map.size;
		li.setAttribute('value', value);
		li.innerHTML = new Template(template || this._option_template).evaluate(
			Object.assign({label: label.trim()}, label_extra || {})
		);
		class_name && li.classList.add(class_name);
		is_disabled && li.setAttribute('disabled', 'disabled');

		this._options_map.set(value.toString(), Object.defineProperties(option, {
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
			}
		}));

		// Should accept both integer and string.
		if (this._value == value) {
			this._preselected_index = li._index;
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

	get value() {
		return this._input.value;
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

	set selectedIndex(value) {
		this._highlight(value);
		this._preSelect();
		this._change();
	}

	_expand() {
		const {height: button_height, y: button_y, left: button_left} = this._button.getBoundingClientRect();
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

		this._highlight(this._preselected_index);

		document.addEventListener('wheel', this._events.document_wheel);
	}

	_collapse() {
		this._expanded = false;
		this.classList.remove('is-expanded');

		document.removeEventListener('wheel', this._events.document_wheel);
	}

	_highlight(index) {
		const {_node: old_node} = this.getOptionByIndex(this._staged_index);
		const {_node: new_node, disabled} = this.getOptionByIndex(index);

		if (!disabled) {
			old_node.classList.remove('hover');
			new_node.classList.add('hover');

			this._expanded && new_node.scrollIntoView({block: 'nearest'});
			this._staged_index = index;
		}
	}

	_preSelect() {
		const {label} = this.getOptionByIndex(this._staged_index);

		this._button.innerText = label;
		this._preselected_index = this._staged_index;
	}

	_change() {
		const {value} = this.getOptionByIndex(this._preselected_index);

		if (this._input.value != value) {
			this.setAttribute('value', value);
			this._input.setAttribute('value', value);
			this.dispatchEvent(new Event('change'));
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
		let start = this._staged_index - size;
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

	registerEvents() {
		this._events = {
			button_mousedown: () => {
				if (this._expanded) {
					this._change();
					this._collapse();
				}
				else {
					this._expand();
				}
			},

			button_keydown: (e) => {
				if (e.which !== KEY_SPACE && !e.metaKey && !e.ctrlKey && e.key.length === 1) {
					const index = this._search(e.key);
					if (index !== null) {
						this._highlight(index);
						this._preSelect();
						!this._expanded && this._change();
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
								new_index = this._prev(this._staged_index);
								scroll = -48;
								break;

							case KEY_ARROW_DOWN:
								new_index = this._next(this._staged_index);
								scroll = 48;
								break;

							case KEY_PAGE_UP:
								new_index = this._prevPage(this._staged_index);
								break;

							case KEY_PAGE_DOWN:
								new_index = this._nextPage(this._staged_index);
								break;

							case KEY_HOME:
								new_index = this._first(this._staged_index);
								break;

							case KEY_END:
								new_index = this._last(this._staged_index);
								break;
						}

						if (scroll !== 0 && this._staged_index === new_index) {
							this._list.scrollTop += scroll;
						}
						else {
							this._highlight(new_index);
							this._preSelect();
							!this._expanded && this._change();
						}
						break;

					case KEY_ENTER:
						if (!this._expanded) {
							this._expand();
						}
						else {
							this._preSelect();
							this._change();
							this._collapse();
						}
						break;

					case KEY_TAB:
						if (this._expanded) {
							e.preventDefault();
							this._preSelect();
							this._change();
							this._collapse();
						}
						break;

					case KEY_SPACE:
						!this._expanded && this._expand();
						break;

					case KEY_ESCAPE:
						this._expanded && e.stopPropagation();
						this._change();
						this._collapse();
						break;
				}
			},

			button_blur: () => {
				this._change();
				this._collapse();
			},

			list_mouseenter: () => {
				this._list_hovered = true;
			},

			list_mouseleave: () => {
				this._list_hovered = false;
			},

			list_mousedown: (e) => {
				const index = e.target._index;

				if (index !== undefined) {
					e.preventDefault();
					this._highlight(index);
					this._preSelect();
					this._change();
					this._collapse();
				}
			},

			list_mousemove: (e) => {
				const index = e.target._index;

				if (index !== undefined && this._staged_index !== index) {
					const {disabled} = this.getOptionByIndex(index);

					!disabled && this._highlight(index);
				}
			},

			document_wheel: () => {
				if (!this._list_hovered) {
					this._change();
					this._collapse();
				}
			},

			window_resize: () => {
				this._change();
				this._collapse();
			}
		}

		this._button.addEventListener('mousedown', this._events.button_mousedown);
		this._button.addEventListener('keydown', this._events.button_keydown);
		this._button.addEventListener('blur', this._events.button_blur);

		this._list.addEventListener('mouseenter', this._events.list_mouseenter);
		this._list.addEventListener('mouseleave', this._events.list_mouseleave);
		this._list.addEventListener('mousedown', this._events.list_mousedown);
		this._list.addEventListener('mousemove', this._events.list_mousemove);

		window.addEventListener('resize', this._events.window_resize);
	}

	unregisterEvents() {
		this._button.removeEventListener('mousedown', this._events.button_mousedown);
		this._button.removeEventListener('keydown', this._events.button_keydown);
		this._button.removeEventListener('blur', this._events.button_blur);

		this._list.removeEventListener('mouseenter', this._events.list_mouseenter);
		this._list.removeEventListener('mouseleave', this._events.list_mouseleave);
		this._list.removeEventListener('mousedown', this._events.list_mousedown);
		this._list.removeEventListener('mousemove', this._events.list_mousemove);

		window.removeEventListener('resize', this._events.window_resize);
	}
}

customElements.define('z-select', ZSelect);
