/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


/**
 * Get menu popup host section data.
 *
 * @param string options['hostid']			host id
 * @param array  options['scripts']			host scripts (optional)
 * @param string options[]['name']			script name
 * @param string options[]['scriptid']		script id
 * @param string options[]['confirmation']	confirmation text
 * @param bool   options['hasScreens']		link to host screen page
 * @param bool   options['hasGoTo']			"Go to" block in popup
 *
 * @return array
 */
function getMenuPopupHost(options) {
	var sections = [];

	// scripts
	if (typeof options.scripts !== 'undefined') {
		var menuTree = {};

		for (var key in options.scripts) {
			var script = options.scripts[key];

			if (typeof script.scriptid !== 'undefined') {
				var items = splitPath(script.name),
					name = (items.length > 0) ? items.pop() : script.name;

				appendTreeItem(menuTree, name, items, {
					hostId: options.hostid,
					scriptId: script.scriptid,
					confirmation: script.confirmation
				});
			}
		}

		sections[sections.length] = {
			type: 'scripts',
			title: t('Scripts'),
			data: menuTree
		};
	}

	// go to section
	if (options.hasGoTo) {
		var gotos = [];

		// latest
		gotos[gotos.length] = {
			label: t('Latest data'),
			url: new Curl('latest.php?hostid=' + options.hostid).getUrl()
		};

		// inventory
		gotos[gotos.length] = {
			label: t('Host inventory'),
			url: new Curl('hostinventories.php?hostid=' + options.hostid).getUrl()
		};

		// screens
		if (options.hasScreens) {
			gotos[gotos.length] = {
				label: t('Host screens'),
				url: new Curl('host_screen.php?hostid=' + options.hostid).getUrl()
			};
		}

		sections[sections.length] = {
			type: 'links',
			title: t('Go to'),
			data: gotos
		};
	}
	return sections;
}

/**
 * Get menu popup map section data.
 *
 * @param string options['hostid']					host id
 * @param array  options['scripts']					host scripts (optional)
 * @param string options[]['name']					script name
 * @param string options[]['scriptid']				script id
 * @param string options[]['confirmation']			confirmation text
 * @param object options['gotos']					links section (optional)
 * @param array  options['gotos']['inventory']		link to host inventory page
 * @param array  options['gotos']['screens']		link to host screen page with url parameters ("name" => "value")
 * @param array  options['gotos']['triggerStatus']	link to trigger status page with url parameters ("name" => "value")
 * @param array  options['gotos']['submap']			link to submap page with url parameters ("name" => "value")
 * @param array  options['gotos']['events']			link to events page with url parameters ("name" => "value")
 * @param array  options['urls']					local and global map urls (optional)
 * @param string options['url'][]['label']			url label
 * @param string options['url'][]['url']			url
 *
 * @return array
 */
