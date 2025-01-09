<?php
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
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('items.js');
$this->addJsFile('multilineinput.js');
$this->includeJsFile('search.js.php');

$sections = [];

$table = (new CTableInfo())
	->setHeader((new CRowHeader())
		->addItem(new CColHeader(_('Host')))
		->addItem(new CColHeader(_('IP')))
		->addItem(new CColHeader(_('DNS')))
		->addItem((new CColHeader(_('Monitoring')))
			->setColSpan($data['hosts'] ? 5 : 1)
			->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
		)
		->addItem((new CColHeader(_('Configuration')))
			->setColSpan($data['hosts'] ? 5 : 1)
			->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
		)
	)
	->removeId()
	->setNoDataMessage(_('No data found'));

foreach ($data['hosts'] as $hostid => $host) {
	$interface = reset($host['interfaces']);
	$visible_name = make_decoration($host['name'], $data['search']);

	$host_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'host.edit')
		->setArgument('hostid', $hostid)
		->getUrl();

	$name_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? (new CLink($visible_name, $host_url))
			->setAttribute('data-hostid', $host['hostid'])
			->setAttribute('data-action', 'host.edit')
		: new CSpan($visible_name);

	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$name_link
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_RED);
	}

	// Display the host name only if it matches the search string and is different from the visible name.
	if ($host['host'] !== $host['name'] && stripos($host['host'], $data['search']) !== false) {
		$name_link = [$name_link, BR(), '(', make_decoration($host['host'], $data['search']), ')'];
	}

	$latest_data_link = $data['allowed_ui_latest_data']
		? new CLink(_('Latest data'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'latest.view')
				->setArgument('hostids[]', $hostid)
				->setArgument('filter_set', '1')
		)
		: _('Latest data');

	$latest_data_link = (new CCol($latest_data_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

	$problems_link = $data['allowed_ui_problems']
		? new CLink(_('Problems'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('hostids', [$hostid])
				->setArgument('filter_set', '1')
		)
		: _('Problems');

	$charts_link = $data['allowed_ui_hosts']
		? new CLink(_('Graphs'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'charts.view')
				->setArgument('filter_hostids', (array) $hostid)
				->setArgument('filter_set', '1')
		)
		: _('Graphs');

	$dashboards_link = $data['allowed_ui_hosts']
		? new CLink(_('Dashboards'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'host.dashboard.view')
				->setArgument('hostid', $hostid)
		)
		: _('Dashboards');

	$web_link = $data['allowed_ui_hosts']
		? new CLink(_('Web'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'web.view')
				->setArgument('filter_hostids[]', $hostid)
				->setArgument('filter_set', '1')
		)
		: _('Web');

	$item_count = CViewHelper::showNum($host['items']);
	$items_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Items'), (new CUrl('zabbix.php'))
			->setArgument('action', 'item.list')
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
			->setArgument('context', 'host')
		), $item_count]
		: _('Items');

	$items_link = (new CCol($items_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

	$trigger_count = CViewHelper::showNum($host['triggers']);
	$triggers_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Triggers'), (new CUrl('zabbix.php'))
			->setArgument('action', 'trigger.list')
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
			->setArgument('context', 'host')
		), $trigger_count]
		: _('Triggers');

	$graph_count = CViewHelper::showNum($host['graphs']);
	$graphs_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Graphs'), (new CUrl('graphs.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
			->setArgument('context', 'host')
		), $graph_count]
		: _('Graphs');

	$discovery_count = CViewHelper::showNum($host['discoveries']);
	$discovery_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Discovery'), (new CUrl('host_discovery.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
			->setArgument('context', 'host')
		), $discovery_count]
		: _('Discovery');

	$httptest_count = CViewHelper::showNum($host['httpTests']);
	$httptests_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Web'), (new CUrl('httpconf.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
			->setArgument('context', 'host')
		), $httptest_count]
		: _('Web');

	$table->addRow([
		$name_link,
		$interface ? make_decoration($interface['ip'], $data['search']) : '',
		$interface ? make_decoration($interface['dns'], $data['search']) : '',
		$latest_data_link,
		$problems_link,
		$charts_link,
		$dashboards_link,
		$web_link,
		$items_link,
		$triggers_link,
		$graphs_link,
		$discovery_link,
		$httptests_link
	]);
}

