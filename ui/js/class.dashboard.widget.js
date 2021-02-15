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
const WIDGET_EVENT_EDIT_CLICK                   = 'edit-click';
const WIDGET_EVENT_ENTER                        = 'enter';
const WIDGET_EVENT_LEAVE                        = 'leave';

class CDashboardWidget extends CBaseComponent {

	constructor(widget) {
		const $target = $('<div>');
		super($target[0]);

		this.div = $target;

		// Replace empty arrays (or anything non-object) with empty objects.
		if (typeof widget.fields !== 'object') {
			widget.fields = {};
		}

		if (typeof widget.configuration !== 'object') {
			widget.configuration = {};
		}

		widget = {
			'widgetid': '',
			'type': '',
			'header': '',
			'view_mode': ZBX_WIDGET_VIEW_MODE_NORMAL,
			'pos': {
				'x': 0,
				'y': 0,
				'width': 1,
				'height': 1
			},
			'rf_rate': 0,
			'preloader_timeout': 10000,	// in milliseconds
			'update_paused': false,
			'initial_load': true,
			'ready': false,
			'storage': {},
			cell_width: 0,
			cell_height: 0,
			iterator: false,
			is_editable: false,
			'parent': false,
			...widget,
			$button_iterator_previous_page: undefined,
			$button_iterator_next_page: undefined,
			$button_edit: undefined
		};

		if (typeof widget.new_widget === 'undefined') {
			widget.new_widget = !widget.widgetid.length;
		}

		if (!widget.fields.dynamic || widget.fields.dynamic != 1) {
			this.dynamic_hostid = null;
		}

		widget.iterator = widget.defaults.iterator;

		if (widget.iterator) {
			widget = {
				...widget,
				'page': 1,
				'page_count': 1,
				'children': [],
				'update_pending': false
			};
		}

		for (const key in widget) {
			this[key] = widget[key];
		}

		this._is_active = false;

		this._makeWidgetDiv();

		this.registerEvents();
	}

	activate() {
		this._is_active = true;
	}

	deactivate() {
		this._is_active = false;
	}

	showPreloader() {
		if (this.iterator) {
			this.div.find('.dashbrd-grid-iterator-content').addClass('is-loading');
		}
		else {
			this.div.find('.dashbrd-grid-widget-content').addClass('is-loading');
		}
	}

	clearUpdateWidgetContentTimer() {
		if (typeof this.rf_timeoutid !== 'undefined') {
			clearTimeout(this.rf_timeoutid);
			delete this.rf_timeoutid;
		}
	}

	/**
	 * Enable user functional interaction with widget.
	 */
	enableWidgetControls() {
		this.content_header.find('button').prop('disabled', false);
	}

	/**
	 * Disable user functional interaction with widget.
	 */
	disableWidgetControls() {
		this.content_header.find('button').prop('disabled', true);
	}

	removeWidgetInfoButtons() {
		// Note: this function is used only for widgets and not iterators.

		$('.dashbrd-grid-widget-actions', this.content_header).find('.widget-info-button').remove();
	}

