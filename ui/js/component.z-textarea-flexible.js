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

	/**
	 * @type {HTMLTextAreaElement | null}
	 */
	#textarea;

	/**
	 * @type {ResizeObserver | null}
	 */
	#resize_observer = null;

	constructor() {
		super();

		this.#textarea = document.createElement('textarea');
	}

	connectedCallback() {
		this.appendChild(this.#textarea);

		this.#textarea.name = this.getAttribute('name');
		this.#textarea.value = this.getAttribute('value');
		this.#textarea.placeholder = this.getAttribute('placeholder');
		this.#textarea.disabled = this.hasAttribute('disabled');
		this.#textarea.readOnly = this.hasAttribute('readonly');
		this.#textarea.singleline = this.hasAttribute('singleline');
		this.#textarea.spellcheck = this.getAttribute('spellcheck') !== 'false';

		const maxlength = parseInt(this.getAttribute('maxlength'));
		if (Number.isFinite(maxlength) && maxlength >= 0) {
			this.#textarea.maxLength = maxlength;
		}
		else {
			this.#textarea.removeAttribute('maxlength');
		}

		this.#initVisibilityWatch();
		this.#registerEvents();
		this.#updateHeight();
	}

	disconnectedCallback() {
		this.#unregisterEvents();
		if (this.#resize_observer !== null) {
			this.#resize_observer.disconnect();
			this.#resize_observer = null;
		}
	}

	static get observedAttributes() {
		return ['width', 'value', 'maxlength', 'placeholder', 'disabled', 'readonly', 'singleline',	'spellcheck'];
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (old_value === new_value) {
			return;
		}

		switch (name) {
			case 'width':
				this.style.width = new_value;
				break;

			case 'maxlength':
				const maxlength = parseInt(new_value);
				if (Number.isFinite(maxlength) && maxlength >= 0) {
					this.#textarea.maxLength = maxlength;
				}
				else {
					this.#textarea.removeAttribute('maxlength');
				}

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
		this.#textarea.addEventListener('keydown', this.#keydownHandler);
		this.#textarea.addEventListener('blur', this.#blurHandler);
		this.#textarea.addEventListener('focus', this.#focusHandler);
		this.#textarea.addEventListener('input', this.#inputHandler);
	}

	#unregisterEvents() {
		this.#textarea.removeEventListener('keydown', this.#keydownHandler);
		this.#textarea.removeEventListener('blur', this.#blurHandler);
		this.#textarea.removeEventListener('focus', this.#focusHandler);
		this.#textarea.removeEventListener('input', this.#inputHandler);
	}

	#keydownHandler = (e) => {
		if (e.key === 'Enter' && this.#textarea.singleline) {
			e.preventDefault();
			this.closest('form')?.requestSubmit();
		}
	}

	#blurHandler = () => {
		this.#updateHeight();
		this.dispatchEvent(new Event('blur', { bubbles: true }));
	}

	#focusHandler = () => {
		this.dispatchEvent(new Event('focus', { bubbles: true }));
	}

	#inputHandler = () => {
		let value = this.#textarea.value;

		if ((value.includes('\n') || value.includes('\r')) && this.#textarea.singleline) {
			this.#textarea.value = value.replace(/[\r\n]+/g, ' ');
		}

		this.#updateHeight();
		this.dispatchEvent(new Event('input', { bubbles: true }));
	}

	#updateHeight() {
		this.#textarea.style.height = '0';
		this.#textarea.style.height = `${this.#textarea.scrollHeight}px`;
	}

	#initVisibilityWatch() {
		if (this.#resize_observer === null){
			this.#resize_observer = new ResizeObserver(entries => {
				for (const entry of entries) {
					if (entry.contentRect.width > 0) {
						requestAnimationFrame(() => this.#updateHeight());

						this.#resize_observer.disconnect();
						this.#resize_observer = null;
						break;
					}
				}
			});

			this.#resize_observer.observe(this);
		}
	}

	get width() {
		return this.style.width;
	}

	set width(width) {
		this.style.width = width;
	}

	get maxLength() {
		return this.#textarea.maxLength;
	}

	set maxLength(maxlength) {
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
