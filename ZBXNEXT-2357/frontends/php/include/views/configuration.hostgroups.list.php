<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$hostGroupWidget = (new CWidget())->setTitle(_('Host groups'));

// create new hostgroup button
$createForm = new CForm('get');
$createForm->cleanItems();

$controls = new CList();

if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$tmpItem = new CSubmit('form', _('Create host group'));
}
else {
	$tmpItem = new CSubmit('form', _('Create host group').SPACE._('(Only super admins can create groups)'));
	$tmpItem->setEnabled(false);
}
$controls->addItem($tmpItem);
$createForm->addItem($controls);

$hostGroupWidget->setControls($createForm);

// create form
$hostGroupForm = new CForm();
$hostGroupForm->setName('hostgroupForm');

// create table
$hostGroupTable = new CTableInfo();
$hostGroupTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_groups', null, "checkAll('".$hostGroupForm->getName()."', 'all_groups', 'groups');")))->
		addClass('cell-width'),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Hosts'),
	_('Templates'),
	_('Members'),
	_('Info')
]);

$currentTime = time();

foreach ($this->data['groups'] as $group) {
	$hostsOutput = [];
	$i = 0;

	foreach ($group['templates'] as $template) {
		$i++;

		if ($i > $this->data['config']['max_in_table']) {
			$hostsOutput[] = ' &hellip;';

			break;
		}

		$url = 'templates.php?form=update&templateid='.$template['templateid'].'&groupid='.$group['groupid'];

		if ($i > 1) {
			$hostsOutput[] = ', ';
		}

		$hostsOutput[] = new CLink($template['name'], $url, ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY);
	}

	if ($group['hosts'] && $i < $this->data['config']['max_in_table']) {
		if ($hostsOutput) {
			$hostsOutput[] = BR();
			$hostsOutput[] = BR();
		}

		$n = 0;

		foreach ($group['hosts'] as $host) {
			$i++;
			$n++;

			if ($i > $this->data['config']['max_in_table']) {
				$hostsOutput[] = ' &hellip;';

				break;
			}

			switch ($host['status']) {
				case HOST_STATUS_NOT_MONITORED:
					$style = ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_RED;
					$url = 'hosts.php?form=update&hostid='.$host['hostid'].'&groupid='.$group['groupid'];
					break;

				default:
					$style = null;
					$url = 'hosts.php?form=update&hostid='.$host['hostid'].'&groupid='.$group['groupid'];
			}

			if ($n > 1) {
				$hostsOutput[] = ', ';
			}

			$hostsOutput[] = new CLink($host['name'], $url, $style);
		}
	}

	$hostCount = $this->data['groupCounts'][$group['groupid']]['hosts'];
	$templateCount = $this->data['groupCounts'][$group['groupid']]['templates'];

	// name
	$name = [];
	if ($group['discoveryRule']) {
		$name[] = new CLink($group['discoveryRule']['name'], 'host_prototypes.php?parent_discoveryid='.$group['discoveryRule']['itemid'], ZBX_STYLE_ORANGE);
		$name[] = NAME_DELIMITER;
	}
	$name[] = new CLink($group['name'], 'hostgroups.php?form=update&groupid='.$group['groupid']);

	// info, discovered item lifetime indicator
	if ($group['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $group['groupDiscovery']['ts_delete']) {
		$info = new CDiv(SPACE, 'status_icon iconwarning');

		// Check if host group should've been deleted in the past.
		if ($currentTime > $group['groupDiscovery']['ts_delete']) {
			$info->setHint(_s(
				'The host group is not discovered anymore and will be deleted the next time discovery rule is processed.'
			));
		}
		else {
			$info->setHint(_s(
				'The host group is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
				zbx_date2age($group['groupDiscovery']['ts_delete']),
				zbx_date2str(DATE_FORMAT, $group['groupDiscovery']['ts_delete']),
				zbx_date2str(TIME_FORMAT, $group['groupDiscovery']['ts_delete'])
			));
		}
	}
	else {
		$info = '';
	}

	$hostGroupTable->addRow([
		new CCheckBox('groups['.$group['groupid'].']', null, null, $group['groupid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[new CLink(_('Hosts'), 'hosts.php?groupid='.$group['groupid']), CViewHelper::showNum($hostCount)],
		[new CLink(_('Templates'), 'templates.php?groupid='.$group['groupid'], ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY),
				CViewHelper::showNum($templateCount)],
		empty($hostsOutput) ? '' : $hostsOutput,
		$info
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
$hostGroupWidget->addItem($hostGroupForm);

return $hostGroupWidget;
