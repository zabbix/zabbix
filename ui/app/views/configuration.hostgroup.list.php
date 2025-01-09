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
	])
	->setPageNavigation($data['paging']);

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
			$host_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'host.edit')
				->setArgument('hostid', $host['hostid'])
				->getUrl();

			$host_output = (new CLink($host['name'], $host_url))
				->setAttribute('data-hostid', $host['hostid'])
				->setAttribute('data-action', 'host.edit')
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

	$group_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'hostgroup.edit')
		->setArgument('groupid', $group['groupid'])
		->getUrl();

	$name[] = (new CLink($group['name'], $group_url))
		->setAttribute('data-groupid', $group['groupid'])
		->setAttribute('data-action', 'hostgroup.edit');

	$info_icons = [];

	if ($group['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$max = 0;

		foreach ($group['groupDiscoveries'] as $group_discovery) {
			if ($group_discovery['ts_delete'] == 0) {
				$max = 0;
				break;
			}

			if ($group_discovery['status'] == ZBX_LLD_STATUS_LOST) {
				$max = max($max, (int) $group_discovery['ts_delete']);
			}
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
		(new CCol($name))
			->addClass(ZBX_STYLE_WORDBREAK)
			->setWidth('15%'),
		(new CCol($count))->addClass(ZBX_STYLE_CELL_WIDTH),
		$hosts_output ? (new CCol($hosts_output))->addClass(ZBX_STYLE_WORDBREAK) : '',
		makeInformationList($info_icons)
	]);
}

$form->addItem([
	$table,
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
		->setArgument(CSRF_TOKEN_NAME, $csrf_token)
		->getUrl(),
	'disable_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.disable')
		->setArgument(CSRF_TOKEN_NAME, $csrf_token)
		->getUrl(),
	'delete_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'hostgroup.delete')
		->setArgument(CSRF_TOKEN_NAME, $csrf_token)
		->getUrl()
]).');'))
	->setOnDocumentReady()
	->show();
