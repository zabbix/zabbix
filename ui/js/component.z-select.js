customElements.define('z-select', class extends HTMLElement {

	// TODO: check if disconnectedCallback() needed ? is cleanup needed?

	state = {
		is_open: false,
		idx_commited: 0,
		idx_staged: 0,
		option_idx: [],
		option_map: {},
		focused: false,
		list_hover: false
	}

	root = {
		button: null,
		list: null,
		input: null
	}

	// TODO: Previous change event is not unbinded if this attribte is changed.
	onchange = () => {}

	constructor() {
		super();

		this.root.button = document.createElement('button');
		this.root.button.setAttribute('type', 'button');

		this.root.input = document.createElement('input');
		this.root.input.setAttribute('type', 'hidden');

		this.root.list = document.createElement('ul');
		this.root.list.classList.add('list');

		window.DEV = this;
	}

	connectedCallback() {
		const {options, name, value, disabled, buttonid, width, onchange} = JSON.parse(this.getAttribute('data-select'));
		this.removeAttribute('data-select');

		for (const option of options) {
			option.value instanceof Array
				? this.addOptionGroup(option.title, option.value)
				: this.addOption(option);
		}

		if (value !== null) {
			this.setAttribute('value', value);
		}
		else {
			const auto_select = this._nextEnabledIdx(-1);
			if (auto_select !== -1) {
				this.setAttribute('value', this._getOptionByIdx(auto_select).value);
			}
		}

		onchange && this.setAttribute('onchange', onchange);
		disabled && this.setAttribute('disabled', 'disabled');
		buttonid && this.root.button.setAttribute('id', buttonid);

		this.root.input.setAttribute('name', name);

		this._bindButtonNodeEvents(this.root.button);
		this._bindListNodeEvents(this.root.list);

		if (width === null) {
			this.setAttribute('width', this._measureListWidth() + 15);
		}
		else {
			this.setAttribute('width', width);
		}

		this.appendChild(this.root.button);
		this.appendChild(this.root.list);
		this.appendChild(this.root.input);
	}

	/**
	 * attributeChangedCallback
	 *
	 * @return {array}
	 */
	static get observedAttributes() {
		return ['disabled', 'value', 'name', 'onchange', 'width'];
	}

	attributeChangedCallback(name, _old_value, new_value) {
		switch (name) {
			case 'name':
				this.root.input.setAttribute('name', new_value);
				break;
			case 'value':
				const option = this.state.option_map[new_value];
				if (!option) {
					throw new Error(`Option of value "${new_value}" does not exist.`);
				}

				if (option.disabled) {
					throw new Error(`Disabled option "${new_value}" connot be used as value.`);
				}

				this._stageIdx(option._data_idx);
				this._commit();
				this._push();
				break;
			case 'disabled':
				(new_value === null)
					? this.root.button.removeAttribute('disabled')
					: this.root.button.setAttribute('disabled', 'disabled');
				break;
			case 'width':
				this.root.button.style.width = `${new_value}px`;
				this.root.list.style.width = `${new_value}px`;
				break;
			case 'onchange':
				this.onchange = new Function('event', new_value);
				break;
		}
	}

	_collapseList() {
		this.state.is_open = false;
		this.root.list.classList.remove('is-expanded');
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

		if (this.value !== value) {
			const evt = new Event('change');

			this.root.input.setAttribute('value', value);
			this.dispatchEvent(evt);
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

	_bindListNodeEvents(node) {
		// NOTE: Native select element collapses on document wheel event while list is not hovered and on resize event.
		node.onmouseenter = () => this.state.list_hover = true;
		node.onmouseleave = () => this.state.list_hover = false;

		window.addEventListener('resize', () => {
			this._push();
			this._collapseList();
		});

		// TODO: These events should be bound and unbound during list collapse and list expand
		// and then "this.state.focused" could be removed.
		document.addEventListener('wheel', () => {
			if (!this.state.focused) {
				return;
			}

			if (!this.state.list_hover) {
				this._push();
				this._collapseList();
			}
		});
	}

	_bindButtonNodeEvents(node) {
		node.onfocus = () => {
			this.state.focused = true;
		};

		node.onblur = () => {
			this.state.focused = false;
			this._push();
			this._collapseList();
		};
		node.onmousedown = () => {
			if (this.state.is_open) {
				this._push();
				this._collapseList();
			}
			else {
				this._expandList();
			}
		};
		node.onkeydown = (e) => {
			if (e.which != 32 && !e.metaKey && !e.ctrlKey && e.key.length == 1) {
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
				case 33: // PAGE_UP
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
				case 34: // PAGE_DOWN
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
				case 35: // END
					e.preventDefault();
					e.stopPropagation();
					this._stageIdx(this._lastIdx());
					this._commit();
					!this.state.is_open && this._push();
					break;
				case 36: // HOME
					e.preventDefault();
					e.stopPropagation();
					this._stageIdx(this._firstIdx());
					this._commit();
					!this.state.is_open && this._push();
					break;
				case 40: // down
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
				case 38: // up
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
				case 13: // enter
					if (!this.state.is_open) {
						this._expandList();
					}
					else {
						this._commit();
						this._push();
						this._collapseList();
					}
					break;
				case 9: // tab
					// NOTE: Native select element if opened, on "tab" submits hovered option and remains focused.
					if (this.state.is_open) {
						e.preventDefault();
						this._commit();
						this._push();
						this._collapseList();
					}
					break;
				case 32: // space
					// NOTE: Native select element does not closes or chooses option on "space" key, only opens dropdown.
					!this.state.is_open && this._expandList();
					break;
				case 27: // escape
					this.state.is_open && e.stopPropagation();
					this._push();
					this._collapseList();
					break;
			}
		};
	}

	_bindOptionNodeEvents(node) {
		/**
		 * NOTE: Native select element option responds to mouse move, also if mouseenter event is used, it is triggered when
		 * node has to be scrolled in view, and pointer happens to be over another node after scroll.
		 */
		node.onmousemove = () => {
			if (this.state.idx_staged != node.data_idx) {
				const {disabled} = this._getOptionByIdx(node.data_idx);
				!disabled && this._stageIdx(node.data_idx);
			}
		};

		node.onmousedown = (e) => {
			e.preventDefault();
			this._stageIdx(node.data_idx);
			this._commit();
			this._push();
			this._collapseList();
		};

		return node;
	}

	_createOptionNode({title, value, desc, disabled}) {
		const li = document.createElement('li');

		li.innerText = title;
		li.setAttribute('value', value);
		desc && li.setAttribute('description', desc);
		disabled && li.setAttribute('disabled', 'disabled');

		return this._bindOptionNodeEvents(li);
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

	// TODO: I do not like that optional container parameter for public API.
	addOption({title, value, desc, disabled}, container) {
		title = title.trim();
		if (!container) {
			container = this.root.list;
		}

		const node = this._createOptionNode({title, value, desc, disabled});
		this._registerOption({title, value, desc, disabled}, node);

		container.appendChild(node);
	}

	get value() {
		return this.root.input.value;
	}

	set value(val) {
		this.setAttribute("value", val);
	}
});
