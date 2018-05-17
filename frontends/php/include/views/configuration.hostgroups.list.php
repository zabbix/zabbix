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


$widget = (new CWidget())
	->setTitle(_('Host groups'))
	->setControls((new CTag('nav', true, (new CList())
			->addItem(CWebUser::getType() == USER_TYPE_SUPER_ADMIN
				? new CRedirectButton(_('Create host group'), (new CUrl())
						->setArgument('form', 'create')
						->getUrl()
					)
				: (new CSubmit('form', _('Create host group').' '._('(Only super admins can create groups)')))
					->setEnabled(false)
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setProfile('web.groups.filter', 0)
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
		])
	);

// create form
$hostGroupForm = (new CForm())->setName('hostgroupForm');

// create table
$hostGroupTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_groups'))
				->onClick("checkAll('".$hostGroupForm->getName()."', 'all_groups', 'groups');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Hosts'),
		_('Templates'),
		_('Members'),
		(new CColHeader(_('Info')))->addClass(ZBX_STYLE_CELL_WIDTH)
	]);

$current_time = time();

foreach ($this->data['groups'] as $group) {
	$hostsOutput = [];
	$n = 0;

	foreach ($group['templates'] as $template) {
		$n++;

		if ($n > $this->data['config']['max_in_table']) {
			$hostsOutput[] = ' &hellip;';

			break;
		}

		if ($n > 1) {
			$hostsOutput[] = ', ';
		}

		$url = 'templates.php?form=update&templateid='.$template['templateid'].'&groupid='.$group['groupid'];

		$hostsOutput[] = (new CLink($template['name'], $url))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_GREY);
	}

	if ($group['templates'] && $group['hosts']) {
		$hostsOutput[] = BR();
		$hostsOutput[] = BR();
	}

	$n = 0;

	foreach ($group['hosts'] as $host) {
		$n++;

		if ($n > $this->data['config']['max_in_table']) {
			$hostsOutput[] = ' &hellip;';

			break;
		}

		if ($n > 1) {
			$hostsOutput[] = ', ';
		}

		$url = 'hosts.php?form=update&hostid='.$host['hostid'].'&groupid='.$group['groupid'];
		$hostsOutput[] = (new CLink($host['name'], $url))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass($host['status'] == HOST_STATUS_MONITORED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
	}

	$hostCount = $this->data['groupCounts'][$group['groupid']]['hosts'];
	$templateCount = $this->data['groupCounts'][$group['groupid']]['templates'];

	// name
	$name = [];
	if ($group['discoveryRule']) {
		$name[] = (new CLink($group['discoveryRule']['name'], 'host_prototypes.php?parent_discoveryid='.$group['discoveryRule']['itemid']))
			->addClass(ZBX_STYLE_ORANGE);
		$name[] = NAME_DELIMITER;
	}
	$name[] = new CLink($group['name'], 'hostgroups.php?form=update&groupid='.$group['groupid']);

	// info, discovered item lifetime indicator
	$info_icons = [];
	if ($group['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $group['groupDiscovery']['ts_delete'] != 0) {
		$info_icons[] = getHostGroupLifetimeIndicator($current_time, $group['groupDiscovery']['ts_delete']);
	}

	$hostGroupTable->addRow([
		new CCheckBox('groups['.$group['groupid'].']', $group['groupid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[new CLink(_('Hosts'), 'hosts.php?groupid='.$group['groupid']), CViewHelper::showNum($hostCount)],
		[
			(new CLink(_('Templates'), 'templates.php?groupid='.$group['groupid'])),
			CViewHelper::showNum($templateCount)
		],
		empty($hostsOutput) ? '' : $hostsOutput,
		makeInformationList($info_icons)
	]);
}

// append table to form
$hostGroupForm->addItem([
	$hostGroupTable,
	$this->data['paging'],
	new CActionButtonList('action', 'groups', [
		'hostgroup.massenable' => ['name' => _('Enable hosts'), 'confirm' => _('Enable selected hosts?')],
		'hostgroup.massdisable' => ['name' => _('Disable hosts'),
			'confirm' => _('Disable hosts in the selected host groups?')
		],
		'hostgroup.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected host groups?')]
	])
]);

// append form to widget
$widget->addItem($hostGroupForm);

return $widget;
