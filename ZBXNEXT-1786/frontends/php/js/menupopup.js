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
 * @param string options['hostId']			host id
 * @param array  options['scripts']			host scripts
 * @param bool   options['hasScreens']		link to host screen page
 * @param bool   options['hasInventory']	link to host inventory page
 *
 * @return array
 */
function getMenuPopupHost(options) {
	var sections = [], gotos = [];

	// scripts
	if (typeof(options.scripts) !== 'undefined') {
		sections[sections.length] = {
			type: 'scripts',
			title: t('Scripts'),
			hostId: options.hostId,
			data: options.scripts
		};
	}

	// latest
	gotos[gotos.length] = {
		label: t('Latest data'),
		url: new Curl('latest.php?hostid=' + options.hostId).getUrl()
	};

	// inventories
	if (options.hasInventory) {
		gotos[gotos.length] = {
			label: t('Host inventories'),
			url: new Curl('hostinventories.php?hostid=' + options.hostId).getUrl()
		};
	}

	// screens
	if (options.hasScreens) {
		gotos[gotos.length] = {
			label: t('Host screens'),
			url: new Curl('host_screen.php?hostid=' + options.hostId).getUrl()
		};
	}

	sections[sections.length] = {
		type: 'links',
		title: t('Go to'),
		data: gotos
	};

	return sections;
}

/**
 * Get menu popup map section data.
 *
 * @param string options['hostId']					host id
 * @param array  options['scripts']					host scripts
 * @param object options['gotos']					links section
 * @param array  options['gotos']['screens']		link to host screen page with url parameters ("name" => "value")
 * @param array  options['gotos']['triggerStatus']	link to trigger status page with url parameters ("name" => "value")
 * @param array  options['gotos']['submap']			link to submap page with url parameters ("name" => "value")
 * @param array  options['gotos']['events']			link to events page with url parameters ("name" => "value")
 * @param array  options['urls']					local and global map urls
 *
 * @return array
 */
