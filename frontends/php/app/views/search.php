<?php
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


$widgets = [];

$table = (new CTableInfo())
	->setHeader([
		_('Host'),
		_('IP'),
		_('DNS'),
		_('Latest data'),
		_('Problems'),
		_('Graphs'),
		_('Screens'),
		_('Web'),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web')
	])
	->removeId();

foreach ($data['hosts'] as $hostid => $host) {
	$interface = reset($host['interfaces']);
	$link = 'hostid='.$hostid;
	$visible_name = make_decoration($host['name'], $data['search']);
	$name = $host['editable'] ? new CLink($visible_name, 'hosts.php?form=update&'.$link) : new CSpan($visible_name);
	$app_count = CViewHelper::showNum($host['applications']);
	$item_count = CViewHelper::showNum($host['items']);
	$trigger_count = CViewHelper::showNum($host['triggers']);
	$graph_count = CViewHelper::showNum($host['graphs']);
	$discovery_count = CViewHelper::showNum($host['discoveries']);
	$httptest_count = CViewHelper::showNum($host['httpTests']);

	$applications_link = $host['editable']
		? [new CLink(_('Applications'), 'applications.php?'.$link), $app_count]
		: [_('Applications'), $app_count];

	$items_link = $host['editable']
		? [new CLink(_('Items'), (new CUrl('items.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $item_count]
		: [_('Items'), $item_count];

	$triggers_link = $host['editable']
		? [new CLink(_('Triggers'), (new CUrl('triggers.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_hostids', [$hostid])
		), $trigger_count]
		: [_('Triggers'), $trigger_count];

	$graphs_link = $host['editable']
		? [new CLink(_('Graphs'), 'graphs.php?'.$link), $graph_count]
		: [_('Graphs'), $graph_count];

	$discovery_link = $host['editable']
		? [new CLink(_('Discovery'), 'host_discovery.php?'.$link), $discovery_count]
		: [_('Discovery'), $discovery_count];

	$httptests_link = $host['editable']
		? [new CLink(_('Web'), 'httpconf.php?'.$link), $httptest_count]
		: [_('Web'), $httptest_count];

	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$name
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_RED);
	}

	// Display the host name only if it matches the search string and is different from the visible name.
	if ($host['host'] !== $host['name'] && stripos($host['host'], $data['search']) !== false) {
		$name = [$name, BR(), '(', make_decoration($host['host'], $data['search']), ')'];
	}

	$table->addRow([
		$name,
		make_decoration($interface['ip'], $data['search']),
		make_decoration($interface['dns'], $data['search']),
		new CLink(_('Latest data'), 'latest.php?filter_set=1&hostids[]='.$hostid),
		new CLink(_('Problems'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('filter_hostids[]', $hostid)
			->setArgument('filter_set', '1')
		),
		new CLink(_('Graphs'), 'charts.php?'.$link),
		new CLink(_('Screens'), 'host_screen.php?hostid='.$hostid),
		new CLink(_('Web'), 'zabbix.php?action=web.view&'.$link),
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
	->setHeader([
		_('Host group'),
		_('Latest data'),
		_('Problems'),
		_('Graphs'),
		_('Web'),
		$data['admin'] ? _('Hosts') : null,
		$data['admin'] ? _('Templates') : null
	]);

foreach ($data['groups'] as $groupid => $group) {
	$caption = make_decoration($group['name'], $data['search']);
	$link = 'groupid='.$groupid.'&hostid=0';
	$hosts_link = null;
	$templates_link = null;

	if ($data['admin']) {
		$hosts_link = $group['editable']
			? $group['hosts']
				? [new CLink(_('Hosts'), 'hosts.php?groupid='.$groupid), CViewHelper::showNum($group['hosts'])]
				: _('Hosts')
			: _('Hosts');

		$templates_link = $group['editable']
			? $group['templates']
				? [new CLink(_('Templates'), 'templates.php?groupid='.$groupid),
					CViewHelper::showNum($group['templates'])
				]
				: _('Templates')
			: _('Templates');
	}

	$table->addRow([
		$group['editable'] ? new CLink($caption, 'hostgroups.php?form=update&'.$link) : new CSpan($caption),
		new CLink(_('Latest data'), 'latest.php?filter_set=1&groupids[]='.$groupid),
		new CLink(_('Problems'), (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('filter_groupids[]', $groupid)
			->setArgument('filter_set', '1')
		),
		new CLink(_('Graphs'), 'charts.php?'.$link),
		new CLink(_('Web'), 'zabbix.php?action=web.view&'.$link),
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
	$table = (new CTableInfo())->setHeader([
		_('Template'), _('Applications'), _('Items'), _('Triggers'), _('Graphs'), _('Screens'), _('Discovery'), _('Web')
	]);

	foreach ($data['templates'] as $templateid => $template) {
		$link = 'groupid='.$template['groups'][0]['groupid'].'&hostid='.$templateid;
		$visible_name = make_decoration($template['name'], $data['search']);
		$app_count = CViewHelper::showNum($template['applications']);
		$item_count = CViewHelper::showNum($template['items']);
		$trigger_count = CViewHelper::showNum($template['triggers']);
		$graph_count = CViewHelper::showNum($template['graphs']);
		$screen_count = CViewHelper::showNum($template['screens']);
		$discovery_count = CViewHelper::showNum($template['discoveries']);
		$httptest_count = CViewHelper::showNum($template['httpTests']);

		$template_cell = $template['editable']
			? [new CLink($visible_name,'templates.php?form=update&'.'&templateid='.$templateid)]
			: [new CSpan($visible_name)];

		$applications_link = $template['editable']
			? [new CLink(_('Applications'), 'applications.php?'.$link), $app_count]
			: [_('Applications'), $app_count];

		$items_link = $template['editable']
			? [new CLink(_('Items'), (new CUrl('items.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $item_count]
			: [_('Items'), $item_count];

		$triggers_link = $template['editable']
			? [new CLink(_('Triggers'), (new CUrl('triggers.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$templateid])
			), $trigger_count]
			: [_('Triggers'), $trigger_count];

		$graphs_link = $template['editable']
			? [new CLink(_('Graphs'), 'graphs.php?'.$link), $graph_count]
			: [_('Graphs'), $graph_count];

		$screens_link = $template['editable']
			? [new CLink(_('Screens'), 'screenconf.php?templateid='.$templateid), $screen_count]
			: [_('Screens'), $screen_count];

		$discovery_link = $template['editable']
			? [new CLink(_('Discovery'), 'host_discovery.php?'.$link), $discovery_count]
			: [_('Discovery'), $discovery_count];

		$httptests_link = $template['editable']
			? [new CLink(_('Web'), 'httpconf.php?'.$link), $httptest_count]
			: [_('Web'), $httptest_count];

		if ($template['host'] !== $template['name'] && strpos($template['host'], $data['search']) !== false) {
			$template_cell[] = BR();
			$template_cell[] = '(';
			$template_cell[] = make_decoration($template['host'], $data['search']);
			$template_cell[] = ')';
		}

		$table->addRow([$template_cell, $applications_link, $items_link, $triggers_link, $graphs_link, $screens_link,
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
