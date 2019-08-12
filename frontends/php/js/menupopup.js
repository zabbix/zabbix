/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Get menu popup history section data.
 *
 * @param string options['itemid']           Item ID.
 * @param bool   options['hasLatestGraphs']  Link to history page with showgraph action (optional).
 *
 * @return array
 */
function getMenuPopupHistory(options) {
	var items = [],
		url = new Curl('history.php', false);

	url.setArgument('itemids[]', options.itemid);

	// latest graphs
	if (typeof options.hasLatestGraphs !== 'undefined' && options.hasLatestGraphs) {
		url.setArgument('action', 'showgraph');
		url.setArgument('to', 'now');

		url.setArgument('from', 'now-1h');
		items.push({
			label: t('Last hour graph'),
			url: url.getUrl()
		});

		url.setArgument('from', 'now-7d');
		items.push({
			label: t('Last week graph'),
			url: url.getUrl()
		});

		url.setArgument('from', 'now-1M');
		items.push({
			label: t('Last month graph'),
			url: url.getUrl()
		});
	}

	// latest values
	url.setArgument('action', 'showvalues');
	url.setArgument('from', 'now-1h');
	items.push({
		label: t('Latest values'),
		url: url.getUrl()
	});

	return [{
		label: t('History'),
		items: items
	}];
}

/**
 * Get menu popup host section data.
 *
 * @param {string} options['hostid']             Host ID.
 * @param {array}  options['scripts']            Host scripts (optional).
 * @param {string} options[]['name']             Script name.
 * @param {string} options[]['scriptid']         Script ID.
 * @param {string} options[]['confirmation']     Confirmation text.
 * @param {bool}   options['showGraphs']         Link to host graphs page.
 * @param {bool}   options['showScreens']        Link to host screen page.
 * @param {bool}   options['showTriggers']       Link to Monitoring->Problems page.
 * @param {bool}   options['hasGoTo']            "Go to" block in popup.
 * @param {int}    options['severity_min']       (optional)
 * @param {bool}   options['show_suppressed']    (optional)
 * @param {array}  options['urls']               (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 * @param {string} options['filter_application'] (optional) Application name for filter by application.
 * @param {object} trigger_elmnt                 UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupHost(options, trigger_elmnt) {
	var sections = [];

	// scripts
	if (typeof options.scripts !== 'undefined') {
		sections.push({
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, options.hostid, trigger_elmnt)
		});
	}

	// go to section
	if (options.hasGoTo) {
		// inventory
		var	host_inventory = {
				label: t('Host inventory')
			},
			host_inventory_url = new Curl('hostinventories.php', false),
			// latest
			latest_data = {
				label: t('Latest data')
			},
			latest_data_url = new Curl('latest.php', false),
			// problems
			problems = {
				label: t('Problems')
			},
			// graphs
			graphs = {
				label: t('Graphs')
			},
			// screens
			screens = {
				label: t('Host screens')
			};

		// inventory link
		host_inventory_url.setArgument('hostid', options.hostid);
		host_inventory.url = host_inventory_url.getUrl();

		// latest data link
		if (typeof options.filter_application !== 'undefined') {
			latest_data_url.setArgument('application', options.filter_application);
		}
		latest_data_url.setArgument('hostids[]', options.hostid);
		latest_data_url.setArgument('filter_set', '1');
		latest_data.url = latest_data_url.getUrl();

		if (!options.showTriggers) {
			problems.disabled = true;
		}
		else {
			var url = new Curl('zabbix.php', false);
			url.setArgument('action', 'problem.view');
			url.setArgument('filter_hostids[]', options.hostid);
			if (typeof options.severity_min !== 'undefined') {
				url.setArgument('filter_severity', options.severity_min);
			}
			if (typeof options.show_suppressed !== 'undefined' && options.show_suppressed) {
				url.setArgument('filter_show_suppressed', '1');
			}
			if (typeof options.filter_application !== 'undefined') {
				url.setArgument('filter_application', options.filter_application);
			}
			url.setArgument('filter_set', '1');
			problems.url = url.getUrl();
		}

		if (!options.showGraphs) {
			graphs.disabled = true;
		}
		else {
			var graphs_url = new Curl('charts.php', false);

			graphs_url.setArgument('hostid', options.hostid);
			graphs.url = graphs_url.getUrl();
		}

		if (!options.showScreens) {
			screens.disabled = true;
		}
		else {
			var screens_url = new Curl('host_screen.php', false);

			screens_url.setArgument('hostid', options.hostid);
			screens.url = screens_url.getUrl();
		}

		sections.push({
			label: t('Go to'),
			items: [
				host_inventory,
				latest_data,
				problems,
				graphs,
				screens
			]
		});
	}

	// urls
	if (typeof options.urls !== 'undefined') {
		sections.push({
			label: t('URLs'),
			items: options.urls
		});
	}

	return sections;
}

/**
 * Get menu popup submap map element section data.
 *
 * @param {array}  options['sysmapid']
 * @param {int}    options['severity_min']     (optional)
 * @param {int}    options['widget_uniqueid']  (optional)
 * @param {array}  options['urls']             (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 *
 * @return array
 */
