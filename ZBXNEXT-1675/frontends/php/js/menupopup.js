/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Get menu popup favourite graphs section data.
 *
 * @param array options['graphs']			graphs as id => label (optional)
 * @param array options['simpleGraphs']		simple graphs as id => label (optional)
 *
 * @return array
 */
function getMenuPopupFavouriteGraphs(options) {
	var sections = [];

	if (typeof options.graphs !== 'undefined') {
		sections[sections.length] = getMenuPopupFavouriteData(
			t('Favourite graphs'),
			options.graphs,
			'graphid',
			'popup.php?srctbl=graphs&srcfld1=graphid&reference=graphid&multiselect=1&real_hosts=1'
		);
	}

	if (typeof options.simpleGraphs !== 'undefined') {
		sections[sections.length] = getMenuPopupFavouriteData(
			t('Favourite simple graphs'),
			options.simpleGraphs,
			'itemid',
			'popup.php?srctbl=items&srcfld1=itemid&reference=itemid&multiselect=1&numeric=1'
				+ '&with_simple_graph_items=1&real_hosts=1'
		);
	}

	return sections;
}

/**
 * Get menu popup favourite maps section data.
 *
 * @param array options['maps']		maps as id => label
 *
 * @return array
 */
function getMenuPopupFavouriteMaps(options) {
	return [getMenuPopupFavouriteData(
		t('Favourite maps'),
		options.maps,
		'sysmapid',
		'popup.php?srctbl=sysmaps&srcfld1=sysmapid&reference=sysmapid&multiselect=1'
	)];
}

/**
 * Get menu popup favourite screens section data.
 *
 * @param array options['screens']		screens as id => label (optional)
 * @param array options['slideshows']	slideshows as id => label (optional)
 *
 * @return array
 */
function getMenuPopupFavouriteScreens(options) {
	var sections = [];

	if (typeof options.screens !== 'undefined') {
		sections[sections.length] = getMenuPopupFavouriteData(
			t('Favourite screens'),
			options.screens,
			'screenid',
			'popup.php?srctbl=screens&srcfld1=screenid&reference=screenid&multiselect=1'
		);
	}

	if (typeof options.slideshows !== 'undefined') {
		sections[sections.length] = getMenuPopupFavouriteData(
			t('Favourite slide shows'),
			options.slideshows,
			'slideshowid',
			'popup.php?srctbl=slides&srcfld1=slideshowid&reference=slideshowid&multiselect=1'
		);
	}

	return sections;
}

/**
 * Prepare data for favourite section.
 *
 * @param string label			item label
 * @param array  data			item submenu
 * @param string favouriteObj	favourite object name
 * @param string addParams		popup parameters
 *
 * @returns array
 */
function getMenuPopupFavouriteData(label, data, favouriteObj, addParams) {
	var removeItems = [];

	if (objectSize(data) > 0) {
		jQuery.each(data, function(i, item) {
			removeItems[i] = {
				label: item.label,
				clickCallback: function() {
					var obj = jQuery(this);

					sendAjaxData('zabbix.php?action=dashboard.favourite&operation=delete', {
						data: {
							object: favouriteObj,
							'objectids[]': [item.id]
						}
					});

					obj.closest('.action-menu').fadeOut(100);
					obj.remove();
				}
			};
		});
	}

	var add = {
			label: t('Add'),
			clickCallback: function() {
				PopUp(addParams);

				jQuery(this).closest('.action-menu').fadeOut(100);
			}
		},
		remove = {
			label: t('Remove')
		},
		remove_all = {
			label: t('Remove all')
		};

	if (removeItems.length != 0) {
		remove.items = removeItems;
		remove_all.clickCallback = function() {
			sendAjaxData('zabbix.php?action=dashboard.favourite&operation=delete', {
				data: {
					object: favouriteObj,
					'objectids[]': [0]
				}
			});

			jQuery(this).closest('.action-menu').fadeOut(100);
		};
	}
	else {
		remove.disabled = true;
		remove_all.disabled = true;
	}

	return {
		label: label,
		items: [add, remove, remove_all]
	};
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
			url: new Curl('history.php?itemids[]=' + options.itemid + '&action=showgraph&period=3600').getUrl()
		};

		items[items.length] = {
			label: t('Last week graph'),
			url: new Curl('history.php?itemids[]=' + options.itemid + '&action=showgraph&period=604800').getUrl()
		};

		items[items.length] = {
			label: t('Last month graph'),
			url: new Curl('history.php?itemids[]=' + options.itemid + '&action=showgraph&period=2678400').getUrl()
		};
	}

	// latest values
	items[items.length] = {
		label: t('Latest values'),
		url: new Curl('history.php?itemids[]=' + options.itemid + '&action=showvalues&period=3600').getUrl()
	};

	return [{
		label: t('History'),
		items: items
	}];
}