function getMenuPopupMap(options) {
	var sections = [];

	// scripts
	if (typeof options.scripts !== 'undefined') {
		var menuTree = {};

		for (var key in options.scripts) {
			var script = options.scripts[key];

			if (typeof script.scriptid !== 'undefined') {
				var items = splitPath(script.name),
					name = (items.length > 0) ? items.pop() : script.name;

				appendTreeItem(menuTree, name, items, {
					hostId: options.hostid,
					scriptId: script.scriptid,
					confirmation: script.confirmation
				});
			}
		}

		sections[sections.length] = {
			type: 'scripts',
			title: t('Scripts'),
			data: menuTree
		};
	}

	/*
	 * Gotos section
	 */
	if (typeof options.gotos !== 'undefined') {
		var gotos = [];

		// inventory
		if (typeof options.gotos.inventory !== 'undefined') {
			var url = new Curl('hostinventories.php');

			jQuery.each(options.gotos.inventory, function(name, value) {
				url.setArgument(name, value);
			});

			gotos[gotos.length] = {
			label: t('Host inventory'),
				url: url.getUrl()
			};
		}

		// screens
		if (typeof options.gotos.screens !== 'undefined') {
			var url = new Curl('host_screen.php');

			jQuery.each(options.gotos.screens, function(name, value) {
				url.setArgument(name, value);
			});

			gotos[gotos.length] = {
				label: t('Host screens'),
				url: url.getUrl()
			};
		}

		// trigger status
		if (typeof options.gotos.triggerStatus !== 'undefined') {
			var url = new Curl('tr_status.php?filter_set=1');

			jQuery.each(options.gotos.triggerStatus, function(name, value) {
				url.setArgument(name, value);
			});

			gotos[gotos.length] = {
				label: t('Status of triggers'),
				url: url.getUrl()
			};
		}

		// submap
		if (typeof options.gotos.submap !== 'undefined') {
			var url = new Curl('maps.php');

			jQuery.each(options.gotos.submap, function(name, value) {
				url.setArgument(name, value);
			});

			gotos[gotos.length] = {
				label: t('Submap'),
				url: url.getUrl()
			};
		}

		// events
		if (typeof options.gotos.events !== 'undefined') {
			var url = new Curl('events.php?source=0');

			jQuery.each(options.gotos.events, function(name, value) {
				url.setArgument(name, value);
			});

			gotos[gotos.length] = {
				label: t('Latest events'),
				url: url.getUrl()
			};
		}

		sections[sections.length] = {
			type: 'links',
			title: t('Go to'),
			data: gotos
		};
	}

	// urls
	if (typeof options.urls !== 'undefined') {
		sections[sections.length] = {
			type: 'links',
			title: t('URLs'),
			data: options.urls
		};
	}

	return sections;
}

/**
 * Get menu popup trigger section data.
 *
 * @param string options['triggerid']				trigger id
 * @param object options['items']					link to trigger item history page (optional)
 * @param string options['items'][]['name']			item name
 * @param object options['items'][]['params']		item url parameters ("name" => "value")
 * @param object options['acknowledge']				link to acknowledge page (optional)
 * @param string options['acknowledge']['eventid']	event id
 * @param string options['acknowledge']['screenid']	screen id (optional)
 * @param string options['acknowledge']['backurl']	return url (optional)
 * @param int    options['eventTime']				event page url navigation time parameter (optional)
 * @param object options['configuration']			link to trigger configuration page (optional)
 * @param string options['url']						trigger url link (optional)
 *
 * @return array
 */
function getMenuPopupTrigger(options) {
	var sections = [], items = [];

	// events
	var url = new Curl('events.php?triggerid=' + options.triggerid);

	if (!empty(options.eventTime)) {
		url.setArgument('nav_time', options.eventTime);
	}

	items[items.length] = {
		label: t('Events'),
		url: url.getUrl()
	};

	// acknowledge
	if (!empty(options.acknowledge)) {
		var url = new Curl('acknow.php');

		jQuery.each(options.acknowledge, function(name, value) {
			url.setArgument(name, value);
		});

		items[items.length] = {
			label: t('Acknowledge'),
			url: url.getUrl()
		};
	}

	// configuration
	if (!empty(options.configuration)) {
		var url = new Curl('triggers.php?triggerid=' + options.triggerid +
			'&hostid=' + options.configuration.hostid + '&form=update&switch_node=' + options.configuration.switchNode);

		items[items.length] = {
			label: t('Configuration'),
			url: url.getUrl()
		};
	}

	// url
	if (!empty(options.url)) {
		items[items.length] = {
			label: t('URL'),
			url: options.url
		};
	}

	sections[sections.length] = {
		type: 'links',
		title: t('Trigger'),
		data: items
	};

	// items
	if (!empty(options.items)) {
		var items = [];

		jQuery.each(options.items, function(i, item) {
			var url = new Curl('history.php');

			jQuery.each(item.params, function(key, value) {
				url.setArgument(key, value);
			});

			items[items.length] = {
				label: item.name,
				url: url.getUrl()
			};
		});

		sections[sections.length] = {
			type: 'links',
			title: t('History'),
			data: items
		};
	}

	return sections;
}

