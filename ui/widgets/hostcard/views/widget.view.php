<?php declare(strict_types = 0);
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
 * Host card widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\HostCard\Includes\CWidgetFieldHostSections;
use Widgets\HostCard\Widget;

if ($data['error'] !== null) {
	$body = (new CTableInfo())->setNoDataMessage($data['error']);
}
elseif ($data['host']) {
	$host = $data['host'];
	$sections = [];

	foreach ($data['sections'] as $section) {
		switch ($section) {
			case CWidgetFieldHostSections::SECTION_HOST_GROUPS:
				$sections[] = makeSectionHostGroups($host['hostgroups']);
				break;

			case CWidgetFieldHostSections::SECTION_DESCRIPTION:
				$sections[] = makeSectionDescription($host['description']);
				break;

			case CWidgetFieldHostSections::SECTION_MONITORING:
				$sections[] = makeSectionMonitoring($host['hostid'], $host['dashboard_count'], $host['item_count'],
					$host['graph_count'], $host['web_scenario_count']
				);
				break;

			case CWidgetFieldHostSections::SECTION_AVAILABILITY:
				$sections[] = makeSectionAvailability($host['interfaces']);
				break;

			case CWidgetFieldHostSections::SECTION_MONITORED_BY:
				$sections[] = makeSectionMonitoredBy($host);
				break;

			case CWidgetFieldHostSections::SECTION_TEMPLATES:
				$sections[] = makeSectionTemplates($host['templates']);
				break;

			case CWidgetFieldHostSections::SECTION_INVENTORY:
				$sections[] = makeSectionInventory($host['hostid'], $host['inventory'], $data['inventory']);
				break;

			case CWidgetFieldHostSections::SECTION_TAGS:
				$sections[] = makeSectionTags($data['host']['tags']);
				break;
		}
	}

	$body = (new CDiv([
		makeSectionsHeader($host),
		(new CDiv($sections))->addClass(Widget::ZBX_STYLE_SECTIONS)
	]))->addClass(Widget::ZBX_STYLE_CLASS);
}
else {
	$body = (new CDiv(_('No data found')))
		->addClass(ZBX_STYLE_NO_DATA_MESSAGE)
		->addClass(ZBX_ICON_SEARCH_LARGE);
}

(new CWidgetView($data))
	->addItem($body)
	->show();

function makeSectionsHeader(array $host): CDiv {
	$host_status = '';
	$maintenance_status = '';
	$problems_indicator = '';

	if ($host['status'] == HOST_STATUS_MONITORED) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenance_status = makeMaintenanceIcon($host['maintenance_type'], $host['maintenance']['name'],
				$host['maintenance']['description']
			);
		}

		$problems = [];

		foreach ($host['problem_count'] as $severity => $count) {
			if ($count > 0) {
				$problems[] = (new CSpan($count))
					->addClass(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM)
					->addClass(CSeverityHelper::getStatusStyle($severity))
					->setTitle(CSeverityHelper::getName($severity));
			}
		}

		if ($problems) {
			$problems_indicator = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
				? new CLink(null,
					(new CUrl('zabbix.php'))
						->setArgument('action', 'problem.view')
						->setArgument('hostids', [$host['hostid']])
						->setArgument('filter_set', '1')
				)
				: new CSpan();

			$problems_indicator
				->addClass(ZBX_STYLE_PROBLEM_ICON_LINK)
				->addItem($problems);
		}
	}
	else {
		$host_status = (new CDiv('('._('Disabled').')'))->addClass(ZBX_STYLE_RED);
	}

	return (new CDiv([
		(new CDiv([
			(new CLinkAction($host['name']))
				->setTitle($host['name'])
				->setMenuPopup(CMenuPopupHelper::getHost($host['hostid'])),
			$host_status,
			$maintenance_status
		]))->addClass('host-name'),
		$problems_indicator
	]))->addClass('sections-header');
}

