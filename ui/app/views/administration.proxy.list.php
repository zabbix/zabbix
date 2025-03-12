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

$this->includeJsFile('administration.proxy.list.js.php');

$filter = (new CFilter())
	->addVar('action', 'proxy.list')
	->setResetUrl(
		(new CUrl('zabbix.php'))->setArgument('action', 'proxy.list')
	)
	->setProfile('web.proxies.filter')
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Mode')),
				new CFormField(
					(new CRadioButtonList('filter_operating_mode', (int) $data['filter']['operating_mode']))
						->addValue(_('Any'), -1)
						->addValue(_('Active'), PROXY_OPERATING_MODE_ACTIVE)
						->addValue(_('Passive'), PROXY_OPERATING_MODE_PASSIVE)
						->setModern(true)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Version')),
				new CFormField(
					(new CRadioButtonList('filter_version', (int) $data['filter']['version']))
						->addValue(_('Any'), -1)
						->addValue(_('Current'), ZBX_PROXY_VERSION_CURRENT)
						->addValue(_('Outdated'), ZBX_PROXY_VERSION_ANY_OUTDATED)
						->setModern(true)
				)
			])
	]);

$form = (new CForm())
	->setId('proxy-list')
	->setName('proxy_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'proxy.list')
	->getUrl();

$proxy_list = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'proxyids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Mode'), 'operating_mode', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Encryption'), 'tls_accept', $data['sort'], $data['sortorder'], $view_url),
		_('State'),
		make_sorting_header(_('Version'), 'version', $data['sort'], $data['sortorder'], $view_url),
		make_sorting_header(_('Last seen (age)'), 'lastaccess', $data['sort'], $data['sortorder'], $view_url),
		_('Item count'),
		_('Required vps'),
		(new CColHeader(_('Hosts')))->setColSpan(2)
	])
	->setPageNavigation($data['paging']);

foreach ($data['proxies'] as $proxyid => $proxy) {
	$proxy_name_prefix = [];

	if ($proxy['proxyGroup']) {
		$proxy_group_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'proxygroup.edit')
			->setArgument('proxy_groupid', $proxy['proxy_groupid'])
			->getUrl();

		$proxy_name_prefix[] = $data['user']['can_edit_proxy_groups']
			? (new CLink($proxy['proxyGroup']['name'], $proxy_group_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY)
			: $proxy['proxyGroup']['name'];
		$proxy_name_prefix[] = NAME_DELIMITER;
	}

	$version = $proxy['version'];

	// Info icons.
	$info_icons = [];
	if ($proxy['compatibility'] == ZBX_PROXY_VERSION_OUTDATED) {
		$version = (new CSpan($version))->addClass(ZBX_STYLE_RED);
		$info_icons[] = makeWarningIcon(_s(
			'Proxy version is outdated, only data collection and remote execution is available with server version %1$s.',
			$data['server_version']
		));
	}
	elseif ($proxy['compatibility'] == ZBX_PROXY_VERSION_UNSUPPORTED) {
		$version = (new CSpan($version))->addClass(ZBX_STYLE_RED);
		$info_icons[] = makeErrorIcon(
			_s('Proxy version is not supported by server version %1$s.', $data['server_version'])
		);
	}

	if ($proxy['operating_mode'] == PROXY_OPERATING_MODE_PASSIVE) {
		switch ($proxy['tls_connect']) {
			case HOST_ENCRYPTION_NONE:
				$encryption = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
				break;

			case HOST_ENCRYPTION_PSK:
				$encryption = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
				break;

			default:
				$encryption = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
				break;
		}
	}
	else {
		$encryption = (new CDiv())->addClass(ZBX_STYLE_STATUS_CONTAINER);

		if (($proxy['tls_accept'] & HOST_ENCRYPTION_NONE) != 0) {
			$encryption->addItem((new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN));
		}

		if (($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) != 0) {
			$encryption->addItem((new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN));
		}

		if (($proxy['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0) {
			$encryption->addItem((new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN));
		}
	}

	switch ($proxy['state']) {
		case ZBX_PROXY_STATE_UNKNOWN:
			$state = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_STATUS_GREY);
			break;

		case ZBX_PROXYGROUP_STATE_OFFLINE:
			$state = (new CSpan(_('Offline')))->addClass(ZBX_STYLE_STATUS_RED);
			break;

		case ZBX_PROXY_STATE_ONLINE:
			$state = (new CSpan(_('Online')))->addClass(ZBX_STYLE_STATUS_GREEN);
			break;
	}

	$can_enable_disable_hosts = false;
	$host_count_total = '';
	$hosts = [];

	if ($proxy['hosts']) {
		foreach ($proxy['hosts'] as $host) {
			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$can_enable_disable_hosts = true;
			}

			$host_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'host.edit')
				->setArgument('hostid', $host['hostid'])
				->getUrl();

			$hosts[] = $data['user']['can_edit_hosts']
				? (new CLink($host['name'], $host_url))
					->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null)
				: (new CSpan($host['name']))
					->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null);
			$hosts[] = ', ';
		}

		array_pop($hosts);

		if ($proxy['host_count_total'] > count($proxy['hosts'])) {
			$hosts[] = [', ', HELLIP()];
		}

		$host_count_total = (new CSpan($proxy['host_count_total']))->addClass(ZBX_STYLE_ENTITY_COUNT);
	}

	$proxy_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'proxy.edit')
		->setArgument('proxyid', $proxyid)
		->getUrl();

	$proxy_list->addRow([
		(new CCheckBox('proxyids['.$proxyid.']', $proxyid))
			->setAttribute('data-actions', $can_enable_disable_hosts ? 'enable_hosts disable_hosts' : null),
		(new CCol([
			$proxy_name_prefix,
			new CLink($proxy['name'], $proxy_url)
		]))->addClass(ZBX_STYLE_NOWRAP),
		$proxy['operating_mode'] == PROXY_OPERATING_MODE_ACTIVE ? _('Active') : _('Passive'),
		$encryption,
		$state,
		$info_icons ? [$version, NBSP(), makeInformationList($info_icons)] : $version,
		$proxy['lastaccess'] == 0
			? (new CSpan(_('Never')))->addClass(ZBX_STYLE_RED)
			: zbx_date2age($proxy['lastaccess']),
		array_key_exists('item_count', $proxy) ? $proxy['item_count'] : '',
		array_key_exists('vps_total', $proxy) ? $proxy['vps_total'] : '',
		(new CCol($host_count_total))->addClass(ZBX_STYLE_CELL_WIDTH),
		$hosts
	]);
}

$form->addItem([
	$proxy_list,
	new CActionButtonList('action', 'proxyids', [
		'proxy.config.refresh' => [
			'content' => (new CSimpleButton(_('Refresh configuration')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-refresh-proxy-config')
				->addClass('js-no-chkbxrange')
		],
		'proxy.host.massenable' => [
			'content' => (new CSimpleButton(_('Enable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-proxy-host')
				->addClass('js-no-chkbxrange')
				->setAttribute('data-required', 'enable_hosts')
		],
		'proxy.host.massdisable' => [
			'content' => (new CSimpleButton(_('Disable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-proxy-host')
				->addClass('js-no-chkbxrange')
				->setAttribute('data-required', 'disable_hosts')
		],
		'proxy.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-proxy')
				->addClass('js-no-chkbxrange')
		]
	], 'proxy')
]);

(new CHtmlPage())
	->setTitle(_('Proxies'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_PROXY_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Create proxy')))->addClass('js-create-proxy')
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
