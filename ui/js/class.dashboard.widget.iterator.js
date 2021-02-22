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


const WIDGET_EVENT_ITERATOR_PREVIOUS_PAGE_CLICK = 'iterator-previous-page-click';
const WIDGET_EVENT_ITERATOR_NEXT_PAGE_CLICK     = 'iterator-next-page-click';

class CDashboardWidgetIterator extends CDashboardWidget {

	constructor(config) {
		super({
			...config,
			is_iterator: true,
			css_classes: {
				actions: 'dashbrd-grid-iterator-actions',
				container: 'dashbrd-grid-iterator-container',
				content: 'dashbrd-grid-iterator-content',
				focus: 'dashbrd-grid-iterator-focus',
				head: 'dashbrd-grid-iterator-head',
				hidden_header: 'dashbrd-grid-iterator-hidden-header',
				mask: 'dashbrd-grid-iterator-mask',
				root: 'dashbrd-grid-iterator',
			}
		});

		this.page = 1;
		this.page_count = 1;
		this.children = [];
		this.update_pending = false;

		this._min_rows = config.min_rows;
	}

	setViewMode(view_mode) {
		if (this.view_mode !== view_mode) {
			this.view_mode = view_mode;

			if (view_mode === ZBX_WIDGET_VIEW_MODE_NORMAL) {
				this.div.removeClass('iterator-double-header');
			}

			for (const child of this.children) {
				child.setViewMode(view_mode);
			}

			this.div.toggleClass(this._css_classes.hidden_header, view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER);
		}

		return this;
	}

	addWidget(child) {
		child = new CDashboardWidget({
			view_mode: this.view_mode,
			...child,
			cell_height: this._cell_height,
			cell_width: this._cell_width,
			parent: this,
			is_editable: this._is_editable,
			is_iterator: false,
			is_new: false
		});

		this.content_body.append(child.div);
		this.children.push(child);

		child.showPreloader();
	}

	/**
	 * Focus specified top-level iterator.
	 */
	enter() {
		this.div.addClass(this._css_classes.focus);

		/** @type  {CDashboardWidget}  */
		let child_hovered = null;

		for (const child of this.children) {
			if (child.div.is(':hover')) {

				child_hovered = child;
			}
		}

		if (child_hovered !== null) {
			child_hovered.enterIteratorWidget();
		}
	}

	/**
	 * Blur specified top-level iterator.
	 */
	leave() {
		super.leave();

		this.leaveIteratorWidgetsExcept();
		this.div.removeClass('iterator-double-header');
	}

	/**
	 * Blur all child widgets of iterator, except the specified one.
	 *
	 * @param {object} except_child  Dashboard widget object.
	 */
	leaveIteratorWidgetsExcept(except_child = null) {
		for (const child of this.children) {
			if (except_child !== null && child.uniqueid === except_child.uniqueid) {
				continue;
			}

			child.div.removeClass(child.getCssClass('focus'));
		}
	}

	numColumns() {
		return this.fields['columns'] ? this.fields['columns'] : 2;
	}

	numRows() {
		return this.fields['rows'] ? this.fields['rows'] : 1;
	}

	isTooSmall(pos) {
		return pos.width < this.numColumns()
			|| pos.height < this.numRows() * this._min_rows;
	}

	getTooSmallState() {
		return this.div.hasClass('iterator-too-small');
	}

	setTooSmallState(enabled) {
		this.div.toggleClass('iterator-too-small', enabled);

		return this;
	}

	updatePager(page = this.page, page_count = this.page_count) {
		this.page = page;
		this.page_count = page_count;

		$('.dashbrd-grid-iterator-pager-info', this.content_header)
			.text(`${this.page} / ${this.page_count}`);

		this.content_header.addClass('pager-visible');

		const too_narrow = this.content_header.width() <
			$('.dashbrd-grid-iterator-pager', this.content_header).outerWidth(true)
			+ $('.dashbrd-grid-iterator-actions', this.content_header).outerWidth(true);

		const is_pager_visible = this.page_count > 1 && !too_narrow && !this.getTooSmallState();

		this.content_header.toggleClass('pager-visible', is_pager_visible);

		return this;
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			iteratorPreviousPage: () => {
				this.fire(WIDGET_EVENT_ITERATOR_PREVIOUS_PAGE_CLICK);
			},

			iteratorNextPage: () => {
				this.fire(WIDGET_EVENT_ITERATOR_NEXT_PAGE_CLICK);
			}
		}

		if (!this.parent) {
			this.$button_iterator_previous_page.on('click', this._events.iteratorPreviousPage);
			this.$button_iterator_next_page.on('click', this._events.iteratorNextPage);
		}
	}

	_unregisterEvents() {
		super._unregisterEvents();

		if (!this.parent) {
			this.$button_iterator_previous_page.off('click', this._events.iteratorPreviousPage);
			this.$button_iterator_next_page.off('click', this._events.iteratorNextPage);
		}
	}
}