function makeSectionHostGroups(array $host_groups): CDiv {
	$groups = [];

	$i = 0;
	$group_count = count($host_groups);

	foreach ($host_groups as $group) {
		$groups[] = (new CSpan([
			(new CSpan($group['name']))
				->addClass('host-group-name')
				->setTitle($group['name']),
			++$i < $group_count ? (new CSpan(', '))->addClass('delimiter') : null
		]))->addClass('host-group');
	}

	if ($groups) {
		$groups[] = (new CLink(_('more')))
			->addClass(ZBX_STYLE_LINK_ALT)
			->setHint(implode(', ', array_column($host_groups, 'name')), ZBX_STYLE_HINTBOX_WRAP);
	}

	return (new CDiv([
		(new CDiv(_('Host groups')))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv($groups))
			->addClass(Widget::ZBX_STYLE_SECTION_BODY)
			->addClass('host-groups')
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-host-groups');
}

function makeSectionDescription(string $description): CDiv {
	return (new CDiv(
		(new CDiv($description))
			->addClass(ZBX_STYLE_LINE_CLAMP)
			->setTitle($description)
	))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-description');
}

function makeSectionMonitoring(string $hostid, int $dashboard_count, int $item_count, int $graph_count,
		int $web_scenario_count): CDiv {
	$can_view_monitoring_hosts = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS);

	return (new CDiv([
		(new CDiv(_('Monitoring')))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv([
			(new CDiv([
				$can_view_monitoring_hosts && $dashboard_count > 0
					? (new CLink(_('Dashboards'),
						(new CUrl('zabbix.php'))
							->setArgument('action', 'host.dashboard.view')
							->setArgument('hostid', $hostid)
					))
						->addClass('monitoring-item-name')
						->setTitle(_('Dashboards'))
					: (new CSpan(_('Dashboards')))
						->addClass('monitoring-item-name')
						->setTitle(_('Dashboards')),
				(new CSpan($dashboard_count))
					->addClass(ZBX_STYLE_ENTITY_COUNT)
					->setTitle($dashboard_count)
			]))->addClass('monitoring-item'),
			(new CDiv([
				$can_view_monitoring_hosts && $graph_count > 0
					? (new CLink(_('Graphs'),
						(new CUrl('zabbix.php'))
							->setArgument('action', 'charts.view')
							->setArgument('filter_hostids', [$hostid])
							->setArgument('filter_show', GRAPH_FILTER_HOST)
							->setArgument('filter_set', '1')
					))
						->addClass('monitoring-item-name')
						->setTitle(_('Graphs'))
					: (new CSpan(_('Graphs')))
						->addClass('monitoring-item-name')
						->setTitle(_('Graphs')),
				(new CSpan($graph_count))
					->addClass(ZBX_STYLE_ENTITY_COUNT)
					->setTitle($graph_count)
			]))->addClass('monitoring-item'),
			(new CDiv([
				CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA) && $item_count > 0
					? (new CLink(_('Latest data'),
						(new CUrl('zabbix.php'))
							->setArgument('action', 'latest.view')
							->setArgument('hostids', [$hostid])
							->setArgument('filter_set', '1')
					))
						->addClass('monitoring-item-name')
						->setTitle(_('Latest data'))
					: (new CSpan(_('Latest data')))
						->addClass('monitoring-item-name')
						->setTitle(_('Latest data')),
				(new CSpan($item_count))
					->addClass(ZBX_STYLE_ENTITY_COUNT)
					->setTitle($item_count)
			]))->addClass('monitoring-item'),
			(new CDiv([
				$can_view_monitoring_hosts && $web_scenario_count > 0
					? (new CLink(_('Web'),
						(new CUrl('zabbix.php'))
							->setArgument('action', 'web.view')
							->setArgument('filter_hostids', [$hostid])
							->setArgument('filter_set', '1')
					))
						->addClass('monitoring-item-name')
						->setTitle(_('Web scenarios'))
					: (new CSpan(_('Web')))
						->addClass('monitoring-item-name')
						->setTitle(_('Web scenarios')),
				(new CSpan($web_scenario_count))
					->addClass(ZBX_STYLE_ENTITY_COUNT)
					->setTitle($web_scenario_count)
			]))->addClass('monitoring-item')
		]))
			->addClass(Widget::ZBX_STYLE_SECTION_BODY)
			->addClass('monitoring')
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-monitoring');
}

function makeSectionAvailability(array $interfaces): CDiv {
	return (new CDiv([
		(new CDiv(_('Availability')))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv(getHostAvailabilityTable($interfaces)))->addClass(Widget::ZBX_STYLE_SECTION_BODY)
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-availability');
}

function makeSectionMonitoredBy(array $host): CDiv {
	switch ($host['monitored_by']) {
		case ZBX_MONITORED_BY_SERVER:
			$monitored_by = [
				new CIcon(ZBX_ICON_SERVER, _('Zabbix server')),
				_('Zabbix server')
			];
			break;

		case ZBX_MONITORED_BY_PROXY:
			$proxy_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'proxy.edit')
				->setArgument('proxyid', $host['proxyid'])
				->getUrl();

			$proxy = CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				? new CLink($host['proxy']['name'], $proxy_url)
				: new CSpan($host['proxy']['name']);

			$proxy->setTitle($host['proxy']['name']);

			$monitored_by = [
				new CIcon(ZBX_ICON_PROXY, _('Proxy')),
				$proxy
			];
			break;

		case ZBX_MONITORED_BY_PROXY_GROUP:
			$proxy_group_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'proxygroup.edit')
				->setArgument('proxy_groupid', $host['proxy_groupid'])
				->getUrl();

			$proxy_group = CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)
				? new CLink($host['proxy_group']['name'], $proxy_group_url)
				: new CSpan($host['proxy_group']['name']);

			$proxy_group->setTitle($host['proxy_group']['name']);

			$monitored_by = [
				new CIcon(ZBX_ICON_PROXY_GROUP, _('Proxy group')),
				$proxy_group
			];
	}

	return (new CDiv([
		(new CDiv(_('Monitored by')))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv($monitored_by))->addClass(Widget::ZBX_STYLE_SECTION_BODY)
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-monitored-by');
}

function makeSectionTemplates(array $host_templates): CDiv {
	$templates = [];

	$i = 0;
	$template_count = count($host_templates);
	$hint_templates = [];

	foreach ($host_templates as $template) {
		$template_fullname = $template['parentTemplates']
			? $template['name'].' ('.implode(', ', array_column($template['parentTemplates'], 'name')).')'
			: $template['name'];

		$templates[] = (new CSpan([
			(new CSpan($template['name']))
				->addClass('template-name')
				->setTitle($template_fullname),
			++$i < $template_count ? (new CSpan(', '))->addClass('delimiter') : null
		]))->addClass('template');

		$hint_templates[] = $template_fullname;
	}

	if ($templates) {
		$templates[] = (new CLink(_('more')))
			->addClass(ZBX_STYLE_LINK_ALT)
			->setHint(implode(', ', $hint_templates), ZBX_STYLE_HINTBOX_WRAP);
	}

	return (new CDiv([
		(new CDiv(_('Templates')))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv($templates))
			->addClass(Widget::ZBX_STYLE_SECTION_BODY)
			->addClass('templates')
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-templates');
}

function makeSectionInventory(string $hostid, array $host_inventory, array $inventory_fields): CDiv {
	$inventory_list = [];

	if ($host_inventory) {
		foreach (getHostInventories() as $inventory) {
			if (($inventory_fields && !array_key_exists($inventory['db_field'], $host_inventory))
					|| (!$inventory_fields && $host_inventory[$inventory['db_field']] === '')) {
				continue;
			}

			$inventory_list[] = (new CDiv($inventory['title']))
				->addClass('inventory-field-name')
				->setTitle($inventory['title']);
			$inventory_list[] = (new CDiv($host_inventory[$inventory['db_field']]))
				->addClass('inventory-field-value')
				->setTitle($host_inventory[$inventory['db_field']]);
		}
	}

	return (new CDiv([
		(new CDiv(
			CWebuser::checkAccess(CRoleHelper::UI_INVENTORY_HOSTS)
				? new CLink(_('Inventory'), (new CUrl('hostinventories.php'))->setArgument('hostid', $hostid))
				: _('Inventory')
		))->addClass(Widget::ZBX_STYLE_SECTION_NAME),
		(new CDiv($inventory_list))->addClass(Widget::ZBX_STYLE_SECTION_BODY)
	]))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-inventory');
}

function makeSectionTags(array $host_tags): CDiv {
	$tags = [];

	foreach ($host_tags as $tag) {
		$tag = $tag['tag'].($tag['value'] === '' ? '' : ': '.$tag['value']);

		$tags[] = (new CSpan($tag))
			->addClass(ZBX_STYLE_TAG)
			->setHint($tag);
	}

	if ($tags) {
		$tags[] = (new CButtonIcon(ZBX_ICON_MORE))->setHint($tags, ZBX_STYLE_HINTBOX_WRAP);
	}

	return (new CDiv(
		(new CDiv($tags))->addClass('tags')
	))
		->addClass(Widget::ZBX_STYLE_SECTION)
		->addClass('section-tags');
}
