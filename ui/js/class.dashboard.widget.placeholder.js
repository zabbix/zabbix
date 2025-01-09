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


/**
 * New widget placeholder class.
 */
class CDashboardWidgetPlaceholder {

	static ZBX_STYLE_CLASS = 'dashboard-widget-placeholder';
	static ZBX_STYLE_BOX = 'dashboard-widget-placeholder-box';
	static ZBX_STYLE_LABEL = 'dashboard-widget-placeholder-label';
	static ZBX_STYLE_RESIZING = 'dashboard-widget-placeholder-resizing';

	static STATE_ADD_NEW = 0;
	static STATE_RESIZING = 1;
	static STATE_POSITIONING = 2;

	static EVENT_ADD_NEW_WIDGET = 'widget-placeholder-add-new-widget';

	/**
	 * @type {HTMLDivElement}
	 */
	#target;

	/**
	 * Dashboard grid cell width in percents.
	 *
	 * @type {number}
	 */
	#cell_width;

	/**
	 * Dashboard grid cell height in pixels.
	 *
	 * @type {number}
	 */
	#cell_height;

	/**
	 * @type {HTMLDivElement}
	 */
	#placeholder_box;

	/**
	 * @type {HTMLDivElement}
	 */
	#placeholder_box_label

	/**
	 * @type {HTMLDivElement}
	 */
	#placeholder_box_label_wrap

	/**
	 * Create new widget placeholder instance.
	 *
	 * @param {number} cell_width   Dashboard grid cell width in percents.
	 * @param {number} cell_height  Dashboard grid cell height in pixels.
	 */
	constructor(cell_width, cell_height) {
		this.#target = document.createElement('div');

		this.#cell_width = cell_width;
		this.#cell_height = cell_height;

		this.#target.classList.add(CDashboardWidgetPlaceholder.ZBX_STYLE_CLASS);

		this.#placeholder_box = document.createElement('div');
		this.#placeholder_box.classList.add(CDashboardWidgetPlaceholder.ZBX_STYLE_BOX);

		this.#placeholder_box_label = document.createElement('div');
		this.#placeholder_box_label.classList.add(CDashboardWidgetPlaceholder.ZBX_STYLE_LABEL);

		this.#placeholder_box_label_wrap = document.createElement('div');

		this.#placeholder_box_label.appendChild(this.#placeholder_box_label_wrap);
		this.#placeholder_box.appendChild(this.#placeholder_box_label);
		this.#target.appendChild(this.#placeholder_box);

		this.setState(CDashboardWidgetPlaceholder.STATE_ADD_NEW);
	}

	/**
	 * Get node of the new widget placeholder.
	 *
	 * @returns {HTMLDivElement}
	 */
	getNode() {
		return this.#target;
	}

	/**
	 * Set state of the new widget placeholder.
	 *
	 * @param {number} state  STATE_* constant.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	setState(state) {
		this.#target.classList.add(ZBX_STYLE_DISPLAY_NONE);

		this.#target.classList.remove('disabled');
		this.#placeholder_box.classList.remove(CDashboardWidgetPlaceholder.ZBX_STYLE_RESIZING);
		this.#placeholder_box_label_wrap.textContent = '';

		this.off('click', this.#listeners.onAddNewClick);

		switch (state) {
			case CDashboardWidgetPlaceholder.STATE_ADD_NEW:
				const link = document.createElement('a');

				link.textContent = t('Add a new widget');
				link.href = 'javascript:void(0)';

				this.#placeholder_box_label_wrap.appendChild(link);

				this.on('click', this.#listeners.onAddNewClick);

				break;

			case CDashboardWidgetPlaceholder.STATE_RESIZING:
				this.#placeholder_box.classList.add(CDashboardWidgetPlaceholder.ZBX_STYLE_RESIZING);
				this.#placeholder_box_label_wrap.textContent = t('Release to create a widget.');

				break;

			case CDashboardWidgetPlaceholder.STATE_POSITIONING:
				this.#placeholder_box_label_wrap.textContent = t('Click and drag to desired size.');

				break;
		}

		return this;
	}

	/**
	 * Resize the new widget placeholder. Use to update visibility of the label of the placeholder.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	resize() {
		if (!this.#target.classList.contains(ZBX_STYLE_DISPLAY_NONE)) {
			this.#placeholder_box_label.classList.remove(ZBX_STYLE_DISPLAY_NONE);
			this.#placeholder_box_label_wrap.classList.remove(ZBX_STYLE_DISPLAY_NONE);

			if (this.#placeholder_box_label.scrollWidth > this.#placeholder_box_label.clientWidth) {
				this.#placeholder_box_label.classList.add(ZBX_STYLE_DISPLAY_NONE);
			}

			if (this.#placeholder_box_label.scrollHeight > this.#placeholder_box_label.clientHeight) {
				this.#placeholder_box_label_wrap.classList.add(ZBX_STYLE_DISPLAY_NONE);
			}
		}

		return this;
	}

	/**
	 * Show new widget placeholder at given position.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	showAtPosition({x, y, width, height}) {
		this.#target.style.position = 'absolute';
		this.#target.style.left = `${x * this.#cell_width}%`;
		this.#target.style.top = `${y * this.#cell_height}px`;
		this.#target.style.width = `${width * this.#cell_width}%`;
		this.#target.style.height = `${height * this.#cell_height}px`;
		this.#target.classList.remove(ZBX_STYLE_DISPLAY_NONE);

		this.resize();

		return this;
	}

	/**
	 * Show new widget placeholder at the default position.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	showAtDefaultPosition() {
		this.#target.style.position = null;
		this.#target.style.left = null;
		this.#target.style.top = null;
		this.#target.style.width = null;
		this.#target.style.height = null;
		this.#target.classList.remove(ZBX_STYLE_DISPLAY_NONE);

		this.resize();

		return this;
	}

	/**
	 * Hide new widget placeholder.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	hide() {
		this.#target.classList.add(ZBX_STYLE_DISPLAY_NONE);

		return this;
	}

	#listeners = {
		onAddNewClick: (e) => {
			e.stopImmediatePropagation();

			this.fire(CDashboardWidgetPlaceholder.EVENT_ADD_NEW_WIDGET);
		}
	}

	/**
	 * Attach event listener to widget placeholder events.
	 *
	 * @param {string}        type
	 * @param {function}      listener
	 * @param {Object|false}  options
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	on(type, listener, options = false) {
		this.#target.addEventListener(type, listener, options);

		return this;
	}

	/**
	 * Detach event listener from widget placeholder events.
	 *
	 * @param {string}        type
	 * @param {function}      listener
	 * @param {Object|false}  options
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	off(type, listener, options = false) {
		this.#target.removeEventListener(type, listener, options);

		return this;
	}

	/**
	 * Dispatch widget placeholder event.
	 *
	 * @param {string}  type
	 * @param {Object}  detail
	 * @param {Object}  options
	 *
	 * @returns {boolean}
	 */
	fire(type, detail = {}, options = {}) {
		return this.#target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}
}
