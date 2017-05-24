jQuery(function($) {
	/**
	 * Create Navigation Tree element.
	 *
	 * @return object
	 */

	if (typeof($.fn.zbx_navtree) === 'undefined') {
		$.fn.zbx_navtree = function(input) {
			$this = $(this);
			var dropped_to = null;

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
			var buttonCssRemove = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL0Nyb3NzPC90aXRsZT4KICAgIDxkZXNjPkNyZWF0ZWQgd2l0aCBTa2V0Y2guPC9kZXNjPgogICAgPGRlZnM+PC9kZWZzPgogICAgPGcgaWQ9IjIweDIwIiBzdHJva2U9Im5vbmUiIHN0cm9rZS13aWR0aD0iMSIgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPgogICAgICAgIDxnIGlkPSIyMHgyMC9Dcm9zcyIgc3Ryb2tlPSIjMzY0MzREIj4KICAgICAgICAgICAgPGcgaWQ9IkNyb3NzIj4KICAgICAgICAgICAgICAgIDxnIGlkPSJJY29uIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgyLjAwMDAwMCwgMi4wMDAwMDApIj4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNLTEuMzg1NDk3ODMsOCBMMTcuMzg1NDk3OCw4IiBpZD0iTGluZS0yIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg4LjAwMDAwMCwgOC4wMDAwMDApIHJvdGF0ZSgtMzE1LjAwMDAwMCkgdHJhbnNsYXRlKC04LjAwMDAwMCwgLTguMDAwMDAwKSAiPjwvcGF0aD4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNOCwtMS4zODU0OTc4MyBMOCwxNy4zODU0OTc4IiBpZD0iTGluZS0xIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg4LjAwMDAwMCwgOC4wMDAwMDApIHJvdGF0ZSgtMzE1LjAwMDAwMCkgdHJhbnNsYXRlKC04LjAwMDAwMCwgLTguMDAwMDAwKSAiPjwvcGF0aD4KICAgICAgICAgICAgICAgIDwvZz4KICAgICAgICAgICAgPC9nPgogICAgICAgIDwvZz4KICAgIDwvZz4KPC9zdmc+')",
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

			var getDroppableOptions = function() {
				var widget_data = $this.data('widgetData');

				return {
					hoverClass: 'drop-hover',
					addClasses: false,
					accept: function() {
						return (widget_data.maxDepth >= +$('.tree-list', this).data('depth'));
					},
					addClasses: false,
					tolerance: 'pointer',
					drop: function(event, ui) {
						dropped_to = $(this);
					},
					greedy: true
				};
			};

			var drawTree = function() {
				var widget_data = $this.data('widgetData'),
						root = createTreeBranch('root'),
						tree_items = getTreeWidgetItems(),
						tree = buildTree(tree_items, 0);

				$('.root', $this).remove();
				$('.tree', $this).append(root);

				$.each(tree, function(i, item) {
					if (typeof item === 'object') {
						root.append(createTreeItem(item));
					}
				});

				setTreeHandlers();
			};

			var parseProblems = function() {
				var widget_data = $this.data('widgetData');
				if (isEditMode() || typeof widget_data.severityLevels === 'undefined') {
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

					$.each(widget_data.severityLevels, function(sev, conf) {
						var sum = 0;
						if (typeof obj.data('problems'+sev) !== 'undefined') {
							sum += +obj.data('problems'+sev);
						}
						$('[data-problems'+sev+']', obj).each(function() {
							sum += +$(this).data('problems'+sev);
						});
						if(sum){
							obj.attr('data-problems'+sev, sum);
						}
					});
				});

				$.each(widget_data.severityLevels, function(sev, conf) {
					$('[data-problems'+sev+']', $this).each(function() {
						var id = $(this).data('id'),
								obj = $(this),
								notif = $('<span></span>')
									.css('background', '#'+conf['color'])
									.html(obj.attr('data-problems'+sev))
									.addClass('problems-per-item')
									.attr('title', conf['name']);

						$('[data-id='+id+']>.row', $this).append(notif);
					});
				});
			};

			var createTreeBranch = function(className) {
				var className = className||null,
						ul = $('<ul></ul>').addClass('tree-list');

				if (className) {
					$(ul).addClass(className);
				}
				return ul;
			};

			var getCookieName = function(key) {
				var widget_data = $this.data('widgetData')
				return 'zbx_widget'+widget_data['widgetid']+'_'+key;
			}

			var storeUIState = function() {
				var opened = [];
				$('.opened.is-parent', $this).each(function() {
					opened.push($(this).data('id'));
				});

				if (opened.length) {
					cookie.create(getCookieName('opened_nodes'), opened.join(','));
				}
				else {
					cookie.erase(getCookieName('opened_nodes'));
				}
			};

			var createTreeItem = function(item, depth) {
				var widget_data = $this.data('widgetData'),
						depth = depth||1,
						link, span, li, ul, arrow;

				if (typeof item.mapid !== 'undefined' && item.mapid) {
					link = $('<a></a>').attr('href', '#');
				}
				else {
					link = $('<span></span>');
				}

				link.addClass('item-name').text(item.name);

				if (isEditMode()) {
					link.click(function(e) {
						e.preventDefault();
					});
				}
				else {
					link.click(function(e) {
						e.preventDefault();
						var data_to_share = {mapid: $(this).data('mapid')},
								widget = getWidgetData();

						$(".dashbrd-grid-widget-container").dashboardGrid("widgetDataShare", widget, data_to_share);
					});
				}

				span = $('<span></span>')
								.addClass('row')
								.append(link);

				li = $('<li></li>').addClass('tree-item').append(span);

				if (typeof item.mapid !== 'undefined' && item.mapid) {
					li.attr('data-mapid', item.mapid);
					link.attr('data-mapid', item.mapid);
				}

				ul = createTreeBranch(null);

				arrow = $('<span></span>')
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
					})
					.addClass(li.hasClass('opened') ? 'arrow-down' : 'arrow-right');

				if (typeof item.children !== 'undefined') {
					if (item.children.length) {
						li.addClass('is-parent');
					}

					if (widget_data.maxDepth > depth) {
						$.each(item.children, function(i, item) {
							if (typeof item === 'object') {
								ul.append(createTreeItem(item, depth+1));
								if (item.id > widget_data.lastId) {
									widget_data.lastId = item.id;
								}
							}
						});
					}
				}

				if (isEditMode()) {
					var tools = $('<div></div>').addClass('tools').insertAfter(link);

					$('<input>')
						.click(function() {
							var parentId = $(this).data('id'),
									widget_data = $this.data('widgetData'),
									depth = +$(this).closest('.tree-list').data('depth'),
									branch = $('.tree-item[data-id='+parentId+']>ul', $this),
									new_item = {
										name: t('New item'),
										parent: parentId,
										id: getNextId()
									};

							if (widget_data.maxDepth > depth) {
								if (!branch.size()) {
									branch = createTreeBranch(null);
									branch.appentTo($('.tree-item[data-id='+parentId+']', $this));
								}

								$('.tree-item[data-id='+parentId+']', $this).addClass('is-parent opened').removeClass('closed');
								branch.append(createTreeItem(new_item));
								$(".tree-item").droppable(getDroppableOptions());
								storeUIState();
							}
						})
						.addClass('add-child-btn')
						.attr({'type':'button', 'data-id':item.id})
						.css(buttonCssAdd)
						.appendTo(tools);

					$('<input>')
						.click(function() {
							var id = $(this).data('id');
							var url = new Curl('zabbix.php');

							var ajax_data = {
								map_name: $('[name="map.name.'+id+'"]', $this).val(),
								mapid: $('[name="mapid.'+id+'"]', $this).val(),
								map_id: id
							};

							url.setArgument('action', 'widget.navigationtree.edititem');

							jQuery.ajax({
								url: url.getUrl(),
								method: 'POST',
								data: ajax_data,
								dataType: 'json',
								success: function(resp) {
									overlayDialogue({
										'title': t('Edit Tree Widget item'),
										'content': resp.body,
										'buttons': [
											{
												'title': t('Update'),
												'class': 'dialogue-widget-save',
												'action': function() {
													var id = ajax_data.map_id,
															form = $('#widget_dialogue_form'),
															name = $('[name="map.name.'+id+'"]', form).val(),
															map = $('[name="linked_map_id"]', form).val();

													$('[name="map.name.'+id+'"]', $this).val(name);
													$('[name="mapid.'+id+'"]', $this).val(map);
													$('[data-id='+id+'] > .row > .item-name', $this).html(name);
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
						})
						.addClass('edit-item-btn')
						.attr({'type':'button', 'data-id':item.id})
						.css(buttonCssEdit)
						.appendTo(tools);

					$('<input>')
						.click(function(){
							removeItem([$(this).data('id')]);
						})
						.attr({'type':'button', 'data-id':item.id})
						.addClass('remove-item-btn')
						.css(buttonCssRemove)
						.appendTo(tools);

					$('<input>')
						.attr({'type':'hidden', 'name':'map.name.'+item.id})
						.val(item.name)
						.appendTo(li);

					$('<input>')
						.attr({'type':'hidden', 'name':'map.parent.'+item.id})
						.val(item.parent||0)
						.appendTo(li);

					$('<input>')
						.attr({'type':'hidden', 'name':'mapid.'+item.id})
						.val(typeof item.mapid !== 'undefined' ? item.mapid : '0')
						.appendTo(li);
				}

				arrow.insertBefore(link);
				li.attr({'data-id': item.id}).append(ul);
				return li;
			};

			// Returns a number of levels till the deepest branch (ul.tree-list).
			var levelsUnder = function(item) {
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
			};

			var setTreeHandlers = function() {
				var opened_nodes = cookie.read(getCookieName('opened_nodes')),
						widget_data = $this.data('widgetData');

				opened_nodes = opened_nodes ? opened_nodes.split(',') : [];

				// Add .is-parent class for branches with sub-items.
				$('.tree-list', $this).each(function() {
					if ($('.tree-item', $(this)).size()) {
						$(this).closest('.tree-item').addClass('is-parent');
					}
					else {
						$(this).closest('.tree-item').removeClass('is-parent');
					}
				});

				// Set which brances are opened and which ones are closed.
				$('.tree-item[data-id]').each(function() {
					var id = $(this).data('id');
					if (opened_nodes.indexOf(id.toString()) !== -1) {
						$(this).addClass('opened').removeClass('closed');
					}
					else {
						$(this).addClass('closed').removeClass('opened');
					}
				});

				// Set [data-depth] for list and each sublist.
				$('.tree-list').each(function() {
					$(this).attr('data-depth', $(this).parents('.tree-list').size()+1);
				});

				// Show/hide 'add new items' buttons.
				$('.tree-list').filter(function(){return +$(this).data('depth') >= widget_data.maxDepth;}).each(function() {
					$('.add-child-btn', $(this)).hide();
				});
				$('.tree-list').filter(function(){return widget_data.maxDepth > +$(this).data('depth');}).each(function() {
					$('>.tree-item>.row>.tools>.add-child-btn', $(this)).show();
				});

				// Change arrow style.
				$('.is-parent.opened .arrow-right', $this).removeClass('arrow-right').addClass('arrow-down');
				$('.is-parent.closed .arrow-down', $this).removeClass('arrow-down').addClass('arrow-right');
			};

			var addEditTools = function() {
				var root = $('ul.root', $this),
						widget_data = $this.data('widgetData'),
						toolBar;

				if (!isEditMode()) {
					return false;
				}

				removeEditTools();

				toolBar = $('<div></div>')
					.appendTo($this.closest('.navtree'))
					.addClass('buttons');

				$('<button></button>')
					.click(function() {
						var widget_data = $this.data('widgetData'),
								new_item = {
									name: t('New item'),
									id: getNextId(),
									parent: 0
								};

						root = (typeof root !== 'undefined') ? root : $('ul.root', $this);
						root.append(createTreeItem(new_item));
						$(".tree-item").droppable(getDroppableOptions());
					})
					.appendTo(toolBar)
					.html(t('Add'));

					$('.tree-list.root').sortable({
						items: '.tree-item',
						connectWith: '.tree-list',
						placeholder: 'sortable-item-placeholder',
						cursor: 'move',
						axis: 'y',
						opacity: .75,
						distance: 5,
						cursorAt: {bottom: 5},
						forcePlaceholderSize: true,
						forceHelperSize: true,
						tolerance: 'intersect',
						zIndex: 9999,
						update: function(event, ui) {
							$('.drop-hover').removeClass('drop-hover');
							setTreeHandlers();
						},
						stop: function(event, ui) {
							var dropped_to_root = false,
									new_parent_id = 0,
									overlaped_by = 0,
									item_height = 33,
									tolerance = 10;

							if (dropped_to) {
								// If  $(dropped_to) have [data-depth] > maxDepth, select it's parent.
								while ($('>ul:first', $(dropped_to)).size() &&
											+$('>ul:first', $(dropped_to)).data('depth') > widget_data.maxDepth) {
									dropped_to = $(dropped_to).closest('.tree-list').closest('.tree-item');
								}

								if (dropped_to.length) {
									// Cancel sorting if by dropping an item the maxDepth will be exceeded.
									var num_of_new_levels = $('ul:first', $(dropped_to)).data('depth') + levelsUnder(ui.item);
									if (num_of_new_levels > widget_data.maxDepth) {
										alert('Only '+widget_data.maxDepth+' levels are allowed.');

										$('.tree-list.root').sortable('cancel');
										dropped_to = null;
										setTreeHandlers();
										return;
									}

									dropped_to_root = ($(dropped_to).data('id') === 'undefined');
									overlaped_by = ui.offset.top - dropped_to.offset().top;

									// Add as child element. Otherwise rely on default sortablility.
									if (Math.abs(overlaped_by) <= item_height/2) {
										if (!dropped_to_root) {
											$('>ul:first', $(dropped_to)).append(ui.item);

											$(dropped_to)
												.addClass('is-parent opened')
												.removeClass('closed');
										}
									}
								}
							}

							new_parent_id = $('.tree-item[data-id='+ui.item.data('id')+']').closest('.tree-list')
										.closest('.tree-item').data('id');

							$('[name^="map.parent.'+ui.item.data('id')+'"]').val(new_parent_id||0);
							dropped_to = null;
							storeUIState();
							setTreeHandlers();
						}
					}).disableSelection();

					$(".tree-item").droppable(getDroppableOptions());
			};

			var removeEditTools = function() {
				$('.buttons', $this.closest('.navtree')).remove();
			};

			var getWidgetData = function() {
				var dashboard_data = $(".dashbrd-grid-widget-container").data('dashboardGrid'),
						widget_data = $this.data('widgetData'),
						response = null;

				if (typeof widget_data['widgetid'] !== 'undefined') {
					$.each(dashboard_data['widgets'], function() {
						if (this['widgetid'] == widget_data['widgetid']) {
							response = this;
							return false;
						}
					});
				}

				return response;
			};

			var isEditMode = function() {
				var dashboard_data = $(".dashbrd-grid-widget-container").data('dashboardGrid'),
						response = null;

				$.each(dashboard_data, function(i, db) {
					if (typeof db['edit_mode'] !== 'undefined') {
						response = db['edit_mode'];
					}
				});

				return response;
			};

			// Create an array of items from seperate widget fields.
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

			// Create an multi-level array that represents real child-parent dependencies in tree.
			var buildTree = function(rows, parent_id) {
				var parent_id = (typeof parent_id === 'number') ? parent_id : 0,
						widget_data = $this.data('widgetData'),
						rows = rows||[],
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

			var removeItem = function(id) {
					var parent_id;
					if (confirm('Remove item and all its children?')) {
						parent_id = $('input[name="map.parent.'+id+'"]', $this).val();
						if ($('.tree-item', $('[data-id='+parent_id+']', $this)).length == 1) {
							$('[data-id='+parent_id+']').removeClass('is-parent');
						}
						$('[data-id='+id+']').remove();
					}
			};

			// Records data from DOM to dashboard widget[fields] array.
			var updateWidgetFields = function() {
				var dashboard_widget = getWidgetData(),
						widget_data = $this.data('widgetData');

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
								parent = $('input[name="map.parent.'+id+'"]', dashboard_widget['content_body']).val(),
								mapid = $('input[name="mapid.'+id+'"]', dashboard_widget['content_body']).val(),
								order = $('input[name="map.parent.'+id+'"]', dashboard_widget['content_body']).closest('li')
												.prevAll().length+1;

						dashboard_widget['fields'][$(this).attr('name')] = $(this).val();
						dashboard_widget['fields']['map.parent.'+id] = parent||0;
						dashboard_widget['fields']['map.order.'+id] = order;

						if (mapid) {
							dashboard_widget['fields']['mapid.'+id] = mapid;
						}
					}
				});
			};

			var switchToNavigationMode = function() {
				var widget_data = $this.data('widgetData'),
						dashboard_widget = getWidgetData();

				if (!dashboard_widget) {
					return false;
				}

				removeEditTools();
				drawTree();
				parseProblems();

				$(".dashbrd-grid-widget-container").dashboardGrid('setWidgetRefreshRate', dashboard_widget['widgetid'],
						dashboard_widget['rf_rate']);
			};

			var switchToEditMode = function() {
				var widget_data = $this.data('widgetData'),
						dashboard_widget = getWidgetData();

				if (!dashboard_widget) {
					return false;
				}

				if (typeof dashboard_widget['rf_timeoutid'] !== 'undefined') {
					clearTimeout(dashboard_widget['rf_timeoutid']);
					delete dashboard_widget['rf_timeoutid'];
				}

				removeEditTools();
				drawTree();
				addEditTools();
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
							severityLevels: options.severityLevels||[],
							problems: options.problems||[],
							maxDepth: options.maxDepth||3,
							lastId: 0
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
