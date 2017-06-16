/*
 * Since function addPopupValues can be defined by several dashboard widgets, the variable addPopupValues should be
 * defined in global scope and always re-written with function right before usage. Do this in all widgets where it is
 * needed.
 */
var old_addPopupValues = null;
if (typeof addPopupValues === 'undefined') {
	var addPopupValues = null;
}

if (typeof(zbx_widget_navtree_trigger) !== typeof(Function)) {
	function zbx_widget_navtree_trigger(action ,grid){
		var $navtree = jQuery('.navtree', grid['widget']['content_body']);
		$navtree.zbx_navtree(action);
	}
}

(function($) {
	$.widget('zbx.sortable_tree', $.extend({}, $.ui.sortable.prototype, {
		options: {
			// jQuery UI sortable options:
			placeholder: 'placeholder',
			forcePlaceholderSize: true,
			toleranceElement: '> div',
			forceHelperSize: true,
			tolerance: 'intersect',
			handle: '.drag-icon',
			items: '.tree-item',
			helper:	'clone',
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

			// Compute the helpers position
			this.position = this._generatePosition(event);
			this.positionAbs = this._convertPositionTo('absolute');

			if (!this.lastPositionAbs) {
				this.lastPositionAbs = this.positionAbs;
			}

			// Do scrolling
			if (this.options.scroll) {
				scrolled = false;
				if (this.scrollParent[0] != document && this.scrollParent[0].tagName != 'HTML') {

					if ((this.overflowOffset.top + this.scrollParent[0].offsetHeight)
						- event.pageY < o.scrollSensitivity
					) {
						this.scrollParent[0].scrollTop = scrolled = this.scrollParent[0].scrollTop + o.scrollSpeed;
					}
					else if (event.pageY - this.overflowOffset.top < o.scrollSensitivity) {
						this.scrollParent[0].scrollTop = scrolled = this.scrollParent[0].scrollTop - o.scrollSpeed;
					}

					if ((this.overflowOffset.left + this.scrollParent[0].offsetWidth)
						- event.pageX < o.scrollSensitivity
					) {
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

				if (scrolled !== false && $.ui.ddmanager && !o.dropBehaviour) {
					$.ui.ddmanager.prepareOffsets(this, event);
				}
			}

			// Regenerate the absolute position used for position checks
			this.positionAbs = this._convertPositionTo("absolute");

			prev_offset_top = this.placeholder.offset().top;

			// Set the helper position
			if (!this.options.axis || this.options.axis != "y") this.helper[0].style.left = this.position.left+'px';
			if (!this.options.axis || this.options.axis != "x") this.helper[0].style.top = this.position.top+'px';

			this.hovering = this.hovering ? this.hovering : null;
			this.changing_parent = this.changing_parent ? this.changing_parent : null;
			this.mouseentered = this.mouseentered ? this.mouseentered : false;

			if (this.changing_parent) {
				clearTimeout(this.changing_parent);
			}

			// Rearrange
			for (var i = this.items.length - 1; i >= 0; i--) {

				// Cache variables and intersection, continue if no intersection
				var item = this.items[i], itemElement = item.item[0], intersection = this._intersectsWithPointer(item);
				if (!intersection) continue;

				if (itemElement != this.currentItem[0] // cannot intersect with itself
					&&	this.placeholder[intersection == 1 ? "next" : "prev"]()[0] != itemElement
					&&	!$.contains(this.placeholder[0], itemElement)
					&& (this.options.type == 'semi-dynamic' ? !$.contains(this.element[0], itemElement) : true)
				) {
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

					this.direction = intersection == 1 ? 'down' : 'up';

					if (this._intersectsWithSides(item)) {
						$(itemElement).removeClass('hovering').mouseleave();
						this.mouseentered = false;
						if (this.hovering) {
							clearTimeout(this.hovering);
							this.hovering = null;
						}
						this._rearrange(event, item);
					} else {
						break;
					}

					this._trigger('change', event, this._uiHash());
					break;
				}
			}

			var parent_item = $(this.placeholder.parent()).closest('.tree-item'),
				level = $(this.placeholder.parent()).data('depth'),
				prev_item = this.placeholder[0].previousSibling ? $(this.placeholder[0].previousSibling) : null,
				next_item = this.placeholder[0].nextSibling ? $(this.placeholder[0].nextSibling) : null,
				child_levels = this._levelsUnder(this.currentItem[0]),
				direction_moved = null,
				levels_moved = 0;

			if (prev_item !== null) {
				while (prev_item[0] === this.currentItem[0] || prev_item[0] === this.helper[0]
					|| prev_item[0].className.indexOf('tree-item') == -1
				) {
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
					|| next_item[0].className.indexOf('tree-item') == -1
				) {
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
				&& (this.positionAbs.left <= parent_item.offset().left || this.positionAbs.left <= o.indent_size*-0.6)
			) {
				direction_moved = 'left';
			}
			// If item is moved to the right and there is sibling element before, put it as a child of it.
			else if (prev_item !== null && this.positionAbs.left >= prev_item.offset().left + o.indent_size) {
				direction_moved = 'right';
			}

			if (direction_moved) {
				levels_moved = Math.floor(Math.abs(parent_item.offset().left - this.positionAbs.left) / o.indent_size);
			}

			$('.highliglted-parent').removeClass('highliglted-parent');

			if (direction_moved === 'right' && levels_moved) {
				var drop_to = prev_item,
					uiObj = this;

				this._isAllowed(prev_item, level, level+child_levels);

				this.changing_parent = setTimeout(function() {
					$(drop_to)
						.addClass('highliglted-parent opened')
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

				$(drop_to).addClass('highliglted-parent');

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
			}
			else {
				$(this.placeholder.parent().closest('.tree-item')).addClass('highliglted-parent');
			}

			// Post events to containers
			this._contactContainers(event);

			// Interconnect with droppables
			if($.ui.ddmanager) $.ui.ddmanager.drag(this, event);

			// Call callbacks
			this._trigger('sort', event, this._uiHash());

			this.lastPositionAbs = this.positionAbs;
			return false;
		},

		_mouseStop: function(event, noPropagation) {
			if (!event) return;

			$('.highliglted-parent').removeClass('highliglted-parent');

			if (this.changing_parent) {
				clearTimeout(this.changing_parent);
			}

			// If we are using droppables, inform the manager about the drop
			if ($.ui.ddmanager && !this.options.dropBehaviour) {
				$.ui.ddmanager.drop(this, event);

				var parent_id = this.placeholder.parent().closest('.tree-item').data('id'),
					item_id = $(this.currentItem[0]).data('id');

				$('[name="map.parent.'+item_id+'"]').val(parent_id);
			}

			if (this.options.revert) {
				var self = this;
				var cur = self.placeholder.offset();

				self.reverting = true;

				$(this.helper).animate({
					left: cur.left - this.offset.parent.left - self.margins.left
						+ (this.offsetParent[0] == document.body ? 0 : this.offsetParent[0].scrollLeft),
					top: cur.top - this.offset.parent.top - self.margins.top
						+ (this.offsetParent[0] == document.body ? 0 : this.offsetParent[0].scrollTop)
				}, parseInt(this.options.revert, 10) || 500, function() {
					self._clear(event);
				});
			}
			else {
				this._clear(event, noPropagation);
			}

			return false;
		},

		_isAllowed: function(parentItem, level, levels) {
			if (this.options.max_depth < levels && this.options.max_depth != 0) {
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
				while ($('.tree-list', item).size()) {
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

jQuery(function($) {
	/**
	 * Create Navigation Tree Widget.
	 *
	 * @return object
	 */
	if (typeof($.fn.zbx_navtree) === 'undefined') {
		$.fn.zbx_navtree = function(input) {
			$this = $(this);

			/* TODO miks:
			 * Button styles should be moved to stylesheet.
			 * Icons should be changed.
			 */
			var buttonCssAdd = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL1BsdXM8L3RpdGxlPgogICAgPGRlc2M+Q3JlYXRlZCB3aXRoIFNrZXRjaC48L2Rlc2M+CiAgICA8ZGVmcz48L2RlZnM+CiAgICA8ZyBpZD0iMjB4MjAiIHN0cm9rZT0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIxIiBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgICAgICAgPGcgaWQ9IjIweDIwL1BsdXMiIHN0cm9rZT0iIzM2NDM0RCI+CiAgICAgICAgICAgIDxnIGlkPSJQbHVzIj4KICAgICAgICAgICAgICAgIDxnIGlkPSJJY29uIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgyLjAwMDAwMCwgMi4wMDAwMDApIj4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNMCw4IEwxNiw4IiBpZD0iTGluZS00Ij48L3BhdGg+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTgsMCBMOCwxNiIgaWQ9IkxpbmUtMyI+PC9wYXRoPgogICAgICAgICAgICAgICAgPC9nPgogICAgICAgICAgICA8L2c+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4=') no-repeat left center",
				'background-size': 'cover',
				'border': '0px none',
				'cursor': 'pointer',
				'height': '15px',
				'width': '14px'
			};
			var buttonCssEdit = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL0VkaXQ8L3RpdGxlPgogICAgPGRlc2M+Q3JlYXRlZCB3aXRoIFNrZXRjaC48L2Rlc2M+CiAgICA8ZGVmcz4KICAgICAgICA8cGF0aCBkPSJNMTIuODk0ODI2LDEuNTY5NjQwMDUgQzEzLjY3MjcwNTIsMC43OTE3NjA4OSAxNC45MzUzODM1LDAuNzkzMjQ3ODQ0IDE1LjcyMDk2NjIsMS41Nzg4MzA1NCBMMTYuNDIxMTY5NSwyLjI3OTAzMzg0IEMxNy4yMDQxMjQ0LDMuMDYxOTg4NzYgMTcuMjA3MTA3NSw0LjMyODQyNjQ1IDE2LjQzMDM1OTksNS4xMDUxNzM5NiBMNy4yMzIyMzMwNSwxNC4zMDMzMDA5IEwyLjg3NzA1NjEsMTUuNzU1MDI2NSBDMi4zNTM0MjE3MiwxNS45Mjk1NzEzIDIuMDY3NjQ1OSwxNS42NTQ5MjY3IDIuMjQ0OTczNDksMTUuMTIyOTQzOSBMMy42OTY2OTkxNCwxMC43Njc3NjcgTDEyLjg5NDgyNiwxLjU2OTY0MDA1IEwxMi44OTQ4MjYsMS41Njk2NDAwNSBaIiBpZD0icGF0aC0xIj48L3BhdGg+CiAgICAgICAgPG1hc2sgaWQ9Im1hc2stMiIgbWFza0NvbnRlbnRVbml0cz0idXNlclNwYWNlT25Vc2UiIG1hc2tVbml0cz0ib2JqZWN0Qm91bmRpbmdCb3giIHg9IjAiIHk9IjAiIHdpZHRoPSIxNC44MTgzMTY4IiBoZWlnaHQ9IjE0LjgxOTQ2NyIgZmlsbD0id2hpdGUiPgogICAgICAgICAgICA8dXNlIHhsaW5rOmhyZWY9IiNwYXRoLTEiPjwvdXNlPgogICAgICAgIDwvbWFzaz4KICAgIDwvZGVmcz4KICAgIDxnIGlkPSIyMHgyMCIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPGcgaWQ9IjIweDIwL0VkaXQiPgogICAgICAgICAgICA8ZyBpZD0iRWRpdCI+CiAgICAgICAgICAgICAgICA8ZyBpZD0iSWNvbiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMi4wMDAwMDAsIDAuMDAwMDAwKSI+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTAsMTguNSBMMTYsMTguNSIgaWQ9IkxpbmUtNDIiIHN0cm9rZT0iIzM2NDM0RCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48L3BhdGg+CiAgICAgICAgICAgICAgICAgICAgPHVzZSBpZD0iTGluZS00MSIgc3Ryb2tlPSIjMzU0MjRDIiBtYXNrPSJ1cmwoI21hc2stMikiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InNxdWFyZSIgeGxpbms6aHJlZj0iI3BhdGgtMSI+PC91c2U+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTExLjg4OTA4NzMsNS4xMTA5MTI3IEwxMy44ODkwODczLDUuMTEwOTEyNyIgaWQ9IkxpbmUtNDAiIHN0cm9rZT0iIzM1NDI0QyIgc3Ryb2tlLWxpbmVjYXA9InNxdWFyZSIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTIuODg5MDg3LCA1LjExMDkxMykgcm90YXRlKC0zMTUuMDAwMDAwKSB0cmFuc2xhdGUoLTEyLjg4OTA4NywgLTUuMTEwOTEzKSAiPjwvcGF0aD4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNNC44MTgwMTk0OCwxMi4xODE5ODA1IEw2LjgxODAxOTQ4LDEyLjE4MTk4MDUiIGlkPSJMaW5lLTM5IiBzdHJva2U9IiMzNTQyNEMiIHN0cm9rZS1saW5lY2FwPSJzcXVhcmUiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDUuODE4MDE5LCAxMi4xODE5ODEpIHJvdGF0ZSgtMzE1LjAwMDAwMCkgdHJhbnNsYXRlKC01LjgxODAxOSwgLTEyLjE4MTk4MSkgIj48L3BhdGg+CiAgICAgICAgICAgICAgICA8L2c+CiAgICAgICAgICAgIDwvZz4KICAgICAgICA8L2c+CiAgICA8L2c+Cjwvc3ZnPg==')",
				'background-size': 'cover',
				'border': '0px none',
				'cursor': 'pointer',
				'height': '15px',
				'width': '14px'
			};
			var buttonCssImport = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL1VwbG9hZDwvdGl0bGU+CiAgICA8ZGVzYz5DcmVhdGVkIHdpdGggU2tldGNoLjwvZGVzYz4KICAgIDxkZWZzPjwvZGVmcz4KICAgIDxnIGlkPSIyMHgyMCIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj4KICAgICAgICA8ZyBpZD0iMjB4MjAvVXBsb2FkIiBzdHJva2U9IiMzNTQyNEMiPgogICAgICAgICAgICA8ZyBpZD0iVXBsb2FkIj4KICAgICAgICAgICAgICAgIDxnIGlkPSJJY29uIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwLjAwMDAwMCwgMS4wMDAwMDApIj4KICAgICAgICAgICAgICAgICAgICA8ZyBpZD0idXBsb2FkIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwLjUwMDAwMCwgMC41MDAwMDApIj4KICAgICAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTExLjkxODYyNTYsMTQuNSBMMTMuNSwxNC41IEMxMy41LDE0LjUgMTguOTYxNDcxLDE0Ljg3MDI3MzEgMTksOS41IEMxOC45NjE0NzIxLDQuMDQzOTc4MTEgMTMuNSw0IDEzLjUsNCBDMTIuMjQwNDk2NCwxLjU1MTExMzYzIDkuODYwMjM1MDMsMCA3LDAgQzMuMTg2Njk5MTEsMCAwLDMuMTg2Njk5MTEgMCw3IEMwLDExLjA0ODY4NDIgMywxNC41IDcsMTQuNSIgaWQ9IkxpbmUtNTEiPjwvcGF0aD4KICAgICAgICAgICAgICAgICAgICA8L2c+CiAgICAgICAgICAgICAgICAgICAgPGcgaWQ9InVwbG9hZCIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoNy4wMDAwMDAsIDYuMDAwMDAwKSI+CiAgICAgICAgICAgICAgICAgICAgICAgIDxnIGlkPSJQYXRoLTIxNjQtKy1MaW5lIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxwb2x5bGluZSBpZD0iTGluZS01MCIgcG9pbnRzPSI2LjA0NTczOTE2IDMuNTA1ODM2NTEgMy4wMzQwNjYxNCAwLjQ5NDE2MzQ4OCAwLjA1NzgxOTE4NTIgMy40NzA0MTA0NCI+PC9wb2x5bGluZT4KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxwYXRoIGQ9Ik0zLDEuNSBMMywxMS41NDE1OTQ1IiBpZD0iTGluZS00OSI+PC9wYXRoPgogICAgICAgICAgICAgICAgICAgICAgICA8L2c+CiAgICAgICAgICAgICAgICAgICAgPC9nPgogICAgICAgICAgICAgICAgPC9nPgogICAgICAgICAgICA8L2c+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4=')",
				'background-size': 'cover',
				'cursor': 'pointer',
				'border': '0px none',
				'height': '15px',
				'width': '14px'
			};

			var getNextId = function() {
				var widget_data = $this.data('widgetData');

				widget_data.lastId++;
				while ($('[name="map.name.'+widget_data.lastId+'"]').length) {
					widget_data.lastId++;
				}

				return widget_data.lastId;
			}

			var makeSortable = function() {
				var widget_data = $this.data('widgetData');

				$('.root-item>.tree-list')
					.sortable_tree({
						max_depth: widget_data['max_depth'],
						stop: function( event, ui ) {
							storeUIState();
							setTreeHandlers();
						}
					})
					.disableSelection();
			};

			var drawTree = function() {
				var root = createTreeBranch('root'),
					tree_items = getTreeWidgetItems(),
					tree = buildTree(tree_items, 0);

				$('.root', $this).remove();
				$('.tree', $this).append(root);

				if (isEditMode()) {
					root.append(createTreeItem({name: t('root'), id: 0}, 0, false));
					root = $('.tree-item.root-item[data-id=0] > .tree-list', $this);
				}

				$.each(tree, function(i, item) {
					if (typeof item === 'object') {
						root.append(createTreeItem(item));
					}
				});

				setTreeHandlers();
			};

			var parseProblems = function() {
				var widget_data = $this.data('widgetData');
				if (typeof widget_data.severity_levels === 'undefined') {
					return false;
				}

				$.each(widget_data.problems, function(map) {
					if (typeof widget_data.problems[map] === 'object') {
						$.each(widget_data.problems[map], function(sev, numb) {
							if (numb) {
								$('.tree-item[data-mapid='+map+']').attr('data-problems'+sev, numb);
							}
						});
					}
				});

				$('.tree-item', $this).each(function() {
					var id = $(this).data('id'),
						obj = $(this);

					$.each(widget_data.severity_levels, function(sev, conf) {
						var sum = 0;
						if (typeof obj.data('problems'+sev) !== 'undefined') {
							sum += +obj.data('problems'+sev);
						}

						$('[data-problems'+sev+']', obj).each(function() {
							sum += +$(this).data('problems'+sev);
						});

						if (sum) {
							obj.attr('data-problems'+sev, sum);
						}
					});
				});

				$.each(widget_data.severity_levels, function(sev, conf) {
					$('[data-problems'+sev+']', $this).each(function() {
						var obj = $(this);

						$('>.row', this).append($('<span>', {
								'style': 'background: #'+conf['color'],
								'class': 'problems-per-item',
								'title': conf['name']
							})
							.html(obj.attr('data-problems'+sev))
						);
					});
				});
			};

			var createTreeBranch = function(className) {
				var className = className || null,
					ul = $('<ul></ul>').addClass('tree-list');

				if (className) {
					$(ul).addClass(className);
				}
				return ul;
			};

			var getCookieName = function(key) {
				var widget_data = getWidgetData();
				return 'zbx_widget'+widget_data['fields']['reference']+'_'+key;
			}

			var storeUIState = function() {
				var opened = [];
				$('.opened', $this).each(function() {
					opened.push($(this).data('id'));
				});

				if (opened.length) {
					cookie.create(getCookieName('opened_nodes'), opened.join(','));
				}
				else {
					cookie.erase(getCookieName('opened_nodes'));
				}
			};

			/*
			 * Dialog to create new or edit existing Tree item.
			 *
			 * @param {numeric} id - widget field ID or 0 when creating new item.
			 * @param {numeric} parent - ID of parent item under which new item is created.
			 * @param {numeric} depth - a depth of parent item under which new item is created.
			 */
			var itemEditDialog = function(id, parent, depth) {
				var url = new Curl('zabbix.php'),
					item_edit = !!id,
					ajax_data = {
						map_name: '',
						map_mapid: 0,
						depth: depth || 1,
						map_id: id
					};

				if (id) {
					ajax_data['map_name'] = $('[name="map.name.'+id+'"]', $this).val();
					ajax_data['map_mapid'] = $('[name="mapid.'+id+'"]', $this).val();
				}
				else {
					ajax_data['map_id'] = getNextId();
				}

				url.setArgument('action', 'widget.navigationtree.edititemdialog');

				jQuery.ajax({
					url: url.getUrl(),
					method: 'POST',
					data: ajax_data,
					dataType: 'json',
					success: function(resp) {
						var id = ajax_data['map_id'];

						overlayDialogue({
							'title': t('Edit Tree Widget item'),
							'content': resp.body,
							'buttons': [
								{
									'title': item_edit ? t('Update') : t('Add'),
									'class': 'dialogue-widget-save',
									'action': function() {
										var form = $('#widget_dialogue_form'),
											url = new Curl('zabbix.php'),
											ajax_data = {
												add_submaps: $('[name="add_submaps"]', form).is(':checked') ? 1 : 0,
												map_name: $('[name="map.name.'+id+'"]', form).val(),
												map_mapid: +$('[name="linked_map_id"]', form).val(),
												mapid: id
											};

										url.setArgument('action', 'widget.navigationtree.edititem');

										jQuery.ajax({
											url: url.getUrl(),
											method: 'POST',
											data: ajax_data,
											dataType: 'json',
											success: function(resp) {
												var new_item,
													root;

												$('.msg-bad', form).remove();
												if (typeof resp.errors === 'object' && resp.errors.length > 0) {
													form.prepend(resp.errors);
													return false;
												}
												else {
													if ($('[name="map.name.'+id+'"]', $this).length) {
														$('[name="map.name.'+id+'"]', $this).val(resp['map_name']);
														$('[name="mapid.'+id+'"]', $this).val(resp['map_mapid']);
														$('[data-id='+id+'] > .row > .item-name', $this)
															.html(resp['map_name']);
													}
													else {
														root = $('.tree-item[data-id='+parent+']>ul.tree-list', $this),
														new_item = {
															name: resp['map_name'],
															mapid: +resp['map_mapid'],
															id: +resp['map_id'],
															parent: parent
														};

														root.append(createTreeItem(new_item));

														$(root).closest('.tree-item')
															.removeClass('closed')
															.addClass('opened');
													}

													if (typeof resp.submaps !== 'undefined') {
														root = $('.tree-item[data-id='+id+']>ul.tree-list', $this)
														$.each(resp.submaps, function() {
															var new_item = {
																name: this['name'],
																mapid: +this['sysmapid'],
																id: getNextId(),
																parent: +id
															};

															root.append(createTreeItem(new_item));
														});

														$(root).closest('.tree-item')
															.addClass('is-parent opened')
															.removeClass('closed');
													}

													overlayDialogueDestroy();
													storeUIState();
													setTreeHandlers();
												}
											}
										});

										return false;
									}
								},
								{
									'title': t('Cancel'),
									'class': 'btn-alt',
									'action': function() {}
								}
							]
						});
					}
				});
			};

			/**
			 * Create Tree LI item with everything needed inside it.
			 *
			 * @param {object} item
			 * @param {numeric} depth
			 * @param {boolean} editable - either item in edit-mode will be editable. Root item is not editable.
			 *
			 * @returns {object}
			 */
			var createTreeItem = function(item, depth, editable) {
				var opened_nodes = cookie.read(getCookieName('opened_nodes')),
					widget_data = $this.data('widgetData'),
					ul = createTreeBranch(null),
					item_clases = 'tree-item',
					link;

				if (typeof depth !== 'number') {
					depth = 1;
				}
				if (typeof editable !== 'boolean') {
					editable = true;
				}

				opened_nodes = opened_nodes ? opened_nodes.split(',') : [];
				if (!editable || opened_nodes.indexOf(item.id.toString()) !== -1) {
					item_clases += ' opened';
				}
				else {
					item_clases += ' closed';
				}

				if (!editable) {
					item_clases += ' root-item';
				}

				if (typeof item.children !== 'undefined' && widget_data.max_depth > depth) {
					if (item.children.length) {
						item_clases += ' is-parent';
					}

					$.each(item.children, function(i, item) {
						if (typeof item === 'object') {
							ul.append(createTreeItem(item, depth+1));
							if (item.id > widget_data.lastId) {
								widget_data.lastId = item.id;
							}
						}
					});
				}

				if (!isEditMode() && typeof item.mapid === 'number' && item.mapid > 0) {
					link = $('<a>', {
							'data-mapid': item.mapid,
							'href': '#'
						})
						.click(function(e) {
							var data_to_share = {mapid: $(this).data('mapid')},
								widget = getWidgetData();

							e.preventDefault();
							$(".dashbrd-grid-widget-container").dashboardGrid("widgetDataShare", widget, data_to_share);
						});
				}
				else {
					link = $('<span>');
				}

				return $('<li>', {
						'class': item_clases,
						'data-mapid': item.mapid,
						'data-id': item.id
					})
					.append(
						$('<div>')
							.addClass('row')
							.append((isEditMode() && editable) ? $('<div>').addClass('drag-icon') : null)
							.append(editable ? $('<span>')
								.addClass((item_clases.indexOf('opened') !== -1) ? 'arrow-right' : 'arrow-down')
								.click(function() {
									var branch = $(this).closest('[data-id]');

									if (branch.hasClass('opened')) {
										$(this).addClass('arrow-right').removeClass('arrow-down');
										branch.removeClass('opened').addClass('closed');
									}
									else {
										$(this).addClass('arrow-down').removeClass('arrow-right');
										branch.removeClass('closed').addClass('opened');
									}

									storeUIState();
								}) : null
							)
							.append($(link)
								.addClass('item-name')
								.text(item.name)
							)
							.append(isEditMode() ? $('<div>')
								.addClass('tools')
								.append($('<input>', {
										'type': 'button',
										'data-id': item.id,
										'class': 'add-child-btn',
										'css': buttonCssAdd
									})
									.click(function() {
										var parentId = $(this).data('id'),
											widget_data = $this.data('widgetData'),
											depth = $(this).closest('.tree-list').data('depth'),
											branch = $('.tree-item[data-id='+parentId+']>ul', $this);

											if (typeof depth === 'undefined') {
												depth = 0;
											}

											if (widget_data.max_depth > depth) {
												itemEditDialog(0, parentId, depth);
											}
									})
								)
								.append($('<input>', {
										'class': 'import-items-btn',
										'data-id': item.id,
										'type': 'button',
										'css': buttonCssImport
									})
									.click(function() {
										var url = new Curl('popup.php'),
											id = $(this).data('id');

										url.setArgument('srctbl', 'sysmaps');
										url.setArgument('srcfld1', 'sysmapid');
										url.setArgument('srcfld2', 'name');
										url.setArgument('multiselect', '1');

										if (typeof addPopupValues === 'function') {
											old_addPopupValues = addPopupValues;
										}

										addPopupValues = function(data) {
											var root = $('.tree-item[data-id='+id+']>ul.tree-list', $this),
												new_item;

											$.each(data.values, function() {
												new_item = {
													name: this['name'],
													mapid: +this['sysmapid'],
													id: getNextId(),
													parent: id
												};

												root.append(createTreeItem(new_item));
											});

											$(root).closest('.tree-item')
												.removeClass('closed')
												.addClass('opened');

											storeUIState();
											setTreeHandlers();

											if (typeof old_addPopupValues === 'function') {
												addPopupValues = old_addPopupValues;
												old_addPopupValues = null;
											}
										};

										return PopUp(url.getUrl());
									})
								)
								.append(editable ? $('<input>', {
										'class': 'edit-item-btn',
										'type': 'button',
										'data-id': item.id,
										'css': buttonCssEdit
									})
									.click(function() {
										var id = $(this).data('id'),
											parent = +$('input[name="map.parent.'+id+'"]', $this).val(),
											depth = +$(this).closest('[data-depth]').data('depth');

										itemEditDialog(id, parent, depth)
									}) : null
								)
								.append(editable ? $('<button>', {
										'type': 'button',
										'data-id': item.id,
										'class': 'remove-btn',
										'style': 'top: 8px;'
									})
									.click(function(){
										removeItem([$(this).data('id')]);
									}) : null
								) : null
							)
					)
					.append(ul)
					.append((isEditMode() && editable) ? $('<input>', {
							'type': 'hidden',
							'name': 'map.name.'+item.id
						})
						.val(item.name) : null
					)
					.append((isEditMode() && editable) ? $('<input>', {
							'type': 'hidden',
							'name':'map.parent.'+item.id
						})
						.val(item.parent || 0) : null
					)
					.append((isEditMode() && editable) ? $('<input>', {
							'type': 'hidden',
							'name':'mapid.'+item.id
						})
						.val(typeof item.mapid === 'number' ? item.mapid : 0) : null
					);
			};

			var setTreeHandlers = function() {
				var opened_nodes = cookie.read(getCookieName('opened_nodes')),
					widget_data = $this.data('widgetData'),
					tree_list_depth;

				opened_nodes = opened_nodes ? opened_nodes.split(',') : [];

				// Add .is-parent class for branches with sub-items.
				$('.tree-list', $this).not('.ui-sortable, .root').each(function() {
					if ($('>li', this).length) {
						$(this).closest('.tree-item').addClass('is-parent');
					}
					else {
						$(this).closest('.tree-item').removeClass('is-parent');
					}
				});

				// Set which brances are opened and which ones are closed.
				$('.tree-item[data-id]').not('.root-item').each(function() {
					var id = $(this).data('id');
					if (opened_nodes.indexOf(id.toString()) !== -1) {
						$(this).addClass('opened').removeClass('closed');
					}
					else {
						$(this).addClass('closed').removeClass('opened');
					}
				});

				// Set [data-depth] for list and each sublist.
				$('.tree-list').not('.root').each(function() {
					tree_list_depth = $(this).parents('.tree-list').not('.root').size() + 1;
					$(this).attr('data-depth', tree_list_depth);
				});

				// Show/hide 'add new items' buttons.
				$('.tree-list').filter(function() {
					return +$(this).data('depth') >= widget_data.max_depth;
				}).each(function() {
					$('.import-items-btn', $(this)).css('visibility', 'hidden');
					$('.add-child-btn', $(this)).css('visibility', 'hidden');
				});

				$('.tree-list').filter(function() {
					return widget_data.max_depth > +$(this).data('depth');
				}).each(function() {
					$('>.tree-item>.row>.tools>.import-items-btn', $(this)).css('visibility', 'visible');
					$('>.tree-item>.row>.tools>.add-child-btn', $(this)).css('visibility', 'visible');
				});

				// Change arrow style.
				$('.is-parent.opened .arrow-right', $this).removeClass('arrow-right').addClass('arrow-down');
				$('.is-parent.closed .arrow-down', $this).removeClass('arrow-down').addClass('arrow-right');
			};

			var getWidgetData = function() {
				var widget_data = $this.data('widgetData'),
					response = $(".dashbrd-grid-widget-container").dashboardGrid('getWidgetsBy', 'uniqueid',
						widget_data['uniqueid']);

				if (response.length) {
					return response[0];
				}
				else {
					return null;
				}
			};

			/**
			 * Detects either the dashboard is in edit mode.
			 *
			 * @returns {boolean}
			 */
			var isEditMode = function() {
				return $(".dashbrd-grid-widget-container").dashboardGrid('isEditMode');
			};

			/*
			 * Grouping a seperate widget fields into objects of items. Each items consists of its name, parent, id etc.
			 *
			 * @returns {Array} - an array of item objects.
			 */
			var getTreeWidgetItems = function() {
				var widget_data = getWidgetData(),
					tree_items = [];

					$.each(widget_data['fields'], function(field_name, value) {
						var det = /^map\.name\.(\d+)$/.exec(field_name);
						if (det) {
							var item = {
								name: value,
								parent: 0,
								order: 1,
								mapid: 0,
								id: +det[1]
							};

							if (typeof widget_data['fields']['map.parent.'+item.id] !== 'undefined') {
								item.parent = +widget_data['fields']['map.parent.'+item.id];
							}
							if (typeof widget_data['fields']['mapid.'+item.id] !== 'undefined') {
								item.mapid = +widget_data['fields']['mapid.'+item.id];
							}
							if (typeof widget_data['fields']['map.order.'+item.id] !== 'undefined') {
								item.order = +widget_data['fields']['map.order.'+item.id];
							}

							tree_items.push(item);
						}
				});

				return tree_items;
			};

			// Create multi-level array that represents real child-parent dependencies in tree.
			var buildTree = function(rows, parent_id) {
				var parent_id = (typeof parent_id === 'number') ? parent_id : 0,
					widget_data = $this.data('widgetData'),
					rows = rows || [],
					tree = [];

				if (!rows.length) {
					return [];
				}

				$.each(rows, function(i, item) {
					if (typeof item === 'object') {
						if (item['id'] > widget_data.lastId) {
							widget_data.lastId = item['id'];
						}

						if (item['parent'] == parent_id) {
							var children = buildTree(rows, item['id']);

							if (children.length) {
								item['children'] = children;
							}

							var indx = tree.findIndex(function(el) {
								return el['id'] === item['id'];
							});

							if (indx > -1) {
								tree[indx] = item;
							}
							else {
								tree.push(item);
							}
						}
					}
				});

				tree.sort(function(a, b) {
					if (a['order'] < b['order']) {
						return -1;
					}
					if (a['order'] > b['order']) {
						return 1;
					}

					return 0;
				});

				return tree;
			};

			// Remove item from tree.
			var removeItem = function(id) {
				var parent_id = $('input[name="map.parent.'+id+'"]', $this).val();
				if ($('.tree-item', $('[data-id='+parent_id+']', $this)).length == 1) {
					$('[data-id='+parent_id+']').removeClass('is-parent');
				}
				$('[data-id='+id+']').remove();
			};

			// Records data from DOM to dashboard widget[fields] array.
			var updateWidgetFields = function() {
				var dashboard_widget = getWidgetData();

				if (!dashboard_widget || !isEditMode()) {
					return false;
				}

				// delete existing fields
				for (var field_name in dashboard_widget['fields']) {
					if (/map\.?(?:id|name|parent|order)\.\d+/.test(field_name)) {
						delete dashboard_widget['fields'][field_name];
					}
				}

				// Add fields to widget[fields] array.
				$('input[name^="map.name."]', dashboard_widget['content_body']).each(function() {
					var det = /^map\.name\.(\d+)$/.exec($(this).attr('name'));
					if (det) {
						var id = +det[1],
							parent = +$('input[name="map.parent.'+id+'"]', dashboard_widget['content_body']).val(),
							mapid = +$('input[name="mapid.'+id+'"]', dashboard_widget['content_body']).val(),
							order = $('input[name="map.parent.'+id+'"]', dashboard_widget['content_body']).closest('li')
										.prevAll().length+1;

						dashboard_widget['fields'][$(this).attr('name')] = $(this).val();
						dashboard_widget['fields']['map.parent.'+id] = parent || 0;
						dashboard_widget['fields']['map.order.'+id] = order;

						if (mapid) {
							dashboard_widget['fields']['mapid.'+id] = mapid;
						}
					}
				});
			};

			var switchToNavigationMode = function() {
				drawTree();
				parseProblems();
			};

			var switchToEditMode = function() {
				var dashboard_widget = getWidgetData();

				if (!dashboard_widget) {
					return false;
				}

				// Before switch to edit mode, stop refreshing.
				if (typeof dashboard_widget['rf_timeoutid'] !== 'undefined') {
					clearTimeout(dashboard_widget['rf_timeoutid']);
					delete dashboard_widget['rf_timeoutid'];
				}

				drawTree();
				makeSortable();
			};

			var methods = {
				// beforeConfigLoad trigger method
				beforeConfigLoad: function() {
					return this.each(function() {
						updateWidgetFields();
					});
				},
				// beforeDashboardSave trigger method
				beforeDashboardSave: function() {
					return this.each(function() {
						updateWidgetFields();
					});
				},
				// afterDashboardSave trigger method
				afterDashboardSave: function() {
					return this.each(function() {
						switchToNavigationMode();
					});
				},
				// onEditStart trigger method
				onEditStart: function() {
					return this.each(function() {
						switchToEditMode();
					});
				},
				// onEditStop trigger method
				onEditStop: function() {
					return this.each(function() {
						switchToNavigationMode();
					});
				},
				// initialization of widget
				init: function(options) {
					options = $.extend({}, options);

					return this.each(function() {
						$this.data('widgetData', {
							widgetid: options.widgetId,
							uniqueid: options.uniqueid,
							severity_levels: options.severity_levels || [],
							problems: options.problems || [],
							max_depth: options.max_depth || 10,
							lastId: 0
						});
						var triggers = ['onEditStart', 'onEditStop', 'beforeDashboardSave', 'afterDashboardSave',
							'beforeConfigLoad'];

						$.each(triggers, function(index, trigger) {
							$(".dashbrd-grid-widget-container").dashboardGrid("addAction", trigger,
								'zbx_widget_navtree_trigger',
								{
									'parameters': [trigger],
									'grid': {'widget': String(options.uniqueid)},
									'trigger_name': 'maptree_' + options.uniqueid
								}
							);
						});

						if (isEditMode()) {
							switchToEditMode();
						}
						else {
							switchToNavigationMode();
						}
					});
				}
			};

			if (methods[input]) {
				return methods[input].apply(this, Array.prototype.slice.call(arguments, 1));
			} else if (typeof input === 'object') {
				return methods.init.apply(this, arguments);
			} else {
				return null;
			}
		}
	}
});
