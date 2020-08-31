class ZSelect {
	onchange = () => {}

	_event_handlers = {
		document_wheel: this._onDocumentWheel.bind(this),
		window_resize: this._onWindowResize.bind(this),
		button_blur: this._onButtonBlur.bind(this),
		button_mousedown: this._onButtonMousedown.bind(this),
		button_keydown: this._onButtonKeydown.bind(this),
		button_keydown: this._onButtonKeydown.bind(this),
		list_mouseenter: () => this.state.list_hover = true,
		list_mouseleave: () => this.state.list_hover = false,
		list_mousedown: this._onListMousedown.bind(this),
		list_mousemove: this._onListMousemove.bind(this)
	}

	state = {
		is_open: false,
		idx_commited: 0,
		idx_staged: 0,
		option_idx: [],
		option_map: {},
		list_hover: false,
		value: null
	}

	root = {
		button: null,
		list: null,
		input: null
	}

	constructor() {
		this.root.button = document.createElement('button');
		this.root.button.setAttribute('type', 'button');

		this.root.input = document.createElement('input');
		this.root.input.setAttribute('type', 'hidden');

		this.root.list = document.createElement('ul');
		this.root.list.classList.add('list');
	}

	_collapseList() {
		this.state.is_open = false;
		this.root.list.classList.remove('is-expanded');

		this._unbindDocumentWheelEvent();
	}

	_expandList() {
		this.state.is_open = true;

		const list_height = 332;
		const {height: button_height, y: button_y, left: button_left} = this.root.button.getBoundingClientRect();
		const doc_height = document.body.getBoundingClientRect().height;
		const space_below = doc_height - button_y - button_height;

		if (space_below - list_height > 38) {
			this.root.list.classList.remove('fall-upwards');
			this.root.list.style.top = `${button_y + button_height}px`;
			this.root.list.style.bottom = '';
		}
		else {
			this.root.list.classList.add('fall-upwards')
			this.root.list.style.top = '';
			this.root.list.style.bottom = `${doc_height - button_y}px`;
		}

		this.root.list.style.left = `${button_left}px`;

		this.root.list.classList.add('is-expanded');
		this._stageIdx(this.state.idx_commited);

		this._bindDocumentWheelEvent();
	}

	_scrollIntoBottomOfView(node) {
		node.offsetParent.scrollTop = node.offsetTop - node.offsetParent.clientHeight + node.clientHeight;
	}

	_scrollIntoTopOfView(node) {
		node.offsetParent.scrollTop = node.offsetTop;
	}

	_scrollIntoViewIfNeeded(node) {
		const node_pos = node.offsetTop;
		const node_height = node.scrollHeight;

		const parent_scroll_top = node.offsetParent.scrollTop;
		const parent_height = node.offsetParent.clientHeight;

		const upper_overflow = parent_scroll_top - node_pos;
		const lower_overflow = upper_overflow + parent_height - node_height;

		if (upper_overflow > 0) {
			node.offsetParent.scrollTop -= upper_overflow;
		} else if (lower_overflow < 0) {
			node.offsetParent.scrollTop -= lower_overflow;
		}
	}

	_stageIdx(idx) {
		const {_node: old_node} = this._getOptionByIdx(this.state.idx_staged);
		const {_node: new_node} = this._getOptionByIdx(idx);

		old_node.classList.remove('hover');
		new_node.classList.add('hover');

		this.state.is_open && this._scrollIntoViewIfNeeded(new_node);
		this.state.idx_staged = idx;
	}

	_prevEnabledIdx(exclusive_from) {
		for (let prev_idx = exclusive_from - 1; prev_idx > -1; prev_idx--) {
			if (this._getOptionByIdx(prev_idx).disabled) {
				continue;
			}

			return prev_idx;
		}

		return exclusive_from;
	}

	_firstIdx() {
		let idx_len = this.state.option_idx.length;
		for (let first_idx = 0;first_idx < idx_len;first_idx++) {
			if (!this._getOptionByIdx(first_idx).disabled) {
				return first_idx;
			}
		}

		return this.state.idx_staged;
	}

	_lastIdx() {
		let last_idx = this.state.option_idx.length - 1;
		for (;last_idx > -1;last_idx--) {
			if (!this._getOptionByIdx(last_idx).disabled) {
				return last_idx;
			}
		}

		return this.state.idx_staged;
	}

	_nextEnabledIdx(exclusive_from) {
		const idx_len = this.state.option_idx.length;
		for (let next_idx = exclusive_from + 1; next_idx < idx_len; next_idx++) {
			if (!this._getOptionByIdx(next_idx).disabled) {
				return next_idx;
			}
		}

		return exclusive_from;
	}

	_push() {
		const {value} = this._getOptionByIdx(this.state.idx_commited);

		if (this.getValue() != value) {
			const evt = new Event('change');

			this.root.input.setAttribute('value', value);
			this.onchange(evt);
		}
	}

	_commit() {
		const {title} = this._getOptionByIdx(this.state.idx_staged);

		this.root.button.innerText = title
		this.state.idx_commited = this.state.idx_staged;
	}

	_nextIdxByChar(char) {
		const idx_frame = this.state.option_idx.length;
		let start = this.state.idx_staged - idx_frame;
		const end = start + idx_frame;

		while (start++ < end) {
			const idx = (start + idx_frame) % idx_frame;
			const {title, disabled} = this._getOptionByIdx(idx);

			if (disabled) {
				continue;
			}

			if (title[0].toLowerCase() === char.toLowerCase()) {
				return idx;
			}
		}

		return null;
	}

	_getOptionByIdx(idx) {
		return this.state.option_map[this.state.option_idx[idx]];
	}

	_onDocumentWheel() {
		if (!this.state.list_hover) {
			this._push();
			this._collapseList();
		}
	}

	_bindDocumentWheelEvent() {
		document.addEventListener('wheel', this._event_handlers.document_wheel);
	}

	_unbindDocumentWheelEvent() {
		document.removeEventListener('wheel', this._event_handlers.document_wheel);
	}

	// NOTE: Native select element collapses on document wheel event if list is not hovered and on window resize event.
	_onWindowResize() {
		this._push();
		this._collapseList();
	}

	_onButtonMousedown() {
		if (this.state.is_open) {
			this._push();
			this._collapseList();
		}
		else {
			this._expandList();
		}
	}

	_onButtonBlur() {
		this._push();
		this._collapseList();
	}

	_onButtonKeydown(e) {
		if (e.which != KEY_SPACE && !e.metaKey && !e.ctrlKey && e.key.length == 1) {
			const idx = this._nextIdxByChar(e.key);
			if (idx !== null) {
				this._stageIdx(idx);
				this._commit();
				!this.state.is_open && this._push();
			}

			return;
		}

		// TODO: PAGE_UP and PAGE_DOWN handlers are unreadable!!!!! Must be codefixed
		switch (e.which) {
			case KEY_PAGE_UP:
				/*
				 * NOTE: If open - Native select scrolls currently active option to bottom of visible list, and stages the
				 * first visible option in list, if that is disabled, previous enabled option is chosen.
				 */
				e.preventDefault();
				e.stopPropagation();
				let curr_option = this._getOptionByIdx(this.state.idx_staged);
				if (this.state.is_open) {
					let {_node: node} = curr_option;
					this._scrollIntoBottomOfView(node);

					for (let prev_idx = this.state.idx_staged - 1; prev_idx > -1; prev_idx--) {
						let option = this._getOptionByIdx(prev_idx);
						let is_visible = option._node.offsetTop - option._node.offsetParent.scrollTop > -1;
						if (!is_visible) {
							break;
						}
						curr_option = option;
					}

					if (curr_option.disabled) {
						const prev_enabled = this._prevEnabledIdx(curr_option._data_idx);
						if (prev_enabled === curr_option._data_idx) {
							this._stageIdx(this._nextEnabledIdx(curr_option._data_idx));
						}
						else {
							this._stageIdx(prev_enabled);
						}
					}
					else {
						this._stageIdx(curr_option._data_idx);
					}

					this._commit();
				}
				else {
					let seek_to_idx = this.state.idx_staged;
					for (let i = 0; i < 3; i++) {
						seek_to_idx = this._prevEnabledIdx(seek_to_idx);
					}
					this._stageIdx(seek_to_idx);
					this._commit();
					this._push();
				}
				break;
			case KEY_PAGE_DOWN:
				/*
				 * NOTE: If open - Native select scrolls currently active option to top of visible list, and stages the last
				 * visible option in list, if that is disabled, then next enabled option is chosen.
				 */
				e.preventDefault();
				e.stopPropagation();
				let urr_option = this._getOptionByIdx(this.state.idx_staged);
				if (this.state.is_open) {
					let {_node: node} = urr_option;
					this._scrollIntoTopOfView(node);

					const parent_offset = node.offsetParent.clientHeight + node.offsetParent.scrollTop;
					const idx_len = this.state.option_idx.length;

					for (let next_idx = this.state.idx_staged + 1; next_idx < idx_len; next_idx++) {
						let option = this._getOptionByIdx(next_idx);
						let is_visible = parent_offset - option._node.offsetTop - option._node.offsetHeight > -1;
						if (!is_visible) {
							break;
						}
						urr_option = option;
					}

					if (urr_option.disabled) {
						const next_enabled = this._nextEnabledIdx(urr_option._data_idx);
						if (next_enabled === urr_option._data_idx) {
							this._stageIdx(this._prevEnabledIdx(urr_option._data_idx));
						}
						else {
							this._stageIdx(next_enabled);
						}
					}
					else {
						this._stageIdx(urr_option._data_idx);
					}

					this._commit();
				}
				else {
					let seek_to_idx = this.state.idx_staged;
					for (let i = 0; i < 3; i++) {
						seek_to_idx = this._nextEnabledIdx(seek_to_idx);
					}
					this._stageIdx(seek_to_idx);
					this._commit();
					this._push();
				}
				break;
			case KEY_END:
				e.preventDefault();
				e.stopPropagation();
				this._stageIdx(this._lastIdx());
				this._commit();
				!this.state.is_open && this._push();
				break;
			case KEY_HOME:
				e.preventDefault();
				e.stopPropagation();
				this._stageIdx(this._firstIdx());
				this._commit();
				!this.state.is_open && this._push();
				break;
			case KEY_ARROW_DOWN:
				/*
				 * NOTE: In native select when an arrow key cannot select any next option because all of them are disabled,
				 * and dropdown is opened, then scroll down happens.
				 */
				e.preventDefault();
				e.stopPropagation();
				let next_enabled_idx = this._nextEnabledIdx(this.state.idx_staged);
				if (next_enabled_idx == this.state.idx_staged) {
					this.state.is_open && (this.root.list.scrollTop += 50);
				}
				else {
					this._stageIdx(next_enabled_idx);
					this._commit();
					!this.state.is_open && this._push();
				}
				break
			case KEY_ARROW_UP:
				/*
				 * NOTE: In native select when an arrow key cannot select any previous option because all of them are
				 * disabled and dropdown is opened, then scroll up happens.
				 */
				e.preventDefault();
				e.stopPropagation();
				let prev_enabled_idx = this._prevEnabledIdx(this.state.idx_staged);
				if (prev_enabled_idx == this.state.idx_staged) {
					this.state.is_open && (this.root.list.scrollTop -= 50);
				}
				else {
					this._stageIdx(prev_enabled_idx);
					this._commit();
					!this.state.is_open && this._push();
				}
				break;
			case KEY_ENTER:
				if (!this.state.is_open) {
					this._expandList();
				}
				else {
					this._commit();
					this._push();
					this._collapseList();
				}
				break;
			case KEY_TAB:
				// NOTE: Native select element if opened, on "tab" submits hovered option and remains focused.
				if (this.state.is_open) {
					e.preventDefault();
					this._commit();
					this._push();
					this._collapseList();
				}
				break;
			case KEY_SPACE:
				// NOTE: Native select element does not closes or chooses option on "space" key, only opens dropdown.
				!this.state.is_open && this._expandList();
				break;
			case KEY_ESCAPE:
				this.state.is_open && e.stopPropagation();
				this._push();
				this._collapseList();
				break;
		}
	}

	/**
	 * NOTE: Native select element option responds to mouse move, also if mouseenter event is used, it is triggered when
	 * node has to be scrolled in view, and pointer happens to be over another node after scroll.
	 */

	_onListMousemove(e) {
		const idx = e.target.data_idx;

		if (idx === undefined) {
			return;
		}

		if (this.state.idx_staged != idx) {
			const {disabled} = this._getOptionByIdx(idx);
			!disabled && this._stageIdx(idx);
		}
	}

	_onListMousedown(e) {
		const idx = e.target.data_idx;

		if (idx === undefined) {
			return;
		}

		e.preventDefault();
		this._stageIdx(idx);
		this._commit();
		this._push();
		this._collapseList();
	}

	_createOptionNode({title, value, desc, disabled, class_name}) {
		const li = document.createElement('li');

		li.innerText = title;
		li.setAttribute('value', value);

		if (class_name) {
			li.classList.add(class_name);
		}

		desc && li.setAttribute('description', desc);
		disabled && li.setAttribute('disabled', 'disabled');

		return li;
	}

	_registerOption({title, value, desc, disabled}, node) {
		if (this.state.option_map[value]) {
			throw new Error(`Duplicate option value: ${value}`);
		}

		node.data_idx = this.state.option_idx.length;

		this.state.option_map[value] = {title, value, desc, disabled};
		Object.defineProperties(this.state.option_map[value], {
			_node: {
				get: () => node
			},
			_data_idx: {
				get: () => node.data_idx
			}
		});

		this.state.option_idx.push(value);

		// It is possible to set value of an option before option is registered.
		if (this.state.value === value) {
			this.setIdx(node.data_idx);
		}
	}

	_measureListWidth() {
		const tmp = document.createElement('z-select-clone');
		const list = this.root.list.cloneNode(true);
		list.classList.add('is-expanded');

		tmp.style.left = '-9999px';
		tmp.style.position = 'fixed';
		tmp.appendChild(list);

		document.body.appendChild(tmp);

		const {width} = list.getBoundingClientRect();

		tmp.remove();

		return Math.ceil(width);
	}

	addOptionGroup(title, options) {
		const li = document.createElement('li');
		const ul = document.createElement('ul');

		li.setAttribute('optgroup', title);
		li.appendChild(ul);

		for (const option of options) {
			this.addOption(option, ul);
		}

		this.root.list.appendChild(li);
	}

	addOption({title, value, desc, disabled, class_name}, container) {
		title = title.trim();
		if (!container) {
			container = this.root.list;
		}

		const node = this._createOptionNode({title, value, desc, disabled, class_name});
		this._registerOption({title, value, desc, disabled}, node);

		container.appendChild(node);
	}

	getOptions() {
		const opts = [];

		this.state.option_idx.forEach(value => {
			opts.push(this.getOptionByValue(value));
		});

		return opts;
	}

	getOptionByValue(value) {
		const opt = this.state.option_map[value];

		if (!opt) {
			return null;
		}

		return {
			get disabled() {
				return opt.disabled === true;
			},
			set disabled(val) {
				if (val) {
					opt._node.setAttribute('disabled', 'disabled');
					opt.disabled = true;
				}
				else {
					opt._node.removeAttribute('disabled');
					opt.disabled = false;
				}
			},
			get value() {
				return opt.value;
			}
		};
	}

	unbindEvents() {
		this.root.list.removeEventListener('mouseenter', this._event_handlers.list_mouseenter);
		this.root.list.removeEventListener('mouseleave', this._event_handlers.list_mouseleave);
		this.root.list.removeEventListener('mousedown', this._event_handlers.list_mousedown);
		this.root.list.removeEventListener('mousemove', this._event_handlers.list_mousemove);

		this.root.button.removeEventListener('blur', this._event_handlers.button_blur);
		this.root.button.removeEventListener('mousedown', this._event_handlers.button_mousedown);
		this.root.button.removeEventListener('keydown', this._event_handlers.button_keydown);

		window.removeEventListener('resize', this._event_handlers.window_resize);
	}

	bindEvents() {
		this.root.list.addEventListener('mouseenter', this._event_handlers.list_mouseenter);
		this.root.list.addEventListener('mouseleave', this._event_handlers.list_mouseleave);
		this.root.list.addEventListener('mousedown', this._event_handlers.list_mousedown);
		this.root.list.addEventListener('mousemove', this._event_handlers.list_mousemove);

		this.root.button.addEventListener('blur', this._event_handlers.button_blur);
		this.root.button.addEventListener('mousedown', this._event_handlers.button_mousedown);
		this.root.button.addEventListener('keydown', this._event_handlers.button_keydown);

		window.addEventListener('resize', this._event_handlers.window_resize);
	}

	setName(name) {
		this.root.input.setAttribute('name', name);
	}

	setReadonly(value) {
		if (value) {
			this.root.button.setAttribute('readonly', 'readonly');
			this.root.input.setAttribute('readonly', 'readonly');
		}
		else {
			this.root.button.removeAttribute('readonly');
			this.root.input.removeAttribute('readonly');
		}
	}

	setDisabled(value) {
		if (value) {
			this.root.button.setAttribute('disabled', 'disabled');
			this.root.input.setAttribute('disabled', 'disabled');
		}
		else {
			this.root.button.removeAttribute('disabled');
			this.root.input.removeAttribute('disabled');
		}
	}

	setIdx(idx) {
		this._stageIdx(idx);
		this._commit();
		this._push();
	}

	getIdx() {
		return this.state.idx_commited;
	}

	setButtonId(id) {
		this.root.button.setAttribute('id', id);
	}

	setWidth(width) {
		this.root.button.style.width = `${width}px`;
		this.root.list.style.width = `${width}px`;
	}

	getDisabled() {
		return this.root.input.disabled;
	}

	getName() {
		return this.root.input.name;
	}

	getValue() {
		return this.root.input.value;
	}

	setValue(value) {
		const option = this.state.option_map[value];

		if (option) {
			this._stageIdx(option._data_idx);
			this._commit();
			this._push();
		}

		this.state.value = value;
	}

	focus() {
		this.root.button.focus();
	}
}