function getMenuPopupMap(options) {
	var sections = [];

	// scripts
	if (typeof(options.scripts) !== 'undefined') {
		sections[sections.length] = {
			type: 'scripts',
			title: t('Scripts'),
			hostId: options.hostId,
			data: options.scripts
		};
	}

	// gotos
	if (typeof(options.gotos) !== 'undefined') {
		var gotos = [];

		// screens
		if (typeof(options.gotos.screens) !== 'undefined') {
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
		if (typeof(options.gotos.triggerStatus) !== 'undefined') {
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
		if (typeof(options.gotos.submap) !== 'undefined') {
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
		if (typeof(options.gotos.events) !== 'undefined') {
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
	if (typeof(options.urls) !== 'undefined') {
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
 * @param string options['triggerId']		trigger id
 * @param object options['items']			link to trigger item history page with url parameters ("name" => "value")
 * @param object options['acknowledge']		link to acknowledge page with url parameters ("name" => "value")
 * @param int    options['eventTime']		event page url navigation time parameter
 * @param object options['configuration']	link to trigger configuration page
 * @param string options['url']				trigger url link
 *
 * @return array
 */
function getMenuPopupTrigger(options) {
	var sections = [], items = [];

	// events
	var url = new Curl('events.php?triggerid=' + options.triggerId);

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
		var url = new Curl('triggers.php?triggerid=' + options.triggerId +
			'&hostid=' + options.configuration.hostId + '&form=update&switch_node=' + options.configuration.switchNode);

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
 * @param string options['itemId']				item id
 * @param bool   options['hasLatestGraphs']		link to history page with showgraph action
 *
 * @return array
 */
function getMenuPopupHistory(options) {
	var items = [];

	// latest graphs
	if (typeof(options.hasLatestGraphs) !== 'undefined' && options.hasLatestGraphs) {
		items[items.length] = {
			label: t('Last hour graph'),
			url: new Curl('history.php?itemid=' + options.itemId + '&action=showgraph&period=3600').getUrl()
		};

		items[items.length] = {
			label: t('Last week graph'),
			url: new Curl('history.php?itemid=' + options.itemId + '&action=showgraph&period=604800').getUrl()
		};

		items[items.length] = {
			label: t('Last month graph'),
			url: new Curl('history.php?itemid=' + options.itemId + '&action=showgraph&period=2678400').getUrl()
		};
	}

	// latest values
	items[items.length] = {
		label: t('Latest values'),
		url: new Curl('history.php?itemid=' + options.itemId + '&action=showvalues&period=3600').getUrl()
	};

	return [{
		type: 'links',
		title: t('History'),
		data: items
	}];
}

jQuery(function($) {

	/**
	 * Menu popup.
	 *
	 * @param array  sections	menu sections usign structure "type", "title", "data"
	 * @param object event		menu popup call event
	 */
	$.fn.menuPopup = function(sections, event) {
		if (!event) {
			event = window.event;
		}

		var openner = $(this),
			id = openner.data('menuPopupId'),
			menuPopup = $('#' + id),
			mapContainer;

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
				of: openner,
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
					if (section.type === 'scripts') {
						createScripts(menuPopup, section);
					}
					else {
						createLinks(menuPopup, section);
					}
				});
			}

			// skip menu displaing with empty sections
			if (menuPopup.children().length == 0) {
				return;
			}

			// build jQuery Menu
			$('.menu', menuPopup).menu();

			// map
			if (openner.prop('tagName') == 'AREA') {
				$('.menuPopupContainer').remove();

				var iframe = $('#iframe'),
					mapOpenner = openner.parent().parent();

				mapContainer = jQuery('<div>', {
					'class': 'menuPopupContainer',
					css: {
						position: 'absolute',
						top: (iframe.length > 0)
							? event.clientY + document.body.scrollTop - iframe.position().top
							: event.clientY + document.body.scrollTop,
						left: event.clientX
					}
				})
				.append(menuPopup);

				mapOpenner.append(mapContainer);
			}

			// others
			else {
				openner.data('menuPopupId', id);

				$('body').append(menuPopup);
			}

			// hide all menu popups
			jQuery('.menuPopup').css('display', 'none');

			// display
			menuPopup
				.fadeIn(50)
				.data('isActive', false)
				.mouseenter(function() {
					menuPopup.data('isActive', true);

					clearTimeout(window.menuPopupTimeoutHandler);
				})
				.mouseleave(function() {
					menuPopup.data('isActive', false);

					closeInactiveMenuPopup(menuPopup, 1000);
				})
				.position({
					of: (openner.prop('tagName') == 'AREA') ? mapContainer : openner,
					my: 'left top',
					at: 'left bottom'
				});
		}

		closeInactiveMenuPopup(menuPopup, 2000);
	};

	/**
	 * Closing menu after delay with check is menu was reactived by mouse over action.
	 *
	 * @param object menuPopup
	 * @param int    delay
	 */
	function closeInactiveMenuPopup(menuPopup, delay) {
		clearTimeout(window.menuPopupTimeoutHandler);

		window.menuPopupTimeoutHandler = window.setTimeout(function() {
			if (!menuPopup.data('isActive')) {
				menuPopup.data('isActive', false);

				$('.menu', menuPopup).each(function() {
					$(this).menu('collapseAll', null, true);
				});

				menuPopup.fadeOut(0);
			}
		}, delay);
	}

	/**
	 * Script menu section.
	 * Menu tree with ability to run script in popup window.
	 *
	 * @param object menuPopup
	 * @param string section['title']	section title
	 * @param string section['hostId']	host id
	 * @param array  section['data']	screen items in structure ("name", "scriptId", "confirmation")
	 */
	function createScripts(menuPopup, section) {
		var menuData = {};

		for (var key in section.data) {
			var script = section.data[key];

			if (typeof(script.scriptId) !== 'undefined') {
				var items = splitPath(script.name),
					name = (items.length > 0) ? items.pop() : script.name;

				prepareTree(menuData, name, items, {
					hostId: section.hostId,
					scriptId: script.scriptId,
					confirmation: script.confirmation
				});
			}
		}

		if (objectSize(menuData) > 0) {
			var menu = createMenu(menuPopup, section.title);

			createTree(menu, menuData);

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
		}
	}

	/**
	 * Links menu section.
	 *
	 * @param object menuPopup
	 * @param string section['title']	section title
	 * @param array  section['data']	items as "label" => "url"
	 */
	function createLinks(menuPopup, section) {
		var menu = createMenu(menuPopup, section.title);

		$.each(section.data, function(i, item) {
			menu.append(createMenuItem(item.label, item.url));
		});
	}

	/**
	 * Recursive function to prepare menu tree data for createTree().
	 *
	 * @param array  menu		menu data
	 * @param string name		script name
	 * @param array  items		script path
	 * @param object params		script params ("hostId", "scriptId" and "confirmation" fields)
	 */
	function prepareTree(menu, name, items, params) {
		if (items.length > 0) {
			var item = items.shift();

			if (typeof(menu[item]) === 'undefined') {
				menu[item] = {items: {}};
			}

			prepareTree(menu[item].items, name, items, params);
		}
		else {
			menu[name] = {
				params: params,
				items: {}
			};
		}
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
	 * @param object obj	menu section
	 * @param object data	menu data with prescribed structure
	 */
	function createTree(obj, data) {
		$.each(data, function(name, item) {
			var subMenu = null;

			if (objectSize(item.items) > 0) {
				subMenu = $('<ul>');

				createTree(subMenu, item.items);
			}

			var li = createMenuItem(name, null, subMenu);

			if (objectSize(item.params) > 0) {
				$.each(item.params, function(key, value) {
					li.data(key, value);
				});
			}

			obj.append(li);
		});
	}

	/**
	 * Create new menu section with given title.
	 *
	 * @param object menuPopup
	 * @param string title
	 */
	function createMenu(menuPopup, title) {
		var menu = $('<ul>', {'class': 'menu'});

		menuPopup.append($('<div>', {'class': 'title', text: title}));
		menuPopup.append(menu);

		return menu;
	}

	/**
	 * Create new menu item.
	 *
	 * @param string label		name of item
	 * @param string link		url to create onClick action, by default is empty
	 * @param object subMenu	sub menu to build menu tree
	 */
	function createMenuItem(label, link, subMenu) {
		return $('<li>').append(
			$('<a>', {
				text: label,
				href: link
			}),
			(typeof(subMenu) !== 'undefined') ? subMenu : null
		);
	}
});
