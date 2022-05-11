/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


const ZBX_STYLE_WIDGET_PLACEHOLDER = 'dashboard-widget-placeholder';
const ZBX_STYLE_WIDGET_PLACEHOLDER_BOX = 'dashboard-widget-placeholder-box';
const ZBX_STYLE_WIDGET_PLACEHOLDER_LABEL = 'dashboard-widget-placeholder-label';
const ZBX_STYLE_WIDGET_PLACEHOLDER_RESIZING = 'dashboard-widget-placeholder-resizing';
const ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN = 'hidden';

const WIDGET_PLACEHOLDER_STATE_ADD_NEW = 0;
const WIDGET_PLACEHOLDER_STATE_RESIZING = 1;
const WIDGET_PLACEHOLDER_STATE_POSITIONING = 2;

const WIDGET_PLACEHOLDER_EVENT_ADD_NEW_WIDGET = 'widget-placeholder-add-new-widget';

/**
 * New widget placeholder class.
 */
class CDashboardWidgetPlaceholder extends CBaseComponent {

	/**
	 * Create new widget placeholder instance.
	 *
	 * @param {int} cell_width   Dashboard grid cell width in percents.
	 * @param {int} cell_height  Dashboard grid cell height in pixels.
	 */
	constructor(cell_width, cell_height) {
		super(document.createElement('div'));

		this._cell_width = cell_width;
		this._cell_height = cell_height;

		this._target.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER);

		this._placeholder_box = document.createElement('div');
		this._placeholder_box.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER_BOX);

		this._placeholder_box_label = document.createElement('div');
		this._placeholder_box_label.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER_LABEL);

		this._placeholder_box_label_wrap = document.createElement('span');

		this._placeholder_box_label.appendChild(this._placeholder_box_label_wrap);
		this._placeholder_box.appendChild(this._placeholder_box_label);
		this._target.appendChild(this._placeholder_box);

		this.setState(WIDGET_PLACEHOLDER_STATE_ADD_NEW);
	}

	/**
	 * Get node of the new widget placeholder.
	 *
	 * @returns {HTMLElement}
	 */
	getNode() {
		return this._target;
	}

	/**
	 * Set state of the new widget placeholder.
	 *
	 * @param {int} state  WIDGET_PLACEHOLDER_STATE_* constant.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	setState(state) {
		this._target.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN);

		this._target.classList.remove('disabled');
		this._placeholder_box.classList.remove(ZBX_STYLE_WIDGET_PLACEHOLDER_RESIZING);
		this._placeholder_box_label_wrap.textContent = '';

		switch (state) {
			case WIDGET_PLACEHOLDER_STATE_ADD_NEW:
				const link = document.createElement('a');

				link.textContent = t('Add a new widget');
				link.href = 'javascript:void(0)';

				this._target.addEventListener('click', () => this.fire(WIDGET_PLACEHOLDER_EVENT_ADD_NEW_WIDGET));

				this._placeholder_box_label_wrap.appendChild(link);

				break;

			case WIDGET_PLACEHOLDER_STATE_RESIZING:
				this._placeholder_box.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER_RESIZING);
				this._placeholder_box_label_wrap.textContent = t('Release to create a widget.');

				break;

			case WIDGET_PLACEHOLDER_STATE_POSITIONING:
				this._placeholder_box_label_wrap.textContent = t('Click and drag to desired size.');

				break;
		}

		return this;
	};

	/**
	 * Resize the new widget placeholder. Use to update visibility of the label of the placeholder.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	resize() {
		if (!this._target.classList.contains(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN)) {
			this._placeholder_box_label_wrap.classList.remove(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN);
			if (this._placeholder_box_label.scrollHeight > this._placeholder_box_label.clientHeight) {
				this._placeholder_box_label_wrap.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN);
			}
		}

		return this;
	};

	/**
	 * Show new widget placeholder at given position.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	showAtPosition({x, y, width, height}) {
		this._target.style.position = 'absolute';
		this._target.style.left = `${x * this._cell_width}%`;
		this._target.style.top = `${y * this._cell_height}px`;
		this._target.style.width = `${width * this._cell_width}%`;
		this._target.style.height = `${height * this._cell_height}px`;
		this._target.classList.remove(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN);

		this.resize();

		return this;
	};

	/**
	 * Show new widget placeholder at the default position.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	showAtDefaultPosition() {
		this._target.style.position = null;
		this._target.style.left = null;
		this._target.style.top = null;
		this._target.style.width = null;
		this._target.style.height = null;
		this._target.classList.remove(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN);

		this.resize();

		return this;
	};

	/**
	 * Hide new widget placeholder.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	hide() {
		this._target.classList.add(ZBX_STYLE_WIDGET_PLACEHOLDER_HIDDEN);

		return this;
	};
}
