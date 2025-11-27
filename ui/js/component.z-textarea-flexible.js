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


class ZTextareaFlexible extends HTMLElement {

	#textarea;
	#intersection_observer;

	#events;
	#is_connected = false;

	constructor() {
		super();
	}

	connectedCallback() {
		if (!this.#is_connected) {
			this.#is_connected = true;

			if (!this.querySelector('textarea')) {
				const textarea = document.createElement('textarea');
				this.appendChild(textarea);
			}

			this.#textarea = this.querySelector('textarea');

			// Textarea value will become empty if we remove wrapper value after assigned it to textarea
			const value = this.hasAttribute('value') ? this.getAttribute('value') : null;
			this.removeAttribute('value');

			this.#textarea.name = this.getAttribute('name');
			this.#textarea.width = this.hasAttribute('width') ? Number(this.getAttribute('width')) : null;
			this.#textarea.height = this.hasAttribute('height') ? Number(this.getAttribute('height')) : null;
			this.#textarea.value = value;
			this.#textarea.placeholder = this.hasAttribute('placeholder') ? this.getAttribute('placeholder') : null;
			this.#textarea.disabled = this.hasAttribute('disabled');
			this.#textarea.readOnly = this.hasAttribute('readonly');
			this.#textarea.singleline = this.hasAttribute('singleline')	? this.getAttribute('singleline') : null;
			this.#textarea.spellcheck = this.getAttribute('spellcheck') !== 'false';

			if (this.hasAttribute('maxlength')) {
				this.#textarea.maxLength = Number(this.getAttribute('maxlength'));
			}

			this.#initVisibilityWatch();
			this.#registerEvents();
			this.#updateHeight();
		}
	}

	disconnectedCallback() {
		this.#unregisterEvents();
	}

	static get observedAttributes() {
		return ['width', 'height', 'value', 'maxlength', 'placeholder', 'disabled', 'readonly', 'singleline',
			'spellcheck'];
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (!this.#is_connected || old_value === new_value) {
			return;
		}

		switch (name) {
			case 'width':
				this.#textarea.style.width = new_value;
				break;

			case 'height':
				this.#textarea.style.height = new_value;
				break;

			case 'maxlength':
				this.#textarea.maxLength = new_value;
				break;

			case 'value':
				this.#textarea.value = new_value;
				break;

			case 'placeholder':
				this.#textarea.placeholder = new_value;
				break;

			case 'disabled':
				this.#textarea.disabled = new_value !== null;
				break;

			case 'readonly':
				this.#textarea.readOnly = new_value !== null;
				break;

			case 'singleline':
				this.#textarea.singleline = new_value;
				break;

			case 'spellcheck':
				this.#textarea.spellcheck = new_value !== 'false';
				break;

			default:
				return;
		}

		this.#updateHeight();
	}

	#registerEvents() {
		this.#events = {
			textareaKeydown: e => {
				if (e.key === 'Enter' && this.#textarea.singleline) {
					e.preventDefault();
				}
			},

			textareaBlur: () => {
				this.#updateHeight();
				this.dispatchEvent(new Event('blur', { bubbles: true }));
			},

			textareaResize: () => {
				this.#updateHeight();
				this.dispatchEvent(new Event('input', { bubbles: true }));
			}
		};

		this.#textarea.addEventListener('keydown', this.#events.textareaKeydown);
		this.#textarea.addEventListener('blur', this.#events.textareaBlur);
		this.#textarea.addEventListener('input', this.#events.textareaResize);
	}

	#unregisterEvents() {
		this.#textarea.removeEventListener('keydown', this.#events.textareaKeydown);
		this.#textarea.removeEventListener('blur', this.#events.textareaBlur);
		this.#textarea.removeEventListener('input', this.#events.textareaResize);
	}

	#updateHeight() {
		const computed_style = getComputedStyle(this.#textarea);

		const saved_value = this.#textarea.value;
		if (!saved_value && this.#textarea.placeholder) {
			this.#textarea.value = this.#textarea.placeholder;
		}

		const base = parseFloat(computed_style.minHeight) || 0;
		this.#textarea.style.height = base + 'px';

		const border = parseFloat(computed_style.borderTopWidth) + parseFloat(computed_style.borderBottomWidth);
		const target = this.#textarea.scrollHeight + (computed_style.boxSizing === 'border-box' ? border : 0);

		if (!saved_value && this.#textarea.placeholder) {
			this.#textarea.value = saved_value;
		}

		this.#textarea.style.height = Math.ceil(target) + 'px';
	}

	#initVisibilityWatch() {
		this.#intersection_observer = new IntersectionObserver(entries => {
			if (entries.some(e => e.isIntersecting)) {
				requestAnimationFrame(() => this.#updateHeight());
				this.#intersection_observer.disconnect();
			}
		});

		this.#intersection_observer.observe(this);
	}

	get width() {
		return this.#textarea.style.width;
	}

	set width(width) {
		this.#textarea.style.width = width;
	}

	get height() {
		return this.#textarea.style.height;
	}

	set height(height) {
		this.#textarea.style.height = height;
	}

	get maxlength() {
		return this.#textarea.maxLength;
	}

	set maxlength(maxlength) {
		this.setAttribute('maxlength', maxlength);
	}

	get value() {
		return this.#textarea.value;
	}

	set value(value) {
		this.setAttribute('value', value);
	}

	get placeholder() {
		return this.#textarea.placeholder;
	}

	set placeholder(placeholder) {
		this.setAttribute('placeholder', placeholder);
	}

	get disabled() {
		return this.#textarea.disabled;
	}

	set disabled(disabled) {
		this.toggleAttribute('disabled', disabled);
	}

	get readonly() {
		return this.#textarea.readOnly;
	}

	set readonly(readonly) {
		this.toggleAttribute('readonly', readonly);
	}

	get singleline() {
		return this.#textarea.singleline;
	}

	set singleline(singleline) {
		this.setAttribute('singleline', singleline);
	}

	get spellcheck() {
		return this.#textarea.spellcheck;
	}

	set spellcheck(spellcheck) {
		this.setAttribute('spellcheck', String(spellcheck));
	}
}

customElements.define('z-textarea-flexible', ZTextareaFlexible);
