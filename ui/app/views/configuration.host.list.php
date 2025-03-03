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

$this->includeJsFile('configuration.host.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('hosts');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Hosts'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_LIST))
	->setControls((new CTag('nav', true, (new CList())
			->addItem(
				(new CSimpleButton(_('Create host')))
					->addClass('js-create-host')
			)
			->addItem(
				(new CButton('form', _('Import')))
					->onClick(
						'return PopUp("popup.import", {
							rules_preset: "host", '.
							CSRF_TOKEN_NAME.': "'.CCsrfTokenHelper::get('import').
						'"}, {
							dialogueid: "popup_import",
							dialogue_class: "modal-popup-generic"
						});'
					)
					->removeId()
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

$action_url = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

$filter = (new CFilter())
	->setResetUrl($action_url)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Host groups'), 'filter_groups__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_groups[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groups_',
								'with_hosts' => true,
								'editable' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Templates'), 'filter_templates__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_templates[]',
						'object_name' => 'templates',
						'data' => $data['filter']['templates'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'templates',
								'srcfld1' => 'hostid',
								'srcfld2' => 'host',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_templates_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Name'), 'filter_host'),
				new CFormField(
					(new CTextBox('filter_host', $data['filter']['host']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('DNS'), 'filter_dns'),
				new CFormField(
					(new CTextBox('filter_dns', $data['filter']['dns']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('IP'), 'filter_ip'),
				new CFormField(
					(new CTextBox('filter_ip', $data['filter']['ip']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Port'), 'filter_port'),
				new CFormField(
					(new CTextBox('filter_port', $data['filter']['port']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Status'), 'filter_status'),
				new CFormField(
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), HOST_STATUS_MONITORED)
						->addValue(_('Disabled'), HOST_STATUS_NOT_MONITORED)
						->setModern(true)
				)
			])
			->addItem([
				new CLabel(_('Monitored by'), 'filter_monitored_by'),
				new CFormField(
					(new CRadioButtonList('filter_monitored_by', (int) $data['filter']['monitored_by']))
						->addValue(_('Any'), ZBX_MONITORED_BY_ANY)
						->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
						->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
						->addValue(_('Proxy group'), ZBX_MONITORED_BY_PROXY_GROUP)
						->setModern()
				)
			])
			->addItem([
				(new CLabel(_('Proxies'), 'filter_proxyids__ms'))->addClass('js-filter-proxyids'),
				(new CFormField(
					(new CMultiSelect([
						'name' => 'filter_proxyids[]',
						'object_name' => 'proxies',
						'data' => $data['proxies_ms'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'proxies',
								'srcfld1' => 'proxyid',
								'srcfld2' => 'name',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_proxyids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				))->addClass('js-filter-proxyids')
			])
			->addItem([
				(new CLabel(_('Proxy groups'), 'filter_proxy_groupids__ms'))->addClass('js-filter-proxy-groupids'),
				(new CFormField(
					(new CMultiSelect([
						'name' => 'filter_proxy_groupids[]',
						'object_name' => 'proxy_groups',
						'data' => $data['proxy_groups_ms'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'proxy_groups',
								'srcfld1' => 'proxy_groupid',
								'srcfld2' => 'name',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_proxy_groupids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				))->addClass('js-filter-proxy-groupids')
			])
			->addItem([
				new CLabel(_('Tags')),
				new CFormField(
					CTagFilterFieldHelper::getTagFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'tags' => $data['filter']['tags']
					])
				)
			])
	]);

$html_page->addItem($filter);

// table hosts
$form = (new CForm())->setName('hosts');
$header_checkbox = (new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'hostids');");
$show_monitored_by = $data['filter']['monitored_by'] == ZBX_MONITORED_BY_ANY
	|| $data['filter']['monitored_by'] == ZBX_MONITORED_BY_PROXY
	|| $data['filter']['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP;
$header_sortable_name = make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'],
	$action_url->getUrl()
);
$header_sortable_status = make_sorting_header(_('Status'), 'status', $data['sortField'], $data['sortOrder'],
	$action_url->getUrl()
);

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader($header_checkbox))->addClass(ZBX_STYLE_CELL_WIDTH),
		$header_sortable_name,
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web'),
		_('Interface'),
		$show_monitored_by ? _('Proxy') : null,
		_('Templates'),
		$header_sortable_status,
		_('Availability'),
		_('Agent encryption'),
		_('Info'),
		_('Tags')
	])
	->setPageNavigation($data['paging']);

$current_time = time();
$csrf_token = CCsrfTokenHelper::get('host');

foreach ($data['hosts'] as $host) {
	// Select an interface from the list with highest priority.
	$interface = null;

	if ($host['interfaces']) {
		foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
			$host_interfaces = array_filter($host['interfaces'], function(array $host_interface) use ($interface_type) {
				return ($host_interface['type'] == $interface_type);
			});

			if ($host_interfaces) {
				$interface = reset($host_interfaces);

				break;
			}
		}
	}

	$description = [];

	if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		if ($host['discoveryRule']) {
			if ($host['is_discovery_rule_editable']) {
				$description[] = (new CLink($host['discoveryRule']['name'],
					(new CUrl('host_prototypes.php'))
						->setArgument('form', 'update')
						->setArgument('parent_discoveryid', $host['discoveryRule']['itemid'])
						->setArgument('hostid', $host['hostDiscovery']['parent_hostid'])
						->setArgument('context', 'host')
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_ORANGE);
			}
			else {
				$description[] = (new CSpan($host['discoveryRule']['name']))->addClass(ZBX_STYLE_ORANGE);
			}
		}
		else {
			$description[] = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_ORANGE);
		}

		$description[] = NAME_DELIMITER;
	}

	$host_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'host.edit')
		->setArgument('hostid', $host['hostid'])
		->getUrl();

	$description[] = new CLink($host['name'], $host_url);

	$maintenance_icon = false;

	if ($host['status'] == HOST_STATUS_MONITORED) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			if (array_key_exists($host['maintenanceid'], $data['maintenances'])) {
				$maintenance = $data['maintenances'][$host['maintenanceid']];
				$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
					$maintenance['description']
				);
			}
			else {
				$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], _('Inaccessible maintenance'), '');
			}
		}

		$toggle_status_link = (new CLinkAction(_('Enabled')))
			->addClass(ZBX_STYLE_GREEN)
			->addClass('js-disable-host')
			->setAttribute('data-hostid', $host['hostid']);
	}
	else {
		$toggle_status_link = (new CLinkAction(_('Disabled')))
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable-host')
			->setAttribute('data-hostid', $host['hostid']);
	}

	if ($maintenance_icon) {
		$description[] = $maintenance_icon;
	}

	order_result($host['parentTemplates'], 'name');

	$hostTemplates = [];
	$i = 0;

	foreach ($host['parentTemplates'] as $template) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$hostTemplates[] = [' ', HELLIP()];

			break;
		}

		if (array_key_exists($template['templateid'], $data['writable_templates'])
				&& $data['user']['can_edit_templates']) {
			$template_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $template['templateid'])
				->getUrl();

			$caption = [
				(new CLink($template['name'], $template_url))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY)
			];
		}
		else {
			$caption = [
				(new CSpan($template['name']))->addClass(ZBX_STYLE_GREY)
			];
		}

		$parent_templates = $data['templates'][$template['templateid']]['parentTemplates'];

		if ($parent_templates) {
			order_result($parent_templates, 'name');

			$caption[] = ' (';

			foreach ($parent_templates as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $data['writable_templates'])
						&& $data['user']['can_edit_templates']) {
					$parent_template_url = (new CUrl('zabbix.php'))
						->setArgument('action', 'popup')
						->setArgument('popup', 'template.edit')
						->setArgument('templateid', $parent_template['templateid'])
						->getUrl();

					$caption[] = (new CLink($parent_template['name'], $parent_template_url))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_GREY);
				}
				else {
					$caption[] = (new CSpan($parent_template['name']))->addClass(ZBX_STYLE_GREY);
				}

				$caption[] = ', ';
			}

			array_pop($caption);
			$caption[] = ')';
		}

		if ($hostTemplates) {
			$hostTemplates[] = ', ';
		}

		$hostTemplates[] = $caption;
	}

	$info_icons = [];

	$disable_source = $host['status'] == HOST_STATUS_NOT_MONITORED && $host['hostDiscovery']
		? $host['hostDiscovery']['disable_source']
		: '';

	if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $host['hostDiscovery']['status'] == ZBX_LLD_STATUS_LOST) {
		$info_icons[] = getLldLostEntityIndicator($current_time, $host['hostDiscovery']['ts_delete'],
			$host['hostDiscovery']['ts_disable'], $disable_source, $host['status'] == HOST_STATUS_NOT_MONITORED,
			_('host')
		);
	}

	if ($host['tls_connect'] == HOST_ENCRYPTION_NONE
			&& ($host['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE
			&& ($host['tls_accept'] & HOST_ENCRYPTION_PSK) != HOST_ENCRYPTION_PSK
			&& ($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != HOST_ENCRYPTION_CERTIFICATE) {
		$encryption = (new CDiv((new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN)))
			->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}
	else {
		// Incoming encryption.
		if ($host['tls_connect'] == HOST_ENCRYPTION_NONE) {
			$in_encryption = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		elseif ($host['tls_connect'] == HOST_ENCRYPTION_PSK) {
			$in_encryption = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$in_encryption = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}

		// Outgoing encryption.
		$out_encryption = [];

		if (($host['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE) {
			$out_encryption[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		if (($host['tls_accept'] & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK) {
			$out_encryption[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		if (($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == HOST_ENCRYPTION_CERTIFICATE) {
			$out_encryption[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		$encryption = (new CDiv([new CSpan($in_encryption), ' ', new CSpan($out_encryption)]))
			->addClass(ZBX_STYLE_STATUS_CONTAINER)
			->addClass(ZBX_STYLE_NOWRAP);
	}

	$monitored_by = null;

	if ($show_monitored_by) {
		$proxy_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'proxy.edit');

		if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
			$monitored_by = $data['user']['can_edit_proxies']
				? (new CLink($data['proxies'][$host['proxyid']]['name'],
					$proxy_url->setArgument('proxyid', $host['proxyid'])
				))
				: $data['proxies'][$host['proxyid']]['name'];
		}
		elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
			$proxy_group_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'proxygroup.edit')
				->setArgument('proxy_groupid', $host['proxy_groupid'])
				->getUrl();

			$monitored_by = $data['user']['can_edit_proxy_groups']
				? (new CLink($data['proxy_groups'][$host['proxy_groupid']]['name'], $proxy_group_url))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY)
				: $data['proxy_groups'][$host['proxy_groupid']]['name'];

			if ($host['assigned_proxyid'] != 0) {
				$monitored_by = [$monitored_by];
				$monitored_by[] = NAME_DELIMITER;
				$monitored_by[] = $data['user']['can_edit_proxies']
					? (new CLink($data['proxies'][$host['assigned_proxyid']]['name'],
						$proxy_url->setArgument('proxyid', $host['assigned_proxyid'])
					))
					: $data['proxies'][$host['assigned_proxyid']]['name'];
			}
		}
		else {
			$monitored_by = '';
		}
	}

	$disabled_by_lld = $disable_source == ZBX_DISABLE_SOURCE_LLD;

	$table->addRow([
		new CCheckBox('hostids['.$host['hostid'].']', $host['hostid']),
		(new CCol($description))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(_('Items'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'item.list')
					->setArgument('context', 'host')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
			),
			CViewHelper::showNum($host['items'])
		],
		[
			new CLink(_('Triggers'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.list')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['triggers'])
		],
		[
			new CLink(_('Graphs'),
				(new CUrl('graphs.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['graphs'])
		],
		[
			new CLink(_('Discovery'),
				(new CUrl('host_discovery.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['discoveries'])
		],
		[
			new CLink(_('Web'),
				(new CUrl('httpconf.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['httpTests'])
		],
		getHostInterface($interface),
		$monitored_by,
		$hostTemplates,
		[
			$toggle_status_link,
			$disabled_by_lld ? makeDescriptionIcon(_('Disabled automatically by an LLD rule.')) : null
		],
		getHostAvailabilityTable($host['interfaces']),
		$encryption,
		makeInformationList($info_icons),
		$data['tags'][$host['hostid']]
	]);
}

$status_toggle_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'popup.massupdate.host')
	->setArgument(CSRF_TOKEN_NAME, $csrf_token)
	->setArgument('visible[status]', 1)
	->setArgument('update', 1)
	->setArgument('backurl',
		(new CUrl('zabbix.php'))
			->setArgument('action', 'host.list')
			->setArgument('page', CPagerHelper::loadPage('host.list', null))
			->getUrl()
	);

$form->addItem([
	$table,
	new CActionButtonList('action', 'hostids', [
		'host.enable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-host')
				->addClass('js-no-chkbxrange')
		],
		'host.disable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-host')
				->addClass('js-no-chkbxrange')
		],
		'host.export' => [
			'content' => new CButtonExport('export.hosts', $action_url
				->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
				->getUrl()
			)
		],
		'popup.massupdate.host' => [
			'content' => (new CSimpleButton(_('Mass update')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massupdate-host')
				->addClass('js-no-chkbxrange')
		],
		'host.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-host')
				->addClass('js-no-chkbxrange')
		]
	], 'hosts')
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'applied_filter_groupids' => array_keys($data['filter']['groups']),
		'csrf_token' => $csrf_token
	]).');
'))
	->setOnDocumentReady()
	->show();