$sections[] = (new CSectionCollapsible($table))
	->setId(SECTION_SEARCH_HOSTS)
	->setHeader(new CTag('h4', true, _('Hosts')))
	->setFooter($data['total_hosts_cnt'] != 0
		? _s('Displaying %1$s of %2$s found', count($data['hosts']), $data['total_hosts_cnt'])
		: null
	)
	->setProfileIdx('web.search.hats.'.SECTION_SEARCH_HOSTS.'.state')
	->setExpanded((bool) CProfile::get('web.search.hats.'.SECTION_SEARCH_HOSTS.'.state', true));

$table = (new CTableInfo())
	->setHeader((new CRowHeader())
		->addItem(new CColHeader(_('Host group')))
		->addItem((new CColHeader(_('Monitoring')))
			->setColSpan($data['host_groups'] ? 3 : 1)
			->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
		)
		->addItem(
			$data['admin']
				? (new CColHeader(_('Configuration')))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
				: null
		)
	)
	->setNoDataMessage(_('No data found'));

foreach ($data['host_groups'] as $groupid => $group) {
	$caption = make_decoration($group['name'], $data['search']);
	$group_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'hostgroup.edit')
		->setArgument('groupid', $groupid)
		->getUrl();

	$name_link = $group['editable'] && $data['allowed_ui_conf_host_groups']
		? (new CLink($caption, $group_url))
			->setAttribute('data-groupid', $groupid)
			->setAttribute('data-action', 'hostgroup.edit')
		: new CSpan($caption);

	$hosts_link = null;

	if ($data['admin']) {
		$hosts_link = ($group['editable'] && $data['allowed_ui_conf_hosts'] && $group['hosts'])
			? [new CLink(_('Hosts'), (new CUrl('zabbix.php'))
				->setArgument('action', 'host.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$groupid])
			), CViewHelper::showNum($group['hosts'])]
			: _('Hosts');

		$hosts_link = (new CCol($hosts_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);
	}

	$latest_data_link = $data['allowed_ui_latest_data']
		? new CLink(_('Latest data'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'latest.view')
				->setArgument('groupids[]', $groupid)
				->setArgument('filter_set', '1')
		)
		: _('Latest data');

	$latest_data_link = (new CCol($latest_data_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

	$table->addRow([
		$name_link,
		$latest_data_link,
		$data['allowed_ui_problems']
			? new CLink(_('Problems'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('groupids', [$groupid])
					->setArgument('filter_set', '1')
			)
			: _('Problems'),
		$data['allowed_ui_hosts']
			? new CLink(_('Web'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'web.view')
					->setArgument('filter_groupids[]', $groupid)
					->setArgument('filter_set', '1')
			)
			:_('Web'),
		$hosts_link
	]);
}

$sections[] = (new CSectionCollapsible($table))
	->setId(SECTION_SEARCH_HOSTGROUP)
	->setHeader(new CTag('h4', true, _('Host groups')))
	->setFooter($data['total_host_groups_cnt'] != 0
		? _s('Displaying %1$s of %2$s found', count($data['host_groups']), $data['total_host_groups_cnt'])
		: null
	)
	->setProfileIdx('web.search.hats.'.SECTION_SEARCH_HOSTGROUP.'.state')
	->setExpanded((bool) CProfile::get('web.search.hats.'.SECTION_SEARCH_HOSTGROUP.'.state', true));

if ($data['admin']) {
	$table = (new CTableInfo())
		->setHeader((new CRowHeader())
			->addItem(new CColHeader(_('Template')))
			->addItem((new CColHeader(_('Configuration')))
				->setColSpan($data['templates'] ? 6 : 1)
				->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
			)
		)
		->setNoDataMessage(_('No data found'));

	foreach ($data['templates'] as $templateid => $template) {
		$visible_name = make_decoration($template['name'], $data['search']);
		$item_count = CViewHelper::showNum($template['items']);
		$trigger_count = CViewHelper::showNum($template['triggers']);
		$graph_count = CViewHelper::showNum($template['graphs']);
		$dashboard_count = CViewHelper::showNum($template['dashboards']);
		$discovery_count = CViewHelper::showNum($template['discoveries']);
		$httptest_count = CViewHelper::showNum($template['httpTests']);

		$template_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'template.edit')
			->setArgument('templateid', $templateid)
			->getUrl();

		$template_cell = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [(new CLink($visible_name, $template_url))
				->setAttribute('data-templateid', $templateid)
				->setAttribute('data-action', 'template.edit')
			]
			: [new CSpan($visible_name)];

		$items_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Items'), (new CUrl('zabbix.php'))
				->setArgument('action', 'item.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
				->setArgument('context', 'template')
			), $item_count]
			: _('Items');

		$items_link = (new CCol($items_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

		$triggers_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Triggers'), (new CUrl('zabbix.php'))
				->setArgument('action', 'trigger.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
				->setArgument('context', 'template')
			), $trigger_count]
			: _('Triggers');

		$graphs_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Graphs'), (new CUrl('graphs.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
				->setArgument('context', 'template')
			), $graph_count]
			: _('Graphs');

		$dashboards_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [
				new CLink(_('Dashboards'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'template.dashboard.list')
						->setArgument('templateid', $templateid)
				),
				$dashboard_count
			]
			: _('Dashboards');

		$discovery_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Discovery'), (new CUrl('host_discovery.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
				->setArgument('context', 'template')
			), $discovery_count]
			: _('Discovery');

		$httptests_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Web'), (new CUrl('httpconf.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
				->setArgument('context', 'template')
			), $httptest_count]
			: _('Web');

		if ($template['host'] !== $template['name'] && strpos($template['host'], $data['search']) !== false) {
			$template_cell[] = BR();
			$template_cell[] = '(';
			$template_cell[] = make_decoration($template['host'], $data['search']);
			$template_cell[] = ')';
		}

		$table->addRow([$template_cell, $items_link, $triggers_link, $graphs_link, $dashboards_link,
			$discovery_link, $httptests_link
		]);
	}

	$sections[] = (new CSectionCollapsible($table))
		->setId(SECTION_SEARCH_TEMPLATES)
		->setHeader(new CTag('h4', true, _('Templates')))
		->setFooter($data['total_templates_cnt'] != 0
			? _s('Displaying %1$s of %2$s found', count($data['templates']), $data['total_templates_cnt'])
			: null
		)
		->setProfileIdx('web.search.hats.'.SECTION_SEARCH_TEMPLATES.'.state')
		->setExpanded((bool) CProfile::get('web.search.hats.'.SECTION_SEARCH_TEMPLATES.'.state', true));
}