	_makeWidgetDiv() {
		const iterator_classes = {
			'root': 'dashbrd-grid-iterator',
			'container': 'dashbrd-grid-iterator-container',
			'head': 'dashbrd-grid-iterator-head',
			'content': 'dashbrd-grid-iterator-content',
			'focus': 'dashbrd-grid-iterator-focus',
			'actions': 'dashbrd-grid-iterator-actions',
			'mask': 'dashbrd-grid-iterator-mask',
			'hidden_header': 'dashbrd-grid-iterator-hidden-header'
		};

		const widget_classes = {
			'root': 'dashbrd-grid-widget',
			'container': 'dashbrd-grid-widget-container',
			'head': 'dashbrd-grid-widget-head',
			'content': 'dashbrd-grid-widget-content',
			'focus': 'dashbrd-grid-widget-focus',
			'actions': 'dashbrd-grid-widget-actions',
			'mask': 'dashbrd-grid-widget-mask',
			'hidden_header': 'dashbrd-grid-widget-hidden-header'
		};

		const classes = this.iterator ? iterator_classes : widget_classes;

		this.content_header = $('<div>', {'class': classes.head})
			.append($('<h4>').text((this.header !== '') ? this.header : this.defaults.header));

		if (!this.parent) {
			const widget_actions = {
				'widgetType': this.type,
				'currentRate': this.rf_rate,
				'widget_uniqueid': this.uniqueid,
				'multiplier': '0'
			};

			if ('graphid' in this.fields) {
				widget_actions.graphid = this.fields['graphid'];
			}

			if ('itemid' in this.fields) {
				widget_actions.itemid = this.fields['itemid'];
			}

			if (this.dynamic_hostid !== null) {
				widget_actions.dynamic_hostid = this.dynamic_hostid;
			}

			if (this.iterator) {
				this.$button_iterator_previous_page = $('<button>', {
					'type': 'button',
					'class': 'btn-iterator-page-previous',
					'title': t('Previous page')
				});
				this.$button_iterator_next_page = $('<button>', {
					'type': 'button',
					'class': 'btn-iterator-page-next',
					'title': t('Next page')
				});
			}

			if (this.is_editable) {
				this.$button_edit = $('<button>', {
					'type': 'button',
					'class': 'btn-widget-edit',
					'title': t('Edit')
				});
			}

			// Do not add action buttons for child widgets of iterators.
			this.content_header
				.append(this.iterator
					? $('<div>', {'class': 'dashbrd-grid-iterator-pager'}).append(
						this.$button_iterator_previous_page,
						$('<span>', {'class': 'dashbrd-grid-iterator-pager-info'}),
						this.$button_iterator_next_page
					)
					: ''
				)

				.append($('<ul>', {'class': classes.actions})
					.append(this.is_editable
						? $('<li>').append(this.$button_edit)
						: ''
					)
					.append(
						$('<li>').append(
							$('<button>', {
								'type': 'button',
								'class': 'btn-widget-action',
								'title': t('Actions'),
								'data-menu-popup': JSON.stringify({
									'type': 'widget_actions',
									'data': widget_actions
								}),
								'attr': {
									'aria-expanded': false,
									'aria-haspopup': true
								}
							})
						)
					)
				);
		}

		this.content_body = $('<div>', {'class': classes.content})
			.toggleClass('no-padding', !this.iterator && !this.configuration['padding']);

		this.container = $('<div>', {'class': classes.container})
			.append(this.content_header)
			.append(this.content_body);

		if (this.iterator) {
			this.container
				.append($('<div>', {'class': 'dashbrd-grid-iterator-too-small'})
					.append($('<div>').html(t('Widget is too small for the specified number of columns and rows.')))
				);
		}
		else {
			this.content_script = $('<div>');
			this.container.append(this.content_script);
		}

		this.div = $('<div>', {'class': classes.root})
			.toggleClass(classes.hidden_header, this.view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER)
			.toggleClass('new-widget', this.new_widget);

		if (!this.parent) {
			this.div.css({
				'min-height': `${this.cell_height}px`,
				'min-width': `${this.cell_width}%`
			});
		}

		// Used for disabling widget interactivity in edit mode while resizing.
		this.mask = $('<div>', {'class': classes.mask});

		this.div.append(this.container, this.mask);

		this.div.on('load.image', () => {
			// Call refreshCallback handler for expanded popup menu items.
			if (this.div.find('[data-expanded="true"][data-menu-popup]').length) {
				this.div.find('[data-expanded="true"][data-menu-popup]').menuPopup('refresh', this);
			}
		});
	}

	registerEvents() {
		this._events = {
			iteratorPreviousPage: () => {
				this.fire(WIDGET_EVENT_ITERATOR_PREVIOUS_PAGE_CLICK);
			},

			iteratorNextPage: () => {
				this.fire(WIDGET_EVENT_ITERATOR_NEXT_PAGE_CLICK);
			},

			edit: () => {
				this.fire(WIDGET_EVENT_EDIT_CLICK);
			},

			enter: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				this.fire(WIDGET_EVENT_LEAVE);
			}
		};

		if (!this.parent) {
			if (this.iterator) {
				this.$button_iterator_previous_page.on('click', this._events.iteratorPreviousPage);
				this.$button_iterator_next_page.on('click', this._events.iteratorNextPage);
			}

			if (this.is_editable) {
				this.$button_edit.on('click', this._events.edit);
			}
		}

		this.content_header
			.on('focusin', this._events.enter)
			.on('focusout', (e) => {
				if (!this.content_header.has(e.relatedTarget).length) {
					this._events.leave();
				}
			})
			.on('focusin focusout', () => {
				// Skip mouse events caused by animations which were caused by focus change.
				this._mousemove_waiting = true;
			});

		this.div
			// "Mouseenter" is required, since "mousemove" may not always bubble.
			.on('mouseenter mousemove', () => {
				this._events.enter();

				delete this._mousemove_waiting;
			})
			.on('mouseleave', () => {
				if (!this._mousemove_waiting) {
					this._events.leave();
				}
			});
	}
}
