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

	/** @type {ResizeObserver | null} */
	#resize_observer = null;

	/** @type {HTMLElement} */
	#handle;

	/** @type {HTMLElement} */
	#handle_control;

	/** @type {string} */
	#handle_position = '50%';

	/** @type {string} */
	#handle_min_position = '0%';

	/** @type {string} */
	#handle_max_position = '100%';

	/** @type {'start' | 'end'} */
	#fixed = 'end';

	/** @type {number | null} */
	#fixed_size = null;

	/** @type {boolean} */
	#is_container_resizing = false;

	/** @type {boolean} */
	#is_vertical = false;

	/** @type {number | null} */
	#pointer_id = null;

	/** @type {number | null} */
	#animation_frame_id = null;

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
		return ['position', 'min', 'max', 'fixed', 'vertical'];
	}

	connectedCallback() {
		if (this.#handle.parentNode !== this) {
			this.appendChild(this.#handle);
		}

		this.#applyAttributes();
		this.#addEventListeners();

		this.#updateHandleControlSize();
		this.#updateFixedSize();

		this.#resize_observer = new ResizeObserver(() => this.#onResize());
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
			case 'position':
				this.#handle_position = value?.trim() || '50%';
				this.style.setProperty('--split-view-handle-position', this.#handle_position);

				const control_rect = this.#handle_control.getBoundingClientRect();

				if (control_rect.width > 0 && control_rect.height > 0) {
					const split_view_rect = this.getBoundingClientRect();

					const position = this.#is_vertical
						? control_rect.top + control_rect.height / 2 - split_view_rect.top
						: control_rect.left + control_rect.width / 2 - split_view_rect.left;

					if (!this.#is_container_resizing) {
						this.#updateFixedSize(position);
					}

					const position_value = `${position}px`;

					if (this.#handle_position !== position_value) {
						this.position = position_value;
					}
				}

				break;

			case 'min':
				this.#handle_min_position = value?.trim() || '0%';
				this.style.setProperty('--split-view-handle-min-position', this.#handle_min_position);
				break;

			case 'max':
				this.#handle_max_position = value?.trim() || '100%';
				this.style.setProperty('--split-view-handle-max-position', this.#handle_max_position);
				break;

			case 'fixed':
				this.#fixed = value === 'start' ? 'start' : 'end';

				if (this.isConnected) {
					this.#updateFixedSize();
				}
				break;

			case 'vertical':
				this.#is_vertical = value !== null;
				break;

			default:
				return;
		}
	}

	#updateHandleControlSize() {
		const rect = this.getBoundingClientRect();

		if (this.vertical) {
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

	#getAvailableSize() {
		const rect = this.getBoundingClientRect();

		return this.#is_vertical ? rect.height : rect.width;
	}

	#getCurrentPosition() {
		const control_rect = this.#handle_control.getBoundingClientRect();

		if (control_rect.width === 0 || control_rect.height === 0) {
			return null;
		}

		const split_view_rect = this.getBoundingClientRect();

		return this.#is_vertical
			? control_rect.top + control_rect.height / 2 - split_view_rect.top
			: control_rect.left + control_rect.width / 2 - split_view_rect.left;
	}

	#updateFixedSize(position = null) {
		const available_size = this.#getAvailableSize();

		if (available_size <= 0) {
			return;
		}

		position ??= this.#getCurrentPosition();

		if (position === null) {
			return;
		}

		position = Math.min(available_size, Math.max(0, position));

		this.#fixed_size = this.#fixed === 'start' ? position : available_size - position;
	}

	#onResize() {
		this.#updateHandleControlSize();

		const available_size = this.#getAvailableSize();

		if (available_size <= 0) {
			return;
		}

		if (this.#fixed_size === null) {
			this.#updateFixedSize();
			return;
		}

		const position = this.#fixed === 'start' ? this.#fixed_size : available_size - this.#fixed_size;

		this.#is_container_resizing = true;

		this.position = `${Math.min(available_size, Math.max(0, position))}px`;

		this.#is_container_resizing = false;
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

		const available_size = this.vertical ? rect.height : rect.width;
		const pointer_position = this.vertical ? e.clientY - rect.top : e.clientX - rect.left;
		this.#handle_position = `${Math.min(available_size, Math.max(0, pointer_position))}px`;

		if (this.#animation_frame_id !== null) {
			return;
		}

		this.#animation_frame_id = requestAnimationFrame(() => {
			this.#animation_frame_id = null;

			this.position = this.#handle_position;

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
			this.position = this.#handle_position;
		}

		this.#animation_frame_id = null;

		this.toggleAttribute('resizing', false);
	}

	#dispatchEvent(type) {
		this.dispatchEvent(new CustomEvent(type, {
			bubbles: true,
			detail: {
				position: this.#handle_position,
				vertical: this.#is_vertical
			}
		}));
	}

	get position() {
		return this.#handle_position;
	}

	set position(value) {
		if (value !== null) {
			this.setAttribute('position', value);
		}
		else {
			this.removeAttribute('position');
		}
	}

	get min() {
		return this.#handle_min_position;
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
		return this.#handle_max_position;
	}

	set max(value) {
		if (value !== null) {
			this.setAttribute('max', value);
		}
		else {
			this.removeAttribute('max');
		}
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
