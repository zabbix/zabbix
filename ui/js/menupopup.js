/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
		url = new Curl('history.php');

	if (!options.allowed_ui_latest_data) {
		return [];
	}

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
 * @param {string} options['hostid']                  Host ID.
 * @param {array}  options['scripts']                 Host scripts (optional).
 * @param {string} options['csrf_token']              CSRF token.
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
	const sections = [];
	const items = [];
	const configuration = [];
	let url;

	// go to section
	if (options.hasGoTo) {
		// dashboard
		if (options.allowed_ui_hosts) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'host.dashboard.view')
			url.setArgument('hostid', options.hostid)

			items.push({
				label: t('Dashboards'),
				disabled: !options.showDashboards,
				url: url.getUrl()
			});
		}

		// problems
		if (options.allowed_ui_problems) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'problem.view');
			url.setArgument('hostids[]', options.hostid);
			url.setArgument('filter_set', '1');

			if ('severities' in options) {
				url.setArgument('severities[]', options.severities);
			}

			if ('show_suppressed' in options) {
				url.setArgument('show_suppressed', '1');
			}

			if ('tags' in options) {
				url.setArgument('tags', options.tags);
				url.setArgument('evaltype', options.evaltype);
			}

			items.push({
				label: t('Problems'),
				disabled: !options.showTriggers,
				url: url.getUrl()
			});
		}

		// latest data
		if (options.allowed_ui_latest_data) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'latest.view');

			if ('tags' in options) {
				url.setArgument('tags', options.tags);
				url.setArgument('evaltype', options.evaltype);
			}

			url.setArgument('hostids[]', options.hostid);
			url.setArgument('filter_set', '1');

			items.push({
				label: t('Latest data'),
				url: url.getUrl()
			});
		}

		// graphs
		if (options.allowed_ui_hosts) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'charts.view')
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('filter_set', '1');

			items.push({
				label: t('Graphs'),
				disabled: !options.showGraphs,
				url: url.getUrl()
			});
		}

		// web
		if (options.allowed_ui_hosts) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'web.view');
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('filter_set', '1');

			items.push({
				label: t('Web'),
				disabled: !options.showWeb,
				url: url.getUrl()
			});
		}

		// inventory link
		if (options.allowed_ui_inventory) {
			url = new Curl('hostinventories.php');
			url.setArgument('hostid', options.hostid);

			items.push({
				label: t('Inventory'),
				url: url.getUrl()
			});
		}

		if (items.length) {
			sections.push({
				label: t('View'),
				items: items
			});
		}

		// Configuration
		if (options.allowed_ui_conf_hosts) {
			// host
			url = new Curl('zabbix.php');
			url.setArgument('action', 'popup');
			url.setArgument('popup', 'host.edit');
			url.setArgument('hostid', options.hostid);

			configuration.push({
				label: t('Host'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});

			// items
			url = new Curl('zabbix.php');
			url.setArgument('action', 'item.list');
			url.setArgument('filter_set', '1');
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('context', 'host');

			configuration.push({
				label: t('Items'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});

			// triggers
			url = new Curl('zabbix.php');
			url.setArgument('action', 'trigger.list');
			url.setArgument('filter_set', '1');
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('context', 'host');

			configuration.push({
				label: t('Triggers'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});

			// graphs
			url = new Curl('graphs.php');
			url.setArgument('filter_set', '1');
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('context', 'host');

			configuration.push({
				label: t('Graphs'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});

			// discovery
			url = new Curl('host_discovery.php');
			url.setArgument('filter_set', '1');
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('context', 'host');

			configuration.push({
				label: t('Discovery'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});

			// web scenario
			url = new Curl('httpconf.php');
			url.setArgument('filter_set', '1');
			url.setArgument('filter_hostids[]', options.hostid);
			url.setArgument('context', 'host');

			configuration.push({
				label: t('Web'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});

			sections.push({
				label: t('Configuration'),
				items: configuration
			});
		}
	}

	// urls
	if ('urls' in options) {
		sections.push({
			label: t('Links'),
			items: getMenuPopupURLData(options.urls, trigger_element, options.hostid, null)
		});
	}

	// scripts
	if ('scripts' in options) {
		sections.push({
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, trigger_element, options.hostid, options.eventid,
				options.csrf_token
			)
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

		const submap_url = new Curl('zabbix.php');
		submap_url.setArgument('action', 'map.view');
		submap_url.setArgument('sysmapid', options.sysmapid);
		if (typeof options.severity_min !== 'undefined') {
			submap_url.setArgument('severity_min', options.severity_min);
		}
		item.url = submap_url.getUrl();
	}

	sections.push({
		label: t('Go to'),
		items: [item]
	});

	// urls
	if (typeof options.urls !== 'undefined') {
		sections.push({
			label: t('Links'),
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
		problems_url = new Curl('zabbix.php');

	problems_url.setArgument('action', 'problem.view');
	problems_url.setArgument('filter_set', '1');
	problems_url.setArgument('groupids[]', options.groupid);
	if (typeof options.severities !== 'undefined') {
		problems_url.setArgument('severities[]', options.severities);
	}
	if (typeof options.show_suppressed !== 'undefined' && options.show_suppressed) {
		problems_url.setArgument('show_suppressed', '1');
	}
	if (typeof options.tags !== 'undefined') {
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
	if (typeof options.urls !== 'undefined') {
		sections.push({
			label: t('Links'),
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
	const sections = [];
	const items = [];
	let url;

	if (options.allowed_ui_problems) {
		url = new Curl('zabbix.php');
		url.setArgument('action', 'problem.view');
		url.setArgument('filter_set', '1');
		url.setArgument('triggerids', options.triggers.map((value) => value.triggerid));

		if ('severities' in options) {
			url.setArgument('severities[]', options.severities);
		}

		if ('show_suppressed' in options) {
			url.setArgument('show_suppressed', '1');
		}

		items.push({
			label: t('Problems'),
			disabled: !options.show_events,
			url: url.getUrl()
		});
	}

	// items problems
	if (options.allowed_ui_latest_data && options.items.length) {
		const history = [];

		for (const item of options.items) {
			url = new Curl('history.php');
			url.setArgument('action', item.params.action);
			url.setArgument('itemids[]', item.params.itemid);

			history.push({
				label: item.name,
				url: url.getUrl()
			});
		}

		items.push({
			label: t('History'),
			items: history
		});
	}

	if (items.length) {
		sections.push({
			label: t('View'),
			items: items
		});
	}

	// configuration
	if (options.allowed_ui_conf_hosts) {
		const config_urls = [];
		const trigger_urls = [];
		const item_urls = [];

		for (const value of options.triggers) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'popup');
			url.setArgument('popup', 'trigger.edit');
			url.setArgument('context', 'host');
			url.setArgument('triggerid', value.triggerid);

			trigger_urls.push({
				label: value.description,
				url: url.getUrl()
			})
		}

		config_urls.push({
			label: t('Triggers'),
			items: trigger_urls
		});

		if (options.items.length) {
			for (const item of options.items) {
				if (item.params.is_webitem) {
					item_urls.push({
						label: item.name,
						disabled: true
					});
				}
				else {
					url = new Curl('zabbix.php');
					url.setArgument('action', 'popup');
					url.setArgument('popup', 'item.edit');
					url.setArgument('context', 'host');
					url.setArgument('itemid', item.params.itemid);

					item_urls.push({
						label: item.name,
						url: url.getUrl()
					});
				}
			}

			config_urls.push({
				label: t('Items'),
				items: item_urls
			});
		}

		sections.push({
			label: t('Configuration'),
			items: config_urls
		});
	}

	// urls
	if ('urls' in options) {
		sections.push({
			label: t('Links'),
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
			label: t('Links'),
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
 * @param {string} options['csrf_token']
 * @param {object} trigger_element                   UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupDashboard(options, trigger_element) {
	const sections = [];
	const parameters = {dashboardid: options.dashboardid};

	// Dashboard actions.
	if (options.can_edit_dashboards) {
		const url_create = new Curl('zabbix.php');
		url_create.setArgument('action', 'dashboard.view');
		url_create.setArgument('new', '1');

		const url_clone = new Curl('zabbix.php');
		url_clone.setArgument('action', 'dashboard.view');
		url_clone.setArgument('dashboardid', options.dashboardid);
		url_clone.setArgument('clone', '1');

		const url_delete = new Curl('zabbix.php');
		url_delete.setArgument('action', 'dashboard.delete');
		url_delete.setArgument('dashboardids', [options.dashboardid]);
		url_delete.setArgument(CSRF_TOKEN_NAME, options.csrf_token);

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

						redirect(url_delete.getUrl(), 'post', CSRF_TOKEN_NAME, true);
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
 * @param {object} options
 *        {string} options['triggerid']                   Trigger ID.
 *        {bool}   options['allowed_actions_change_problem_ranking']
 *                                                        Whether user is allowed to change event rank.
 *        {bool}   options['allowed_ui_conf_hosts']       Whether user has access to Configuration > Hosts.
 *        {bool}   options['allowed_ui_latest_data']      Whether user has access to Monitoring > Latest data.
 *        {bool}   options['allowed_ui_problems']         Whether user has access to Monitoring > Problems.
 *        {bool}   options['backurl']                     URL from where the menu popup was called.
 *        {bool}   options['show_events']                 Show Problems item enabled. Default: false.
 *        {string} options['eventid']                     (optional) Required for "Update problem" section and event
 *                                                        rank change.
 *        {array}  options['eventids']                    (optional)
 *        {array}  options['csrf_tokens']                 (optional) CSRF tokens.
 *        {string} options['csrf_tokens']['acknowledge']  (optional) CSRF token for acknowledge action.
 *        {string} options['csrf_tokens']['scriptexec']   (optional) CSRF token for script execution.
 *        {array}  options['items']                       (optional) Link to trigger item history page.
 *        {string} options['items'][]['name']             Item name.
 *        {object} options['items'][]['params']           Item URL parameters ("name" => "value").
 *        {bool}   options['mark_as_cause']               (optional) Whether to enable "Mark as cause".
 *        {bool}   options['mark_selected_as_symptoms']   (optional) Whether to enable "Mark selected as symptoms".
 *        {bool}   options['show_update_problem']         (optional) Whether to show "Update problem".
 *        {bool}   options['show_rank_change_cause']      (optional) Whether to show "Mark as cause".
 *        {bool}   options['show_rank_change_symptom']    (optional) Whether to show "Mark selected as symptoms".
 *        {array}  options['urls']                        (optional) Links.
 * @param {object} trigger_element                        UI element which triggered opening of overlay dialogue.
 *
 * @return array
 */
function getMenuPopupTrigger(options, trigger_element) {
	const sections = [];
	const items = [];
	let url;

	if (options.allowed_ui_problems) {
		// events
		url = new Curl('zabbix.php');
		url.setArgument('action', 'problem.view');
		url.setArgument('filter_set', '1');
		url.setArgument('triggerids[]', options.triggerid);

		items.push({
			label: t('Problems'),
			disabled: !options.show_events,
			url: url.getUrl()
		});
	}

	// items problems
	if (options.allowed_ui_latest_data && options.items.length) {
		const history = [];

		for (const item of options.items) {
			url = new Curl('history.php');
			url.setArgument('action', item.params.action);
			url.setArgument('itemids[]', item.params.itemid);

			history.push({
				label: item.name,
				url: url.getUrl()
			});
		}

		items.push({
			label: t('History'),
			items: history
		});
	}

	if (items.length) {
		sections.push({
			label: t('View'),
			items: items
		});
	}

	if ('show_update_problem' in options && options.show_update_problem) {
		url = new Curl('zabbix.php');
		url.setArgument('action', 'popup');
		url.setArgument('popup', 'acknowledge.edit');
		url.setArgument('eventid', options.eventid);

		sections.push({
			label: t('Actions'),
			items: [{
				label: t('Update problem'),
				url: url.getUrl()
			}]
		});
	}

	// configuration
	if (options.allowed_ui_conf_hosts) {
		const config_urls = [];
		const item_urls = [];

		url = new Curl('zabbix.php');
		url.setArgument('action', 'popup');
		url.setArgument('popup', 'trigger.edit');
		url.setArgument('context', 'host');
		url.setArgument('triggerid', options.triggerid);

		config_urls.push({
			label: t('Trigger'),
			url: url.getUrl()
		});

		if (options.items.length) {
			for (const item of options.items) {
				if (item.params.is_webitem) {
					item_urls.push({
						label: item.name,
						disabled: true
					});
				}
				else {
					url = new Curl('zabbix.php');
					url.setArgument('action', 'popup');
					url.setArgument('popup', 'item.edit');
					url.setArgument('context', 'host');
					url.setArgument('itemid', item.params.itemid);

					item_urls.push({
						label: item.name,
						url: url.getUrl()
					});
				}
			}

			config_urls.push({
				label: t('Items'),
				items: item_urls
			});
		}

		sections.push({
			label: t('Configuration'),
			items: config_urls
		});
	}

	// Check if user role allows to change event rank and if one of the options to show individual menu are true.
	if (options.allowed_actions_change_problem_ranking
			&& ((typeof options.show_rank_change_cause !== 'undefined' && options.show_rank_change_cause)
				|| (typeof options.show_rank_change_symptom !== 'undefined' && options.show_rank_change_symptom))) {
		let items = [];
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'popup.acknowledge.create');

		/*
		 * Some widgets cannot show symptoms. So it is not possible to convert to symptoms cause if only cause events
		 * are displayed.
		 */
		if (typeof options.show_rank_change_cause !== 'undefined' && options.show_rank_change_cause) {
			// Must be synced with PHP ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE.
			const ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE = 0x80;

			items[items.length] = {
				label: t('Mark as cause'),
				clickCallback: function () {
					jQuery(this).closest('.menu-popup').menuPopup('close', null);

					fetch(curl.getUrl(), {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
						body: urlEncodeData({
							eventids: [options.eventid],
							change_rank: ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE,
							[CSRF_TOKEN_NAME]: options.csrf_tokens['acknowledge']
						})
					})
						.then((response) => response.json())
						.then((response) => {
							clearMessages();

							// Show message directly that comes from controller.
							if ('error' in response) {
								addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true,
									true
								));
							}
							else if ('success' in response) {
								addMessage(makeMessageBox('good', [], response.success.title, true, false));

								$.publish('event.rank_change');
							}
						})
						.catch(() => {
							const title = t('Unexpected server error.');
							const message_box = makeMessageBox('bad', [], title)[0];

							clearMessages();
							addMessage(message_box);
						});
				},
				disabled: !options.mark_as_cause
			};
		}

		// Dashboard does not have checkboxes. So it is not possible to mark problems and change rank to symptom.
		if (typeof options.show_rank_change_symptom !== 'undefined' && options.show_rank_change_symptom) {
			// Must be synced with PHP ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM.
			const ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM = 0x100;

			items[items.length] = {
				label: t('Mark selected as symptoms'),
				clickCallback: function () {
					jQuery(this).closest('.menu-popup').menuPopup('close', null);

					fetch(curl.getUrl(), {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
						body: urlEncodeData({
							eventids: options.eventids,
							cause_eventid: options.eventid,
							change_rank: ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM,
							[CSRF_TOKEN_NAME]: options.csrf_tokens['acknowledge']
						})
					})
						.then((response) => response.json())
						.then((response) => {
							clearMessages();

							// Show message directly that comes from controller.
							if ('error' in response) {
								addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true,
									true
								));
							}
							else if ('success' in response) {
								addMessage(makeMessageBox('good', [], response.success.title, true, false));

								const uncheckids = Object.keys(chkbxRange.getSelectedIds());
								uncheckTableRows('problem', []);
								chkbxRange.checkObjects('eventids', uncheckids, false);
								chkbxRange.update('eventids');

								$.publish('event.rank_change');
							}
						})
						.catch(() => {
							const title = t('Unexpected server error.');
							const message_box = makeMessageBox('bad', [], title)[0];

							clearMessages();
							addMessage(message_box);
						});
				},
				disabled: !options.mark_selected_as_symptoms
			};
		}

		sections[sections.length] = {
			label: t('Problem'),
			items: items
		};
	}

	// urls
	if ('urls' in options) {
		sections.push({
			label: t('Links'),
			items: getMenuPopupURLData(options.urls, trigger_element, null, options.eventid)
		});
	}

	// scripts
	if ('scripts' in options) {
		sections.push({
			label: t('Scripts'),
			items: getMenuPopupScriptData(options.scripts, trigger_element, null, options.eventid,
				options.csrf_tokens['scriptexec']
			)
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
 * @param string options['context']                     Determines whether the menu is made for host or template item.
 *
 * @return array
 */
function getMenuPopupItem(options) {
	const sections = [];
	const actions = [];
	const items = [];
	let url;

	if (options.context !== 'template') {
		// latest data link
		if (options.allowed_ui_latest_data) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'latest.view');
			url.setArgument('hostids[]', options.hostid);
			url.setArgument('name', options.name);
			url.setArgument('filter_set', '1');

			items.push({
				label: t('Latest data'),
				url: url.getUrl()
			});
		}

		url = new Curl('history.php');
		url.setArgument('action', 'showgraph');
		url.setArgument('itemids[]', options.itemid);

		items.push({
			label: t('Graph'),
			url: url.getUrl(),
			disabled: !options.showGraph
		});

		url = new Curl('history.php');
		url.setArgument('action', 'showvalues');
		url.setArgument('itemids[]', options.itemid);

		items.push({
			label: t('Values'),
			url: url.getUrl(),
			disabled: !options.history && !options.trends
		});

		url = new Curl('history.php');
		url.setArgument('action', 'showlatest');
		url.setArgument('itemids[]', options.itemid);

		items.push({
			label: t('500 latest values'),
			url: url.getUrl(),
			disabled: !options.history && !options.trends
		});

		sections.push({
			label: t('View'),
			items: items
		});
	}

	if (options.allowed_ui_conf_hosts) {
		const config_urls = [];
		const config_triggers = {
			label: t('Triggers'),
			disabled: options.binary_value_type || options.triggers.length === 0
		};

		if (options.isWriteable) {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'popup');
			url.setArgument('popup', 'item.edit');
			url.setArgument('context', options.context);
			url.setArgument('itemid', options.itemid);

			config_urls.push({
				label: t('Item'),
				url: url.getUrl()
			});
		}

		if (options.context === 'host') {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'popup');
			url.setArgument('popup', 'host.edit');
			url.setArgument('hostid', options.hostid);

			config_urls.push({
				label: t('Host'),
				disabled: !options.isWriteable,
				url: url.getUrl()
			});
		}
		else {
			url = new Curl('zabbix.php');
			url.setArgument('action', 'popup');
			url.setArgument('popup', 'template.edit');
			url.setArgument('templateid', options.hostid);

			config_urls.push({
				label: t('Template'),
				url: url.getUrl()
			});
		}

		if (!config_triggers.disabled) {
			const trigger_items = [];

			for (const value of options.triggers) {
				url = new Curl('zabbix.php');
				url.setArgument('action', 'popup');
				url.setArgument('popup', 'trigger.edit');
				url.setArgument('triggerid', value.triggerid);
				url.setArgument('hostid', options.hostid);
				url.setArgument('context', options.context);

				trigger_items.push({
					label: value.description,
					url: url.getUrl()
				});
			}

			config_triggers.items = trigger_items;
		}

		config_urls.push(config_triggers);

		config_urls.push({
			label: t('Create trigger'),
			disabled: options.binary_value_type,
			clickCallback: function() {
				ZABBIX.PopupManager.open('trigger.edit', {
					hostid: options.hostid,
					name: options.name,
					expression: 'func(/' + options.host + '/' + options.key + ')',
					context: options.context
				});
			}
		});

		if (options.isDiscovery) {
			config_urls.push({
				label: t('Create dependent item'),
				disabled: true
			});
		}
		else {
			config_urls.push({
				label: t('Create dependent item'),
				clickCallback: () => {
					ZABBIX.PopupManager.open('item.edit', {
						context: options.context,
						hostid: options.hostid,
						master_itemid: options.itemid,
						type: 18 // ITEM_TYPE_DEPENDENT
					});
				}
			});
		}

		url = new Curl('host_discovery.php');
		url.setArgument('form', 'create');
		url.setArgument('hostid', options.hostid);
		url.setArgument('type', 18); // ITEM_TYPE_DEPENDENT
		url.setArgument('master_itemid', options.itemid);
		url.setArgument('backurl', options.backurl);
		url.setArgument('context', options.context);

		config_urls.push({
			label: t('Create dependent discovery rule'),
			url: url.getUrl(),
			disabled: options.isDiscovery
		});

		sections.push({
			label: t('Configuration'),
			items: config_urls
		});
	}

	if (options.context !== 'template') {
		if (options.isExecutable) {
			actions.push({
				label: t('Execute now'),
				clickCallback: function() {
					jQuery(this).closest('.menu-popup').menuPopup('close', null);

					view.executeNow(null, {itemids: [options.itemid]});
				}
			})
		}
		else {
			actions.push({
				label: t('Execute now'),
				disabled: true
			});
		}

		sections.push({
			label: t('Actions'),
			items: actions
		});
	}

	return sections;
}

/**
 * Get menu structure for item prototypes.
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
function getMenuPopupItemPrototype(options) {
	const sections = [];
	const config_urls = [];
	let config_triggers = {
		label: t('Trigger prototypes'),
		disabled: true
	};

	const url = new Curl('zabbix.php');
	url.setArgument('action', 'popup');
	url.setArgument('popup', 'item.prototype.edit');
	url.setArgument('context', options.context);
	url.setArgument('itemid', options.itemid);
	url.setArgument('parent_discoveryid', options.parent_discoveryid);

	config_urls.push({
		label: t('Item prototype'),
		url: url.getUrl()
	});

	if (options.trigger_prototypes.length) {
		const trigger_prototypes = [];

		for (const value of options.trigger_prototypes) {
			url.setArgument('action', 'popup');
			url.setArgument('popup', 'trigger.prototype.edit');
			url.setArgument('context', options.context);
			url.setArgument('triggerid', value.triggerid);
			url.setArgument('parent_discoveryid', options.parent_discoveryid);

			trigger_prototypes.push({
				label: value.description,
				url: url.getUrl()
			});
		}

		if (trigger_prototypes.length) {
			config_triggers = {...config_triggers, ...{items: trigger_prototypes, disabled: false}};
		}
	}

	config_urls.push(config_triggers);

	config_urls.push({
		label: t('Create trigger prototype'),
		clickCallback: function() {
			ZABBIX.PopupManager.open('trigger.prototype.edit', {
				parent_discoveryid: options.parent_discoveryid,
				name: options.name,
				hostid: options.hostid,
				expression: 'func(/' + options.host + '/' + options.key + ')',
				context: options.context
			});
		}
	});

	config_urls.push({
		label: t('Create dependent item'),
		clickCallback: () => {
			ZABBIX.PopupManager.open('item.prototype.edit', {
				context: options.context,
				master_itemid: options.itemid,
				parent_discoveryid: options.parent_discoveryid,
				type: 18 // ITEM_TYPE_DEPENDENT
			});
		}
	});

	sections.push({
		label: t('Configuration'),
		items: config_urls
	});

	return sections;
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
				const form = trigger_elem.closest('form').get(0);

				if (!form.dataset.action) {
					form.dataset.action = form.getAttribute('action');
				}

				form.setAttribute('action', item.url);
				form.submit();
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
				document.getElementById('expr_temp').dispatchEvent(new Event('change'));
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
 * @param {array}  scripts            Script names and menu paths.
 * @param {Node}   trigger_element    UI element which triggered opening of overlay dialogue.
 * @param {array}  hostid             Host ID.
 * @param {array}  eventid            Event ID.
 * @param {string} csrf_token         CSRF token for script execution.
 *
 * @return {array}
 */
function getMenuPopupScriptData(scripts, trigger_element, hostid, eventid, csrf_token) {
	let tree = {};

	// Parse scripts and create tree.
	for (let key in scripts) {
		const script = scripts[key];

		if (typeof script.scriptid !== 'undefined') {
			const items = (script.menu_path.length > 0) ? splitPath(script.menu_path) : [];

			appendTreeItem(tree, script.name, items, {
				scriptid: script.scriptid,
				confirmation: script.confirmation,
				hostid: hostid,
				eventid: eventid,
				manualinput: script.manualinput,
				manualinput_prompt: script.manualinput_prompt,
				manualinput_validator_type: script.manualinput_validator_type,
				manualinput_validator: script.manualinput_validator,
				manualinput_default_value: script.manualinput_default_value
			});
		}
	}

	return getMenuPopupScriptItems(tree, trigger_element, csrf_token);
}

/**
 * Build URL menu tree.
 *
 * @param {array}        urls             URL names and menu paths.
 * @param {Node}         trigger_element  UI element which triggered opening of overlay dialogue.
 * @param {string|null}  hostid           ID of host which triggered opening of overlay dialogue.
 * @param {string|null}  eventid          ID of event which triggered opening of overlay dialogue.
 *
 * @return {array}
 */
function getMenuPopupURLData(urls, trigger_element, hostid, eventid) {
	let tree = {};

	// Parse URLs and create tree.
	for (let key in urls) {
		const url = urls[key];

		if (typeof url.menu_path !== 'undefined') {
			const items = (url.menu_path.length > 0) ? splitPath(url.menu_path) : [];

			appendTreeItem(tree, url.label, items, {
				url: url.url,
				target: url.target,
				confirmation: url.confirmation,
				manualinput: url.manualinput,
				manualinput_prompt: url.manualinput_prompt,
				manualinput_validator_type: url.manualinput_validator_type,
				manualinput_validator: url.manualinput_validator,
				manualinput_default_value: url.manualinput_default_value,
				scriptid: url.scriptid,
				hostid,
				eventid
			});
		}
	}

	return getMenuPopupURLItems(tree, trigger_element);
}

/**
 * Add a menu item to tree.
 *
 * @param {object} tree   Menu tree object to where menu items will be added.
 * @param {string} name   Menu element label (name).
 * @param {array}  items  List of menu items to add.
 * @param {object} params Additional menu item parameters like URL, target, clickcallback etc.
 */
function appendTreeItem(tree, name, items, params) {
	if (items.length > 0) {
		const item = items.shift();

		if (typeof tree[item] === 'undefined') {
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
}

/**
 * Build URL menu items from tree.
 *
 * @param {object} tree         Menu tree object to where menu items are.
 * @param {Node}   trigger_elm  UI element which triggered opening of overlay dialogue.
 *
 * @return {array}
 */
function getMenuPopupURLItems(tree, trigger_elm) {
	let items = [];

	if (objectSize(tree) > 0) {
		Object.values(tree).map((data) => {
			const item = {label: data.name};

			if (data.items !== undefined && objectSize(data.items) > 0) {
				item.items = getMenuPopupURLItems(data.items, trigger_elm);
			}

			if (data.params !== undefined) {
				if (data.params.scriptid !== undefined) {
					item.clickCallback = function(e) {
						jQuery(this)
							.closest('.menu-popup-top')
							.menuPopup('close', trigger_elm, false);
						Script.openUrl(data.params.scriptid, data.params.confirmation, trigger_elm,
							data.params.hostid, data.params.eventid, data.params.url, data.params.target,
							data.params.manualinput, data.params.manualinput_prompt,
							data.params.manualinput_validator_type, data.params.manualinput_validator,
							data.params.manualinput_default_value
						);
						cancelEvent(e);
					};
				}
				else {
					item.url = data.params.url;
					if (data.params.target !== '') {
						item.target = data.params.target;
					}
				}
			}

			items[items.length] = item;
		});
	}

	return items;
}

/**
 * Build script menu items from tree.
 *
 * @param {object} tree              Menu tree object to where menu items are.
 * @param {Node}   trigger_elm       UI element which triggered opening of overlay dialogue.
 * @param {string} csrf_token        CSRF token for script execution.
 *
 * @return {array}
 */
function getMenuPopupScriptItems(tree, trigger_elm, csrf_token) {
	let items = [];

	if (objectSize(tree) > 0) {
		Object.values(tree).map((data) => {
			const item = {label: data.name};

			if (data.items !== undefined && objectSize(data.items) > 0) {
				item.items = getMenuPopupScriptItems(data.items, trigger_elm, csrf_token);
			}

			if (data.params !== undefined && data.params.scriptid !== undefined) {
				item.clickCallback = function(e) {
					jQuery(this)
						.closest('.menu-popup-top')
						.menuPopup('close', trigger_elm, false);
					Script.execute(data.params.scriptid, data.params.confirmation, trigger_elm, data.params.hostid,
						data.params.eventid, csrf_token, data.params.manualinput, data.params.manualinput_prompt,
						data.params.manualinput_validator_type, data.params.manualinput_validator,
						data.params.manualinput_default_value
					);
					cancelEvent(e);
				};
			}

			items[items.length] = item;
		});
	}

	return items;
}

/**
 * Get menu structure for discovery rules.
 *
 * @param {array}  options                            An array of options for discovery rule menu popup.
 * @param {string} options['druleid']                 Discovery rule ID.
 * @param {bool}   options['allowed_ui_conf_drules']  Whether user has access to discovery rules configuration page.
 *
 * @return {array}
 */
function getMenuPopupDRule(options) {
	const sections = [];
	const config_urls = [];

	const url = new Curl('zabbix.php');
	url.setArgument('action', 'popup');
	url.setArgument('popup', 'discovery.edit');
	url.setArgument('druleid', options.druleid);

	config_urls.push({
		label: t('Discovery rule'),
		disabled: !options.allowed_ui_conf_drules,
		url: url.getUrl()
	});

	sections.push({
		label: t('Configuration'),
		items: config_urls
	});

	return sections;
}

jQuery(function($) {

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

			var section_label = null;

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

		if (sections.length == 1) {
			if (typeof sections[0].label === 'string' && sections[0].label.length) {
				$menu_popup.attr({'aria-label': sections[0].label});
			}
		}
	}

	var defaultOptions = {
		closeCallback: function(){},
		background_layer: true
	};

	var methods = {
		init: function(sections, event, options) {
			// Don't display empty menu.
			if (!sections.length || !sections[0]['items'].length) {
				return;
			}

			var $opener = $(this);

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
						let max_left = data.horizontal === 'left'
							? document.querySelector('.wrapper').clientWidth
							: document.querySelector('.wrapper').clientWidth - data.element.width;

						pos.top = Math.max(0, pos.top);
						pos.left = Math.max(0, Math.min(max_left, pos.left));

						data.element.element[0].style.top = `${pos.top}px`;
						data.element.element[0].style.left = `${pos.left}px`;
					}
				}
			}, defaultOptions, options || {});

			// Close other action menus and prevent focus jumping before opening a new popup.
			$('.menu-popup-top').menuPopup('close', null, false);

			$opener.attr('aria-expanded', 'true');

			let $menu_popup = $('<ul>', {
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
				$('.wrapper').append($('<div>', {class: 'menu-popup-overlay'}).append($menu_popup));
			}
			else {
				$('.wrapper').append($menu_popup);
			}

			// Position the menu (before hiding).
			$menu_popup.position(options.position);

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
		},

		close: function(trigger_elem, return_focus) {
			var menu_popup = $(this),
				options = $(menu_popup).data('menu_popup') || {};

			if (!menu_popup.is(trigger_elem) && menu_popup.has(trigger_elem).length === 0) {
				$('[aria-expanded="true"]', trigger_elem).attr({'aria-expanded': 'false'});
				menu_popup.fadeOut(0);

				$('.highlighted', menu_popup).removeClass('highlighted');
				$('[aria-expanded="true"]', menu_popup).attr({'aria-expanded': 'false'});

				$(document)
					.off('click dragstart', menuPopupDocumentCloseHandler)
					.off('keydown', menuPopupKeyDownHandler);

				var overlay = removeFromOverlaysStack('menu-popup', return_focus);

				if (overlay && typeof overlay['element'] !== undefined) {
					// Remove expanded attribute of the original opener.
					$(overlay['element']).attr({'aria-expanded': 'false'});
				}

				if (options.background_layer) {
					$('.menu-popup-overlay').remove();
				}
				else {
					menu_popup.remove();
				}

				// Call menu close callback function.
				typeof options.closeCallback === 'function' && options.closeCallback.apply();
			}
		},

		/**
		 * Refresh popup menu, call refreshCallback for every item if defined. Refresh recreate item dom nodes.
		 */
		refresh: function(widget) {
			var $opener = $(this),
				sections = $opener.data('sections'),
				$menu_popup = $opener.data('menu_popup');

			$menu_popup.empty();
			sections.forEach(
				section => section.items && section.items.forEach(
					item => item.refreshCallback && item.refreshCallback.call(item, widget)
			));
			addMenuPopupItems($menu_popup, sections);
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
					const $submenu = $('ul:first', item[0]);

					if ($submenu.length) {
						const position = {
							'top': pos.top - 6,
							'left': pos.left + li.outerWidth() + 14,
						};

						$submenu
							.css('display' ,'block')
							.prev('[role="menuitem"]').attr({'aria-expanded': 'true'});

						let max_relative_left = $(window).outerWidth(true) - $submenu.outerWidth(true)
							- menu[0].getBoundingClientRect().left - 14 * 2;

						position.top = Math.max(0, position.top);
						position.left = Math.max(0, Math.min(max_relative_left, position.left));

						$submenu.css(position);
					}
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

		link.addClass('menu-popup-item');

		if (options.disabled) {
			link.addClass('disabled');
		}
		else {
			if (typeof options.url !== 'undefined') {
				link.attr('href', options.url);

				if ('target' in options) {
					link.attr('target', options.target);
				}

				if ('rel' in options) {
					link.attr('rel', options.rel);
				}
			}

			if (typeof options.clickCallback !== 'undefined') {
				link.on('click', options.clickCallback);
			}
		}

		if (options.selected) {
			link.addClass(['selected', ZBX_ICON_CHECK]);
		}

		if (options.class) {
			link.addClass(options.class);
		}

		if ('dataAttributes' in options) {
			$.each(options.dataAttributes, function(key, value) {
				link.attr((key.substr(0, 5) === 'data-') ? key : 'data-' + key, value);
			});
		}

		if (typeof options.items !== 'undefined' && options.items.length > 0) {
			link.attr({
				'aria-haspopup': 'true',
				'aria-expanded': 'false',
				'aria-hidden': 'true'
			});
			link.on('click', function(e) {
				e.stopPropagation();
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
