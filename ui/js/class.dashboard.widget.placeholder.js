/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


const DASHBOARD_WIDGET_PLACEHOLDER_STATE_ADD_NEW = 0;
const DASHBOARD_WIDGET_PLACEHOLDER_STATE_RESIZING = 1;
const DASHBOARD_WIDGET_PLACEHOLDER_STATE_POSITIONING = 2;
const DASHBOARD_WIDGET_PLACEHOLDER_STATE_KIOSK_MODE = 3;
const DASHBOARD_WIDGET_PLACEHOLDER_STATE_READONLY = 4;

const ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER = 'dashbrd-grid-new-widget-placeholder';
const ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_BOX = 'dashbrd-grid-widget-new-box';
const ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_BOX_LABEL = 'dashbrd-grid-new-widget-label';
const ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_RESIZING = 'dashbrd-grid-widget-set-size';
const ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_POSITIONING = 'dashbrd-grid-widget-set-position';
const ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN = 'hidden';

/**
 * New widget placeholder class.
 */
class CDashboardWidgetPlaceholder {

	/**
	 * Create new widget placeholder instance.
	 *
	 * @param {int}      cell_width        Dashboard grid cell width in percents.
	 * @param {int}      cell_height       Dashboard grid cell height in pixels.
	 * @param {callback} add_new_callback  Callback to execute on click on "Add new widget".
	 */
	constructor(cell_width, cell_height, add_new_callback) {
		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._add_new_callback = add_new_callback;

		this._placeholder = document.createElement('div');
		this._placeholder.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER);

		this._placeholder_box = document.createElement('div');
		this._placeholder_box.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_BOX);

		this._placeholder_box_label = document.createElement('div');
		this._placeholder_box_label.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_BOX_LABEL);

		this._placeholder_box_label_wrap = document.createElement('span');

		this._placeholder_box_label.appendChild(this._placeholder_box_label_wrap);
		this._placeholder_box.appendChild(this._placeholder_box_label);
		this._placeholder.appendChild(this._placeholder_box);

		this.setState(DASHBOARD_WIDGET_PLACEHOLDER_STATE_ADD_NEW);
	}

	/**
	 * Get node of the new widget placeholder.
	 *
	 * @returns {HTMLElement}
	 */
	getNode() {
		return this._placeholder;
	}

	/**
	 * Set state of the new widget placeholder.
	 *
	 * @param {int} state  DASHBOARD_WIDGET_PLACEHOLDER_STATE_* constant.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	setState(state) {
		this._placeholder.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN);

		this._placeholder.classList.remove('disabled');
		this._placeholder.removeEventListener('click', this._add_new_callback);
		this._placeholder_box.classList.remove(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_RESIZING,
			ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_POSITIONING
		);
		this._placeholder_box_label_wrap.textContent = '';

		switch (state) {
			case DASHBOARD_WIDGET_PLACEHOLDER_STATE_ADD_NEW:
				const link = document.createElement('a');
				link.textContent = t('Add a new widget');
				link.href = '#';
				this._placeholder_box_label_wrap.appendChild(link);

				this._placeholder.addEventListener('click', this._add_new_callback);

				break;

			case DASHBOARD_WIDGET_PLACEHOLDER_STATE_RESIZING:
				this._placeholder_box.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_RESIZING);
				this._placeholder_box_label_wrap.textContent = t('Release to create a widget.');

				break;

			case DASHBOARD_WIDGET_PLACEHOLDER_STATE_POSITIONING:
				this._placeholder_box.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_POSITIONING);
				this._placeholder_box_label_wrap.textContent = t('Click and drag to desired size.');

				break;

			case DASHBOARD_WIDGET_PLACEHOLDER_STATE_KIOSK_MODE:
				this._placeholder_box_label_wrap.textContent = t('Cannot add widgets in kiosk mode');
				this._placeholder.classList.add('disabled');

				break;

			case DASHBOARD_WIDGET_PLACEHOLDER_STATE_READONLY:
				this._placeholder_box_label_wrap.textContent = t('You do not have permissions to edit dashboard');
				this._placeholder.classList.add('disabled');

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
		if (!this._placeholder.classList.contains(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN)) {
			this._placeholder_box_label_wrap.classList.remove(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN);
			if (this._placeholder_box_label.scrollHeight > this._placeholder_box_label.clientHeight) {
				this._placeholder_box_label_wrap.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN);
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
		this._placeholder.style.position = 'absolute';
		this._placeholder.style.left = `${x * this._cell_width}%`;
		this._placeholder.style.top = `${y * this._cell_height}px`;
		this._placeholder.style.width = `${width * this._cell_width}%`;
		this._placeholder.style.height = `${height * this._cell_height}px`;
		this._placeholder.classList.remove(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN);

		this.resize();

		return this;
	};

	/**
	 * Show new widget placeholder at the default position.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	showAtDefaultPosition() {
		this._placeholder.style.position = '';
		this._placeholder.style.left = '';
		this._placeholder.style.top = '';
		this._placeholder.style.width = '';
		this._placeholder.style.height = '';
		this._placeholder.classList.remove(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN);

		this.resize();

		return this;
	};

	/**
	 * Hide new widget placeholder.
	 *
	 * @returns {CDashboardWidgetPlaceholder}
	 */
	hide() {
		this._placeholder.classList.add(ZBX_STYLE_DASHBOARD_WIDGET_PLACEHOLDER_HIDDEN);

		return this;
	};
}