function getMenuPopupMapElementSubmap(options) {
	var sections = [],
		submap_url;

	if (typeof options.widget_uniqueid !== 'undefined') {
		submap_url = new Curl('javascript: navigateToSubmap(' + options.sysmapid +
			', "' + options.widget_uniqueid + '");', false);
	}
	else {
		submap_url = new Curl('zabbix.php', false);
		submap_url.setArgument('action', 'map.view');
		submap_url.setArgument('sysmapid', options.sysmapid);
		if (typeof options.severity_min !== 'undefined') {
			submap_url.setArgument('severity_min', options.severity_min);
		}
	}

	sections.push({
		label: t('Go to'),
		items: [{
			label: t('Submap'),
			url: submap_url.getUrl()
		}]
	});

	// urls
	if (typeof options.urls !== 'undefined') {
		sections.push({
			label: t('URLs'),
			items: options.urls
		});
	}

	return sections;
}

/**
 * Get menu popup host group map element section data.
 *
 * @param {string} options['groupid']
 * @param {int}    options['severity_min']       (optional)
 * @param {bool}   options['show_suppressed']    (optional)
 * @param {array}  options['urls']               (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 * @param {string} options['filter_application'] (optional) Application name for filter by application.
 *
 * @return array
 */
function getMenuPopupMapElementGroup(options) {
	var sections = [],
		problems_url = new Curl('zabbix.php', false);

	problems_url.setArgument('action', 'problem.view');
	problems_url.setArgument('filter_groupids[]', options.groupid);
	if (typeof options.severity_min !== 'undefined') {
		problems_url.setArgument('severity_min', options.severity_min);
	}
	if (typeof options.show_suppressed !== 'undefined' && options.show_suppressed) {
		problems_url.setArgument('filter_show_suppressed', '1');
	}
	if (typeof options.filter_application !== 'undefined') {
		problems_url.setArgument('filter_application', options.filter_application);
	}
	problems_url.setArgument('filter_set', '1');

	sections.push({
		label: t('Go to'),
		items: [{
			label: t('Problems'),
			url: problems_url.getUrl()
		}]
	});

	// urls
	if (typeof options.urls !== 'undefined') {
		sections.push({
			label: t('URLs'),
			items: options.urls
		});
	}

	return sections;
}

/**
 * Get menu popup trigger map element section data.
 *
 * @param {array}  options['triggerids']
 * @param {int}    options['severity_min']     (optional)
 * @param {bool}   options['show_suppressed']  (optional)
 * @param {array}  options['urls']             (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 *
 * @return array
 */
function getMenuPopupMapElementTrigger(options) {
	var sections = [],
		problems_url = new Curl('zabbix.php', false);

	problems_url.setArgument('action', 'problem.view');
	problems_url.setArgument('filter_triggerids[]', options.triggerids);
	if (typeof options.severity_min !== 'undefined') {
		problems_url.setArgument('filter_severity', options.severity_min);
	}
	if (typeof options.show_suppressed !== 'undefined' && options.show_suppressed) {
		problems_url.setArgument('filter_show_suppressed', '1');
	}
	problems_url.setArgument('filter_set', '1');

	sections.push({
		label: t('Go to'),
		items: [{
			label: t('Problems'),
			url: problems_url.getUrl()
		}]
	});

	// urls
	if (typeof options.urls !== 'undefined') {
		sections.push({
			label: t('URLs'),
			items: options.urls
		});
	}

	return sections;
}

