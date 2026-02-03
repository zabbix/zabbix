/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

	if (!options.allowed_ui_latest_data) {
		return [];
	}

	url.setArgument('itemids[]', options.itemid);

	// latest graphs
	if (options.hasLatestGraphs !== undefined && options.hasLatestGraphs) {
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
 * @param {string} options['hostid']                  Host ID.
 * @param {array}  options['scripts']                 Host scripts (optional).
 * @param {string} options[]['name']                  Script name.
 * @param {string} options[]['scriptid']              Script ID.
 * @param {string} options[]['confirmation']          Confirmation text.
 * @param {bool}   options['showGraphs']              Link to Monitoring->Hosts->Graphs page.
 * @param {bool}   options['showDashboards']          Link to Monitoring->Hosts->Dashboards page.
 * @param {bool}   options['showWeb']		          Link to Monitoring->Hosts->Web page.
 * @param {bool}   options['showTriggers']            Link to Monitoring->Problems page.
 * @param {bool}   options['hasGoTo']                 "Go to" block in popup.
 * @param {array}  options['severities']              (optional)
 * @param {bool}   options['show_suppressed']         (optional)
 * @param {array}  options['urls']                    (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 * @param {array}  options['tags']                    (optional)
 * @param {string} options['tags'][]['tag']
 * @param {string} options['tags'][]['value']
 * @param {number} options['tags'][]['operator']
 * @param {number} options['evaltype']                (optional)
 * @param {bool}   options['allowed_ui_inventory']    Whether user has access to inventory hosts page.
 * @param {bool}   options['allowed_ui_latest_data']  Whether user has access to latest data page.
 * @param {bool}   options['allowed_ui_problems']     Whether user has access to problems page.
 * @param {bool}   options['allowed_ui_hosts']        Whether user has access to monitoring hosts pages.
 * @param {bool}   options['allowed_ui_conf_hosts']   Whether user has access to configuration hosts page.
 * @param {Node}   trigger_element                    UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupHost(options, trigger_element) {
	var sections = [];

	// go to section
	if (options.hasGoTo) {
		var	host_inventory = {
			label: t('Inventory')
		},
		latest_data = {
			label: t('Latest data')
		},
		problems = {
			label: t('Problems')
		},
		graphs = {
			label: t('Graphs')
		},
		dashboards = {
			label: t('Dashboards')
		},
		web = {
			label: t('Web')
		};

		// inventory link
		var url = new Curl('hostinventories.php', false);

		url.setArgument('hostid', options.hostid);
		host_inventory.url = url.getUrl();

		// latest data link
		var url = new Curl('zabbix.php', false);

		url.setArgument('action', 'latest.view');

		if (typeof options.tags !== 'undefined') {
			url.setArgument('tags', options.tags);
			url.setArgument('evaltype', options.evaltype);
		}

		url.setArgument('hostids[]', options.hostid);
		url.setArgument('filter_set', '1');
		latest_data.url = url.getUrl();

		if (!options.showTriggers) {
			problems.disabled = true;
		}
		else {
			var url = new Curl('zabbix.php', false);

			url.setArgument('action', 'problem.view');
			url.setArgument('filter_set', '1');
			url.setArgument('hostids[]', options.hostid);

			if (options.severities !== undefined) {
				url.setArgument('severities[]', options.severities);
			}

			if (options.show_suppressed !== undefined && options.show_suppressed) {
				url.setArgument('show_suppressed', '1');
			}

			if (options.tags !== undefined) {
				url.setArgument('tags', options.tags);
				url.setArgument('evaltype', options.evaltype);
			}

			problems.url = url.getUrl();
		}

		if (!options.showGraphs) {
			graphs.disabled = true;
		}
		else {
			var graphs_url = new Curl('zabbix.php', false);

			graphs_url.setArgument('action', 'charts.view')
			graphs_url.setArgument('filter_hostids[]', options.hostid);
			graphs_url.setArgument('filter_set', '1');
			graphs.url = graphs_url.getUrl();
		}

		if (!options.showDashboards) {
			dashboards.disabled = true;
		}
		else {
			var dashboards_url = new Curl('zabbix.php', false);

			dashboards_url.setArgument('action', 'host.dashboard.view')
			dashboards_url.setArgument('hostid', options.hostid)
			dashboards.url = dashboards_url.getUrl();
		}

		if (!options.showWeb) {
			web.disabled = true;
		}
		else {
			var web_url = new Curl('zabbix.php', false);

			web_url.setArgument('action', 'web.view');
			web_url.setArgument('filter_hostids[]', options.hostid);
			web_url.setArgument('filter_set', '1');
			web.url = web_url.getUrl();
		}

		var items = [];

		if (options.allowed_ui_inventory) {
			items.push(host_inventory);
		}

		if (options.allowed_ui_latest_data) {
			items.push(latest_data);
		}

		if (options.allowed_ui_problems) {
			items.push(problems);
		}

		if (options.allowed_ui_hosts) {
			items.push(graphs);
			items.push(dashboards);
			items.push(web);
		}

		if (options.allowed_ui_conf_hosts) {
			var config = {
				label: t('Configuration'),
				disabled: !options.isWriteable
			};

			if (options.isWriteable) {
				const config_url = new Curl('zabbix.php', false);
				config_url.setArgument('action', 'host.edit');
				config_url.setArgument('hostid', options.hostid);
				config.url = config_url.getUrl();

				config.clickCallback = function (e) {
					e.preventDefault();
					jQuery(this).closest('.menu-popup').menuPopup('close', null);

					view.editHost(options.hostid);
				};
			}

			items.push(config);
		}

		if (items.length) {
			sections.push({
				label: t('Host'),
				items: items
			});
		}
	}

	// urls
	if (options.urls !== undefined) {
		sections.push({
			label: t('URLs'),
			items: options.urls
		});
	}

	// scripts
	if (options.scripts !== undefined) {
		sections.push({
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, trigger_element, options.hostid)
		});
	}

	return sections;
}

/**
 * Get menu popup submap map element section data.
 *
 * @param {array}  options['sysmapid']
 * @param {int}    options['severity_min']    (optional)
 * @param {int}    options['is_widget']       (optional)
 * @param {array}  options['urls']            (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 *
 * @return array
 */
function getMenuPopupMapElementSubmap(options) {
	const sections = [];
	const item = {label: t('Submap')};

	if (options.unique_id !== undefined) {
		item.clickCallback = ()=> {
			ZABBIX.Dashboard.getDashboardPages().forEach((page) => {
				const widget = page.getWidget(options.unique_id);

				if (widget !== null) {
					widget.navigateToSubmap(options.sysmapid);
				}
			});
		};
	}
	else {
		if (!options.allowed_ui_maps) {
			return [];
		}

		const submap_url = new Curl('zabbix.php', false);
		submap_url.setArgument('action', 'map.view');
		submap_url.setArgument('sysmapid', options.sysmapid);

		if (options.severity_min !== undefined) {
			submap_url.setArgument('severity_min', options.severity_min);
		}

		item.url = submap_url.getUrl();
	}

	sections.push({
		label: t('Go to'),
		items: [item]
	});

	// urls
	if (options.urls !== undefined) {
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
 * @param {array}  options['severities']         (optional)
 * @param {bool}   options['show_suppressed']    (optional)
 * @param {array}  options['urls']               (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 * @param {array}  options['tags']               (optional)
 * @param {string} options['tags'][]['tag']
 * @param {string} options['tags'][]['value']
 * @param {number} options['tags'][]['operator']
 * @param {number} options['evaltype']           (optional)
 *
 * @return array
 */
function getMenuPopupMapElementGroup(options) {
	if (!options.allowed_ui_problems) {
		return [];
	}

	var sections = [],
		problems_url = new Curl('zabbix.php', false);

	problems_url.setArgument('action', 'problem.view');
	problems_url.setArgument('filter_set', '1');
	problems_url.setArgument('groupids[]', options.groupid);

	if (options.severities !== undefined) {
		problems_url.setArgument('severities[]', options.severities);
	}

	if (options.show_suppressed !== undefined && options.show_suppressed) {
		problems_url.setArgument('show_suppressed', '1');
	}

	if (options.tags !== undefined) {
		problems_url.setArgument('tags', options.tags);
		problems_url.setArgument('evaltype', options.evaltype);
	}

	sections.push({
		label: t('Go to'),
		items: [{
			label: t('Problems'),
			url: problems_url.getUrl()
		}]
	});

	// urls
	if (options.urls !== undefined) {
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
 * @param {array}  options['severities']       (optional)
 * @param {bool}   options['show_suppressed']  (optional)
 * @param {array}  options['urls']             (optional)
 * @param {string} options['url'][]['label']
 * @param {string} options['url'][]['url']
 *
 * @return array
 */
function getMenuPopupMapElementTrigger(options) {
	if (!options.allowed_ui_problems) {
		return [];
	}

	var sections = [],
		problems_url = new Curl('zabbix.php', false);

	problems_url.setArgument('action', 'problem.view');
	problems_url.setArgument('filter_set', '1');
	problems_url.setArgument('triggerids[]', options.triggerids);

	if (options.severities !== undefined) {
		problems_url.setArgument('severities[]', options.severities);
	}

	if (options.show_suppressed !== undefined && options.show_suppressed) {
		problems_url.setArgument('show_suppressed', '1');
	}

	sections.push({
		label: t('Go to'),
		items: [{
			label: t('Problems'),
			url: problems_url.getUrl()
		}]
	});

	// urls
	if (options.urls !== undefined) {
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
	if (options.urls !== undefined) {
		return [{
			label: t('URLs'),
			items: options.urls
		}];
	}

	return [];
}

/**
 * Get menu popup dashboard actions data.
 *
 * @param {string} options['dashboardid']
 * @param {bool}   options['editable']
 * @param {bool}   options['has_related_reports']
 * @param {bool}   options['can_edit_dashboards']
 * @param {bool}   options['can_view_reports']
 * @param {bool}   options['can_create_reports']
 * @param {object} trigger_element                   UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupDashboard(options, trigger_element) {
	const sections = [];
	const parameters = {dashboardid: options.dashboardid};

	// Dashboard actions.
	if (options.can_edit_dashboards) {
		const url_create = new Curl('zabbix.php', false);
		url_create.setArgument('action', 'dashboard.view');
		url_create.setArgument('new', '1');

		const url_clone = new Curl('zabbix.php', false);
		url_clone.setArgument('action', 'dashboard.view');
		url_clone.setArgument('source_dashboardid', options.dashboardid);

		const url_delete = new Curl('zabbix.php', false);
		url_delete.setArgument('action', 'dashboard.delete');
		url_delete.setArgument('dashboardids', [options.dashboardid]);

		sections.push({
			label: t('Actions'),
			items: [
				{
					label: t('Sharing'),
					clickCallback: function () {
						jQuery(this).closest('.menu-popup').menuPopup('close', null);

						PopUp('popup.dashboard.share.edit', parameters, {
							dialogueid: 'dashboard_share_edit',
							dialogue_class: 'modal-popup-generic',
							trigger_element
						});
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
					clickCallback: function () {
						jQuery(this).closest('.menu-popup').menuPopup('close', null);

						if (!confirm(t('Delete dashboard?'))) {
							return false;
						}

						redirect(url_delete.getUrl(), 'post', 'sid', true, true);
					},
					disabled: !options.editable
				}
			]
		});
	}

	// Report actions.
	if (options.can_view_reports) {
		const report_actions = [
			{
				label: t('View related reports'),
				clickCallback: function () {
					jQuery(this).closest('.menu-popup').menuPopup('close', null);

					PopUp('popup.scheduledreport.list', parameters, {
						dialogue_class: 'modal-popup-generic',
						trigger_element
					});
				},
				disabled: !options.has_related_reports
			}
		];

		if (options.can_create_reports) {
			report_actions.unshift({
				label: t('Create new report'),
				clickCallback: function () {
					jQuery(this).closest('.menu-popup').menuPopup('close', null);

					PopUp('popup.scheduledreport.edit', parameters, {
						dialogue_class: 'modal-popup-generic',
						trigger_element
					});
				}
			});
		}

		sections.push({
			label: options.can_edit_dashboards ? null : t('Actions'),
			items: report_actions
		})
	}

	return sections;
}

/**
 * Get menu popup trigger section data.
 *
 * @param {string} options['triggerid']               Trigger ID.
 * @param {string} options['eventid']                 (optional) Required for Acknowledge section.
 * @param {object} options['items']                   Link to trigger item history page (optional).
 * @param {string} options['items'][]['name']         Item name.
 * @param {object} options['items'][]['params']       Item URL parameters ("name" => "value").
 * @param {bool}   options['acknowledge']             (optional) Whether to show Acknowledge section.
 * @param {object} options['configuration']           Link to trigger configuration page (optional).
 * @param {bool}   options['showEvents']              Show Problems item enabled. Default: false.
 * @param {string} options['url']                     Trigger URL link (optional).
 * @param {object} trigger_element                      UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupTrigger(options, trigger_element) {
	var sections = [],
		items = [];

	if (options.allowed_ui_problems) {
		// events
		var events = {
			label: t('Problems')
		};

	if (options.showEvents !== undefined && options.showEvents) {
		var url = new Curl('zabbix.php', false);
		url.setArgument('action', 'problem.view');
		url.setArgument('filter_set', '1');
		url.setArgument('triggerids[]', options.triggerid);

			events.url = url.getUrl();
		}
		else {
			events.disabled = true;
		}

		items[items.length] = events;
	}

	// acknowledge
	if (options.acknowledge !== undefined && options.acknowledge) {
		items[items.length] = {
			label: t('Acknowledge'),
			clickCallback: function() {
				jQuery(this).closest('.menu-popup-top').menuPopup('close', null);

				acknowledgePopUp({eventids: [options.eventid]}, trigger_element);
			}
		};
	}

	// configuration
	if (options.allowed_ui_conf_hosts) {
		var url = new Curl('triggers.php', false);

		url.setArgument('form', 'update');
		url.setArgument('triggerid', options.triggerid);
		url.setArgument('context', 'host');

		items[items.length] = {
			label: t('Configuration'),
			url: url.getUrl()
		};
	}

	if (items.length) {
		sections[sections.length] = {
			label: t('S_TRIGGER'),
			items: items
		};
	}

	// urls
	if ('urls' in options) {
		sections[sections.length] = {
			label: t('Links'),
			items: options.urls
		};
	}

	// items
	if (options.allowed_ui_latest_data && options.items !== undefined && objectSize(options.items) > 0) {
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

	// scripts
	if (options.scripts !== undefined) {
		sections.push({
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, trigger_element, null, options.eventid)
		});
	}

	return sections;
}

/**
 * Get menu popup latest data item log section data.
 *
 * @param string options['itemid']
 * @param string options['hostid']
 * @param bool   options['showGraph']                   Link to Monitoring->Items->Graphs page.
 * @param bool   options['history']                     Is history available.
 * @param bool   options['trends']                      Are trends available.
 * @param bool   options['allowed_ui_conf_hosts']       Whether user has access to configuration hosts pages.
 * @param bool   options['isWriteable']                 Whether user has read and write access to host and its items.
 *
 * @return array
 */
function getMenuPopupItem(options) {
	const items = [];
	let url;

	url = new Curl('history.php', false);
	url.setArgument('action', 'showgraph');
	url.setArgument('itemids[]', options.itemid);

	items.push({
		label: t('Graph'),
		url: url.getUrl(),
		disabled: !options.showGraph
	});

	url = new Curl('history.php', false);
	url.setArgument('action', 'showvalues');
	url.setArgument('itemids[]', options.itemid);

	const values = {
		label: t('Values'),
		url: url.getUrl()
	}

	url = new Curl('history.php', false);
	url.setArgument('action', 'showlatest');
	url.setArgument('itemids[]', options.itemid);

	const latest = {
		label: t('500 latest values'),
		url: url.getUrl()
	}

	if (!options.history && !options.trends) {
		values.disabled = true;
		latest.disabled = true;
	}

	items.push(values);
	items.push(latest);

	if (options.allowed_ui_conf_hosts) {
		const config = {
			label: t('Configuration'),
			disabled: !options.isWriteable
		};

		if (options.isWriteable) {
			url = new Curl('items.php', false);
			url.setArgument('form', 'update');
			url.setArgument('hostid', options.hostid);
			url.setArgument('itemid', options.itemid);
			url.setArgument('context', 'host');

			config.url = url.getUrl();
		}

		items.push(config);
	}

	return [{
		label: t('Item'),
		items: items
	}];
}

/**
 * Get menu popup item log section data.
 *
 * @param string options['backurl']                   Url from where the popup menu was called.
 * @param string options['itemid']
 * @param string options['hostid']
 * @param string options['host']                      Host name.
 * @param string options['name']
 * @param string options['key']                       Item key.
 * @param array  options['triggers']                  (optional)
 * @param string options['triggers'][n]['triggerid']
 * @param string options['triggers'][n]['name']
 * @param bool   options['allowed_ui_latest_data']    Whether user has access to latest data page.
 * @param string options['context']                   Additional parameter in URL to identify main section.
 *
 * @return array
 */
function getMenuPopupItemConfiguration(options) {
	const items = [];
	let url;

	if (options.context === 'host' && options.allowed_ui_latest_data) {
		url = new Curl('zabbix.php', false);
		url.setArgument('action', 'latest.view');
		url.setArgument('hostids[]', options.hostid);
		url.setArgument('name', options.name);
		url.setArgument('filter_set', '1');

		items.push({
			label: t('Latest data'),
			url: url.getUrl()
		});
	}

	url = new Curl('triggers.php', false);
	url.setArgument('form', 'create');
	url.setArgument('hostid', options.hostid);
	url.setArgument('description', options.name);
	url.setArgument('expression', 'func(/' + options.host + '/' + options.key + ')');
	url.setArgument('context', options.context);
	url.setArgument('backurl', options.backurl);

	items.push({
		label: t('Create trigger'),
		url: url.getUrl()
	});

	if (options.triggers.length > 0) {
		const triggers = [];

		jQuery.each(options.triggers, function (i, trigger) {
			url = new Curl('triggers.php', false);
			url.setArgument('form', 'update');
			url.setArgument('triggerid', trigger.triggerid);
			url.setArgument('context', options.context);
			url.setArgument('backurl', options.backurl);

			triggers.push({
				label: trigger.name,
				url: url.getUrl()
			});
		});

		items.push({
			label: t('Triggers'),
			items: triggers
		})
	}

	url = new Curl('items.php', false);
	url.setArgument('form', 'create');
	url.setArgument('hostid', options.hostid);
	url.setArgument('type', 18);	// ITEM_TYPE_DEPENDENT
	url.setArgument('master_itemid', options.itemid);
	url.setArgument('context', options.context);

	items.push({
		label: t('Create dependent item'),
		url: url.getUrl(),
		disabled: !options.create_dependent_item
	});

	url = new Curl('host_discovery.php', false);
	url.setArgument('form', 'create');
	url.setArgument('hostid', options.hostid);
	url.setArgument('type', 18);	// ITEM_TYPE_DEPENDENT
	url.setArgument('master_itemid', options.itemid);
	url.setArgument('context', options.context);
	url.setArgument('backurl', options.backurl);

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
 * @param array  options['name']
 * @param string options['backurl']                             Url from where the popup menu was called.
 * @param array  options['key']                                 Item prototype key.
 * @param array  options['host']                                Host name.
 * @param array  options['itemid']
 * @param array  options['trigger_prototypes']                  (optional)
 * @param string options['trigger_prototypes'][n]['triggerid']
 * @param string options['trigger_prototypes'][n]['name']
 * @param array  options['parent_discoveryid']
 * @param string options['context']                             Additional parameter in URL to identify main section.
 *
 * @return array
 */
function getMenuPopupItemPrototypeConfiguration(options) {
	const items = [];
	let url;

	url = new Curl('trigger_prototypes.php', false);
	url.setArgument('parent_discoveryid', options.parent_discoveryid);
	url.setArgument('form', 'create');
	url.setArgument('description', options.name);
	url.setArgument('expression', 'func(/' + options.host + '/' + options.key + ')');
	url.setArgument('context', options.context);
	url.setArgument('backurl', options.backurl);

	items.push({
		label: t('Create trigger prototype'),
		url: url.getUrl()
	});

	if (options.trigger_prototypes.length > 0) {
		const trigger_prototypes = [];

		jQuery.each(options.trigger_prototypes, function (i, trigger) {
			url = new Curl('trigger_prototypes.php', false);
			url.setArgument('form', 'update');
			url.setArgument('parent_discoveryid', options.parent_discoveryid);
			url.setArgument('triggerid', trigger.triggerid)
			url.setArgument('context', options.context);
			url.setArgument('backurl', options.backurl);

			trigger_prototypes.push({
				label: trigger.name,
				url: url.getUrl()
			});
		});

		items.push({
			label: t('Trigger prototypes'),
			items: trigger_prototypes
		});
	}

	url = new Curl('disc_prototypes.php', false);
	url.setArgument('form', 'create');
	url.setArgument('parent_discoveryid', options.parent_discoveryid);
	url.setArgument('type', 18);	// ITEM_TYPE_DEPENDENT
	url.setArgument('master_itemid', options.itemid);
	url.setArgument('context', options.context);

	items.push({
		label: t('Create dependent item'),
		url: url.getUrl()
	});

	return [{
		label: options.name,
		items: items
	}];
}

/**
 * Get dropdown section data.
 *
 * @param {array}  options
 * @param {object} trigger_elem  UI element that was clicked to open overlay dialogue.
 *
 * @returns array
 */
function getMenuPopupDropdown(options, trigger_elem) {
	var items = [];

	jQuery.each(options.items, function(i, item) {
		var row = {
			label: item.label,
			url: item.url || 'javascript:void(0);'
		};

		if (item.class) {
			row.class = item.class;
		}

		if (options.toggle_class) {
			row.clickCallback = () => {
				jQuery(trigger_elem)
					.removeClass()
					.addClass(['btn-alt', options.toggle_class, item.class].join(' '));

				jQuery('input[type=hidden]', jQuery(trigger_elem).parent())
					.val(item.value)
					.trigger('change');
			}
		}
		else if (options.submit_form) {
			row.url = 'javascript:void(0);';
			row.clickCallback = () => {
				var $_form = trigger_elem.closest('form');

				if (!$_form.data("action")) {
					$_form.data("action", $_form.attr("action"));
				}

				$_form.attr("action", item.url);
				$_form.submit();
			}
		}

		items.push(row);
	});

	return [{
		items: items
	}];
}

/**
 * Get menu popup submenu section data.
 *
 * @param object options['submenu']                                    List of menu sections.
 * @param object options['submenu'][section]                           An individual section definition.
 * @param string options['submenu'][section]['label']                  Non-clickable section label.
 * @param object options['submenu'][section]['items']                  List of menu items of the section.
 * @param string options['submenu'][section]['items'][url]             Menu item label for the given url.
 * @param object options['submenu'][section]['items'][key]             Menu item with a submenu.
 * @param object options['submenu'][section]['items'][key]['label']    Non-clickable subsection label.
 * @param object options['submenu'][section]['items'][key]['items']    List of menu items of the subsection.
 * @param object options['submenu'][section]['items'][key]['items'][]  More levels of submenu.
 *
 * @returns array
 */
function getMenuPopupSubmenu(options) {
	var transform = function(sections) {
			var result = [];

			for (var key in sections) {
				if (typeof sections[key] === 'object') {
					var item = {};
					for (var item_key in sections[key]) {
						if (item_key === 'items') {
							item[item_key] = transform(sections[key][item_key]);
						}
						else {
							item[item_key] = sections[key][item_key];
						}
					}
					result.push(item);
				}
				else {
					result.push({
						'label': sections[key],
						'url': key
					});
				}
			}

			return result;
		};

	return transform(options.submenu);
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
 * @param array scripts           Script names amd nenu paths.
 * @param {Node} trigger_element  UI element which triggered opening of overlay dialogue.
 * @param array hostid            Host ID.
 * @param array eventid           Event ID.
 *
 * @returns array
 */
function getMenuPopupScriptData(scripts, trigger_element, hostid, eventid) {
	var tree = {};

	var appendTreeItem = function(tree, name, items, params) {
		if (items.length > 0) {
			var item = items.shift();

			if (tree[item] === undefined) {
				tree[item] = {
					name: item,
					items: {}
				};
			}

			appendTreeItem(tree[item].items, name, items, params);
		}
		else {
			tree['/' + name] = {
				name: name,
				params: params,
				items: {}
			};
		}
	};

	// parse scripts and create tree
	for (var key in scripts) {
		var script = scripts[key];

		if (script.scriptid !== undefined) {
			var items = (script.menu_path.length > 0) ? splitPath(script.menu_path) : [];

			appendTreeItem(tree, script.name, items, {
				scriptid: script.scriptid,
				confirmation: script.confirmation,
				hostid: hostid,
				eventid: eventid
			});
		}
	}

	// Build menu items from tree.
	var getMenuPopupScriptItems = function(tree, trigger_elm) {
		var items = [];

		if (objectSize(tree) > 0) {
			jQuery.each(tree, function(key, data) {
				var item = {label: data.name};

				if (data.items !== undefined && objectSize(data.items) > 0) {
					item.items = getMenuPopupScriptItems(data.items, trigger_elm);
				}

				if (data.params !== undefined && data.params.scriptid !== undefined) {
					item.clickCallback = function(e) {
						jQuery(this)
							.closest('.menu-popup-top')
							.menuPopup('close', trigger_elm, false);
						executeScript(data.params.scriptid, data.params.confirmation, trigger_elm, data.params.hostid,
							data.params.eventid
						);
						cancelEvent(e);
					};
				}

				items[items.length] = item;
			});
		}

		return items;
	};

	return getMenuPopupScriptItems(tree, trigger_element);
}

const WRAPPER_PADDING_RIGHT = 10;

jQuery(function($) {
	const MENU_BORDER = 1;

	const MENU_PADDING_LEFT = 25;
	const MENU_PADDING_RIGHT = 15;
	const MENU_PADDING_Y = 5;

	const WRAPPER_PADDING_Y = 10;

	const SUBMENU_DELTA_Y = 15;

	const SUBMENU_DELAY = 600;

	/**
	 * Menu popup.
	 *
	 * @param array  sections              Menu sections.
	 * @param string sections[n]['label']  Section title (optional).
	 * @param array  sections[n]['items']  Section menu data (see createMenuItem() for available options).
	 * @param object event                 Menu popup call event.
	 * @param object options               Menu popup options (optional).
	 * @param object options['class']      Menu popup additional class name (optional).
	 * @param object options['position']   Menu popup position object (optional).
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

	/**
	 * Created popup menu item nodes and append to $menu_popup.
	 *
	 * @param {object} $menu_popup    jQuery node of popup menu.
	 * @param {array} sections        Array of menu popup sections.
	 */
	function addMenuPopupItems($menu_popup, sections) {
		// Create menu sections.
		$.each(sections, function(i, section) {
			// Add a separator between menu item sections.
			if (i > 0) {
				$menu_popup.append($('<li>').append($('<div>')));
			}

			let section_label = null;

			if (typeof section.label === 'string' && section.label.length) {
				section_label = section.label;
			}

			// Add menu item section label, if provided.
			if (section_label !== null) {
				$menu_popup.append($('<li>').append($('<h3>').text(section_label)));
			}

			// Add individual menu items of the section.
			$.each(section.items, function(i, item) {
				item = $.extend({}, item);
				if (sections.length > 1 && section_label !== null) {
					item.ariaLabel = section_label + ', ' + item['label'];
				}
				$menu_popup.append(createMenuItem(item));
			});
		});

		if (sections.length === 1) {
			if (typeof sections[0].label === 'string' && sections[0].label.length) {
				$menu_popup.attr({'aria-label': sections[0].label});
			}
		}
	}

	const defaultOptions = {
		closeCallback: function() {},
		background_layer: true
	};

	const methods = {
		init: function(sections, event, options) {
			// Don't display empty menu.
			if (!sections.length || !sections[0]['items'].length) {
				return;
			}

			const $opener = $(this);
			const $wrapper = $('.wrapper');
			const wrapper_rect = $wrapper[0].getBoundingClientRect();

			options = $.extend({
				position: {
					/*
					 * Please note that click event is also triggered by hitting spacebar on the keyboard,
					 * in which case the number of mouse clicks (stored in event.originalEvent.detail) will be zero.
					 */
					of: (['click', 'mouseup', 'mousedown'].includes(event.type) && event.originalEvent.detail)
						? event
						: event.target,
					my: 'left top',
					at: 'left bottom',
					using: (pos, data) => {
						const menu = data.element.element[0];
						const margin_right = Math.max(WRAPPER_PADDING_RIGHT,
							wrapper_rect.width - $wrapper.outerWidth()
						);
						const max_left = wrapper_rect.right - margin_right - menu.offsetWidth;

						pos.left = Math.max(wrapper_rect.left, Math.min(max_left, pos.left));

						menu.style.left = `${pos.left}px`;
						menu.style.top = `${pos.top}px`;
					}
				}
			}, defaultOptions, options || {});

			// Close other action menus and prevent focus jumping before opening a new popup.
			$('.menu-popup-top').menuPopup('close', null, false);

			$opener.attr('aria-expanded', 'true');

			const $menu_popup = $('<ul>', {
				'role': 'menu',
				'class': 'menu-popup menu-popup-top',
				'tabindex': 0
			});

			// Add custom class, if specified.
			if ('class' in options) {
				$menu_popup.addClass(options.class);
			}

			$opener.data({
				sections: sections,
				menu_popup: $menu_popup
			});

			addMenuPopupItems($menu_popup, sections);

			$menu_popup.data('menu_popup', options);

			if (options.background_layer) {
				$wrapper.append($('<div>', {class: 'menu-popup-overlay'}));
			}

			$wrapper.append($menu_popup);

			// Position the menu (before hiding).
			$menu_popup.position(options.position);

			const pos_top = Math.max(WRAPPER_PADDING_Y, Math.min($menu_popup.position().top,
				wrapper_rect.bottom - WRAPPER_PADDING_Y - $menu_popup.outerHeight()
			));

			$menu_popup.css('top', `${pos_top}px`);

			// Hide all action menu sub-levels, including the topmost, for fade effect to work.
			$menu_popup.add('.menu-popup', $menu_popup).hide();

			// Position and display the menu.
			$menu_popup.fadeIn(50);

			addToOverlaysStack('menu-popup', event.target, 'menu-popup');

			// Need to be postponed.
			setTimeout(function() {
				$(document)
					.on('click dragstart', {menu: $menu_popup, opener: $opener},
						menuPopupDocumentCloseHandler
					)
					.on('keydown', {menu: $menu_popup}, menuPopupKeyDownHandler);
			});

			$menu_popup.focus();

			$menu_popup.on('scroll', e => {
				e.stopPropagation();

				$menu_popup.actionMenuItemCollapse();
			});

			$(window).on('resize', handleWindowResize);
		},

		close: function(trigger_elem, return_focus) {
			const menu_popup = $(this),
				options = $(menu_popup).data('menu_popup') || {};

			if (!menu_popup.is(trigger_elem) && menu_popup.has(trigger_elem).length === 0) {
				$('[aria-expanded="true"]', trigger_elem).attr({'aria-expanded': 'false'});
				menu_popup.fadeOut(0);

				$('.highlighted', menu_popup).removeClass('highlighted');
				$('[aria-expanded="true"]', menu_popup).attr({'aria-expanded': 'false'});

				$(document)
					.off('click dragstart', menuPopupDocumentCloseHandler)
					.off('keydown', menuPopupKeyDownHandler);

				$(window).off('resize', handleWindowResize);

				const overlay = removeFromOverlaysStack('menu-popup', return_focus);

				if (overlay && overlay['element'] !== undefined) {
					// Remove expanded attribute of the original opener.
					$(overlay['element']).attr({'aria-expanded': 'false'});
				}

				if (options.background_layer) {
					menu_popup.prev().remove();
				}

				menu_popup.remove();

				// Call menu close callback function.
				typeof options.closeCallback === 'function' && options.closeCallback.apply();
			}
		},

		/**
		 * Refresh popup menu, call refreshCallback for every item if defined. Refresh recreate item dom nodes.
		 */
		refresh: function(widget) {
			const $opener = $(this),
				sections = $opener.data('sections'),
				$menu_popup = $opener.data('menu_popup');

			$menu_popup.empty();
			sections.forEach(
				section => section.items && section.items.forEach(
					item => item.refreshCallback && item.refreshCallback.call(item, widget)
				)
			);

			addMenuPopupItems($menu_popup, sections);
		}
	};

	/**
	 * Expends hovered/selected context menu item.
	 */
	$.fn.actionMenuItemExpand = function() {
		const li = $(this),
			li_pos = li[0].getBoundingClientRect(),
			menu = li.closest('.menu-popup');

		for (let item = $('li:first-child', menu); item.length > 0; item = item.next()) {
			if (item[0] === li[0]) {
				if (!$('ul', item[0]).is(':visible')) {
					const $submenu = $('ul:first', item[0]);

					if ($submenu.length) {
						$('> a', li[0]).addClass('highlighted');

						setSubmenuPosition($submenu, li_pos);

						$submenu.prev('[role="menuitem"]').attr({'aria-expanded': 'true'});
					}
				}
				else {
					// Collapses the submenus deeper than +2 level of targeted submenu.
					const $submenu_link = $('ul:visible > li > a', item[0]);

					if ($submenu_link.hasClass('highlighted')) {
						$submenu_link
							.removeClass('highlighted')
							.blur();
					}

					const $submenu_child = $('ul:visible > li > ul:visible', item[0]);

					if ($submenu_child.length) {
						$submenu_child.prev('[role="menuitem"]')
							.removeClass('highlighted')
							.attr({'aria-expanded': 'false'});

						$submenu_child.css({'display': 'none'});
					}
				}
			}
			else {
				// Remove activity from item that has been selected by keyboard and now is deselected using mouse.
				if ($('> a', item[0]).hasClass('highlighted')) {
					$('> a', item[0])
						.removeClass('highlighted')
						.blur();
				}

				// Closes all other submenus from this level, if they were open.
				const $submenu = $('ul', item[0]);

				if ($submenu.is(':visible')) {
					$submenu.prev('[role="menuitem"]')
						.removeClass('highlighted')
						.attr({'aria-expanded': 'false'});

					$submenu.css({'display': 'none'});
				}
			}
		}

		return this;
	};

	/**
	 * Calculates and sets submenu position based on <li> parent element.
	 *
	 * @param {Object}  $submenu
	 * @param {DOMRect} li_pos
	 */
	function setSubmenuPosition($submenu, li_pos) {
		$submenu.css('display', '');

		if ($submenu.data('pos')) {
			$submenu.css($submenu.data('pos'));

			return;
		}

		const wrapper = document.querySelector('.wrapper');
		const wrapper_rect = wrapper.getBoundingClientRect();
		const margin_right = Math.max(WRAPPER_PADDING_RIGHT, wrapper_rect.width - wrapper.clientWidth);
		const margin_bottom = Math.max(WRAPPER_PADDING_Y, wrapper_rect.height - wrapper.clientHeight);
		const max_height = `calc(100vh - ${WRAPPER_PADDING_Y + margin_bottom}px)`;

		// Change of max height to determine the height of the element.
		$submenu.css('maxHeight', max_height);

		const max_relative_top = wrapper_rect.bottom - $submenu.outerHeight() - margin_bottom;
		const max_relative_left = wrapper_rect.right - $submenu.outerWidth() - margin_right;

		// Will use parent directions, if the parent is not menu-popup-top.
		// If the directions are not defined, will use default direction: x - right, y - down.
		let submenu_direction_x = 1;
		let submenu_direction_y = 1;

		const $submenu_parents = $submenu.parents('ul');
		const is_first_child = $submenu_parents.first().hasClass('menu-popup-top');

		if (!is_first_child) {
			const parent_menu_data = $submenu_parents.first().data('pos');

			submenu_direction_x = parent_menu_data.direction_x;
			submenu_direction_y = parent_menu_data.direction_y;
		}

		const submenu_data = {
			top: li_pos.top - (MENU_PADDING_Y + MENU_BORDER),
			left: submenu_direction_x === 1
				? li_pos.right + MENU_PADDING_RIGHT
				: li_pos.left - ($submenu.outerWidth() + MENU_PADDING_LEFT),
			direction_x: submenu_direction_x,
			direction_y: submenu_direction_y,
			maxHeight: max_height
		};

		if (submenu_data.left < wrapper_rect.left || submenu_data.left > max_relative_left) {
			// Reverse the direction on X axis.
			submenu_data.direction_x *= -1;

			submenu_data.left = submenu_data.direction_x === 1
				? li_pos.right + MENU_PADDING_RIGHT
				: li_pos.left - ($submenu.outerWidth() + MENU_PADDING_LEFT);

			submenu_data.left = submenu_data.direction_x === 1
				? Math.min(max_relative_left, submenu_data.left)
				: Math.max(submenu_data.left, wrapper_rect.left);

			if (!is_first_child
					&& Math.abs($submenu_parents[1].getBoundingClientRect().top - submenu_data.top) < SUBMENU_DELTA_Y) {
				const pos_top = submenu_data.top + (submenu_data.direction_y * SUBMENU_DELTA_Y);

				if (max_relative_top < pos_top || WRAPPER_PADDING_Y > pos_top) {
					// Reverse the direction on Y axis.
					submenu_data.direction_y *= -1;
				}

				submenu_data.top += submenu_data.direction_y * SUBMENU_DELTA_Y;
			}
		}

		submenu_data.top = Math.max(WRAPPER_PADDING_Y, Math.min(submenu_data.top, max_relative_top));

		$submenu.css(submenu_data);
		$submenu.data('pos', submenu_data);
	}

	/**
	 * Collapses context menu item that has lost focus or is not selected anymore.
	 */
	$.fn.actionMenuItemCollapse = function() {
		// Remove style and close sub-menus in deeper levels.
		const parent_menu = $(this).closest('.menu-popup');
		$('.highlighted', parent_menu).removeClass('highlighted');
		$('[aria-expanded]', parent_menu).attr({'aria-expanded': 'false'});
		$('.menu-popup', parent_menu)
			.css({
				top: '',
				left: '',
				display: 'none',
				maxHeight: ''
			})
			.removeData('pos');

		// Close actual menu level.
		parent_menu.not('.menu-popup-top')
			.css({
				top: '',
				left: '',
				display: 'none',
				maxHeight: ''
			})
			.removeData('pos');

		parent_menu.prev('[role="menuitem"]').attr({'aria-expanded': 'false'});

		return this;
	};

	function handleWindowResize() {
		$('.menu-popup-top').menuPopup('close', null, false);
	}

	function menuPopupDocumentCloseHandler(event) {
		$(event.data.menu[0]).menuPopup('close', event.data.opener);
	}

	function menuPopupKeyDownHandler(event) {
		const link_selector = '.menu-popup-item',
			menu_popup = $(event.data.menu[0]);
		let	level = menu_popup,
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
			return this === document.activeElement;
		}).length) {
			selected = $(document.activeElement).closest('li');
		}

		// Perform action based on keydown event.
		switch (event.key) {
			case 'ArrowLeft':
				if (selected !== undefined && selected.has('.menu-popup')) {
					if (level !== menu_popup) {
						selected.actionMenuItemCollapse();
						// Must focus previous element, otherwise screen reader will exit menu.
						selected.closest('.menu-popup').prev('[role="menuitem"]').addClass('highlighted').focus();
					}
				}
				break;

			case 'ArrowUp':
				if (selected === undefined) {
					$(link_selector + ':last', level).addClass('highlighted').focus();
				}
				else {
					let prev = items[items.index(selected) - 1];
					if (prev === undefined) {
						prev = items[items.length - 1];
					}

					$(link_selector, selected).removeClass('highlighted');
					$(link_selector + ':first', prev).addClass('highlighted').focus();
				}

				// Prevent page scrolling.
				event.preventDefault();
				break;

			case 'ArrowRight':
				if (selected !== undefined && selected.has('.menu-popup')) {
					selected.actionMenuItemExpand();
					$('ul > li ' + link_selector + ':first', selected).addClass('highlighted').focus();
				}
				break;

			case 'ArrowDown':
				if (selected === undefined) {
					$(link_selector + ':first', items[0]).addClass('highlighted').focus();
				}
				else {
					let next = items[items.index(selected) + 1];
					if (next === undefined) {
						next = items[0];
					}

					$(link_selector, selected).removeClass('highlighted');
					$(link_selector + ':first', next).addClass('highlighted').focus();
				}

				// Prevent page scrolling.
				event.preventDefault();
				break;

			case 'Escape':
				$(menu_popup).menuPopup('close', null);
				break;

			case 'Enter':
				if (selected !== undefined) {
					$('>' + link_selector, selected)[0].click();
				}
				break;

			case 'Tab':
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
	 * @param {bool} options['disabled']       Item disable status.
	 * @param object options['clickCallback']  Item click callback.
	 *
	 * @return object
	 */
	function createMenuItem(options) {
		options = $.extend({
			ariaLabel: options.label,
			selected: false,
			disabled: false,
			class: false
		}, options);

		const item = $('<li>'),
			link = $('<a>', {
				role: 'menuitem',
				tabindex: '-1',
				'aria-label': options.selected ? sprintf(t('S_SELECTED_SR'), options.ariaLabel) : options.ariaLabel
			}).data('aria-label', options.ariaLabel);

		if (options.label !== undefined) {
			link.text(options.label);

			if (options.items !== undefined && options.items.length > 0) {
				// if submenu exists
				link.append($('<span>', {'class': 'arrow-right'}));
			}
		}

		if (options.data !== undefined && objectSize(options.data) > 0) {
			$.each(options.data, function(key, value) {
				link.data(key, value);
			});
		}

		link.addClass('menu-popup-item');

		if (options.disabled) {
			link.addClass('disabled');
		}
		else {
			if (options.url !== undefined) {
				link.attr('href', options.url);

				if ('target' in options) {
					link.attr('target', options.target);
				}

				if ('rel' in options) {
					link.attr('rel', options.rel);
				}
			}

			if (options.clickCallback !== undefined) {
				link.on('click', options.clickCallback);
			}
		}

		if (options.selected) {
			link.addClass('selected');
		}

		if (options.class) {
			link.addClass(options.class);
		}

		if ('dataAttributes' in options) {
			$.each(options.dataAttributes, function(key, value) {
				link.attr((key.substr(0, 5) === 'data-') ? key : 'data-' + key, value);
			});
		}

		if (options.items !== undefined && options.items.length > 0) {
			link.attr({
				'aria-haspopup': 'true',
				'aria-expanded': 'false'
			});
			link.on('click', function(e) {
				e.stopPropagation();
			});
		}

		item.append(link);

		if (options.items !== undefined && options.items.length > 0) {
			const menu = $('<ul>', {
				class : 'menu-popup',
				role: 'menu'
			}).on('mouseover', function(e) {
				// Prevent 'mouseover' event in parent item, that would call actionMenuItemExpand() for parent.
				e.stopPropagation();
			});

			$.each(options.items, function(i, item) {
				menu.append(createMenuItem(item));
			});

			item.append(menu);

			menu.on('scroll', e => {
				e.stopPropagation();

				$('ul', menu).actionMenuItemCollapse();
			});
		}

		let menu_timer;

		item.on('mouseover', function(e) {
			// Prevent 'mouseover' event in parent item, that would call actionMenuItemExpand() for parent.
			e.stopPropagation();

			const $current_item = $(this);

			menu_timer = setTimeout(() => $current_item.actionMenuItemExpand(), SUBMENU_DELAY);
		});

		item.on('mouseleave', () => clearTimeout(menu_timer));

		return item;
	}
});
