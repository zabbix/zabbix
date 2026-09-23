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

class ZSplitView extends HTMLElement {

	static #FIXED_DEFAULT = 'end';

	/** @type {ResizeObserver | null} */
	#resize_observer = null;

	/** @type {HTMLElement} */
	#handle;

	/** @type {HTMLElement} */
	#handle_control;

	/** @type {'start' | 'end'} */
	#fixed = ZSplitView.#FIXED_DEFAULT;

	/** @type {string} */
	#fixed_size = '50%';

	/** @type {string} */
	#min_size = '0%';

	/** @type {string} */
	#max_size = '100%';

	/** @type {boolean} */
	#is_vertical = false;

	/** @type {number | null} */
	#pointer_id = null;

	/** @type {number | null} */
	#animation_frame_id = null;

	/** @type {string | null} */
	#pending_fixed_size = null;

	constructor() {
		super();

		this.#handle = new Template(`
			<z-split-view-handle>
				<div class="z-split-view-handle-control"></div>
			</z-split-view-handle>
		`).evaluateToElement();

		this.#handle_control = this.#handle.querySelector('.z-split-view-handle-control');
	}

	static get observedAttributes() {
		return ['fixed', 'fixed-size', 'min', 'max', 'vertical'];
	}

	connectedCallback() {
		if (this.#handle.parentNode !== this) {
			this.appendChild(this.#handle);
		}

		this.#applyAttributes();
		this.#addEventListeners();
		this.#updateHandleControlSize();

		this.#resize_observer = new ResizeObserver(() => this.#updateHandleControlSize());
		this.#resize_observer.observe(this);
	}

	disconnectedCallback() {
		this.#resize_observer.disconnect();
		this.#resize_observer = null;

		this.#removeEventListeners();
		this.#stopDragging();
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (old_value !== new_value) {
			this.#applyAttribute(name, new_value);
		}
	}

	#applyAttributes() {
		for (const attr of this.constructor.observedAttributes) {
			this.#applyAttribute(attr, this.getAttribute(attr));
		}
	}

	#applyAttribute(name, value) {
		switch (name) {
			case 'fixed':
				this.#fixed = ['start', 'end'].includes(value) ? value : ZSplitView.#FIXED_DEFAULT;
				break;

			case 'fixed-size':
				this.#fixed_size = value?.trim() || '50%';
				this.style.setProperty('--split-view-fixed-size', this.#fixed_size);
				break;

			case 'min':
				this.#min_size = value?.trim() || '0%';
				this.style.setProperty('--split-view-min-size', this.#min_size);
				break;

			case 'max':
				this.#max_size = value?.trim() || '100%';
				this.style.setProperty('--split-view-max-size', this.#max_size);
				break;

			case 'vertical':
				this.#is_vertical = value !== null;

				if (this.isConnected) {
					this.#updateHandleControlSize();
				}
				break;

			default:
				return;
		}
	}

	#updateHandleControlSize() {
		const rect = this.getBoundingClientRect();

		if (this.#is_vertical) {
			this.#handle_control.style.height = '';
			this.#handle_control.style.width = `${rect.width}px`;
		}
		else {
			this.#handle_control.style.width = '';
			this.#handle_control.style.height = `${rect.height}px`;
		}
	}

	#addEventListeners() {
		this.#handle.addEventListener('pointerdown', this.#onPointerDown);
	}

	#removeEventListeners() {
		this.#handle.removeEventListener('pointerdown', this.#onPointerDown);
	}

	#onPointerDown = e => {
		if (this.#pointer_id !== null || (e.pointerType === 'mouse' && e.button !== 0)) {
			return;
		}

		this.#pointer_id = e.pointerId;

		this.#handle.setPointerCapture(this.#pointer_id);
		this.#handle.addEventListener('pointermove', this.#onPointerMove);
		this.#handle.addEventListener('pointerup', this.#onPointerUp);
		this.#handle.addEventListener('pointercancel', this.#onPointerUp);

		this.toggleAttribute('resizing', true);

		this.#dispatchEvent('split-start');

		e.preventDefault();
	}

	#onPointerMove = e => {
		if (e.pointerId !== this.#pointer_id) {
			return;
		}

		const rect = this.getBoundingClientRect();

		const available_size = this.#is_vertical ? rect.height : rect.width;
		const pointer_position = this.#is_vertical ? e.clientY - rect.top : e.clientX - rect.left;
		const position = Math.min(available_size, Math.max(0, pointer_position));
		const fixed_size = this.#fixed === 'start' ? position : available_size - position;

		this.#pending_fixed_size = `${fixed_size}px`;

		if (this.#animation_frame_id !== null) {
			return;
		}

		this.#animation_frame_id = requestAnimationFrame(() => {
			this.#animation_frame_id = null;

			this.#applyPendingFixedSize();
			this.#dispatchEvent('split');
		});
	}

	#onPointerUp = e => {
		if (e.pointerId !== this.#pointer_id) {
			return;
		}

		this.#stopDragging();

		this.#dispatchEvent('split-end');
	}

	#applyPendingFixedSize() {
		if (this.#pending_fixed_size === null) {
			return;
		}

		this.fixedSize = this.#pending_fixed_size;
		this.#pending_fixed_size = null;
	}

	#stopDragging() {
		this.#handle.removeEventListener('pointermove', this.#onPointerMove);
		this.#handle.removeEventListener('pointerup', this.#onPointerUp);
		this.#handle.removeEventListener('pointercancel', this.#onPointerUp);

		if (this.#pointer_id !== null && this.#handle.hasPointerCapture(this.#pointer_id)) {
			this.#handle.releasePointerCapture(this.#pointer_id);
		}

		this.#pointer_id = null;

		if (this.#animation_frame_id !== null) {
			cancelAnimationFrame(this.#animation_frame_id);
			this.#animation_frame_id = null;

			this.#applyPendingFixedSize();
		}

		this.toggleAttribute('resizing', false);
	}

	#dispatchEvent(type) {
		this.dispatchEvent(new CustomEvent(type, {
			bubbles: true,
			detail: {
				fixed: this.#fixed,
				fixedSize: this.#fixed_size,
				vertical: this.#is_vertical
			}
		}));
	}

	get fixed() {
		return this.#fixed;
	}

	set fixed(value) {
		if (value !== null) {
			this.setAttribute('fixed', value === 'start' ? 'start' : 'end');
		}
		else {
			this.removeAttribute('fixed');
		}
	}

	get fixedSize() {
		return this.#fixed_size;
	}

	set fixedSize(value) {
		if (value !== null) {
			this.setAttribute('fixed-size', value);
		}
		else {
			this.removeAttribute('fixed-size');
		}
	}

	get min() {
		return this.#min_size;
	}

	set min(value) {
		if (value !== null) {
			this.setAttribute('min', value);
		}
		else {
			this.removeAttribute('min');
		}
	}

	get max() {
		return this.#max_size;
	}

	set max(value) {
		if (value !== null) {
			this.setAttribute('max', value);
		}
		else {
			this.removeAttribute('max');
		}
	}

	get vertical() {
		return this.#is_vertical;
	}

	set vertical(value) {
		this.toggleAttribute('vertical', value);
	}
}

class ZSplitViewHandle extends HTMLElement {
}

class ZSplitViewPane extends HTMLElement {
}

customElements.define('z-split-view', ZSplitView);
customElements.define('z-split-view-handle', ZSplitViewHandle);
customElements.define('z-split-view-pane', ZSplitViewPane);