/**
 * Get menu popup image map element section data.
 *
 * @param {array}  options['urls']             (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 *
 * @return array
 */
function getMenuPopupMapElementImage(options) {
	// urls
	if (typeof options.urls !== 'undefined') {
		return [{
			label: t('URLs'),
			items: options.urls
		}];
	}

	return [];
}

/**
 * Get menu popup refresh section data.
 *
 * @param {string}   options['widgetName']   Widget name.
 * @param {string}   options['currentRate']  Current rate value.
 * @param {bool}     options['multiplier']   Multiplier or time mode.
 * @param {array}    options['params']       Url parameters (optional).
 * @param {callback} options['callback']     Callback function on success (optional).
 * @param {object}   trigger_elmnt           UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupRefresh(options, trigger_elmnt) {
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
				0: t('No refresh'),
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
				var $obj = jQuery(this),
					currentRate = $obj.data('value');

				// it is a quick solution for slide refresh multiplier, should be replaced with slide.refresh or similar
				if (options.multiplier) {
					sendAjaxData('slides.php', {
						data: jQuery.extend({}, params, {
							widgetName: options.widgetName,
							widgetRefreshRate: currentRate
						}),
						dataType: 'script',
						success: function() {
							// Set new refresh rate as current in slideshow controls.
							trigger_elmnt.data('menu-popup').data.currentRate = currentRate;
						}
					});

					jQuery('a', $obj.closest('.menu-popup')).each(function() {
						var link = jQuery(this);

						if (link.data('value') == currentRate) {
							link
								.addClass('selected')
								.attr('aria-label', sprintf(t('S_SELECTED_SR'), link.data('aria-label')));
						}
						else {
							link
								.removeClass('selected')
								.attr('aria-label', link.data('aria-label'));
						}
					});

					$obj.closest('.menu-popup').menuPopup('close', trigger_elmnt);
				}
				else {
					var url = new Curl('zabbix.php');
					url.setArgument('action', 'dashboard.widget.rfrate');

					jQuery.ajax({
						url: url.getUrl(),
						method: 'POST',
						dataType: 'json',
						data: {
							'widgetid': options.widgetName,
							'rf_rate': currentRate
						},
						success: function() {
							jQuery('a', $obj.closest('.menu-popup')).each(function() {
								var link = jQuery(this);

								if (link.data('value') == currentRate) {
									link
										.addClass('selected')
										.attr('aria-label', sprintf(t('S_SELECTED_SR'), link.data('aria-label')));
								}
								else {
									link
										.removeClass('selected')
										.attr('aria-label', link.data('aria-label'));
								}
							});

							// Set new refresh rate as current in widget controls.
							trigger_elmnt.data('menu-popup').data.currentRate = currentRate;

							$obj.closest('.menu-popup').menuPopup('close', trigger_elmnt);

							jQuery('.dashbrd-grid-container')
								.dashboardGrid('setWidgetRefreshRate', options.widgetName, parseInt(currentRate));
						},
						error: function() {
							$obj.closest('.menu-popup').menuPopup('close', trigger_elmnt);
							// TODO: gentle message about failed saving of widget refresh rate
						}
					});
				}
			}
		};

		if (value == options.currentRate) {
			item.selected = true;
		}

		items[items.length] = item;
	});

	return [{
		label: options.multiplier ? t('Refresh interval multiplier') : t('Refresh interval'),
		items: items
	}];
}

/**
 * Get menu popup trigger section data.
 *
 * @param {string} options['dashboardid']
 * @param {bool}   options['editable']
 * @param {object} trigger_elmnt           UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupDashboard(options, trigger_elmnt) {
	var	url_create = new Curl('zabbix.php', false),
		url_clone = new Curl('zabbix.php', false),
		url_delete = new Curl('zabbix.php', false);

	url_create.setArgument('action', 'dashboard.view');
	url_create.setArgument('new', '1');

	url_clone.setArgument('action', 'dashboard.view');
	url_clone.setArgument('source_dashboardid', options.dashboardid);

	url_delete.setArgument('action', 'dashboard.delete');
	url_delete.setArgument('dashboardids', [options.dashboardid]);

	return [{
		label: t('Actions'),
		items: [
			{
				label: t('Sharing'),
				clickCallback: function () {
					var popup_options = {'dashboardid': options.dashboardid};
					PopUp('dashboard.share.edit', popup_options, 'dashboard_share', trigger_elmnt);

					jQuery(this).closest('.menu-popup').menuPopup('close', null);
				},
				disabled: !options.editable
			},
			{
				label: t('Create new'),
				url: url_create.getUrl()
			},
			{
				label: t('Clone'),
				url: url_clone.getUrl()
			},
			{
				label: t('Delete'),
				url: 'javascript:void(0)',
				clickCallback: function () {
					var	obj = jQuery(this);

					// hide menu
					obj.closest('.menu-popup').hide();

					if (!confirm(t('Delete dashboard?'))) {
						return false;
					}

					redirect(url_delete.getUrl(), 'post', 'sid', true, true);
				},
				disabled: !options.editable
			}
		]
	}];
}

/**
 * Get menu popup trigger section data.
 *
 * @param {string} options['triggerid']               Trigger ID.
 * @param {string} options['eventid']                 (optional) Required for Acknowledge and Description sections.
 * @param {object} options['items']                   Link to trigger item history page (optional).
 * @param {string} options['items'][]['name']         Item name.
 * @param {object} options['items'][]['params']       Item URL parameters ("name" => "value").
 * @param {object} options['acknowledge']             Link to acknowledge page (optional).
 * @param {string} options['acknowledge']['backurl']  Return URL.
 * @param {object} options['configuration']           Link to trigger configuration page (optional).
 * @param {bool}   options['showEvents']              Show Problems item enabled. Default: false.
 * @param {bool}   options['show_description']        Show Description item in context menu. Default: true.
 * @param {bool}   options['description_enabled']     Show Description item enabled. Default: true.
 * @param {string} options['url']                     Trigger URL link (optional).
 * @param {object} trigger_elmnt                      UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupTrigger(options, trigger_elmnt) {
	var sections = [],
		items = [];

	// events
	var events = {
		label: t('Problems')
	};

	if (typeof options.showEvents !== 'undefined' && options.showEvents) {
		var url = new Curl('zabbix.php', false);
		url.setArgument('action', 'problem.view');
		url.setArgument('filter_triggerids[]', options.triggerid);
		url.setArgument('filter_set', '1');

		events.url = url.getUrl();
	}
	else {
		events.disabled = true;
	}

	items[items.length] = events;

	// acknowledge
	if (typeof options.acknowledge !== 'undefined' && objectSize(options.acknowledge) > 0) {
		var url = new Curl('zabbix.php', false);

		url.setArgument('action', 'acknowledge.edit');
		url.setArgument('eventids[]', options.eventid);
		url.setArgument('backurl', options.acknowledge.backurl);

		items[items.length] = {
			label: t('Acknowledge'),
			url: url.getUrl()
		};
	}

	// description
	if (typeof options.show_description === 'undefined' || options.show_description !== false) {
		var trigger_descr = {
			label: t('Description')
		};

		if (typeof options.description_enabled === 'undefined' || options.description_enabled !== false) {
			trigger_descr.clickCallback = function() {
				var	popup_options = {triggerid: options.triggerid};

				if (typeof options.eventid !== 'undefined') {
					popup_options.eventid = options.eventid;
				}

				jQuery(this).closest('.menu-popup').menuPopup('close', null);

				return PopUp('popup.trigdesc.view', popup_options, null, trigger_elmnt);
			}
		}
		else {
			trigger_descr.disabled = true;
		}
		items[items.length] = trigger_descr;
	}

	// configuration
	if (typeof options.configuration !== 'undefined' && options.configuration) {
		var url = new Curl('triggers.php', false);

		url.setArgument('form', 'update');
		url.setArgument('triggerid', options.triggerid);

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
		label: t('S_TRIGGER'),
		items: items
	};

	// items
	if (typeof options.items !== 'undefined' && objectSize(options.items) > 0) {
		var items = [];

		jQuery.each(options.items, function(i, item) {
			var url = new Curl('history.php', false);
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
 * @param string options['itemid']
 * @param string options['hostid']
 * @param string options['name']
 * @param bool   options['show_triggers']             (optional) Show trigger menus.
 * @param array  options['triggers']                  (optional)
 * @param string options['triggers'][n]['triggerid']
 * @param string options['triggers'][n]['name']
 * @param {object} trigger_elmnt                      UI element that was clicked to open overlay dialogue.
 *
 * @return array
 */