/**
 * Get menu popup history section data.
 *
 * @param string options['itemid']				item id
 * @param bool   options['hasLatestGraphs']		link to history page with showgraph action (optional)
 *
 * @return array
 */
function getMenuPopupHistory(options) {
	var items = [];

	// latest graphs
	if (typeof options.hasLatestGraphs !== 'undefined' && options.hasLatestGraphs) {
		items[items.length] = {
			label: t('Last hour graph'),
			url: new Curl('history.php?itemid=' + options.itemid + '&action=showgraph&period=3600').getUrl()
		};

		items[items.length] = {
			label: t('Last week graph'),
			url: new Curl('history.php?itemid=' + options.itemid + '&action=showgraph&period=604800').getUrl()
		};

		items[items.length] = {
			label: t('Last month graph'),
			url: new Curl('history.php?itemid=' + options.itemid + '&action=showgraph&period=2678400').getUrl()
		};
	}

	// latest values
	items[items.length] = {
		label: t('Latest values'),
		url: new Curl('history.php?itemid=' + options.itemid + '&action=showvalues&period=3600').getUrl()
	};

	return [{
		type: 'links',
		title: t('History'),
		data: items
	}];
}

/**
 * Get menu popup refresh section data.
 *
 * @param string options['widgetName']
 * @param string options['currentRate']
 * @param bool   options['multiplier']
 * @param array  options['params']
 *
 * @return array
 */
function getMenuPopupRefresh(options) {
	var intervals = options.multiplier
		? {
			'x0.25': 'x0.25',
			'x0.5': 'x0.5',
			'x1': 'x1',
			'x1.5': 'x1.5',
			'x2': 'x2',
			'x3': 'x3',
			'x4': 'x4',
			'x5': 'x5'
		}
		: {
			10: t('10 seconds'),
			30: t('30 seconds'),
			60: t('1 minute'),
			120: t('2 minutes'),
			600: t('10 minutes'),
			900: t('15 minutes')
		};

	return [{
		type: 'refresh',
		title: options.multiplier ? t('Refresh time multiplier') : t('Refresh time'),
		data: {
			widgetName: options['widgetName'],
			currentRate: options['currentRate'],
			params: options['params'],
			intervals: intervals
		}
	}];
}

/**
 * Get menu popup favourite graphs section data.
 *
 * @param array  options['data']
 * @param string options['data'][n]['id']
 * @param string options['data'][n]['name']
 * @param string options['data'][n]['type']
 *
 * @return array
 */
function getMenuPopupFavouriteGraphs(options) {
	return [{
		type: 'favourite',
		title: t('Favourite graphs'),
		data: options.data
	}];
}

/**
 * Get menu popup favourite maps section data.
 *
 * @param array  options['data']
 * @param string options['data'][n]['id']
 * @param string options['data'][n]['name']
 *
 * @return array
 */
function getMenuPopupFavouriteMaps(options) {
	return [{
		type: 'favourite',
		title: t('Favourite maps'),
		data: options.data
	}];
}

/**
 * Get menu popup favourite screens section data.
 *
 * @param array  options['data']
 * @param string options['data'][n]['id']
 * @param string options['data'][n]['name']
 * @param string options['data'][n]['type']
 *
 * @return array
 */
function getMenuPopupFavouriteScreens(options) {
	return [{
		type: 'favourite',
		title: t('Favourite screens'),
		data: options.data
	}];
}

/**
 * Recursive function to prepare menu tree data for createMenuTree().
 *
 * @param array  tree		menu tree data, will be modified by reference
 * @param string name		script name
 * @param array  items		script path
 * @param object params		script params ("hostId", "scriptId" and "confirmation" fields)
 */
function appendTreeItem(tree, name, items, params) {
	if (items.length > 0) {
		var item = items.shift();

		if (typeof tree[item] === 'undefined') {
			tree[item] = {items: {}};
		}

		appendTreeItem(tree[item].items, name, items, params);
	}
	else {
		tree[name] = {
			params: params,
			items: {}
		};
	}
}