/**
 * Get menu popup host section data.
 *
 * @param string options['hostid']			host id
 * @param array  options['scripts']			host scripts (optional)
 * @param string options[]['name']			script name
 * @param string options[]['scriptid']		script id
 * @param string options[]['confirmation']	confirmation text
 * @param bool   options['showGraphs']		link to host graphs page
 * @param bool   options['showScreens']		link to host screen page
 * @param bool   options['showTriggers']	link to Monitoring->Triggers page
 * @param bool   options['hasGoTo']			"Go to" block in popup
 *
 * @return array
 */
function getMenuPopupHost(options) {
	var sections = [];

	// scripts
	if (typeof options.scripts !== 'undefined') {
		sections[sections.length] = {
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, options.hostid)
		};
	}

	// go to section
	if (options.hasGoTo) {
		var gotos = [],
			// inventory
			host_inventory = {
				label: t('Host inventory'),
				url: new Curl('hostinventories.php?hostid=' + options.hostid).getUrl()
			},
			// latest
			latest_data = {
				label: t('Latest data'),
				url: new Curl('latest.php?filter_set=1&hostids[]=' + options.hostid).getUrl()
			},
			// triggers
			triggers = {
				label: t('Triggers')
			},
			// graphs
			graphs = {
				label: t('Graphs')
			},
			// screens
			screens = {
				label: t('Host screens')
			};

		if (!options.showTriggers) {
			triggers.disabled = true;
		}
		else {
			triggers.url = new Curl('tr_status.php?hostid=' + options.hostid).getUrl();
		}

		if (!options.showGraphs) {
			graphs.disabled = true;
		}
		else {
			graphs.url = new Curl('charts.php?hostid=' + options.hostid).getUrl();
		}

		if (!options.showScreens) {
			screens.disabled = true;
		}
		else {
			screens.url = new Curl('host_screen.php?hostid=' + options.hostid).getUrl();
		}

		gotos[gotos.length] = host_inventory;
		gotos[gotos.length] = latest_data;
		gotos[gotos.length] = triggers;
		gotos[gotos.length] = graphs;
		gotos[gotos.length] = screens;

		sections[sections.length] = {
			label: t('Go to'),
			items: gotos
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
 * @param array  options['gotos']['latestData']		link to latest data page
 * @param array  options['gotos']['inventory']		link to host inventory page
 * @param array  options['gotos']['graphs']			link to host graph page with url parameters ("name" => "value")
 * @param array  options['gotos']['showGraphs']		display "Graphs" link enabled or disabled
 * @param array  options['gotos']['screens']		link to host screen page with url parameters ("name" => "value")
 * @param array  options['gotos']['showScreens']	display "Screens" link enabled or disabled
 * @param array  options['gotos']['triggerStatus']	link to trigger status page with url parameters ("name" => "value")
 * @param array  options['gotos']['showTriggers']	display "Triggers" link enabled or disabled
 * @param array  options['gotos']['submap']			link to submap page with url parameters ("name" => "value")
 * @param array  options['gotos']['events']			link to events page with url parameters ("name" => "value")
 * @param array  options['gotos']['showEvents']		display "Events" link enabled or disabled
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
		sections[sections.length] = {
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, options.hostid)
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

		// latest
		if (typeof options.gotos.latestData !== 'undefined') {
			var url = new Curl('latest.php?filter_set=1');

			jQuery.each(options.gotos.latestData, function(name, value) {
				url.setArgument(name, value);
			});

			gotos[gotos.length] = {
				label: t('Latest data'),
				url: url.getUrl()
			};
		}

		// trigger status
		if (typeof options.gotos.triggerStatus !== 'undefined') {
			var triggers = {
				label: t('Triggers')
			};

			if (!options.gotos.showTriggers) {
				triggers.disabled = true;
			}
			else {
				var url = new Curl('tr_status.php?filter_set=1&show_maintenance=1');

				jQuery.each(options.gotos.triggerStatus, function(name, value) {
					url.setArgument(name, value);
				});

				triggers.url = url.getUrl();
			}

			gotos[gotos.length] = triggers;
		}

		// graphs
		if (typeof options.gotos.graphs !== 'undefined') {
			var graphs = {
				label: t('Graphs')
			};

			if (!options.gotos.showGraphs) {
				graphs.disabled = true;
			}
			else {
				var url = new Curl('charts.php');

				jQuery.each(options.gotos.graphs, function(name, value) {
					url.setArgument(name, value);
				});

				graphs.url = url.getUrl();
			}

			gotos[gotos.length] = graphs;
		}

		// screens
		if (typeof options.gotos.screens !== 'undefined') {
			var screens = {
				label: t('Host screens')
			};

			if (!options.gotos.showScreens) {
				screens.disabled = true;
			}
			else {
				var url = new Curl('host_screen.php');

				jQuery.each(options.gotos.screens, function(name, value) {
					url.setArgument(name, value);
				});

				screens.url = url.getUrl();
			}

			gotos[gotos.length] = screens;
		}

		// submap
		if (typeof options.gotos.submap !== 'undefined') {
			var url = new Curl('zabbix.php?action=map.view');

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
			var events = {
				label: t('Problems')
			};

			if (!options.gotos.showEvents) {
				events.disabled = true;
			}
			else {
				var url = new Curl('zabbix.php');
				url.setArgument('action', 'problem.view');
				url.setArgument('filter_triggerids[]', options.gotos.events.triggerid);
				url.setArgument('filter_set', '1');
				url.unsetArgument('sid');

				events.url = url.getUrl();
			}

			gotos[gotos.length] = events;
		}

		sections[sections.length] = {
			label: t('Go to'),
			items: gotos
		};
	}

	// urls
	if (typeof options.urls !== 'undefined') {
		sections[sections.length] = {
			label: t('URLs'),
			items: options.urls
		};
	}

	return sections;
}

