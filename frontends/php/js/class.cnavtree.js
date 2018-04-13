/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
var old_addPopupValues = null;

if (typeof addPopupValues === 'undefined') {
	var addPopupValues = null;
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

				if (scrolled !== false && $.ui.ddmanager && !o.dropBehaviour) {
					$.ui.ddmanager.prepareOffsets(this, event);
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

			// Interconnect with droppables.
			if ($.ui.ddmanager) {
				$.ui.ddmanager.drag(this, event);
			}

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
				// If we are using droppables, inform the manager about the drop.
				if ($.ui.ddmanager && !this.options.dropBehaviour) {
					$.ui.ddmanager.drop(this, event);

					var parent_id = this.placeholder.parent().closest('.tree-item').data('id'),
						item_id = $(this.currentItem[0]).data('id');

					$('[name="map.parent.' + item_id + '"]').val(parent_id);
				}

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
			var getNextId = function($obj) {
				var widget_data = $obj.data('widgetData');

				widget_data.lastId++;

				while ($('[name="map.name.' + widget_data.lastId + '"]').length) {
					widget_data.lastId++;
				}

				return widget_data.lastId;
			};

			var makeSortable = function($obj) {
				var widget_data = $obj.data('widgetData');

				$('.root-item>.tree-list')
					.sortable_tree({
						max_depth: widget_data['max_depth'],
						stop: function(event, ui) {
							setTreeHandlers($obj);
						}
					})
					.disableSelection();
			};

			/*
			 * Find and fix Circular Dependencies in parent - child (id) relations.
			 * Once the circular dependency is found, an item parent is set to be 0.
			 *
			 * @param {array} tree_items - array of tree items.
			 */
			var fixCircularDependencies = function($obj, tree_items) {
				var tree_items = tree_items || [],
					item_to_test,
					parents;

				$.each(tree_items, function(i, item) {
					if (item['parent'] != 0) {
						item_to_test = item;

						while (item_to_test['parent'] != 0) {
							if (item_to_test['parent'] == item['id']) {
								tree_items[i]['parent'] = 0;
								break;
							}

							parents = tree_items.filter(function(item) {
								return item['id'] == item_to_test['parent'];
							});

							if (parents.length) {
								item_to_test = parents[0];
							}
							else {
								break;
							}
						}
					}
				});

				return tree_items;
			};

			var drawTree = function($obj, isEditMode) {
				var root = createTreeBranch($obj, 'root', null),
					widget_data = $obj.data('widgetData'),
					prefix = widget_data['uniqueid'] + '_',
					tree_items = getTreeWidgetItems($obj),
					tree_items = fixCircularDependencies($obj, tree_items),
					tree = buildTree($obj, tree_items, 0);

				$('.root', $obj).remove();
				$('.tree', $obj).append(root);

				if (isEditMode) {
					var edit_mode_tree = createTreeItem($obj, {name: t('root'), id: 0}, 0, false, true);

					root.appendChild(edit_mode_tree);

					if (tree.length) {
						var new_class = edit_mode_tree.getAttribute('class').replace('closed', 'opened');
						edit_mode_tree.setAttribute('class', new_class);
					}

					root = document.getElementById(prefix + 'children-of-0');
				}

				$.each(tree, function(i, item) {
					if (typeof item === 'object') {
						root.appendChild(createTreeItem($obj, item, 1, true, isEditMode));
					}
				});

				setTreeHandlers($obj);
			};

			var parseProblems = function($obj) {
				var widget_data = $obj.data('widgetData'),
					empty_tmpl = {};

				if (typeof widget_data.severity_levels === 'undefined') {
					return false;
				}

				$.each(widget_data.severity_levels, function(sev, conf) {
					empty_tmpl[sev] = 0;
				});

				$.each(widget_data.problems, function(itemid, problems) {
					problems = problems ? problems : empty_tmpl;

					$.each(problems, function(sev, numb) {
						if (numb) {
							$('.tree-item[data-id=' + itemid + ']').attr('data-problems' + sev, numb);
						}
					});
				});

				$.each(widget_data.severity_levels, function(sev, conf) {
					$('[data-problems' + sev + ']', $obj).each(function() {
						var obj = $(this);

						$('>.tree-row>.problems', this).append($('<span/>', {
								'style': 'background: #' + conf['color'],
								'class': 'problems-per-item',
								'title': conf['name']
							})
							.html(obj.attr('data-problems' + sev))
						);
					});
				});
			};

			var createTreeBranch = function($obj, className, parentId) {
				var className = className || '',
					widget_data = $obj.data('widgetData'),
					prefix = widget_data['uniqueid'] + '_',
					ul = document.createElement('UL');

				if (parentId !== null) {
					ul.setAttribute('id', prefix + 'children-of-' + parentId);
				}

				className += ' tree-list';
				ul.setAttribute('class', className);

				return ul;
			};

			/*
			 * Dialog to create new or edit existing Tree item.
			 *
			 * @param {numeric} id - widget field ID or 0 when creating new item.
			 * @param {numeric} parent - ID of parent item under which new item is created.
			 * @param {numeric} depth - a depth of parent item under which new item is created.
			 * @param {object}  trigger_elmnt - UI element clicked to open dialog.
			 */
			var itemEditDialog = function($obj, id, parent, depth, trigger_elmnt) {
				var url = new Curl('zabbix.php'),
					item_edit = !!id,
					ajax_data = {
						map_name: '',
						map_mapid: 0,
						depth: depth || 1,
						map_id: id
					};

				if (id) {
					ajax_data['map_name'] = $('[name="map.name.' + id + '"]', $obj).val();
					ajax_data['map_mapid'] = $('[name="mapid.' + id + '"]', $obj).val();
				}
				else {
					ajax_data['map_id'] = getNextId($obj);
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
							'title': t('Edit tree element'),
							'content': resp.body,
							'buttons': [
								{
									'title': item_edit ? t('Apply') : t('Add'),
									'class': 'dialogue-widget-save',
									'action': function() {
										var form = $('#widget_dialogue_form'),
											url = new Curl('zabbix.php'),
											ajax_data = {
												add_submaps: $('[name="add_submaps"]', form).is(':checked') ? 1 : 0,
												map_name: $('[name="map.name.' + id + '"]', form).val(),
												map_mapid: +$('[name="linked_map_id"]', form).val(),
												depth: depth || 1,
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
													if ($('[name="map.name.' + id + '"]', $obj).length) {
														$('[name="map.name.' + id + '"]', $obj).val(resp['map_name']);
														$('[name="mapid.' + id + '"]', $obj).val(resp['map_mapid']);
														$('[data-id=' + id + '] > .tree-row > .content > .item-name',
																$obj
															)
															.empty()
															.attr('title', resp['map_name'])
															.append($('<span/>').text(resp['map_name']));
													}
													else {
														root = $('.tree-item[data-id=' + parent + ']>ul.tree-list',
																$obj
															).get(0),
														id = +resp['map_id'];
														new_item = {
															name: resp['map_name'],
															mapid: +resp['map_mapid'],
															id: +resp['map_id'],
															parent: parent
														};

														root.appendChild(createTreeItem($obj, new_item, 1, true, true));

														$(root).closest('.tree-item')
															.removeClass('closed')
															.addClass('opened is-parent');
													}

													if (typeof resp.hierarchy !== 'undefined') {
														var add_child_levels = function($obj, mapid, itemid) {
															if (typeof resp.hierarchy[mapid] !== 'undefined') {
																var root = $('.tree-item[data-id=' + itemid +
																		']>ul.tree-list', $obj
																	).get(0);

																$.each(resp.hierarchy[mapid], function(i, submapid) {
																	if (typeof resp.submaps[submapid] !== 'undefined') {
																		var submap_item = resp.submaps[submapid],
																			submap_itemid = getNextId($obj),
																			new_item = {
																				name: submap_item['name'],
																				mapid: +submap_item['sysmapid'],
																				id: submap_itemid,
																				parent: +itemid
																			};

																		root.appendChild(createTreeItem($obj, new_item,
																			1, true, true
																		));
																		add_child_levels($obj, +submapid,
																			submap_itemid
																		);
																	}
																});
															}
														};

														add_child_levels($obj, +resp['map_mapid'], id);

														$(root).closest('.tree-item')
															.addClass('is-parent opened')
															.removeClass('closed');
													}

													overlayDialogueDestroy('navtreeitem');
													setTreeHandlers($obj);
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
							],
							'dialogueid': 'navtreeitem'
						}, trigger_elmnt);
					}
				});
			};

			/**
			 * Create Tree LI item with everything needed inside it.
			 * Native javascript element building is used to improve performance.
			 *
			 * @param {object}  item
			 * @param {numeric} depth
			 * @param {bool}    editable     Eeither item in edit-mode will be editable. Root item is not editable.
			 * @param {booln}   isEditMode   Indicates either dashboard is in edit mode.
			 *
			 * @returns {object}
			 */
			var createTreeItem = function($obj, item, depth, editable, isEditMode) {
				var widget_data = $obj.data('widgetData'),
					prefix = widget_data['uniqueid'] + '_',
					ul = createTreeBranch($obj, null, item.id),
					item_clases = 'tree-item';

				if (!editable || widget_data['navtree_items_opened'].indexOf(item.id.toString()) !== -1) {
					item_clases += ' opened';
				}
				else {
					item_clases += ' closed';
				}

				if (!editable) {
					item_clases += ' root-item';
				}

				if (isEditMode && item.mapid == 0) {
					item_clases += ' no-map';
				}

				if (typeof item.children !== 'undefined' && widget_data.max_depth > depth) {
					var child_items_visible = 0;

					$.each(item.children, function(i, item) {
						if (typeof item === 'object') {
							ul.appendChild(createTreeItem($obj, item, depth + 1, true, isEditMode));

							if (item.id > widget_data.lastId) {
								widget_data.lastId = item.id;
							}

							if (item.item_visible === true) {
								child_items_visible++;
							}
						}
					});

					if (item.children.length && child_items_visible > 0) {
						item_clases += ' is-parent';
					}
				}

				if (item.item_active === false && !isEditMode && item.mapid > 0) {
					item_clases += ' inaccessible';
				}

				if (!isEditMode && typeof item.mapid === 'number' && item.mapid > 0 && item.item_active === true) {
					var	link = document.createElement('A');

					link.setAttribute('data-mapid', item.mapid);
					link.setAttribute('href', '#');
					link.addEventListener('click', function(event) {
						var data_to_share = {mapid: $(this).data('mapid')},
							itemid = $(this).closest('.tree-item').data('id'),
							step_in_path = $(this).closest('.tree-item'),
							widget = getWidgetData($obj);

						if ($('.dashbrd-grid-widget-container').dashboardGrid('widgetDataShare', widget,
								'selected_mapid', data_to_share)
						) {
							$('.selected', $obj).removeClass('selected');
							while ($(step_in_path).length) {
								$(step_in_path).addClass('selected');
								step_in_path = $(step_in_path).parent().closest('.tree-item');
							}
							$(this).closest('.tree-item').addClass('selected');
						}

						event.preventDefault();
						updateUserProfile('web.dashbrd.navtree.item.selected', itemid, [widget['widgetid']]);
					});
				}
				else {
					var	link = document.createElement('SPAN');
				}

				link.setAttribute('class', 'item-name');
				link.setAttribute('title', item.name);
				link.innerText = item.name;

				var li_item = document.createElement('LI');

				li_item.setAttribute('class', item_clases);
				li_item.setAttribute('data-id', item.id);
				li_item.setAttribute('id', prefix + 'tree-item-' + item.id);

				if (item.mapid) {
					li_item.setAttribute('data-mapid', item.mapid);
				}

				if (item.item_visible === false) {
					li_item.style.display = 'none';
				}

				var tree_row = document.createElement('DIV');

				tree_row.setAttribute('class', 'tree-row');
				li_item.appendChild(tree_row);

				if (isEditMode) {
					var tools = document.createElement('DIV');
					tools.setAttribute('class', 'tools');
					tree_row.appendChild(tools);
				}
				else {
					var problems = document.createElement('DIV');
					problems.setAttribute('class', 'problems');
					tree_row.appendChild(problems);
				}

				var content = document.createElement('DIV');

				content.setAttribute('class', 'content');
				tree_row.appendChild(content);

				var margin_lvl = document.createElement('DIV');

				margin_lvl.setAttribute('class', 'margin-lvl');
				content.appendChild(margin_lvl);

				if (isEditMode) {
					var btn1 = document.createElement('INPUT');

					btn1.setAttribute('type', 'button');
					btn1.setAttribute('data-id', item.id);
					btn1.setAttribute('class', 'add-child-btn');
					btn1.setAttribute('title', t('Add child element'));
					btn1.addEventListener('click', function(event) {
						var parentId = $(this).data('id'),
							widget_data = $obj.data('widgetData'),
							depth = $(this).closest('.tree-list').attr('data-depth'),
							branch = $('.tree-item[data-id=' + parentId + ']>ul', $obj);

						if (typeof depth === 'undefined') {
							depth = 0;
						}

						if (widget_data.max_depth > +depth) {
							itemEditDialog($obj, 0, parentId, +depth + 1, event.target);
						}
					});
					tools.appendChild(btn1);

					var btn2 = document.createElement('INPUT');

					btn2.setAttribute('type', 'button');
					btn2.setAttribute('data-id', item.id);
					btn2.setAttribute('class', 'import-items-btn');
					btn2.setAttribute('title', t('Add multiple maps'));
					btn2.addEventListener('click', function(event) {
						var id = $(this).data('id');

						if (typeof addPopupValues === 'function') {
							old_addPopupValues = addPopupValues;
						}

						addPopupValues = function(data) {
							var root = $('.tree-item[data-id=' + id + ']>ul.tree-list', $obj).get(0),
								new_item;

							$.each(data.values, function() {
								new_item = {
									name: this['name'],
									mapid: +this['sysmapid'],
									id: getNextId($obj),
									parent: id
								};

								root.appendChild(createTreeItem($obj, new_item, 1, true, isEditMode));
							});

							$(root)
								.closest('.tree-item')
								.removeClass('closed')
								.addClass('opened');

							setTreeHandlers($obj);

							if (typeof old_addPopupValues === 'function') {
								addPopupValues = old_addPopupValues;
								old_addPopupValues = null;
							}
						};

						return PopUp('popup.generic', {
							srctbl: 'sysmaps',
							srcfld1: 'sysmapid',
							srcfld2: 'name',
							multiselect: '1'
						}, null, event.target);
						});
					tools.appendChild(btn2);

					if (editable) {
						var btn3 = document.createElement('INPUT');

						btn3.setAttribute('type', 'button');
						btn3.setAttribute('data-id', item.id);
						btn3.setAttribute('class', 'edit-item-btn');
						btn3.setAttribute('title', t('Edit'));
						btn3.addEventListener('click', function(event) {
							var id = $(this).data('id'),
								parent = +$('input[name="map.parent.' + id + '"]', $obj).val(),
								depth = +$(this).closest('[data-depth]').attr('data-depth');

							itemEditDialog($obj, id, parent, depth, event.target);
						});
						tools.appendChild(btn3);

						var btn4 = document.createElement('BUTTON');

						btn4.setAttribute('type', 'button');
						btn4.setAttribute('data-id', item.id);
						btn4.setAttribute('class', 'remove-btn');
						btn4.setAttribute('title', t('Remove'));
						btn4.addEventListener('click', function() {
							removeItem($obj, [$(this).data('id')]);
						});
						tools.appendChild(btn4);
					}
				}

				if (isEditMode && editable) {
					var drag = document.createElement('DIV');

					drag.setAttribute('class', 'drag-icon');
					content.appendChild(drag);
				}

				var arrow = document.createElement('DIV');

				arrow.setAttribute('class', 'arrow');
				content.appendChild(arrow);

				if (editable) {
					var arrow_btn = document.createElement('BUTTON'),
						arrow_span = document.createElement('SPAN');

					arrow_btn.setAttribute('type', 'button');
					arrow_btn.setAttribute('class', 'treeview');
					arrow_span.setAttribute('class',
						(item_clases.indexOf('opened') !== -1) ? 'arrow-right' : 'arrow-down'
					);
					arrow_btn.appendChild(arrow_span);
					arrow.appendChild(arrow_btn);
					arrow_btn.addEventListener('click', function(event) {
						var widget_data = getWidgetData($obj),
							widget_options = $obj.data('widgetData'),
							branch = $(this).closest('[data-id]'),
							button = $(this),
							closed_state = '1';

						if (branch.hasClass('opened')) {
							$('span', button)
								.addClass('arrow-right')
								.removeClass('arrow-down');

							branch.removeClass('opened').addClass('closed');
						}
						else {prefix
							$('span', button)
								.addClass('arrow-down')
								.removeClass('arrow-right');

							branch.removeClass('closed').addClass('opened');
							closed_state = '0';
						}

						if (widget_data['widgetid'].length) {
							updateUserProfile(
								'web.dashbrd.navtree-' + branch.data('id') + '.toggle',
								closed_state, [widget_data['widgetid']]
							);

							var index = widget_options['navtree_items_opened'].indexOf(branch.data('id').toString());
							if (index > -1) {
								if (closed_state === '1') {
									widget_options['navtree_items_opened'].splice(index, 1);
								}
								else {
									widget_options['navtree_items_opened'].push(branch.data('id').toString());
								}
							}
							else if (closed_state === '0' && index == -1) {
								widget_options['navtree_items_opened'].push(branch.data('id').toString());
							}
						}
					});
				}

				content.appendChild(link);
				li_item.appendChild(ul);

				if (isEditMode && editable) {
					var name_fld = document.createElement('INPUT');
					name_fld.setAttribute('type', 'hidden');
					name_fld.setAttribute('name', 'map.name.' + item.id);
					name_fld.setAttribute('id', prefix + 'map.name.' + item.id);
					name_fld.value = item.name;
					li_item.appendChild(name_fld);

					var parent_fld = document.createElement('INPUT');
					parent_fld.setAttribute('type', 'hidden');
					parent_fld.setAttribute('name', 'map.parent.' + item.id);
					parent_fld.setAttribute('id', prefix + 'map.parent.' + item.id);
					parent_fld.value = item.parent || 0;
					li_item.appendChild(parent_fld);

					var mapid_fld = document.createElement('INPUT');
					mapid_fld.setAttribute('type', 'hidden');
					mapid_fld.setAttribute('name', 'mapid.' + item.id);
					mapid_fld.setAttribute('id', prefix + 'mapid.' + item.id);
					mapid_fld.value = typeof item.mapid === 'number' ? item.mapid : 0;
					li_item.appendChild(mapid_fld);
				}

				return li_item;
			};

			var setTreeHandlers = function($obj) {
				var widget_data = $obj.data('widgetData'),
					tree_list_depth;

				// Add .is-parent class for branches with sub-items.
				$('.tree-list', $obj).not('.ui-sortable, .root').each(function() {
					if ($('>li', this).not('.inaccessible').length) {
						$(this).closest('.tree-item').addClass('is-parent');
					}
					else {
						$(this).closest('.tree-item').removeClass('is-parent');
					}
				});

				// Set [data-depth] for list and each sublist.
				$('.tree-list', $obj).not('.root').each(function() {
					tree_list_depth = $(this).parents('.tree-list').not('.root').size() + 1;
					$(this).attr('data-depth', tree_list_depth);
				}).promise().done(function() {
					// Show/hide 'add new items' buttons.
					$('.tree-list', $obj).filter(function() {
						return +$(this).attr('data-depth') >= widget_data.max_depth;
					}).each(function() {
						$('.import-items-btn', $(this)).css('visibility', 'hidden');
						$('.add-child-btn', $(this)).css('visibility', 'hidden');
					});

					// Show/hide buttons in deepest levels.
					$('.tree-list', $obj).filter(function() {
						return widget_data.max_depth > +$(this).attr('data-depth');
					}).each(function() {
						$('>.tree-item>.tree-row>.tools>.import-items-btn', $(this)).css('visibility', 'visible');
						$('>.tree-item>.tree-row>.tools>.add-child-btn', $(this)).css('visibility', 'visible');
					});
				});

				// Change arrow style.
				$('.is-parent', $obj).each(function() {
					var arrow = $('> .tree-row > .content > .arrow > .treeview > span', $(this));

					if ($(this).hasClass('opened')) {
						arrow.removeClass('arrow-right').addClass('arrow-down');
					}
					else {
						$(arrow).removeClass('arrow-down a1').addClass('arrow-right');
					}
				});
			};

			var getWidgetData = function($obj) {
				var widget_data = $obj.data('widgetData'),
					response = $(".dashbrd-grid-widget-container")
						.dashboardGrid('getWidgetsBy', 'uniqueid', widget_data['uniqueid']);

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
			var getTreeWidgetItems = function($obj) {
				var widget_data = getWidgetData($obj),
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

						if (typeof widget_data['fields']['map.parent.' + item.id] !== 'undefined') {
							item.parent = +widget_data['fields']['map.parent.' + item.id];
						}
						if (typeof widget_data['fields']['mapid.' + item.id] !== 'undefined') {
							item.mapid = +widget_data['fields']['mapid.' + item.id];
						}
						if (typeof widget_data['fields']['map.order.' + item.id] !== 'undefined') {
							item.order = +widget_data['fields']['map.order.' + item.id];
						}

						tree_items.push(item);
					}
				});

				return tree_items;
			};

			// Create multi-level array that represents real child-parent dependencies in tree.
			var buildTree = function($obj, rows, parent_id) {
				var parent_id = (typeof parent_id === 'number') ? parent_id : 0,
					widget_data = $obj.data('widgetData'),
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
							var children = buildTree($obj, rows, item['id']),
								item_visible = true,
								item_active = true;

							if (children.length) {
								item['children'] = children;
							}

							if (widget_data.show_unavailable && item.mapid
									&& widget_data['maps_accessible'].indexOf(item.mapid) === -1) {
								item_active = false;
							}
							else {
								if (item.mapid) {
									item_active = widget_data['maps_accessible'].indexOf(item.mapid) !== -1;

									if (!widget_data.show_unavailable && !item_active) {
										item_visible = false;
									}
								}
								else {
									item_active = false;
								}
							}

							item['item_visible'] = item_visible;
							item['item_active'] = item_active;

							tree.push(item);
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
			var removeItem = function($obj, id) {
				var item = $('[data-id=' + id + ']', $obj),
					widget_data = $obj.data('widgetData'),
					prefix = widget_data['uniqueid'] + '_',
					parent = $('#' + prefix + 'map.parent.' + id, item).val();

				if ($('#' + prefix + 'children-of-' + parent + '>.tree-item', $obj).length == 1) {
					$('#' + prefix + 'tree-item-' + parent).removeClass('is-parent');
				}

				$(item).remove();
				setTreeHandlers($obj);
			};

			// Records data from DOM to dashboard widget[fields] array.
			var updateWidgetFields = function($obj) {
				var dashboard_widget = getWidgetData($obj),
					prefix = dashboard_widget['uniqueid'] + '_',
					widget_fields = {};

				if (!dashboard_widget || !isEditMode()) {
					return false;
				}

				for (var field_name in dashboard_widget['fields']) {
					if (!/map\.?(?:id|parent|name|order)\.\d+/.test(field_name)) {
						widget_fields[field_name] = dashboard_widget['fields'][field_name];
					}
				}

				$('input[name^="map.name."]', dashboard_widget['content_body']).each(function(index, field) {
					var id = +field.getAttribute('name').substr(9);

					if (id) {
						var parent = document.getElementById(prefix + 'map.parent.' + id).value,
							mapid = document.getElementById(prefix + 'mapid.' + id).value,
							sibl = document.getElementById(prefix + 'children-of-' + parent).childNodes,
							order = 0;

						while (typeof sibl[order] !== 'undefined' && +sibl[order].getAttribute('data-id') !== id) {
							order++;
						}

						widget_fields['map.name.' + id] = field.value;
						widget_fields['map.parent.' + id] = parent || 0;
						widget_fields['map.order.' + id] = order + 1;

						if (mapid) {
							widget_fields['mapid.' + id] = +mapid;
						}
					}
				});

				dashboard_widget['fields'] = widget_fields;
			};

			var openBranch = function($obj, id) {
				if (!$('.tree-item[data-id=' + id + ']').is(':visible')) {
					var selector = '> .tree-row > .content > .arrow > .treeview > span',
						branch_to_open = $('.tree-item[data-id=' + id + ']').closest('.tree-list').not('.root');

					while (branch_to_open.length) {
						branch_to_open.closest('.tree-item.is-parent')
							.removeClass('closed')
							.addClass('opened');

						$(selector, branch_to_open.closest('.tree-item.is-parent'))
							.removeClass('arrow-right')
							.addClass('arrow-down');

						branch_to_open = branch_to_open.closest('.tree-item.is-parent')
							.closest('.tree-list').not('.root');
					}
				}
			};

			var switchToNavigationMode = function($obj) {
				drawTree($obj, isEditMode());
				parseProblems($obj);
			};

			var switchToEditMode = function($obj) {
				var dashboard_widget = getWidgetData($obj);

				if (!dashboard_widget) {
					return false;
				}

				drawTree($obj, isEditMode());
				makeSortable($obj);
			};

			var markTreeItemSelected = function($obj, item_id, send_data) {
				var widget = getWidgetData($obj),
					prefix = widget['uniqueid'] + '_',
					selected_item = $('#' + prefix + 'tree-item-' + item_id),
					step_in_path = selected_item;

				/**
				 * If 'send_data' is set to be 'false', use an unexisting 'data_name', just to check if widget has
				 * linked widgets, but avoid real data sharing.
				 */
				if (item_id && $('.dashbrd-grid-widget-container').dashboardGrid('widgetDataShare', widget,
						send_data ? 'selected_mapid' : '', {mapid: $(selected_item).data('mapid')})) {
					$('.selected', $obj).removeClass('selected');

					while ($(step_in_path).length) {
						$(step_in_path).addClass('selected');
						step_in_path = $(step_in_path).parent().closest('.tree-item');
					}
				}
			};

			var methods = {
				// beforeConfigLoad trigger method
				beforeConfigLoad: function() {
					var $this = $(this);
					return this.each(function() {
						updateWidgetFields($this);
					});
				},

				// beforeDashboardSave trigger method
				beforeDashboardSave: function() {
					var $this = $(this);
					return this.each(function() {
						updateWidgetFields($this);
					});
				},

				// onEditStart trigger method
				onEditStart: function() {
					var $this = $(this);
					return this.each(function() {
						switchToEditMode($this);
					});
				},

				// onDashboardReady trigger method
				onDashboardReady: function() {
					var $this = $(this);

					return this.each(function() {
						var widget = getWidgetData($this),
							widget_data = $this.data('widgetData');

						if (!widget_data.navtree_item_selected
								|| !$('.tree-item[data-id=' + widget_data.navtree_item_selected + ']').is(':visible')) {
							widget_data.navtree_item_selected = $('.tree-item:visible', $this)
								.not('[data-mapid="0"]')
								.first()
								.data('id');
						}

						markTreeItemSelected($this, widget_data.navtree_item_selected, true);
					});
				},

				// initialization of widget
				init: function(options) {
					options = $.extend({}, options);

					return this.each(function() {
						var $this = $(this);

						$this.data('widgetData', {
							uniqueid: options.uniqueid,
							severity_levels: options.severity_levels || [],
							navtree_items_opened: options.navtree_items_opened.toString().split(',') || [],
							navtree_item_selected: +options.navtree_item_selected || null,
							maps_accessible: options.maps_accessible || [],
							show_unavailable: options.show_unavailable == 1 || false,
							problems: options.problems || [],
							max_depth: options.max_depth || 10,
							lastId: 0
						});

						var widget_data = getWidgetData($this),
							triggers = ['onEditStart', 'beforeDashboardSave','beforeConfigLoad', 'onDashboardReady'];

						$.each(triggers, function(index, trigger) {
							$(".dashbrd-grid-widget-container").dashboardGrid("addAction", trigger,
								'zbx_widget_navtree_trigger', options.uniqueid, {
									'parameters': [trigger],
									'grid': {'widget': 1},
									'priority': 5,
									'trigger_name': 'maptree_' + options.uniqueid
								}
							);
						});

						if (isEditMode()) {
							switchToEditMode($this);
						}
						else {
							$('.dashbrd-grid-widget-container').dashboardGrid('registerDataExchange', {
								uniqueid: widget_data['uniqueid'],
								data_name: 'current_sysmapid',
								callback: function(widget, data) {
									var item,
										selector = '',
										mapid_selector = '',
										prev_map_selector = '';

									mapid_selector = '.tree-item[data-mapid=' + data[0]['submapid'] + ']';

									if (data[0]['previous_maps'].length) {
										var prev_maps = data[0]['previous_maps'].split(','),
											prev_maps = prev_maps.length
												? prev_maps[prev_maps.length-1]
												: null;

										if (prev_maps) {
											var sc = '.selected',
												mapid = '[data-mapid=' + prev_maps + ']',
												prev_map_selectors = [
													Array(4).join(sc + ' ') + '.tree-item' + sc + mapid,
													Array(3).join(sc + ' ') + '.tree-item' + sc + mapid,
													Array(2).join(sc + ' ') + '.tree-item' + sc + mapid,
													sc + ' .tree-item' + sc + mapid,
													'.tree-item' + sc + mapid,
													'.tree-item' + mapid
												],
												indx = 0;

											while (!prev_map_selector.length
													&& typeof prev_map_selectors[indx] !== 'undefined') {
												if ($(prev_map_selectors[indx], $this).length) {
													prev_map_selector = prev_map_selectors[indx] + ' ';
												}
												indx++;
											}
										}
									}

									if (prev_map_selector.length && mapid_selector.length) {
										selector = prev_map_selector + ' > .tree-list > ' + mapid_selector;
										if (!data[0]['moving_upward']) {
											selector = selector + ':first';
										}
										item = $(selector.trim(selector), $this);
									}
									else {
										item = $('.selected', $this).closest(mapid_selector);
									}

									if (item.length) {
										item = item.first();

										var step_in_path = $(item).closest('.tree-item');

										$('.selected', $this).removeClass('selected');
										$(item).addClass('selected');

										while ($(step_in_path).length) {
											$(step_in_path).addClass('selected');
											step_in_path = $(step_in_path).parent().closest('.tree-item');
										}

										openBranch($this, $(item).data('id'));
										updateUserProfile('web.dashbrd.navtree.item.selected', $(item).data('id'),
											[widget['widgetid']]
										);
									}
								}
							});

							switchToNavigationMode($this);

							if (!options['initial_load']) {
								markTreeItemSelected($this, options.navtree_item_selected, false);
							}
						}
					});
				}
			};

			if (methods[input]) {
				return methods[input].apply(this, Array.prototype.slice.call(arguments, 1));
			}
			else if (typeof input === 'object') {
				return methods.init.apply(this, arguments);
			}
			else {
				return null;
			}
		};
	}
});
