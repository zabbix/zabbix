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


jQuery(function($) {

	/**
	 * Menu popup.
	 *
	 * @param object options
	 * @param object event
	 */
	$.fn.menuPopup = function(options, event) {
		if (!event) {
			event = window.event;
		}

		var defaults = {
			labels: {
				'Acknowledge': t('Acknowledge'),
				'Cancel': t('Cancel'),
				'Configuration': t('Configuration'),
				'Events': t('Events'),
				'Execute': t('Execute'),
				'Execution confirmation': t('Execution confirmation'),
				'Go to': t('Go to'),
				'History': t('History'),
				'Host inventories': t('Host inventories'),
				'Host screens': t('Host screens'),
				'Latest data': t('Latest data'),
				'Latest events': t('Latest events'),
				'Latest values': t('Latest values'),
				'Last hour graph': t('Last hour graph'),
				'Last month graph': t('Last month graph'),
				'Last week graph': t('Last week graph'),
				'Scripts': t('Scripts'),
				'Status of triggers': t('Status of triggers'),
				'Submap': t('Submap'),
				'Trigger': t('Trigger'),
				'URL': t('URL'),
				'URLs': t('URLs')
			}
		};
		options = $.extend({}, defaults, options);

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
		}
		else {
			id = new Date().getTime();

			menuPopup = $('<div>', {
				id: id,
				'class': 'menuPopup'
			});

			// create sub menus
			createScripts(menuPopup, options);
			createGotos(menuPopup, options);
			createTrigger(menuPopup, options);
			createHistory(menuPopup, options);
			createUrls(menuPopup, options);

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
				})
				.mouseleave(function() {
					menuPopup.data('isActive', false);

					closeInactiveMenuPopup(menuPopup, 500);
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
		setTimeout(function() {
			if (!menuPopup.data('isActive')) {
				menuPopup.data('isActive', false);
				menuPopup.fadeOut(50);
			}
		}, delay);
	}

	/**
	 * Script menu section.
	 * Menu tree with ability to run script in popup window.
	 *
	 * @param object menuPopup
	 * @param object options['scripts']
	 */
	function createScripts(menuPopup, options) {
		if (typeof(options['scripts']) !== 'undefined' && objectSize(options['scripts']) > 0) {
			var menuData = {};

			for (var key in options['scripts']) {
				var script = options['scripts'][key];

				if (typeof(script.hostid) !== 'undefined') {
					var items = script.name.split(/\//g),
						name = items.pop();

					prepareTree(menuData, name, items, {
						scriptId: script.scriptid,
						hostId: script.hostid,
						confirmation: script.confirmation
					});
				}
			}

			if (objectSize(menuData) > 0) {
				var menu = createMenu(menuPopup, options.labels['Scripts']);

				createTree(menu, menuData);

				// execute script
				$('li', menu).each(function() {
					var item = $(this);

					if (!empty(item.data('scriptId'))) {
						item.click(function() {
							menuPopup.fadeOut(50);

							executeScript(
								item.data('hostId'),
								item.data('scriptId'),
								item.data('confirmation'),
								options.labels
							);
						});
					}
				});
			}
		}
	}

	/**
	 * Goto menu section.
	 *
	 * @param object options['goto']['params']	global params for every item
	 * @param object options['goto']['items']	array of items with structure "name" => "parameters"
	 */
	function createGotos(menuPopup, options) {
		if (typeof(options['goto']) !== 'undefined' && typeof(options['goto']['items']) !== 'undefined'
				&& objectSize(options['goto']['items']) > 0) {
			var menu = createMenu(menuPopup, options.labels['Go to']);

			// latest
			if (typeof(options['goto']['items']['latest']) !== 'undefined') {
				menu.append(createMenuItem(
					options.labels['Latest data'],
					new Curl('latest.php'
						+ getUrlParams(options['goto']['items']['latest'], options['goto']['params'])).getUrl()
				));
			}

			// screens
			if (typeof(options['goto']['items']['screens']) !== 'undefined') {
				menu.append(createMenuItem(
					options.labels['Host screens'],
					new Curl('host_screen.php'
						+ getUrlParams(options['goto']['items']['screens'], options['goto']['params'])).getUrl()
				));
			}

			// inventories
			if (typeof(options['goto']['items']['inventories']) !== 'undefined') {
				menu.append(createMenuItem(
					options.labels['Host inventories'],
					new Curl('hostinventories.php'
						+ getUrlParams(options['goto']['items']['inventories'], options['goto']['params'])).getUrl()
				));
			}

			// triggerStatus
			if (typeof(options['goto']['items']['triggerStatus']) !== 'undefined') {
				menu.append(createMenuItem(
					options.labels['Status of triggers'],
					new Curl('tr_status.php'
						+ getUrlParams(options['goto']['items']['triggerStatus'], options['goto']['params'])).getUrl()
				));
			}

			// map
			if (typeof(options['goto']['items']['map']) !== 'undefined') {
				menu.append(createMenuItem(
					options.labels['Submap'],
					new Curl('maps.php'
						+ getUrlParams(options['goto']['items']['map'], options['goto']['params'])).getUrl()
				));
			}

			// events
			if (typeof(options['goto']['items']['events']) !== 'undefined') {
				menu.append(createMenuItem(
					options.labels['Latest events'],
					new Curl('events.php'
						+ getUrlParams(options['goto']['items']['events'], options['goto']['params'])).getUrl()
				));
			}
		}
	}

	/**
	 * Trigger menu section.
	 *
	 * @param string options['trigger']['triggerid']		global parameter trigger id
	 * @param object options['trigger']['events']			url parameters for event page
	 * @param object options['trigger']['acknow']			url parameters for acknowledge page
	 * @param object options['trigger']['configuration']	url parameters for trigger configuration page
	 * @param object options['trigger']['url']				trigger url
	 * @param object options['trigger']['items']			links to items history related with given trigger,
	 *														using structure "name" => "parameters"
	 */
	function createTrigger(menuPopup, options) {
		if (typeof(options['trigger']) !== 'undefined') {
			var url,
				menu = createMenu(menuPopup, options.labels['Trigger']),
				params = {triggerid: options['trigger']['triggerid']};

			// events
			if (typeof(options['trigger']['events']) !== 'undefined') {
				url = new Curl('events.php' + getUrlParams(params, options['trigger']['events']));

				menu.append(createMenuItem(options.labels['Events'], url.getUrl()));
			}

			// acknow
			if (typeof(options['trigger']['acknow']) !== 'undefined') {
				url = new Curl('acknow.php' + getUrlParams(options['trigger']['acknow']));

				menu.append(createMenuItem(options.labels['Acknowledge'], url.getUrl()));
			}

			// configuration
			if (typeof(options['trigger']['configuration']) !== 'undefined') {
				url = new Curl('triggers.php' + getUrlParams(params, options['trigger']['configuration']));

				menu.append(createMenuItem(options.labels['Configuration'], url.getUrl()));
			}

			// url
			if (typeof(options['trigger']['url']) !== 'undefined') {
				menu.append(createMenuItem(options.labels['URL'], options['trigger']['url']));
			}

			// items
			if (typeof(options['trigger']['items']) !== 'undefined') {
				menu = createMenu(menuPopup, options.labels['History']);

				$.each(options['trigger']['items'], function(name, params) {
					url = new Curl('history.php' + getUrlParams(params));

					menu.append(createMenuItem(name, url.getUrl()));
				});
			}
		}
	}

	/**
	 * History menu section.
	 *
	 * @param object options['history']['params']			global parameters
	 * @param object options['history']['latestGraphs']		link to latest item history graphs
	 * @param object options['history']['latestValues']		link to latest item history 500 values
	 */
	function createHistory(menuPopup, options) {
		if (typeof(options['history']) !== 'undefined') {
			var url, menu = createMenu(menuPopup, options.labels['History']);

			// latest graphs
			if (typeof(options['history']['latestGraphs']) !== 'undefined') {
				url = new Curl('history.php' + getUrlParams(
					{action: 'showgraph', period: '3600'},
					options['history']['params']
				));

				menu.append(createMenuItem(options.labels['Last hour graph'], url.getUrl()));

				url = new Curl('history.php' + getUrlParams(
					{action: 'showgraph', period: '604800'},
					options['history']['params']
				));

				menu.append(createMenuItem(options.labels['Last week graph'], url.getUrl()));

				url = new Curl('history.php' + getUrlParams(
					{action: 'showgraph', period: '2678400'},
					options['history']['params']
				));

				menu.append(createMenuItem(options.labels['Last month graph'], url.getUrl()));
			}

			// latest values
			if (typeof(options['history']['latestValues']) !== 'undefined') {
				url = new Curl('history.php' + getUrlParams(
					{action: 'showvalues', period: '3600'},
					options['history']['params']
				));

				menu.append(createMenuItem(options.labels['Latest values'], url.getUrl()));
			}
		}
	}

	/**
	 * Urls menu section.
	 *
	 * @param object options['urls']	array of links using structure "name" => "url"
	 */
	function createUrls(menuPopup, options) {
		if (typeof(options['urls']) !== 'undefined' && objectSize(options['urls']) > 0) {
			var menu = createMenu(menuPopup, options.labels['URLs']);

			$.each(options['urls'], function(name, url) {
				menu.append(createMenuItem(name, url));
			});
		}
	}

	/**
	 * Recursive function to prepare menu tree items.
	 *
	 * @param array  menu		menu data
	 * @param string name		script name
	 * @param array  items		script path
	 * @param object params		script params
	 */
	function prepareTree(menu, name, items, params) {
		if (items.length > 0) {
			item = items.shift();

			if (typeof(menu[item]) === 'undefined') {
				menu[item] = {items: {}};
			}

			prepareTree(menu[item]['items'], name, items, params);
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

	/**
	 * Execute script.
	 *
	 * @param string hostId			host id
	 * @param string scriptId		script id
	 * @param string confirmation	confirmation text
	 * @param object labels			labels
	 */
	function executeScript(hostId, scriptId, confirmation, labels) {
		var execute = function() {
			openWinCentered('scripts_exec.php?execute=1&hostid=' + hostId + '&scriptid=' + scriptId, 'Tools', 560, 470,
				'titlebar=no, resizable=yes, scrollbars=yes, dialog=no'
			);
		};

		if (confirmation.length > 0) {
			var scriptDialog = $('#scriptDialog');

			if (scriptDialog.length == 0) {
				scriptDialog = $('<div>', {
					id: 'scriptDialog',
					css: {
						display: 'none',
						'white-space': 'normal',
						'z-index': 1000
					}
				});

				$('body').append(scriptDialog);
			}

			scriptDialog
				.text(confirmation)
				.dialog({
					buttons: [
						{text: labels['Execute'], click: function() {
							$(this).dialog('destroy');
							execute();
						}},
						{text: labels['Cancel'], click: function() {
							$(this).dialog('destroy');
						}}
					],
					draggable: false,
					modal: true,
					width: (scriptDialog.outerWidth() + 20 > 600) ? 600 : 'inherit',
					resizable: false,
					minWidth: 200,
					minHeight: 100,
					title: labels['Execution confirmation'],
					close: function() {
						$(this).dialog('destroy');
					}
				});

			$('.ui-dialog-buttonset button:first').addClass('main');
		}
		else {
			execute();
		}
	}

	/**
	 * Get url parameters.
	 * Merge global parameters and local parameters in one URL string.
	 *
	 * @param object $items
	 * @param object $globals
	 *
	 * @return string
	 */
	function getUrlParams(items, globals) {
		var params = '';

		if (typeof(globals) !== 'undefined' && objectSize(globals) > 0) {
			if (objectSize(items) == 0) {
				items = {};
			}

			items = $.extend({}, items);
			items = (objectSize(items) > 0) ? $.extend(items, globals) : globals;
		}

		$.each(items, function(name, value) {
			params += ((params.length > 0) ? '&' : '?') + name + '=' + value;
		});

		return params;
	}
});
