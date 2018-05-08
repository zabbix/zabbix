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


if ($data['uncheck']) {
	uncheckTableRows('proxy');
}

$widget = (new CWidget())
	->setTitle(_('Proxies'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(new CRedirectButton(_('Create proxy'), 'zabbix.php?action=proxy.edit')))
	)
	->addItem((new CFilter())
		->setProfile('web.proxies.filter', 0)
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(
				_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(
				_('Mode'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Active'), HOST_STATUS_PROXY_ACTIVE)
					->addValue(_('Passive'), HOST_STATUS_PROXY_PASSIVE)
					->setModern(true)
			)
		])
		->addVar('action', 'proxy.list')
	);

// create form
$proxyForm = (new CForm('get'))->setName('proxyForm');

// create table
$proxyTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))
				->onClick("checkAll('".$proxyForm->getName()."', 'all_hosts', 'proxyids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'host', $data['sort'], $data['sortorder']),
		_('Mode'),
		_('Encryption'),
		_('Last seen (age)'),
		_('Host count'),
		_('Item count'),
		_('Required performance (vps)'),
		_('Hosts')
	]);

foreach ($data['proxies'] as $proxy) {
	$hosts = [];
	$i = 0;

	foreach ($proxy['hosts'] as $host) {
		if (++$i > $data['config']['max_in_table']) {
			$hosts[] = ' &hellip;';

			break;
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

		if ($hosts) {
			$hosts[] = ', ';
		}

		$hosts[] = (new CLink($host['name'], 'hosts.php?form=update&hostid='.$host['hostid']))->addClass($style);
	}

	$name = new CLink($proxy['host'], 'zabbix.php?action=proxy.edit&proxyid='.$proxy['proxyid']);

	// encryption
	$in_encryption = '';
	$out_encryption = '';

	if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
		// input encryption
		if ($proxy['tls_connect'] == HOST_ENCRYPTION_NONE) {
			$in_encryption = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		elseif ($proxy['tls_connect'] == HOST_ENCRYPTION_PSK) {
			$in_encryption = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$in_encryption = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
	}
	else {
		// output encryption
		$out_encryption_array = [];
		if (($proxy['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE) {
			$out_encryption_array[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		if (($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK) {
			$out_encryption_array[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		if (($proxy['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == HOST_ENCRYPTION_CERTIFICATE) {
			$out_encryption_array[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}

		$out_encryption = (new CDiv($out_encryption_array))->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}

	$proxyTable->addRow([
		new CCheckBox('proxyids['.$proxy['proxyid'].']', $proxy['proxyid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		$proxy['status'] == HOST_STATUS_PROXY_ACTIVE ? _('Active') : _('Passive'),
		$proxy['status'] == HOST_STATUS_PROXY_ACTIVE ? $out_encryption : $in_encryption,
		($proxy['lastaccess'] == 0)
			? (new CSpan(_('Never')))->addClass(ZBX_STYLE_RED)
			: zbx_date2age($proxy['lastaccess']),
		array_key_exists('host_count', $proxy) ? $proxy['host_count'] : '',
		array_key_exists('item_count', $proxy) ? $proxy['item_count'] : '',
		array_key_exists('vps_total', $proxy) ? $proxy['vps_total'] : '',
		$hosts ? $hosts : ''
	]);
}

// append table to form
$proxyForm->addItem([
	$proxyTable,
	$data['paging'],
	new CActionButtonList('action', 'proxyids', [
		'proxy.hostenable' => ['name' => _('Enable hosts'),
			'confirm' => _('Enable hosts monitored by selected proxies?')
		],
		'proxy.hostdisable' => ['name' => _('Disable hosts'),
			'confirm' => _('Disable hosts monitored by selected proxies?')
		],
		'proxy.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected proxies?')]
	], 'proxy')
]);

// append form to widget
$widget->addItem($proxyForm)->show();
