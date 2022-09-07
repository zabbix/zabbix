<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var array $data
 */

$this->includeJsFile('configuration.hostgroup.list.js.php');

$widget = (new CWidget())
	->setTitle(_('Host groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_HOSTGROUPS_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					CWebUser::getType() == USER_TYPE_SUPER_ADMIN
						? (new CSimpleButton(_('Create host group')))->addClass('js-create-hostgroup')
						: (new CSimpleButton(_('Create host group').' '._('(Only super admins can create groups)')))
							->setEnabled(false)
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'hostgroup.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addItem([
					new CLabel(_('Name'), 'filter_name'),
					new CFormField(
						(new CTextBox('filter_name', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
							->setAttribute('autofocus', 'autofocus')
					)
				])
		])
		->addVar('action', 'hostgroup.list')
	);

$form = (new CForm())->setName('hostgroup_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'hostgroup.list')
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_groups'))
				->onClick("checkAll('".$form->getName()."', 'all_groups', 'groups');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
		(new CColHeader(_('Hosts')))->setColSpan(2),
		(new CColHeader(_('Info')))->addClass(ZBX_STYLE_CELL_WIDTH)
	]);

$current_time = time();

foreach ($data['groups'] as $group) {
	$hosts_output = [];
	$n = 0;

	foreach ($group['hosts'] as $host) {
		$n++;

		if ($n > $data['config']['max_in_table']) {
			$hosts_output[] = ' &hellip;';

			break;
		}

		if ($n > 1) {
			$hosts_output[] = ', ';
		}

		if ($data['allowed_ui_conf_hosts']) {
			$host_output = (new CLink($host['name'], (new CUrl('zabbix.php'))
				->setArgument('action', 'host.edit')
				->setArgument('hostid', $host['hostid'])
			))
			->setAttribute('data-hostid', $host['hostid'])
			->onClick('view.editHost(event, this.dataset.hostid);')
			->addClass(ZBX_STYLE_LINK_ALT);
		}
		else {
			$host_output = new CSpan($host['name']);
		}

		$host_output->addClass($host['status'] == HOST_STATUS_MONITORED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
		$hosts_output[] = $host_output;
	}

	$host_count = $data['groupCounts'][$group['groupid']]['hosts'];

	$name = [];

	if ($group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		if ($group['discoveryRule']) {
			if ($data['allowed_ui_conf_hosts'] && $group['is_discovery_rule_editable']) {
				$lld_name = (new CLink($group['discoveryRule']['name'],
					(new CUrl('host_prototypes.php'))
						->setArgument('form', 'update')
						->setArgument('parent_discoveryid', $group['discoveryRule']['itemid'])
						->setArgument('hostid', $group['hostPrototype']['hostid'])
						->setArgument('context', 'host')
				))->addClass(ZBX_STYLE_LINK_ALT);
			}
			else {
				$lld_name = new CSpan($group['discoveryRule']['name']);
			}

			$name[] = $lld_name->addClass(ZBX_STYLE_ORANGE);
		}
		else {
			$name[] = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_ORANGE);
		}

		$name[] = NAME_DELIMITER;
	}

	$name[] = (new CLink(CHtml::encode($group['name']),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'hostgroup.edit')
			->setArgument('groupid', $group['groupid'])
	))
		->addClass('js-edit-hostgroup')
		->setAttribute('data-groupid', $group['groupid']);

	$info_icons = [];
	if ($group['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $group['groupDiscovery']['ts_delete'] != 0) {
		$info_icons[] = getHostGroupLifetimeIndicator($current_time, $group['groupDiscovery']['ts_delete']);
	}

	$count = '';
	if ($host_count > 0) {
		if ($data['allowed_ui_conf_hosts']) {
			$count = new CLink($host_count, (new CUrl('zabbix.php'))
				->setArgument('action', 'host.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$group['groupid']]));
		}
		else {
			$count = new CSpan($host_count);
		}

		$count->addClass(ZBX_STYLE_ICON_COUNT);
	}

	$table->addRow([
		new CCheckBox('groups['.$group['groupid'].']', $group['groupid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol($count))->addClass(ZBX_STYLE_CELL_WIDTH),
		$hosts_output ? $hosts_output : '',
		makeInformationList($info_icons)
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'groups', [
		'hostgroup.massenable' => [
			'content' => (new CSimpleButton(_('Enable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-hostgroup')
				->addClass('no-chkbxrange')
		],
		'hostgroup.massdisable' => [
			'content' => (new CSimpleButton(_('Disable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-hostgroup')
				->addClass('no-chkbxrange')
		],
		'hostgroup.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-hostgroup')
				->addClass('no-chkbxrange')
		]
	], 'hostgroup')
]);

$widget
	->addItem($form)
	->show();

(new CScriptTag('view.init('.json_encode([
	'enable_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.enable')
		->setArgumentSID()
		->getUrl(),
	'disable_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.disable')
		->setArgumentSID()
		->getUrl(),
	'delete_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.delete')
		->setArgumentSID()
		->getUrl()
]).');'))
	->setOnDocumentReady()
	->show();