/**
 * Get menu popup refresh section data.
 *
 * @param string options['widgetName']		widget name
 * @param string options['currentRate']		current rate value
 * @param bool   options['multiplier']		multiplier or time mode
 * @param array  options['params']			url parameters (optional)
 *
 * @return array
 */
function getMenuPopupRefresh(options) {
	var items = [],
		params = (typeof options.params === 'undefined' || options.params.length == 0) ? {} : options.params,
		intervals = options.multiplier
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

	jQuery.each(intervals, function(value, label) {
		var item = {
			label: label,
			data: {
				value: value
			},
			clickCallback: function() {
				var obj = jQuery(this),
					currentRate = obj.data('value');

				// it is a quick solution for slide refresh multiplier, should be replaced with slide.refresh or similar
				if (options.multiplier) {
					sendAjaxData('slides.php', {
						data: jQuery.extend({}, params, {
							widgetName: options.widgetName,
							widgetRefreshRate: currentRate
						}),
						dataType: 'script',
						success: function(js) { js }
					});
				}
				else {
					sendAjaxData('zabbix.php?action=dashboard.widget', {
						data: jQuery.extend({}, params, {
							widget: options.widgetName,
							refreshrate: currentRate
						}),
						dataType: 'script',
						success: function(js) { js }
					});
				}

				jQuery('a').each(function() {
					var link = jQuery(this);

					if (link.data('value') == currentRate) {
						link.addClass('selected');
					}
					else {
						link.removeClass('selected');
					}
				});

				obj.closest('.action-menu').fadeOut(100);
			}
		};

		if (value == options.currentRate) {
			item.selected = true;
		}

		items[items.length] = item;
	});

	return [{
		label: options.multiplier ? t('Refresh time multiplier') : t('Refresh time'),
		items: items
	}];
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
 * @param string options['acknowledge']['backurl']	return url
 * @param object options['configuration']			link to trigger configuration page (optional)
 * @param string options['url']						trigger url link (optional)
 *
 * @return array
 */
function getMenuPopupTrigger(options) {
	var sections = [], items = [];

	// events
	var events = {
		label: t('Problems')
	};

	if (typeof options.showEvents !== 'undefined' && options.showEvents) {
		var url = new Curl('zabbix.php');
		url.setArgument('action', 'problem.view');
		url.setArgument('filter_triggerids[]', options.triggerid);
		url.setArgument('filter_set', '1');
		url.unsetArgument('sid');

		events.url = url.getUrl();
	}
	else {
		events.disabled = true;
	}

	items[items.length] = events;

	// acknowledge
	if (typeof options.acknowledge !== 'undefined' && objectSize(options.acknowledge) > 0) {
		var url = new Curl('zabbix.php?action=acknowledge.edit&eventids[]=' + options.acknowledge.eventid + '&backurl=' + options.acknowledge.backurl);

		items[items.length] = {
			label: t('Acknowledge'),
			url: url.getUrl()
		};
	}

	// configuration
	if (typeof options.configuration !== 'undefined' && options.configuration) {
		var url = new Curl('triggers.php?form=update&triggerid=' + options.triggerid);

		items[items.length] = {
			label: t('Configuration'),
			url: url.getUrl()
		};
	}

	// url
	if (typeof options.url !== 'undefined' && options.url.length > 0) {
		items[items.length] = {
			label: t('URL'),
			url: options.url
		};
	}

	sections[sections.length] = {
		label: t('Trigger'),
		items: items
	};

	// items
	if (typeof options.items !== 'undefined' && objectSize(options.items) > 0) {
		var items = [];

		jQuery.each(options.items, function(i, item) {
			var url = new Curl('history.php');
			url.setArgument('action', item.params.action);
			url.setArgument('itemids[]', item.params.itemid);

			items[items.length] = {
				label: item.name,
				url: url.getUrl()
			};
		});

		sections[sections.length] = {
			label: t('History'),
			items: items
		};
	}

	return sections;
}

/**
 * Get menu popup trigger log section data.
 *
 * @param string options['itemid']				item id
 * @param string options['itemName']			item name
 * @param array  options['triggers']			triggers (optional)
 * @param string options['triggers'][n]['id']	trigger id
 * @param string options['triggers'][n]['name']	trigger name
 *
 * @return array
 */
function getMenuPopupTriggerLog(options) {
	var items = [];

	// create
	items[items.length] = {
		label: t('Create trigger'),
		clickCallback: function() {
			openWinCentered(
				'tr_logform.php?sform=1&itemid=' + options.itemid,
				'TriggerLog',
				1000,
				700,
				'titlebar=no, resizable=yes, scrollbars=yes, dialog=no'
			);

			jQuery(this).closest('.action-menu').fadeOut(100);
		}
	};

	var edit_trigger = {
		label: t('Edit trigger')
	};

	// edit
	if (options.triggers.length > 0) {
		var triggers = [];

		jQuery.each(options.triggers, function(i, trigger) {
			triggers[triggers.length] = {
				label: trigger.name,
				clickCallback: function() {
					openWinCentered(
						'tr_logform.php?sform=1&itemid=' + options.itemid + '&triggerid=' + trigger.id,
						'TriggerLog',
						1000,
						700,
						'titlebar=no, resizable=yes, scrollbars=yes'
					);

					jQuery(this).closest('.action-menu').fadeOut(100);
				}
			};
		});

		edit_trigger.items = triggers;
	}
	else {
		edit_trigger.disabled = true;
	}

	items[items.length] = edit_trigger;

	return [{
		label: sprintf(t('Item "%1$s"'), options.itemName),
		items: items
	}];
}

/**
 * Get data for the "Insert expression" menu in the trigger expression constructor.
 *
 * @return array
 */
function getMenuPopupTriggerMacro(options) {
	var items = [],
		expressions = [
			{
				label: t('Trigger status "OK"'),
				string: '{TRIGGER.VALUE}=0'
			},
			{
				label: t('Trigger status "Problem"'),
				string: '{TRIGGER.VALUE}=1'
			}
		];

	jQuery.each(expressions, function(key, expression) {
		items[items.length] = {
			label: expression.label,
			clickCallback: function() {
				var expressionInput = jQuery('#expr_temp');

				if (expressionInput.val().length > 0 && !confirm(t('Do you wish to replace the conditional expression?'))) {
					return false;
				}

				expressionInput.val(expression.string);

				jQuery(this).closest('.action-menu').fadeOut(100);
			}
		};
	});

	return [{
		label: t('Insert expression'),
		items: items
	}];
}

/**
 * Build script menu tree.
 *
 * @param array scripts		scripts names
 * @param array hostId		host id
 *
 * @returns array
 */
function getMenuPopupScriptData(scripts, hostId) {
	var tree = {};

	var appendTreeItem = function(tree, name, items, params) {
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
	};

	// parse scripts and create tree
	for (var key in scripts) {
		var script = scripts[key];

		if (typeof script.scriptid !== 'undefined') {
			var items = splitPath(script.name),
				name = (items.length > 0) ? items.pop() : script.name;

			appendTreeItem(tree, name, items, {
				hostId: hostId,
				scriptId: script.scriptid,
				confirmation: script.confirmation
			});
		}
	}

	// build menu items from tree
	var getMenuPopupScriptItems = function(tree) {
		var items = [];

		if (objectSize(tree) > 0) {
			jQuery.each(tree, function(name, data) {
				var item = {label: name};

				if (typeof data.items !== 'undefined' && objectSize(data.items) > 0) {
					item.items = getMenuPopupScriptItems(data.items);
				}

				if (typeof data.params !== 'undefined' && typeof data.params.scriptId !== 'undefined') {
					item.clickCallback = function(e) {
						executeScript(data.params.hostId, data.params.scriptId, data.params.confirmation);
						cancelEvent(e);
						jQuery(this).closest('.action-menu-top').fadeOut(100);
					};
				}

				items[items.length] = item;
			});
		}

		return items;
	};

	return getMenuPopupScriptItems(tree);
}

jQuery(function($) {

	/**
	 * Menu popup.
	 *
	 * @param array  sections				menu sections
	 * @param string sections[n]['label']	(optional) section title
	 * @param array  sections[n]['items']	section menu data (see createMenuItem() for available options)
	 * @param object event					menu popup call event
	 *
	 * @see createMenuItem()
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
			jQuery('.action-menu').css('display', 'none');

			if (display === 'block') {
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

			menuPopup = $('<ul>', {
				id: id,
				'class': 'action-menu action-menu-top'
			});

			// create sections
			if (sections.length > 0) {
				$.each(sections, function(i, section) {
					if ((typeof section.label !== 'undefined') && (section.label.length > 0)) {
						var h3 = $('<h3>').text(section.label);
						var sectionItem = $('<li>').append(h3);
					}

					// add section delimited for all sections except first one
					if (i > 0) {
						menuPopup.append($('<li>').append($('<div>')));
					}
					menuPopup.append(sectionItem);

					$.each(section.items, function(i, item) {
						menuPopup.append(createMenuItem(item));
					});
				});
			}

			// skip displaying empty menu sections
			if (menuPopup.children().length == 0) {
				return;
			}

			// set menu popup for map area
			if (opener.prop('tagName') === 'AREA') {
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
			jQuery('.action-menu').css('display', 'none');

			// display
			menuPopup
				.fadeIn(50)
				.data('is-active', false)
				.mouseenter(function() {
					menuPopup.data('is-active', true);

					clearTimeout(window.menuPopupTimeoutHandler);
				})
				.position({
					of: (opener.prop('tagName') === 'AREA') ? mapContainer : event,
					my: 'left top',
					at: 'left bottom'
				});
		}

		$(document).click(function(e) {
			if (!menuPopup.is(e.target) && menuPopup.has(e.target).length === 0) {
				menuPopup.data('is-active', false);
				menuPopup.fadeOut(0);
			}
		});
	};

	/**
	 * Create menu item.
	 *
	 * @param string options['label']			link text
	 * @param string options['url']				link url
	 * @param string options['css']				item class
	 * @param array  options['data']			item data as key => value
	 * @param array  options['items']			item sub menu
	 * @param object options['clickCallback']	item click callback
	 *
	 * @return object
	 */
	function createMenuItem(options) {
		var item = $('<li>'),
			link = $('<a>');

		if (typeof options.label !== 'undefined') {
			link.text(options.label);

			if (typeof options.items !== 'undefined' && options.items.length > 0) {
				// if submenu exists
				link.append($('<span>', {'class': 'arrow-right'}));
			}
		}

		if (typeof options.data !== 'undefined' && objectSize(options.data) > 0) {
			$.each(options.data, function(key, value) {
				link.data(key, value);
			});
		}

		if (typeof options.disabled !== 'undefined' && options.disabled) {
			link.addClass('action-menu-item-disabled');
		}
		else {
			link.addClass('action-menu-item');

			if (typeof options.url !== 'undefined') {
				link.attr('href', options.url);
			}

			if (typeof options.clickCallback !== 'undefined') {
				link.click(options.clickCallback);
			}
		}

		if (typeof options.selected !== 'undefined' && options.selected) {
			link.addClass('selected');
		}

		item.append(link);

		if (typeof options.items !== 'undefined' && options.items.length > 0) {
			var menu = $('<ul>', {'class' : 'action-menu'});

			$.each(options.items, function(i, item) {
				menu.append(createMenuItem(item));
			});

			item.append(menu);

			item.hover(function() {
				var li = $(this),
					pos = li.position(),
					menu = li.closest('.action-menu');

				for (var item = $('li:first-child', menu); item.length > 0; item = item.next()) {
					if (item[0] == li[0]) {
						var display = $('ul', item[0]).css('display');

						if (display !== 'block') {
							$('ul:first', item[0])
								.css({
									'top': pos.top - 6,
									'left': pos.left + li.outerWidth() + 14,
									'display': 'block'
								});
						}
					}
					else {
						var display = $('ul', item[0]).css('display');

						if (display === 'block') {
							$('ul', item[0]).css({'display': 'none'});
						}
					}
				}
			});
		}
		else {
			item.hover(function() {
				$('li ul', $(this).closest('.action-menu')).css({'display': 'none'});
			});
		}

		return item;
	}
});