class ZSelectElement extends HTMLElement {

	constructor() {
		super()
		this._select = new ZSelect();
		this._select.onchange = event => this.dispatchEvent(event);
	}

	disconnectedCallback() {
		this._select.unbindEvents();
	}

	connectedCallback() {
		this.appendChild(this._select.root.button);
		this.appendChild(this._select.root.list);
		this.appendChild(this._select.root.input);

		if (!this.hasAttribute('width')) {
			this.setAttribute('width', this._select._measureListWidth() + 13);
		}

		this._select.bindEvents();
	}

	/**
	 * @return {array}
	 */
	static get observedAttributes() {
		return ['disabled', 'value', 'name', 'onchange', 'width', 'data-options', 'data-buttonid'];
	}

	attributeChangedCallback(name, _old_value, new_value) {
		switch (name) {
			case 'data-buttonid': this._select.setButtonId(new_value); break;
			case 'name': this._select.setName(new_value); break;
			case 'value': this._select.setValue(new_value); break;
			case 'width': this._select.setWidth(new_value); break;
			case 'disabled': this._select.setDisabled(new_value !== null); break;
			case 'data-options':
				if (new_value === null) {
					return;
				}

				const options = JSON.parse(new_value);

				for (const option of options) {
					option.value instanceof Array
						? this._select.addOptionGroup(option.title, option.value)
						: this._select.addOption(option);
				}

				this.removeAttribute('data-options');
				break;
			case 'onchange':
				this.onchange = new Function('event', new_value);
				break;
		}
	}

	addOption({title, value, desc, disabled, class_name}) {
		this._select.addOption({title, value, desc, disabled, class_name});
	}

	getOptionByValue(value) {
		return this._select.getOptionByValue(value);
	}

	getOptions() {
		return this._select.getOptions();
	}

	focus() {
		this._select.focus();
	}

	get disabled() {
		return this._select.getDisabled();
	}

	set disabled(val) {
		if (val) {
			this.setAttribute('disabled', 'disabled');
		}
		else {
			this.removeAttribute('disabled');
		}
	}

	get name() {
		return this._select.getName();
	}

	set name(val) {
		this.setAttribute('name', val);
	}

	get value() {
		return this._select.getValue();
	}

	set value(val) {
		this.setAttribute('value', val);
	}

	get selectedIndex() {
		return this._select.getIdx();
	}

	set selectedIndex(val) {
		this._select.setIdx(val);
	}
}

customElements.define('z-select', ZSelectElement);
