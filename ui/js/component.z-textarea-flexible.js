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


class ZTextareaFlexible extends HTMLElement {

	/**
	 * Enables this custom element to behave like a native form control (<input>/<textarea>),
	 * participate in forms, handle validity, and respond to form resets.
	 */
	static formAssociated = true;

	/**
	 * ElementInternals gives access to form-related APIs (form value, validity, and form participation)
	 */
	#internals;

	/** @type {HTMLTextAreaElement} */
	#textarea;

	/** @type {ResizeObserver | null} */
	#resize_observer = null;

	/** @type {boolean} */
	#singleline = true;

	/** @type {boolean} */
	#is_resize_locked = false;

	/** @type {number | null} */
	#animation_frame_id = null;

	constructor() {
		super();

		this.#internals = this.attachInternals();
		this.#textarea = document.createElement('textarea');
	}

	static get observedAttributes() {
		return [
			'autofocus',
			'disabled',
			'maxlength',
			'placeholder',
			'readonly',
			'singleline',
			'spellcheck',
			'value'
		];
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (old_value !== new_value) {
			this.#applyAttribute(name, new_value);
		}
	}

	connectedCallback() {
		if (!this.contains(this.#textarea)) {
			this.appendChild(this.#textarea);
		}

		this.#applyAttributes();
		this.#syncAria();
		this.#addEventListeners();

		this.#resize_observer = new ResizeObserver(() => this.#updateHeight());
		this.#resize_observer.observe(this.#textarea);
	}

	disconnectedCallback() {
		this.#resize_observer.disconnect();
		this.#resize_observer = null;

		this.#removeEventListeners();
		cancelAnimationFrame(this.#animation_frame_id);
	}

	blur() {
		this.isConnected && this.#textarea.blur();
	}

	focus(options) {
		this.isConnected && this.#textarea.focus(options);
	}

	select() {
		this.isConnected && this.#textarea.select();
	}

	#applyAttributes() {
		for (const attr of this.constructor.observedAttributes) {
			this.#applyAttribute(attr, this.getAttribute(attr));
		}
	}

	#applyAttribute(name, value) {
		switch (name) {
			case 'autofocus':
				this.#textarea.autofocus = value !== null;
				break;

			case 'disabled':
				this.#textarea.disabled = value !== null;
				break;

			case 'maxlength':
				const max_length = parseInt(value);

				if (Number.isFinite(max_length) && max_length >= 0) {
					this.#textarea.maxLength = max_length;
				}
				else {
					this.#textarea.removeAttribute('maxlength');
				}
				break;

			case 'placeholder':
				this.#textarea.placeholder = value ?? '';
				break;

			case 'readonly':
				this.#textarea.readOnly = value !== null;
				break;

			case 'singleline':
				this.#singleline = value !== null;
				this.#updateHeight();
				break;

			case 'spellcheck':
				this.#textarea.spellcheck = value !== 'false';
				break;

			case 'value':
				const normalized = this.#singleline
					? (value ?? '').replace(/[\r\n]+/g, ' ')
					: value ?? '';

				this.#textarea.value = normalized;
				this.#internals.setFormValue(normalized);

				this.#updateHeight();
				break;

			default:
				return;
		}
	}

	#syncAria() {
		for (const aria_attr of this.getAttributeNames().filter(attr => attr.startsWith('aria-'))) {
			this.#textarea.setAttribute(aria_attr, this.getAttribute(aria_attr));
		}
	}

	#updateHeight() {
		if (!this.isConnected || this.#is_resize_locked) {
			return;
		}

		this.#is_resize_locked = true;

		this.#animation_frame_id = requestAnimationFrame(() => {
			const styles = getComputedStyle(this.#textarea);

			this.#textarea.style.height = '0';
			this.#textarea.style.height = `${this.#textarea.scrollHeight + parseInt(styles.borderWidth) * 2}px`;
			this.#is_resize_locked = false;
		});
	}

	#addEventListeners() {
		this.#textarea.addEventListener('input', this.#inputHandler);
		this.#textarea.addEventListener('keydown', this.#keydownHandler);
		this.#textarea.addEventListener('blur', this.#reemitFocus);
		this.#textarea.addEventListener('focus', this.#reemitFocus);
	}

	#removeEventListeners() {
		this.#textarea.removeEventListener('input', this.#inputHandler);
		this.#textarea.removeEventListener('keydown', this.#keydownHandler);
		this.#textarea.removeEventListener('blur', this.#reemitFocus);
		this.#textarea.removeEventListener('focus', this.#reemitFocus);
	}

	#inputHandler = (e) => {
		this.value = e.target.value;
	}

	#keydownHandler = (e) => {
		if (e.key === 'Enter' && this.#singleline) {
			e.preventDefault();
			this.closest('form')?.requestSubmit();
		}
	}

	#reemitFocus = (e) => {
		this.dispatchEvent(new FocusEvent(e.type));
	}

	get autofocus() {
		return this.#textarea.autofocus;
	}

	set autofocus(autofocus) {
		this.toggleAttribute('autofocus', autofocus);
	}

	get disabled() {
		return this.#textarea.disabled;
	}

	set disabled(disabled) {
		this.toggleAttribute('disabled', disabled);
	}

	get maxLength() {
		return this.#textarea.maxLength;
	}

	set maxLength(maxlength) {
		this.setAttribute('maxlength', maxlength);
	}

	get placeholder() {
		return this.#textarea.placeholder;
	}

	set placeholder(placeholder) {
		this.setAttribute('placeholder', placeholder);
	}

	get readonly() {
		return this.#textarea.readOnly;
	}

	set readonly(readonly) {
		this.toggleAttribute('readonly', readonly);
	}

	get singleline() {
		return this.#singleline;
	}

	set singleline(singleline) {
		this.toggleAttribute('singleline', singleline);
	}

	get spellcheck() {
		return this.#textarea.spellcheck;
	}

	set spellcheck(spellcheck) {
		this.setAttribute('spellcheck', String(spellcheck));
	}

	get value() {
		return this.#textarea.value;
	}

	set value(value) {
		this.setAttribute('value', value);
	}
}

customElements.define('z-textarea-flexible', ZTextareaFlexible);
