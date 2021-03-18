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




const WIDGET_ITERATOR_EVENT_PREVIOUS_PAGE_CLICK = 'iterator-previous-page-click';
const WIDGET_ITERATOR_EVENT_NEXT_PAGE_CLICK     = 'iterator-next-page-click';

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

	activate() {
		super.activate();

		for (const child of this.children) {
			child.activate();
		}
	}

	deactivate() {
		super.deactivate();

		for (const child of this.children) {
			child.deactivate();
		}
	}

	getViewMode() {
		return this.view_mode;
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

	/**
	 * Clear and reset the state of the iterator.
	 */
	clear() {
		for (const child of this.children) {
			this._removeWidget(child);
		}

		this.content_body.empty();
		this.children = [];

		this.div.removeClass('iterator-alt-content');
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
			this.enterChild(child_hovered);
		}
	}

	/**
	 * Focus specified child widget of iterator.
	 */
	enterChild(child) {
		child.enter();

		if (this.div.hasClass(this._css_classes.hidden_header)) {
			this.div.toggleClass('iterator-double-header', child.div.position().top == 0);
		}
	}

	/**
	 * Blur specified top-level iterator.
	 */
	leave() {
		super.leave();

		this.leaveChildrenExcept();
		this.div.removeClass('iterator-double-header');
	}

	/**
	 * Blur all child widgets of iterator, except the specified one.
	 *
	 * @param {object} except_child  Dashboard widget object.
	 */
	leaveChildrenExcept(except_child = null) {
		for (const child of this.children) {
			if (child != except_child) {
				child.div.removeClass(child.getCssClass('focus'));
			}
		}
	}

	getNumColumns() {
		return this.fields['columns'] ? this.fields['columns'] : 2;
	}

	getNumRows() {
		return this.fields['rows'] ? this.fields['rows'] : 1;
	}

	isTooSmall(pos) {
		return pos.width < this.getNumColumns()
			|| pos.height < this.getNumRows() * this._min_rows;
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

	/**
	 * Update ready state of the iterator.
	 *
	 * @returns {boolean}  True, if status was updated.
	 */
	updateReady() {
		let is_ready_updated = false;

		if (this.children.length == 0) {
			// Set empty iterator to ready state.

			is_ready_updated = !this._is_ready;
			this._is_ready = true;
		}

		return is_ready_updated;
	}

	_makeView() {
		super._makeView();

		this.content_header.prepend(
			$('<div>', {'class': 'dashbrd-grid-iterator-pager'})
				.append($('<button>', {
					'type': 'button',
					'class': 'btn-iterator-page-previous',
					'title': t('Previous page')
				}))
				.append($('<span>', {'class': 'dashbrd-grid-iterator-pager-info'}))
				.append($('<button>', {
					'type': 'button',
					'class': 'btn-iterator-page-next',
					'title': t('Next page')
				}))
		);

		this.content_body.removeClass('no-padding');

		this.container.append(
			$('<div>', {'class': 'dashbrd-grid-iterator-too-small'})
				.append($('<div>').html(t('Widget is too small for the specified number of columns and rows.')))
		);

		this._addPlaceholders(this.getNumColumns() * this.getNumRows());
		this.alignContents(this.pos);
	}

	_addPlaceholders(count) {
		$('.dashbrd-grid-iterator-placeholder', this.content_body).remove();

		for (let index = 0; index < count; index++) {
			this.content_body.append(
				$('<div>', {'class': 'dashbrd-grid-iterator-placeholder'})
					.append('<div>').on('mouseenter', () => {
						// Set single-line header for the iterator.
						this.div.removeClass('iterator-double-header');
					})
			);
		}
	}

	/**
	 * @returns {boolean}  Returns true, if to small state.
	 */
	alignContents(pos) {
		if (this.isTooSmall(pos)) {
			this.setTooSmallState(true);

			return false;
		}

		if (this.getTooSmallState() && this.update_pending) {
			this.setTooSmallState(false);
			this.showPreloader();

			return true;
		}

		this.setTooSmallState(false);

		const $placeholders = this.content_body.find('.dashbrd-grid-iterator-placeholder');
		const num_columns = this.getNumColumns();
		const num_rows = this.getNumRows();

		for (let index = 0, count = num_columns * num_rows; index < count; index++) {
			const cell_column = index % num_columns;
			const cell_row = Math.floor(index / num_columns);
			const cell_width_min = Math.floor(pos.width / num_columns);
			const cell_height_min = Math.floor(pos.height / num_rows);

			const num_enlarged_columns = pos.width - cell_width_min * num_columns;
			const num_enlarged_rows = pos.height - cell_height_min * num_rows;

			const x = cell_column * cell_width_min + Math.min(cell_column, num_enlarged_columns);
			const y = cell_row * cell_height_min + Math.min(cell_row, num_enlarged_rows);
			const width = cell_width_min + (cell_column < num_enlarged_columns ? 1 : 0);
			const height = cell_height_min + (cell_row < num_enlarged_rows ? 1 : 0);

			let css = {
				left: `${x / pos.width * 100}%`,
				top: `${y * this._cell_height}px`,
				width: `${width / pos.width * 100}%`,
				height: `${height * this._cell_height}px`
			};

			if (cell_column === (num_columns - 1)) {
				// Setting right side for last column of widgets (fixes IE11 and Opera issues).
				css = {
					...css,
					'width': 'auto',
					'right': '0px'
				};
			}
			else {
				css = {
					...css,
					'width': `${Math.round(width / pos.width * 100 * 100) / 100}%`,
					'right': 'auto'
				};
			}

			if (index < this.children.length) {
				this.children[index].div.css(css);
			}
			else {
				$placeholders.eq(index - this.children.length).css(css);
			}
		}

		return false;
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			previousPage: () => {
				this.fire(WIDGET_ITERATOR_EVENT_PREVIOUS_PAGE_CLICK);
			},

			nextPage: () => {
				this.fire(WIDGET_ITERATOR_EVENT_NEXT_PAGE_CLICK);
			}
		}

//		this.$button_previous_page.on('click', this._events.previousPage);
//		this.$button_next_page.on('click', this._events.nextPage);
	}

	_unregisterEvents() {
		super._unregisterEvents();

//		this.$button_previous_page.off('click', this._events.previousPage);
//		this.$button_next_page.off('click', this._events.nextPage);
	}
}
