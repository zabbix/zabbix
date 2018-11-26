<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

extract($data);
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

foreach ($hosts['rows'] as $host) {
	$hostid = $host['hostid'];

	$interface = reset($host['interfaces']);
	$host['ip'] = $interface['ip'];
	$host['dns'] = $interface['dns'];
	$host['port'] = $interface['port'];
	$link = 'hostid='.$hostid;
	$visible_name = make_decoration($host['name'], $search);

	if ($admin && array_key_exists($hostid, $hosts['editable_rows'])) {
		$host_name = new CLink($visible_name, 'hosts.php?form=update&'.$link);
		$applications_link = [
			new CLink(_('Applications'), 'applications.php?'.$link),
			CViewHelper::showNum($host['applications'])
		];
		$items_link = [
			new CLink(_('Items'), 'items.php?filter_set=1&'.$link),
			CViewHelper::showNum($host['items'])
		];
		$triggers_link = [
			new CLink(_('Triggers'), 'triggers.php?'.$link),
			CViewHelper::showNum($host['triggers'])
		];
		$graphs_link = [
			new CLink(_('Graphs'), 'graphs.php?'.$link),
			CViewHelper::showNum($host['graphs'])
		];
		$discovery_link = [
			new CLink(_('Discovery'), 'host_discovery.php?'.$link),
			CViewHelper::showNum($host['discoveries'])
		];
		$httptests_link = [
			new CLink(_('Web'), 'httpconf.php?'.$link),
			CViewHelper::showNum($host['httpTests'])
		];
	}
	else {
		$host_name = new CSpan($visible_name);
		$applications_link = [
			_('Applications'),
			CViewHelper::showNum($host['applications'])
		];
		$items_link = [
			_('Items'),
			CViewHelper::showNum($host['items'])
		];
		$triggers_link = [
			_('Triggers'),
			CViewHelper::showNum($host['triggers'])
		];
		$graphs_link = [
			_('Graphs'),
			CViewHelper::showNum($host['graphs'])
		];
		$discovery_link = [
			_('Discovery'),
			CViewHelper::showNum($host['discoveries'])
		];
		$httptests_link = [
			_('Web'),
			CViewHelper::showNum($host['httpTests'])
		];
	}

	if ($host['status'] == HOST_STATUS_NOT_MONITORED) {
		$host_name
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_RED);
	}

	// display the host name only if it matches the search string and is different from the visible name
	if ($host['host'] !== $host['name'] && stripos($host['host'], $search) !== false) {
		$host_name = [$host_name, BR(), '(', make_decoration($host['host'], $search), ')'];
	}

	$hostip = make_decoration($host['ip'], $search);
	$hostdns = make_decoration($host['dns'], $search);

	$table->addRow([
		$host_name,
		$hostip,
		$hostdns,
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
	->setExpanded((bool) CProfile::get($hosts['hat'], true))
	->setHeader(_('Hosts'), [], false, $hosts['hat'])
	->setFooter(new CList([_s('Displaying %1$s of %2$s found', $hosts['count'], $hosts['overall_count'])]));


$table = (new CTableInfo())
	->setHeader([
		_('Host group'),
		_('Latest data'),
		_('Problems'),
		_('Graphs'),
		_('Web'),
		$admin ? _('Hosts') : null,
		$admin ? _('Templates') : null
	]);

foreach ($host_groups['rows'] as $group) {
	$hostgroupid = $group['groupid'];
	$caption = make_decoration($group['name'], $search);
	$link = 'groupid='.$hostgroupid.'&hostid=0';

	$hosts_link = null;
	$templates_link = null;
	$hgroup_link = new CSpan($caption);

	if ($admin) {
		if (array_key_exists($hostgroupid, $host_groups['editable_rows'])) {
			if ($group['hosts']) {
				$hosts_link = [
					new CLink(_('Hosts'), 'hosts.php?groupid='.$hostgroupid),
					CViewHelper::showNum($group['hosts'])
				];
			}
			else {
				$hosts_link = _('Hosts');
			}

			if ($group['templates']) {
				$templates_link = [
					new CLink(_('Templates'), 'templates.php?groupid='.$hostgroupid),
					CViewHelper::showNum($group['templates'])
				];
			}
			else {
				$templates_link = _('Templates');
			}

			$hgroup_link = new CLink($caption, 'hostgroups.php?form=update&'.$link);
		}
		else {
			$hosts_link = _('Hosts');
			$templates_link = _('Templates');
		}
	}

	$table->addRow([
		$hgroup_link,
		new CLink(_('Latest data'), 'latest.php?filter_set=1&groupids[]='.$hostgroupid),
		new CLink(_('Problems'), (new CUrl('zabbix.php'))
			->setArgument('action', 'problem.view')
			->setArgument('filter_groupids[]', $hostgroupid)
			->setArgument('filter_set', '1')
		),
		new CLink(_('Graphs'), 'charts.php?'.$link),
		new CLink(_('Web'), 'zabbix.php?action=web.view&'.$link),
		$hosts_link,
		$templates_link
	]);
}

$widgets[] = (new CCollapsibleUiWidget(WIDGET_SEARCH_HOSTGROUP, $table))
	->setExpanded((bool) CProfile::get($host_groups['hat'], true))
	->setHeader(_('Host groups'), [], false, $host_groups['hat'])
	->setFooter(new CList([_s('Displaying %1$s of %2$s found', $host_groups['count'], $host_groups['overall_count'])]));


if ($templates !== null) {
	$table = (new CTableInfo())->setHeader([
		_('Template'), _('Applications'), _('Items'), _('Triggers'), _('Graphs'), _('Screens'), _('Discovery'), _('Web')
	]);

	foreach ($templates['rows'] as $template) {
		$templateid = $template['templateid'];
		$link = 'groupid='.$template['groups'][0]['groupid'].'&hostid='.$templateid;

		$visible_name = make_decoration($template['name'], $search);
		if (array_key_exists($templateid, $templates['editable_rows'])) {
			$template_cell = [new CLink($visible_name,
				'templates.php?form=update&'.'&templateid='.$templateid
			)];
			$applications_link = [
				new CLink(_('Applications'), 'applications.php?'.$link),
				CViewHelper::showNum($template['applications'])
			];
			$items_link = [
				new CLink(_('Items'), 'items.php?filter_set=1&'.$link),
				CViewHelper::showNum($template['items'])
			];
			$triggers_link = [
				new CLink(_('Triggers'), 'triggers.php?'.$link),
				CViewHelper::showNum($template['triggers'])
			];
			$graphs_link = [
				new CLink(_('Graphs'), 'graphs.php?'.$link),
				CViewHelper::showNum($template['graphs'])
			];
			$screens_link = [
				new CLink(_('Screens'), 'screenconf.php?templateid='.$templateid),
				CViewHelper::showNum($template['screens'])
			];
			$discovery_link = [
				new CLink(_('Discovery'), 'host_discovery.php?'.$link),
				CViewHelper::showNum($template['discoveries'])
			];
			$httptests_link = [
				new CLink(_('Web'), 'httpconf.php?'.$link),
				CViewHelper::showNum($template['httpTests'])
			];
		}
		else {
			$template_cell = [new CSpan($visible_name)];
			$applications_link = _('Applications').' ('.$template['applications'].')';
			$items_link = _('Items').' ('.$template['items'].')';
			$triggers_link = _('Triggers').' ('.$template['triggers'].')';
			$graphs_link = _('Graphs').' ('.$template['graphs'].')';
			$screens_link = _('Screens').' ('.$template['screens'].')';
			$discovery_link = _('Discovery').' ('.$template['discoveries'].')';
			$httptests_link = _('Web').' ('.$template['httpTests'].')';
		}

		if ($template['host'] !== $template['name'] && strpos($template['host'], $search) !== false) {
			$template_cell[] = BR();
			$template_cell[] = '(';
			$template_cell[] = make_decoration($template['host'], $search);
			$template_cell[] = ')';
		}

		$table->addRow([
			$template_cell,
			$applications_link,
			$items_link,
			$triggers_link,
			$graphs_link,
			$screens_link,
			$discovery_link,
			$httptests_link
		]);
	}

	$widgets[] = (new CCollapsibleUiWidget(WIDGET_SEARCH_TEMPLATES, $table))
		->setExpanded((bool) CProfile::get($templates['hat'], true))
		->setHeader(_('Templates'), [], false, $templates['hat'])
		->setFooter(new CList([_s('Displaying %1$s of %2$s found', $templates['count'], $templates['overall_count'])]));
}

(new CWidget())
	->setTitle(_('Search').':'.SPACE.$search)
	->addItem(new CDiv($widgets))
	->show();