function getMenuPopupItem(options, trigger_elmnt) {
	var items = [];

	if (typeof options.show_triggers !== 'undefined' && options.show_triggers) {
		// create
		items.push({
			label: t('Create trigger'),
			clickCallback: function() {
				jQuery(this).closest('.menu-popup').menuPopup('close', null);

				return PopUp('popup.triggerwizard', {
					itemid: options.itemid
				}, null, trigger_elmnt);
			}
		});

		var edit_trigger = {
			label: t('Edit trigger')
		};

		// edit
		if (options.triggers.length > 0) {
			var triggers = [];

			jQuery.each(options.triggers, function(i, trigger) {
				triggers.push({
					label: trigger.name,
					clickCallback: function() {
						jQuery(this).closest('.menu-popup-top').menuPopup('close', null);

						return PopUp('popup.triggerwizard', {
							itemid: options.itemid,
							triggerid: trigger.triggerid
						}, null, trigger_elmnt);
					}
				});
			});

			edit_trigger.items = triggers;
		}
		else {
			edit_trigger.disabled = true;
		}

		items.push(edit_trigger);
	}

	var url = new Curl('items.php', false);

	url.setArgument('form', 'create');
	url.setArgument('hostid', options.hostid);
	url.setArgument('type', 18);	// ITEM_TYPE_DEPENDENT
	url.setArgument('master_itemid', options.itemid);

	items.push({
		label: t('Create dependent item'),
		url: url.getUrl(),
		disabled: !options.create_dependent_item
	});

	url = new Curl('host_discovery.php');
	url.setArgument('form', 'create');
	url.setArgument('hostid', options.hostid);
	url.setArgument('type', 18);	// ITEM_TYPE_DEPENDENT
	url.setArgument('master_itemid', options.itemid);

	items.push({
		label: t('Create dependent discovery rule'),
		url: url.getUrl(),
		disabled: !options.create_dependent_discovery
	});

	return [{
		label: options.name,
		items: items
	}];
}

