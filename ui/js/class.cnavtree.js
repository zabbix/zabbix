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


/*
 * Since function addPopupValues can be defined by several dashboard widgets, the variable addPopupValues should be
 * defined in global scope and always re-written with function right before usage. Do this in all widgets where it is
 * needed.
 */
let old_addPopupValues = null;

if (typeof addPopupValues === 'undefined') {
	window.addPopupValues = null;
}

if (typeof (zbx_widget_navtree_trigger) !== typeof (Function)) {
	function zbx_widget_navtree_trigger(action, grid) {
		var $navtree = jQuery('.navtree', grid['widget']['content_body']);

		$navtree.zbx_navtree(action);
	}
}
(function($) {
	$.widget('zbx.sortable_tree', $.extend({}, $.ui.sortable.prototype, {
		options: {
			// jQuery UI sortable options:
			cursor: 'grabbing',
			placeholder: 'placeholder',
			forcePlaceholderSize: true,
			toleranceElement: '> div',
			forceHelperSize: true,
			tolerance: 'intersect',
			handle: '.drag-icon',
			items: '.tree-item',
			helper:	'clone',
			revert:	10,
			opacity: .75,
			scrollSpeed: 20,

			// Custom options:
			parent_change_delay: 0,
			parent_expand_delay: 600,
			indent_size: 15,
			max_depth: 10
		},

		_create: function() {
			$.ui.sortable.prototype._create.apply(this, arguments);
		},

		_mouseDrag: function(event) {
			var o = this.options,
				prev_offset_top,
				scrolled;

			// Compute the helpers position.
			this.position = this._generatePosition(event);
			this.positionAbs = this._convertPositionTo('absolute');

			if (!this.lastPositionAbs) {
				this.lastPositionAbs = this.positionAbs;
			}

			// Do scrolling.
			if (this.options.scroll) {
				scrolled = false;
				if (this.scrollParent[0] != document && this.scrollParent[0].tagName != 'HTML') {

					if ((this.overflowOffset.top + this.scrollParent[0].offsetHeight)
							- event.pageY < o.scrollSensitivity) {
						this.scrollParent[0].scrollTop = scrolled = this.scrollParent[0].scrollTop + o.scrollSpeed;
					}
					else if (event.pageY - this.overflowOffset.top < o.scrollSensitivity) {
						this.scrollParent[0].scrollTop = scrolled = this.scrollParent[0].scrollTop - o.scrollSpeed;
					}

					if ((this.overflowOffset.left + this.scrollParent[0].offsetWidth)
							- event.pageX < o.scrollSensitivity) {
						this.scrollParent[0].scrollLeft = scrolled = this.scrollParent[0].scrollLeft + o.scrollSpeed;
					}
					else if (event.pageX - this.overflowOffset.left < o.scrollSensitivity) {
						this.scrollParent[0].scrollLeft = scrolled = this.scrollParent[0].scrollLeft - o.scrollSpeed;
					}
				}
				else {
					if (event.pageY - $(document).scrollTop() < o.scrollSensitivity) {
						scrolled = $(document).scrollTop($(document).scrollTop() - o.scrollSpeed);
					}
					else if ($(window).height() - (event.pageY - $(document).scrollTop()) < o.scrollSensitivity) {
						scrolled = $(document).scrollTop($(document).scrollTop() + o.scrollSpeed);
					}

					if (event.pageX - $(document).scrollLeft() < o.scrollSensitivity) {
						scrolled = $(document).scrollLeft($(document).scrollLeft() - o.scrollSpeed);
					}
					else if ($(window).width() - (event.pageX - $(document).scrollLeft()) < o.scrollSensitivity) {
						scrolled = $(document).scrollLeft($(document).scrollLeft() + o.scrollSpeed);
					}

				}
			}

			// Regenerate the absolute position used for position checks.
			this.positionAbs = this._convertPositionTo('absolute');

			prev_offset_top = this.placeholder.offset().top;

			// Set the helper position.
			if (!this.options.axis || this.options.axis !== 'y') {
				this.helper[0].style.left = this.position.left + 'px';
			}

			if (!this.options.axis || this.options.axis !== 'x') {
				this.helper[0].style.top = this.position.top + 'px';
			}

			this.hovering = this.hovering ? this.hovering : null;
			this.changing_parent = this.changing_parent ? this.changing_parent : null;
			this.mouseentered = this.mouseentered ? this.mouseentered : false;

			if (this.changing_parent) {
				clearTimeout(this.changing_parent);
			}

			// re-arrange
			for (var i = this.items.length - 1; i >= 0; i--) {

				// Cache variables and intersection, continue if no intersection.
				var item = this.items[i], itemElement = item.item[0], intersection = this._intersectsWithPointer(item);

				if (!intersection) {
					continue;
				}

				// Cannot intersect with itself.
				if (itemElement != this.currentItem[0]
						&& this.placeholder[(intersection == 1) ? 'next' : 'prev']()[0] != itemElement
						&& !$.contains(this.placeholder[0], itemElement)
						&& (this.options.type == 'semi-dynamic' ? !$.contains(this.element[0], itemElement) : true)) {
					if (!this.hovering && !$(itemElement).hasClass('opened')) {
						var uiObj = this;

						$(itemElement).addClass('hovering');

						this.hovering = setTimeout(function() {
							$(itemElement)
								.removeClass('closed')
								.addClass('opened');

							uiObj.refreshPositions();
						}, o.parent_expand_delay);
					}

					if (!this.mouseentered) {
						$(itemElement).mouseenter();
						this.mouseentered = true;
					}

					this.direction = (intersection == 1) ? 'down' : 'up';

					if (this._intersectsWithSides(item)) {
						$(itemElement).removeClass('hovering').mouseleave();
						this.mouseentered = false;

						if (this.hovering) {
							clearTimeout(this.hovering);
							this.hovering = null;
						}
						this._rearrange(event, item);
					}
					else {
						break;
					}

					this._trigger('change', event, this._uiHash());
					break;
				}
			}

			var parent_item = $(this.placeholder.parent()).closest('.tree-item'),
				level = +$(this.placeholder.parent()).attr('data-depth'),
				prev_item = this.placeholder[0].previousSibling ? $(this.placeholder[0].previousSibling) : null,
				next_item = this.placeholder[0].nextSibling ? $(this.placeholder[0].nextSibling) : null,
				child_levels = this._levelsUnder(this.currentItem[0]),
				direction_moved = null,
				levels_moved = 0;

			if (prev_item !== null) {
				while (prev_item[0] === this.currentItem[0] || prev_item[0] === this.helper[0]
						|| prev_item[0].className.indexOf('tree-item') == -1) {
					if (prev_item[0].previousSibling) {
						prev_item = $(prev_item[0].previousSibling);
					}
					else {
						prev_item = null;

						break;
					}
				}
			}

			if (next_item !== null) {
				while (next_item[0] === this.currentItem[0] || next_item[0] === this.helper[0]
						|| next_item[0].className.indexOf('tree-item') == -1) {
					if (next_item[0].nextSibling) {
						next_item = $(next_item[0].nextSibling);
					}
					else {
						next_item = null;

						break;
					}
				}
			}

			if (parent_item.get(0) === this.currentItem[0]) {
				$(this.element[0]).append(this.placeholder[0]);
				this._trigger('stop', event, this._uiHash());

				return false;
			}

			this.beyondMaxLevels = 0;

			/*
			 * If item is moved to the left and it is last element of the list, add it as a child element to the
			 * element before.
			 */
			if (parent_item !== null && next_item === null
					&& (this.positionAbs.left <= parent_item.offset().left
						|| this.positionAbs.left <= o.indent_size*-0.6)) {
				direction_moved = 'left';
			}
			// If item is moved to the right and there is sibling element before, put it as a child of it.
			else if (prev_item !== null && this.positionAbs.left >= prev_item.offset().left + o.indent_size) {
				direction_moved = 'right';
			}

			if (direction_moved) {
				levels_moved = Math.floor(Math.abs(parent_item.offset().left - this.positionAbs.left) / o.indent_size);
			}

			$('.highlighted-parent').removeClass('highlighted-parent');

			if (direction_moved === 'right' && levels_moved) {
				var drop_to = prev_item,
					uiObj = this;

				this._isAllowed(prev_item, level, level + child_levels);

				this.changing_parent = setTimeout(function() {
					$(drop_to)
						.addClass('highlighted-parent opened')
						.removeClass('closed');

					if (prev_offset_top && (prev_offset_top <= prev_item.offset().top)) {
						$('>.tree-list', drop_to).prepend(uiObj.placeholder);
					}
					else {
						$('>.tree-list', drop_to).append(uiObj.placeholder);
					}

					uiObj.refreshPositions();
				}, o.parent_change_delay);
			}

			else if (direction_moved === 'left' && levels_moved) {
				var drop_to = $(this.currentItem[0]).closest('.tree-item'),
					one_before = null,
					uiObj = this;

				while (levels_moved > 0) {
					if ($(drop_to).parent().closest('.tree-item').length) {
						one_before = drop_to;
						drop_to = $(drop_to).parent().closest('.tree-item');
					}
					levels_moved--;
				}

				$(drop_to).addClass('highlighted-parent');

				this.changing_parent = setTimeout(function() {
					if (one_before && one_before.length) {
						$(uiObj.placeholder).insertAfter(one_before);
					}
					else {
						$('>.tree-list', drop_to).append(uiObj.placeholder);
					}

					if (drop_to.children('.tree-list').children('li:visible:not(.ui-sortable-helper)').length < 1) {
						drop_to.removeClass('opened');
					}
					uiObj.refreshPositions();
				}, o.parent_change_delay);

				this._isAllowed(prev_item, level, level + child_levels);
			}
			else {
				$(this.placeholder.parent().closest('.tree-item')).addClass('highlighted-parent');
				this._isAllowed(prev_item, level, level + child_levels);
			}

			// Post events to containers.
			this._contactContainers(event);

			// Call callbacks.
			this._trigger('sort', event, this._uiHash());

			this.lastPositionAbs = this.positionAbs;

			return false;
		},

		_mouseStop: function(event, noPropagation) {
			if (!event) {
				return;
			}

			$('.highlighted-parent').removeClass('highlighted-parent');
			this.placeholder.removeClass('sortable-error');

			if (this.changing_parent) {
				clearTimeout(this.changing_parent);
			}

			if (this.beyondMaxLevels > 0) {
				this.reverting = true;

				if (this.domPosition.prev) {
					$(this.domPosition.prev).after(this.placeholder);
				}
				else {
					$(this.domPosition.parent).prepend(this.placeholder);
				}

				this._trigger('revert', event, this._uiHash());
				this.refreshPositions();
				this._clear(event, noPropagation);
			}
			else {
				if (this.options.revert) {
					var self = this,
						cur = self.placeholder.offset();

					self.reverting = true;

					$(this.helper).animate({
						left: cur.left - this.offset.parent.left - self.margins.left
							+ ((this.offsetParent[0] == document.body) ? 0 : this.offsetParent[0].scrollLeft),
						top: cur.top - this.offset.parent.top - self.margins.top
							+ ((this.offsetParent[0] == document.body) ? 0 : this.offsetParent[0].scrollTop)
					}, parseInt(this.options.revert, 10) || 500, function() {
						self._clear(event);
					});
				}
				else {
					this._clear(event, noPropagation);
				}
			}

			return false;
		},

		_isAllowed: function(parentItem, level, levels) {
			if (this.options.max_depth != 0 && (this.options.max_depth < levels
					|| +this.placeholder.closest('[data-depth]').attr('data-depth') > this.options.max_depth)
			) {
				this.placeholder.addClass('sortable-error');
				this.beyondMaxLevels = levels - this.options.max_depth;
			}
			else {
				this.placeholder.removeClass('sortable-error');
				this.beyondMaxLevels = 0;
			}
		},

		_levelsUnder: function(item) {
			var depths = [], levels;

			$('.tree-list', item).not(':empty').each(function(i, item) {
				levels = 0;

				while ($('.tree-list', item).length) {
					item = $('.tree-list', item).not(':empty');
					levels++;
				}

				depths.push(levels);
			});

			return depths.length ? Math.max.apply(null, depths) : 0;
		}
	}));

	$.zbx.sortable_tree.prototype.options = $.extend({}, $.ui.sortable.prototype.options,
		$.zbx.sortable_tree.prototype.options
	);
})(jQuery);
