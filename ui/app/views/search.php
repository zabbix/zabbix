<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * @var CView $this
 */

$widgets = [];

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
			->setColSpan($data['hosts'] ? 6 : 1)
			->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
		)
	)

	->removeId();

foreach ($data['hosts'] as $hostid => $host) {
	$interface = reset($host['interfaces']);
	$link = 'hostid='.$hostid;
	$visible_name = make_decoration($host['name'], $data['search']);

	$name_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? new CLink($visible_name, (new CUrl('hosts.php'))
			->setArgument('form', 'update')
			->setArgument('hostid', $hostid)
		)
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
				->setArgument('filter_hostids[]', $hostid)
				->setArgument('filter_set', '1')
		)
		: _('Latest data');

	$latest_data_link = (new CCol($latest_data_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

	$problems_link = $data['allowed_ui_problems']
		? new CLink(_('Problems'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('filter_name', '')
				->setArgument('hostids', [$hostid])
		)
		: _('Problems');

	$charts_link = $data['allowed_ui_hosts']
		? new CLink(_('Graphs'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'charts.view')
				->setArgument('view_as', HISTORY_GRAPH)
				->setArgument('filter_hostids[]', $hostid)
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

	$app_count = CViewHelper::showNum($host['applications']);
	$applications_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Applications'), (new CUrl('zabbix.php'))
			->setArgument('action', 'application.list')
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $app_count]
		: [_('Applications'), $app_count];

	$applications_link = (new CCol($applications_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

	$item_count = CViewHelper::showNum($host['items']);
	$items_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Items'), (new CUrl('items.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $item_count]
		: [_('Items'), $item_count];

	$trigger_count = CViewHelper::showNum($host['triggers']);
	$triggers_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Triggers'), (new CUrl('triggers.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $trigger_count]
		: [_('Triggers'), $trigger_count];

	$graph_count = CViewHelper::showNum($host['graphs']);
	$graphs_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Graphs'), (new CUrl('graphs.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $graph_count]
		: [_('Graphs'), $graph_count];

	$discovery_count = CViewHelper::showNum($host['discoveries']);
	$discovery_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Discovery'), (new CUrl('host_discovery.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $discovery_count]
		: [_('Discovery'), $discovery_count];

	$httptest_count = CViewHelper::showNum($host['httpTests']);
	$httptests_link = ($host['editable'] && $data['allowed_ui_conf_hosts'])
		? [new CLink(_('Web'), (new CUrl('httpconf.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $httptest_count]
		: [_('Web'), $httptest_count];

	$table->addRow([
		$name_link,
		$interface ? make_decoration($interface['ip'], $data['search']) : '',
		$interface ? make_decoration($interface['dns'], $data['search']) : '',
		$latest_data_link,
		$problems_link,
		$charts_link,
		$dashboards_link,
		$web_link,
		$applications_link,
		$items_link,
		$triggers_link,
		$graphs_link,
		$discovery_link,
		$httptests_link
	]);
}

$widgets[] = (new CCollapsibleUiWidget(WIDGET_SEARCH_HOSTS, $table))
	->addClass(ZBX_STYLE_DASHBRD_WIDGET_FLUID)
	->setExpanded((bool) CProfile::get('web.search.hats.'.WIDGET_SEARCH_HOSTS.'.state', true))
	->setHeader(_('Hosts'), [], 'web.search.hats.'.WIDGET_SEARCH_HOSTS.'.state')
	->setFooter(new CList([
		_s('Displaying %1$s of %2$s found', count($data['hosts']), $data['total_hosts_cnt'])
	]));

$table = (new CTableInfo())
	->setHeader((new CRowHeader())
		->addItem(new CColHeader(_('Host group')))
		->addItem((new CColHeader(_('Monitoring')))
			->setColSpan($data['groups'] ? 3 : 1)
			->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
		)
		->addItem($data['admin']
			? (new CColHeader(_('Configuration')))
				->setColSpan($data['groups'] ? 2 : 1)
				->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
			: null
		)
	);

foreach ($data['groups'] as $groupid => $group) {
	$caption = make_decoration($group['name'], $data['search']);
	$link = 'groupid='.$groupid.'&hostid=0';
	$hosts_link = null;
	$templates_link = null;

	if ($data['admin']) {
		$hosts_link = ($group['editable'] && $data['allowed_ui_conf_hosts'] && $group['hosts'])
			? [new CLink(_('Hosts'), (new CUrl('hosts.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$groupid])
			), CViewHelper::showNum($group['hosts'])]
			: _('Hosts');

		$hosts_link = (new CCol($hosts_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

		$templates_link = ($group['editable'] && $data['allowed_ui_conf_templates'] && $group['templates'])
			? [new CLink(_('Templates'), (new CUrl('templates.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$groupid])
			), CViewHelper::showNum($group['templates'])]
			: _('Templates');
	}

	$latest_data_link = $data['allowed_ui_latest_data']
		? new CLink(_('Latest data'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'latest.view')
				->setArgument('filter_groupids[]', $groupid)
				->setArgument('filter_set', '1')
		)
		: _('Latest data');

	$latest_data_link = (new CCol($latest_data_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

	$table->addRow([
		$group['editable'] && $data['allowed_ui_conf_host_groups']
			? new CLink($caption, 'hostgroups.php?form=update&'.$link)
			: new CSpan($caption),
		$latest_data_link,
		$data['allowed_ui_problems']
			? new CLink(_('Problems'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('groupids', [$groupid])
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
		$hosts_link,
		$templates_link
	]);
}

$widgets[] = (new CCollapsibleUiWidget(WIDGET_SEARCH_HOSTGROUP, $table))
	->addClass(ZBX_STYLE_DASHBRD_WIDGET_FLUID)
	->setExpanded((bool) CProfile::get('web.search.hats.'.WIDGET_SEARCH_HOSTGROUP.'.state', true))
	->setHeader(_('Host groups'), [], 'web.search.hats.'.WIDGET_SEARCH_HOSTGROUP.'.state')
	->setFooter(new CList([
		_s('Displaying %1$s of %2$s found', count($data['groups']), $data['total_groups_cnt'])
	]));

if ($data['admin']) {
	$table = (new CTableInfo())
		->setHeader((new CRowHeader())
			->addItem(new CColHeader(_('Template')))
			->addItem((new CColHeader(_('Configuration')))
				->setColSpan($data['templates'] ? 7 : 1)
				->addClass(ZBX_STYLE_TABLE_LEFT_BORDER)
			)
		);

	foreach ($data['templates'] as $templateid => $template) {
		$visible_name = make_decoration($template['name'], $data['search']);
		$app_count = CViewHelper::showNum($template['applications']);
		$item_count = CViewHelper::showNum($template['items']);
		$trigger_count = CViewHelper::showNum($template['triggers']);
		$graph_count = CViewHelper::showNum($template['graphs']);
		$dashboard_count = CViewHelper::showNum($template['dashboards']);
		$discovery_count = CViewHelper::showNum($template['discoveries']);
		$httptest_count = CViewHelper::showNum($template['httpTests']);

		$template_cell = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink($visible_name, (new CUrl('templates.php'))
				->setArgument('form', 'update')
				->setArgument('templateid', $templateid)
			)]
			: [new CSpan($visible_name)];

		$applications_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Applications'), (new CUrl('zabbix.php'))
				->setArgument('action', 'application.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $app_count]
			: [_('Applications'), $app_count];

		$applications_link = (new CCol($applications_link))->addClass(ZBX_STYLE_TABLE_LEFT_BORDER);

		$items_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Items'), (new CUrl('items.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $item_count]
			: [_('Items'), $item_count];

		$triggers_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Triggers'), (new CUrl('triggers.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $trigger_count]
			: [_('Triggers'), $trigger_count];

		$graphs_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Graphs'), (new CUrl('graphs.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $graph_count]
			: [_('Graphs'), $graph_count];

		$dashboards_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [
				new CLink(_('Dashboards'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'template.dashboard.list')
						->setArgument('templateid', $templateid)
				),
				$dashboard_count
			]
			: [_('Dashboards'), $dashboard_count];

		$discovery_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Discovery'), (new CUrl('host_discovery.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $discovery_count]
			: [_('Discovery'), $discovery_count];

		$httptests_link = ($template['editable'] && $data['allowed_ui_conf_templates'])
			? [new CLink(_('Web'), (new CUrl('httpconf.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $httptest_count]
			: [_('Web'), $httptest_count];

		if ($template['host'] !== $template['name'] && strpos($template['host'], $data['search']) !== false) {
			$template_cell[] = BR();
			$template_cell[] = '(';
			$template_cell[] = make_decoration($template['host'], $data['search']);
			$template_cell[] = ')';
		}

		$table->addRow([$template_cell, $applications_link, $items_link, $triggers_link, $graphs_link, $dashboards_link,
			$discovery_link, $httptests_link
		]);
	}

	$widgets[] = (new CCollapsibleUiWidget(WIDGET_SEARCH_TEMPLATES, $table))
		->addClass(ZBX_STYLE_DASHBRD_WIDGET_FLUID)
		->setExpanded((bool) CProfile::get('web.search.hats.'.WIDGET_SEARCH_TEMPLATES.'.state', true))
		->setHeader(_('Templates'), [], 'web.search.hats.'.WIDGET_SEARCH_TEMPLATES.'.state')
		->setFooter(new CList([
			_s('Displaying %1$s of %2$s found', count($data['templates']), $data['total_templates_cnt'])
		]));
}

(new CWidget())
	->setTitle(_('Search').': '.$data['search'])
	->addItem(new CDiv($widgets))
	->show();