/**
 * Get menu structure for item prototypess.
 *
 * @param array options['name']
 * @param array options['itemid']
 * @param array options['parent_discoveryid']
 *
 * @return array
 */
function getMenuPopupItemPrototype(options) {
	var url = new Curl('disc_prototypes.php', false);

	url.setArgument('form', 'create');
	url.setArgument('parent_discoveryid', options.parent_discoveryid);
	url.setArgument('type', 18);	// ITEM_TYPE_DEPENDENT
	url.setArgument('master_itemid', options.itemid);

	return [{
		label: options.name,
		items: [{
			label: t('Create dependent item'),
			url: url.getUrl()
		}]
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

				jQuery(this).closest('.menu-popup').menuPopup('close', null);
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
 * @param array scripts           Scripts names.
 * @param array hostId            Host ID.
 * @param {object} trigger_elmnt  UI element which triggered opening of overlay dialogue.
 *
 * @returns array
 */
function getMenuPopupScriptData(scripts, hostId, trigger_elmnt) {
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
	var getMenuPopupScriptItems = function(tree, trigger_elm) {
		var items = [];

		if (objectSize(tree) > 0) {
			jQuery.each(tree, function(name, data) {
				var item = {label: name};

				if (typeof data.items !== 'undefined' && objectSize(data.items) > 0) {
					item.items = getMenuPopupScriptItems(data.items, trigger_elm);
				}

				if (typeof data.params !== 'undefined' && typeof data.params.scriptId !== 'undefined') {
					item.clickCallback = function(e) {
						jQuery(this).closest('.menu-popup-top').menuPopup('close', trigger_elm, false);
						executeScript(data.params.hostId, data.params.scriptId, data.params.confirmation, trigger_elm);
						cancelEvent(e);
					};
				}

				items[items.length] = item;
			});
		}

		return items;
	};

	return getMenuPopupScriptItems(tree, trigger_elmnt);
}

jQuery(function($) {

	/**
	 * Menu popup.
	 *
	 * @param array  sections              Menu sections.
	 * @param string sections[n]['label']  Section title (optional).
	 * @param array  sections[n]['items']  Section menu data (see createMenuItem() for available options).
	 * @param object event                 Menu popup call event.
	 *
	 * @see createMenuItem()
	 */
	$.fn.menuPopup = function(method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		}
		else {
			return methods.init.apply(this, arguments);
		}
	};

	var methods = {
		init: function(sections, event) {
			var opener = $(this),
				id = opener.data('menu-popup-id'),
				menuPopup = $('#' + id),
				mapContainer = null,
				position_target = event.target;

			if (event.type === 'contextmenu' || (IE && opener.closest('svg').length > 0)
					|| event.originalEvent.detail !== 0) {
				position_target = event;
			}

			opener.attr('data-expanded', 'true');

			// Close other action menus.
			$('.menu-popup-top').not('#' + id).menuPopup('close');

			if (menuPopup.length > 0) {
				var display = menuPopup.css('display');

				// Hide current action menu sub-levels.
				$('.menu-popup', menuPopup).css('display', 'none');

				if (display === 'block') {
					menuPopup.fadeOut(0);
					$(opener).removeAttr('data-expanded');
				}
				else {
					menuPopup.fadeIn(50);
				}

				menuPopup.position({
					of: position_target,
					my: 'left top',
					at: 'left bottom'
				});
			}
			else {
				id = new Date().getTime();

				menuPopup = $('<ul>', {
					'id': id,
					'role': 'menu',
					'class': 'menu-popup menu-popup-top',
					'tabindex': 0
				});

				// create sections
				var sections_length = sections.length;
				if (sections_length) {
					$.each(sections, function(i, section) {
						if ((typeof section.label !== 'undefined') && (section.label.length > 0)) {
							var h3 = $('<h3>').text(section.label);
							var sectionItem = $('<li>').append(h3);
						}

						// Add section delimited for all sections except first one.
						if (i > 0) {
							menuPopup.append($('<li>').append($('<div>')));
						}
						menuPopup.append(sectionItem);

						$.each(section.items, function(i, item) {
							if (sections_length > 1) {
								item['ariaLabel'] = section.label + ', ' + item['label'];
							}
							menuPopup.append(createMenuItem(item));
						});
					});
				}

				if (sections_length == 1) {
					menuPopup.attr({'aria-label': sections[0].label});
				}

				// Skip displaying empty menu sections.
				if (menuPopup.children().length == 0) {
					return;
				}

				// Set menu popup for map area.
				if (opener.prop('tagName') === 'AREA') {
					$('.menuPopupContainer').remove();

					mapContainer = $('<div>', {
						'class': 'menuPopupContainer',
						'css': {
							position: 'absolute',
							top: event.pageY,
							left: event.pageX
						}
					})
					.append(menuPopup);

					$('body').append(mapContainer);
				}
				// Set menu popup for common html elements.
				else {
					opener.data('menu-popup-id', id);

					$('body').append(menuPopup);
				}

				// Hide current action menu sub-levels.
				$('.menu-popup', menuPopup).css('display', 'none');

				// display
				menuPopup
					.fadeIn(50)
					.data('is-active', false)
					.mouseenter(function() {
						menuPopup.data('is-active', true);

						clearTimeout(window.menuPopupTimeoutHandler);
					})
					.on('click', function(e) {
						e.stopPropagation();
					})
					.position({
						of: (opener.prop('tagName') === 'AREA') ? mapContainer : position_target,
						my: 'left top',
						at: 'left bottom'
					});
			}

			addToOverlaysStack('menu-popup', event.target, 'menu-popup');

			$(document)
				.on('click', {menu: menuPopup, opener: opener}, menuPopupDocumentCloseHandler)
				.on('keydown', {menu: menuPopup}, menuPopupKeyDownHandler);

			menuPopup.focus();
		},
		close: function(trigger_elmnt, return_focus) {
			var menuPopup = $(this);
			if (!menuPopup.is(trigger_elmnt) && menuPopup.has(trigger_elmnt).length === 0) {
				menuPopup.data('is-active', false);
				$(trigger_elmnt).removeAttr('data-expanded');
				menuPopup.fadeOut(0);

				$('.highlighted', menuPopup).removeClass('highlighted');
				$('[aria-expanded="true"]', menuPopup).attr({'aria-expanded': 'false'});

				$(document)
					.off('click', menuPopupDocumentCloseHandler)
					.off('keydown', menuPopupKeyDownHandler);

				removeFromOverlaysStack('menu-popup', return_focus);
				menuPopup.remove();
			}
		}
	};

	/**
	 * Expends hovered/selected context menu item.
	 */
	$.fn.actionMenuItemExpand = function() {
		var li = $(this),
			pos = li.position(),
			menu = li.closest('.menu-popup');

		for (var item = $('li:first-child', menu); item.length > 0; item = item.next()) {
			if (item[0] == li[0]) {
				$('>a', li[0]).addClass('highlighted');

				if (!$('ul', item[0]).is(':visible')) {
					$('ul:first', item[0]).prev('[role="menuitem"]').attr({'aria-expanded': 'true'});

					$('ul:first', item[0])
						.css({
							'top': pos.top - 6,
							'left': pos.left + li.outerWidth() + 14,
							'display': 'block'
						});
				}
			}
			else {
				// Remove activity from item that has been selected by keyboard and now is deselected using mouse.
				if ($('>a', item[0]).hasClass('highlighted')) {
					$('>a', item[0]).removeClass('highlighted').blur();
				}

				// Closes all other submenus from this level, if they were open.
				if ($('ul', item[0]).is(':visible')) {
					$('ul', item[0]).prev('[role="menuitem"]').removeClass('highlighted');
					$('ul', item[0]).prev('[role="menuitem"]').attr({'aria-expanded': 'false'});
					$('ul', item[0]).css({'display': 'none'});
				}
			}
		}

		return this;
	};

	/**
	 * Collapses context menu item that has lost focus or is not selected anymore.
	 */
	$.fn.actionMenuItemCollapse = function() {
		// Remove style and close sub-menus in deeper levels.
		var parent_menu = $(this).closest('.menu-popup');
		$('.highlighted', parent_menu).removeClass('highlighted');
		$('[aria-expanded]', parent_menu).attr({'aria-expanded': 'false'});
		$('.menu-popup', parent_menu).css({'display': 'none'});

		// Close actual menu level.
		parent_menu.not('.menu-popup-top').css({'display': 'none'});
		parent_menu.prev('[role="menuitem"]').attr({'aria-expanded': 'false'});

		return this;
	};

	function menuPopupDocumentCloseHandler(event) {
		$(event.data.menu[0]).menuPopup('close', event.data.opener);
	}

	function menuPopupKeyDownHandler(event) {
		var link_selector = '.menu-popup-item',
			menu_popup = $(event.data.menu[0]),
			level = menu_popup,
			selected,
			items;

		// Find active menu level.
		while ($('[aria-expanded="true"]:visible', level).length) {
			level = $('[aria-expanded="true"]:visible:first', level.get(0)).next('[role="menu"]');
		}

		// Find active menu items.
		items = $('>li', level).filter(function() {
			return $(this).has('.menu-popup-item').length;
		});

		// Find an element that was selected when key was pressed.
		if ($('.menu-popup-item.highlighted', level).length) {
			selected = $(link_selector + '.highlighted', level).closest('li');
		}
		else if ($('.menu-popup-item', level).filter(function() {
			return this == document.activeElement;
		}).length) {
			selected = $(document.activeElement).closest('li');
		}

		// Perform action based on keydown event.
		switch (event.which) {
			case 37: // arrow left
				if (typeof selected !== 'undefined' && selected.has('.menu-popup')) {
					if (level != menu_popup) {
						selected.actionMenuItemCollapse();

						// Must focus previous element, otherwise screen reader will exit menu.
						selected.closest('.menu-popup').prev('[role="menuitem"]').addClass('highlighted').focus();
					}
				}
				break;

			case 38: // arrow up
				if (typeof selected === 'undefined') {
					$(link_selector + ':last', level).addClass('highlighted').focus();
				}
				else {
					var prev = items[items.index(selected) - 1];
					if (typeof prev === 'undefined') {
						prev = items[items.length - 1];
					}

					$(link_selector, selected).removeClass('highlighted');
					$(link_selector + ':first', prev).addClass('highlighted').focus();
				}

				// Prevent page scrolling.
				event.preventDefault();
				break;

			case 39: // arrow right
				if (typeof selected !== 'undefined' && selected.has('.menu-popup')) {
					selected.actionMenuItemExpand();
					$('ul > li ' + link_selector + ':first', selected).addClass('highlighted').focus();
				}
				break;

			case 40: // arrow down
				if (typeof selected === 'undefined') {
					$(link_selector + ':first', items[0]).addClass('highlighted').focus();
				}
				else {
					var next = items[items.index(selected) + 1];
					if (typeof next === 'undefined') {
						next = items[0];
					}

					$(link_selector, selected).removeClass('highlighted');
					$(link_selector + ':first', next).addClass('highlighted').focus();
				}

				// Prevent page scrolling.
				event.preventDefault();
				break;

			case 27: // ESC
				$(menu_popup).menuPopup('close', null);
				break;

			case 13: // Enter
				if (typeof selected !== 'undefined') {
					$('>' + link_selector, selected)[0].click();
				}
				break;

			case 9: // Tab
				event.preventDefault();
				break;
		}

		return false;
	}

	/**
	 * Create menu item.
	 *
	 * @param string options['label']          Link label.
	 * @param string options['ariaLabel']	   Aria-label text.
	 * @param string options['url']            Link url.
	 * @param string options['css']            Item class.
	 * @param array  options['data']           Item data ("key" => "value").
	 * @param array  options['items']          Item sub menu.
	 * @param object options['clickCallback']  Item click callback.
	 *
	 * @return object
	 */
	function createMenuItem(options) {
		options = $.extend({
			ariaLabel: options.label,
			selected: false,
			disabled: false
		}, options);

		var item = $('<li>'),
			link = $('<a>', {
				role: 'menuitem',
				tabindex: '-1',
				'aria-label': options.selected ? sprintf(t('S_SELECTED_SR'), options.ariaLabel) : options.ariaLabel
			}).data('aria-label', options.ariaLabel);

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

		if (options.disabled) {
			link.addClass('menu-popup-item-disabled');
		}
		else {
			link.addClass('menu-popup-item');

			if (typeof options.url !== 'undefined') {
				link.attr('href', options.url);
			}

			if (typeof options.clickCallback !== 'undefined') {
				link.on('click', options.clickCallback);
			}
		}

		if (options.selected) {
			link.addClass('selected');
		}

		if (typeof options.items !== 'undefined' && options.items.length > 0) {
			link.attr({
				'aria-haspopup': 'true',
				'aria-expanded': 'false',
				'area-hidden': 'true'
			});
		}

		item.append(link);

		if (typeof options.items !== 'undefined' && options.items.length > 0) {
			var menu = $('<ul>', {
					class : 'menu-popup',
					role: 'menu'
				})
				.on('mouseenter', function(e) {
					// Prevent 'mouseenter' event in parent item, that would call actionMenuItemExpand() for parent.
					e.stopPropagation();
				});

			$.each(options.items, function(i, item) {
				menu.append(createMenuItem(item));
			});

			item.append(menu);
		}

		item.on('mouseenter', function(e) {
			e.stopPropagation();
			$(this).actionMenuItemExpand();
		});

		return item;
	}
});