jQuery(function($) {

	/**
	 * Menu popup.
	 *
	 * @param array  sections			menu sections
	 * @param string sections['type']	section display type "script" or "links"
	 * @param string sections['data']	if section type is "script": menu tree getted from appendTreeItem()
	 *									if section type is "links": array with "name" => "url"
	 * @param object event				menu popup call event
	 */
	$.fn.menuPopup = function(sections, event) {
		if (!event) {
			event = window.event;
		}

		var opener = $(this),
			id = opener.data('menu-popup-id'),
			menuPopup = $('#' + id),
			mapContainer = null;

		if (menuPopup.length > 0) {
			var display = menuPopup.css('display');

			// hide all menu popups
			jQuery('.menuPopup').css('display', 'none');

			if (display == 'block') {
				menuPopup.fadeOut(0);
			}
			else {
				menuPopup.fadeIn(50);
			}

			menuPopup.position({
				of: event,
				my: 'left top',
				at: 'left bottom'
			});
		}
		else {
			id = new Date().getTime();

			menuPopup = $('<div>', {
				id: id,
				'class': 'menuPopup'
			});

			// create sections
			if (sections.length > 0) {
				$.each(sections, function(i, section) {
					// scripts
					if (section.type === 'scripts') {
						if (objectSize(section.data) > 0) {
							var menu = $('<ul>', {'class': 'menu'});

							createMenuTree(menu, section.data);

							// execute script
							$('li', menu).each(function() {
								var item = $(this);

								if (!empty(item.data('scriptId'))) {
									item.click(function(e) {
										menuPopup.fadeOut(50);

										executeScript(
											item.data('hostId'),
											item.data('scriptId'),
											item.data('confirmation')
										);

										cancelEvent(e);
									});
								}
								else {
									item.click(function(e) {
										cancelEvent(e);
									});
								}
							});

							menuPopup.append($('<div>', {'class': 'title', text: section.title}));
							menuPopup.append(menu);
						}
					}

					// refresh
					else if (section.type === 'refresh') {
						var menu = $('<ul>', {'class': 'menu'});

						$.each(section.data.intervals, function(value, label) {
							css = (value === section.data.currentRate) ? 'selected' : '';

							menu.append(createMenuItem(label, null, value, css, null, function() {
								sendAjaxData({
									data: jQuery.extend({}, section.data.params, {
										widgetName: section.data.widgetName,
										widgetRefreshRate: value
									}),
									dataType: 'script',
									success: function(js) { js }
								});

								var currentRate = $(this).data('value');

								$('li', menu).each(function() {
									var obj = $(this);

									if (obj.data('value') == currentRate) {
										obj.addClass('selected');
									}
									else {
										obj.removeClass('selected');
									}
								});

								menuPopup.fadeOut(100);
							}));
						});

						menuPopup.append($('<div>', {'class': 'title', text: section.title}));
						menuPopup.append(menu);
					}

					// favourite
					else if (section.type === 'favourite') {
						/*'javascript: PopUp(\'popup.php?srctbl=graphs&srcfld1=graphid&reference=graphid&monitored_hosts=1&multiselect=1\',800,450); void(0);',

						'javascript: PopUp(\'popup.php?srctbl=items&srcfld1=itemid&monitored_hosts=1&reference=itemid'.
							'&multiselect=1&numeric=1&templated=0&with_simple_graph_items=1\',800,450); void(0);',

						'javascript: PopUp(\'popup.php?srctbl=sysmaps&srcfld1=sysmapid&reference=sysmapid&multiselect=1\',800,450); void(0);',

						'javascript: PopUp(\'popup.php?srctbl=screens&srcfld1=screenid&reference=screenid&multiselect=1\', 800, 450); void(0);',

						'javascript: PopUp(\'popup.php?srctbl=slides&srcfld1=slideshowid&reference=slideshowid&multiselect=1\', 800, 450); void(0);'
						*/
					}

					// links
					else {
						var menu = $('<ul>', {'class': 'menu'});

						$.each(section.data, function(i, item) {
							menu.append(createMenuItem(item.label, item.url));
						});

						menuPopup.append($('<div>', {'class': 'title', text: section.title}));
						menuPopup.append(menu);
					}
				});
			}

			// skip displaying empty menu sections
			if (menuPopup.children().length == 0) {
				return;
			}

			// build jQuery Menu
			$('.menu', menuPopup).menu();

			// set menu popup for map area
			if (opener.prop('tagName') == 'AREA') {
				$('.menuPopupContainer').remove();

				mapContainer = jQuery('<div>', {
					'class': 'menuPopupContainer',
					css: {
						position: 'absolute',
						top: event.pageY,
						left: event.pageX
					}
				})
				.append(menuPopup);

				$('body').append(mapContainer);
			}
			// set menu popup for common html elements
			else {
				opener.data('menu-popup-id', id);

				$('body').append(menuPopup);
			}

			// hide all menu popups
			jQuery('.menuPopup').css('display', 'none');

			// display
			menuPopup
				.fadeIn(50)
				.data('is-active', false)
				.mouseenter(function() {
					menuPopup.data('is-active', true);

					clearTimeout(window.menuPopupTimeoutHandler);
				})
				.mouseleave(function() {
					menuPopup.data('is-active', false);

					closeInactiveMenuPopup(menuPopup, 1000);
				})
				.position({
					of: (opener.prop('tagName') == 'AREA') ? mapContainer : event,
					my: 'left top',
					at: 'left bottom'
				});
		}

		closeInactiveMenuPopup(menuPopup, 2000);
	};

	/**
	 * Closing menu after delay.
	 *
	 * @param object menuPopup
	 * @param int    delay
	 */
	function closeInactiveMenuPopup(menuPopup, delay) {
		clearTimeout(window.menuPopupTimeoutHandler);

		window.menuPopupTimeoutHandler = window.setTimeout(function() {
			if (!menuPopup.data('is-active')) {
				menuPopup.data('is-active', false);

				$('.menu', menuPopup).each(function() {
					$(this).menu('collapseAll', null, true);
				});

				menuPopup.fadeOut(0);
			}
		}, delay);
	}

	/**
	 * Create menu using structure:
	 *
	 * array(
	 * 	'a' => array(
	 * 		'items' => array(
	 * 			'a' => array(
	 * 				'items' => array(
	 * 					'a' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					),
	 * 					'b' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					)
	 * 				)
	 * 			),
	 * 			'b' => array(
	 * 				'items' => array(
	 * 					'a' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					),
	 * 					'b' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					)
	 * 				)
	 * 			)
	 * 		),
	 * 	'b' => array(
	 * 		'params' => array(),
	 * 		'items' => array()
	 * 	)
	 * )
	 *
	 * @param object ul		list object, will be modified by reference
	 * @param object items	tree data with prescribed structure
	 */
	function createMenuTree(ul, items) {
		$.each(items, function(name, item) {
			var innerUl = null;

			if (objectSize(item.items) > 0) {
				innerUl = $('<ul>');

				createMenuTree(innerUl, item.items);
			}

			var li = createMenuItem(name, null, null, null, innerUl);

			if (objectSize(item.params) > 0) {
				$.each(item.params, function(key, value) {
					li.data(key, value);
				});
			}

			ul.append(li);
		});
	}

	/**
	 * Create new menu item link.
	 *
	 * @param string label			name of item
	 * @param string url			url to create onClick action, by default is empty
	 * @param string value			item value
	 * @param string css			css class
	 * @param object subMenu		sub menu to build menu tree
	 * @param object clickCallback	item click javascript callback
	 *
	 * @return object				list item
	 */
	function createMenuItem(label, url, value, css, subMenu, clickCallback) {
		var item = $('<li>').append($('<a>', {html: label, href: url}));

		if (typeof value !== 'undefined') {
			item.data('value', value);
		}

		if (typeof css !== 'undefined') {
			item.addClass(css);
		}

		if (typeof clickCallback !== 'undefined') {
			item.click(clickCallback);
		}

		if (typeof subMenu !== 'undefined') {
			item.append(subMenu);
		}

		return item;
	}
});