$table = (new CTableInfo())
	->setHeader((new CRowHeader())
		->addItem(new CColHeader(_('Template group')))
		->addItem(
			$data['admin']
				? (new CColHeader(_('Configuration')))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
				: null
		)
	)
	->setNoDataMessage(_('No data found'));

foreach ($data['template_groups'] as $groupid => $group) {
	$caption = make_decoration($group['name'], $data['search']);
	$templategroup_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'templategroup.edit')
		->setArgument('groupid', $groupid)
		->getUrl();

	$name_link = $group['editable'] && $data['allowed_ui_conf_template_groups']
		? (new CLink($caption, $templategroup_url))
			->setAttribute('data-groupid', $groupid)
			->setAttribute('data-action', 'templategroup.edit')
		: new CSpan($caption);

	$templates_link = null;

	if ($data['admin']) {
		$templates_link = ($group['editable'] && $data['allowed_ui_conf_templates'] && $group['templates'])
			? [new CLink(_('Templates'), (new CUrl('zabbix.php'))
				->setArgument('action', 'template.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$groupid])
			), CViewHelper::showNum($group['templates'])]
			: _('Templates');
		$templates_link = (new CCol($templates_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);
	}

	$table->addRow([$name_link, $templates_link]);
}

$sections[] = (new CSectionCollapsible($table))
	->setId(SECTION_SEARCH_TEMPLATEGROUP)
	->setHeader(new CTag('h4', true, _('Template groups')))
	->setFooter($data['total_template_groups_cnt'] != 0
		? _s('Displaying %1$s of %2$s found', count($data['template_groups']), $data['total_template_groups_cnt'])
		: null
	)
	->setProfileIdx('web.search.hats.'.SECTION_SEARCH_TEMPLATEGROUP.'.state')
	->setExpanded((bool) CProfile::get('web.search.hats.'.SECTION_SEARCH_TEMPLATEGROUP.'.state', true));

(new CHtmlPage())
	->setTitle(_('Search').': '.$data['search'])
	->setDocUrl(CDocHelper::getUrl(CDocHelper::SEARCH))
	->addItem(new CDiv($sections))
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
