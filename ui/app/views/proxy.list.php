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

$this->includeJsFile('proxy.list.js.php');

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
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Active'), HOST_STATUS_PROXY_ACTIVE)
						->addValue(_('Passive'), HOST_STATUS_PROXY_PASSIVE)
						->setModern(true)
				)
			])
	]);

$form = (new CForm())
	->setId('proxy-list')
	->setName('proxy_list');

$proxy_list = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'proxyids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'host', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'proxy.list')
				->getUrl()
		),
		_('Mode'),
		_('Encryption'),
		_('Compression'),
		_('Last seen (age)'),
		_('Host count'),
		_('Item count'),
		_('Required performance (vps)'),
		_('Hosts')
	]);

foreach ($data['proxies'] as $proxyid => $proxy) {
	$hosts = [];

	foreach ($proxy['hosts'] as $host_index => $host) {
		if ($host_index >= $data['config']['max_in_table']) {
			$hosts[] = ' &hellip;';

			break;
		}

		if ($hosts) {
			$hosts[] = ', ';
		}

		switch ($host['status']) {
			case HOST_STATUS_MONITORED:
				$style = null;
				break;

			case HOST_STATUS_TEMPLATE:
				$style = ZBX_STYLE_GREY;
				break;

			default:
				$style = ZBX_STYLE_RED;
		}

		$hosts[] = $data['allowed_ui_conf_hosts']
			? (new CLink($host['name']))
				->addClass($style)
				->addClass('js-edit-host')
				->setAttribute('data-hostid', $host['hostid'])
			: (new CSpan($host['name']))->addClass($style);
	}

	if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
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

	$proxy_list->addRow([
		new CCheckBox('proxyids['.$proxyid.']', $proxyid),
		(new CCol(
			(new CLink($proxy['host']))
				->addClass('js-edit-proxy')
				->setAttribute('data-proxyid', $proxyid)
		))->addClass(ZBX_STYLE_WORDBREAK),
		$proxy['status'] == HOST_STATUS_PROXY_ACTIVE ? _('Active') : _('Passive'),
		$encryption,
		($proxy['auto_compress'] == HOST_COMPRESSION_ON)
			? (new CSpan(_('On')))->addClass(ZBX_STYLE_STATUS_GREEN)
			: (new CSpan(_('Off')))->addClass(ZBX_STYLE_STATUS_GREY),
		($proxy['lastaccess'] == 0)
			? (new CSpan(_('Never')))->addClass(ZBX_STYLE_RED)
			: zbx_date2age($proxy['lastaccess']),
		array_key_exists('host_count', $proxy) ? $proxy['host_count'] : '',
		array_key_exists('item_count', $proxy) ? $proxy['item_count'] : '',
		array_key_exists('vps_total', $proxy) ? $proxy['vps_total'] : '',
		$hosts ?: ''
	]);
}

$form->addItem([$proxy_list, $data['paging']]);

$form->addItem(
	new CActionButtonList('action', 'proxyids', [
		'proxy.config.refresh' => [
			'content' => (new CSimpleButton(_('Refresh configuration')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-refresh-proxy-config')
				->addClass('no-chkbxrange')
		],
		'proxy.host.massenable' => [
			'content' => (new CSimpleButton(_('Enable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-proxy-host')
				->addClass('no-chkbxrange')
		],
		'proxy.host.massdisable' => [
			'content' => (new CSimpleButton(_('Disable hosts')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-proxy-host')
				->addClass('no-chkbxrange')
		],
		'proxy.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-proxy')
				->addClass('no-chkbxrange')
		]
	], 'proxy')
);

(new CWidget())
	->setTitle(_('Proxies'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_PROXY_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create proxy')))->addClass('js-create-proxy')
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('
	view.init();
'))
	->setOnDocumentReady()
	->show();
