<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$this->addJsFile('items.js');
$this->addJsFile('multilineinput.js');
$this->includeJsFile('configuration.hostgroup.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Host groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOSTGROUPS_LIST))
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
			$hosts_output[] = [' ', HELLIP()];

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
		if ($group['discoveryRules']) {
			$lld_rule_count = count($group['discoveryRules']);

			if ($lld_rule_count > 1) {
				$group['discoveryRules'] = [
					reset($group['discoveryRules']),
					end($group['discoveryRules'])
				];
			}

			foreach ($group['discoveryRules'] as $lld_rule) {
				if ($data['allowed_ui_conf_hosts'] && $lld_rule['is_editable']
						&& array_key_exists($lld_rule['itemid'], $data['ldd_rule_to_host_prototype'])) {
					$lld_name = (new CLink($lld_rule['name'],
						(new CUrl('host_prototypes.php'))
							->setArgument('form', 'update')
							->setArgument('parent_discoveryid', $lld_rule['itemid'])
							->setArgument('hostid', reset($data['ldd_rule_to_host_prototype'][$lld_rule['itemid']]))
							->setArgument('context', 'host')
					))->addClass(ZBX_STYLE_LINK_ALT);
				}
				else {
					$lld_name = new CSpan($lld_rule['name']);
				}

				$name[] = $lld_name->addClass(ZBX_STYLE_ORANGE);

				if ($lld_rule_count > 2) {
					$name[] = ', ..., ';
				}
				else {
					$name[] = ', ';
				}
			}

			array_pop($name);
		}
		else {
			$name[] = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_ORANGE);
		}

		$name[] = NAME_DELIMITER;
	}

	$name[] = (new CLink($group['name'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'hostgroup.edit')
			->setArgument('groupid', $group['groupid'])
	))
		->addClass('js-edit-hostgroup')
		->setAttribute('data-groupid', $group['groupid']);

	$info_icons = [];

	if ($group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$max = 0;

		foreach ($group['groupDiscoveries'] as $group_discovery) {
			if ($group_discovery['ts_delete'] == 0) {
				$max = 0;
				break;
			}

			$max = max($max, (int) $group_discovery['ts_delete']);
		}

		if ($max > 0) {
			$info_icons[] = getHostGroupLifetimeIndicator($current_time, $max);
		}
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

		$count->addClass(ZBX_STYLE_ENTITY_COUNT);
	}

	$table->addRow([
		new CCheckBox('groups['.$group['groupid'].']', $group['groupid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol($count))->addClass(ZBX_STYLE_CELL_WIDTH),
		$hosts_output ?: '',
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
				->addClass('js-no-chkbxrange')
		],
		'hostgroup.massdisable' => [
			'content' => (new CSimpleButton(_('Disable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-hostgroup')
				->addClass('js-no-chkbxrange')
		],
		'hostgroup.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-hostgroup')
				->addClass('js-no-chkbxrange')
		]
	], 'hostgroup')
]);

$html_page
	->addItem($form)
	->show();

$csrf_token = CCsrfTokenHelper::get('hostgroup');

(new CScriptTag('view.init('.json_encode([
	'enable_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.enable')
		->setArgument(CCsrfTokenHelper::CSRF_TOKEN_NAME, $csrf_token)
		->getUrl(),
	'disable_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.disable')
		->setArgument(CCsrfTokenHelper::CSRF_TOKEN_NAME, $csrf_token)
		->getUrl(),
	'delete_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.delete')
		->setArgument(CCsrfTokenHelper::CSRF_TOKEN_NAME, $csrf_token)
		->getUrl()
]).');'))
	->setOnDocumentReady()
	->show();
