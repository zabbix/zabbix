<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$proxyWidget = new CWidget();

// create new proxy button
$configComboBox = new CComboBox('config', 'proxies.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('nodes.php', _('Nodes'));
$configComboBox->addItem('proxies.php', _('Proxies'));

$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem($configComboBox);
$createForm->addItem(new CSubmit('form', _('Create proxy')));
$proxyWidget->addPageHeader(_('CONFIGURATION OF PROXIES'), $createForm);
$proxyWidget->addHeader(_('Proxies'));
$proxyWidget->addHeaderRowNumber();

// create form
$proxyForm = new CForm('get');
$proxyForm->setName('proxyForm');

// create table
$proxyTable = new CTableInfo(_('No proxies defined.'));
$proxyTable->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$proxyForm->getName()."', 'all_hosts', 'hosts');"),
	make_sorting_header(_('Name'), 'host'),
	_('Mode'),
	_('Last seen (age)'),
	_('Host count'),
	_('Item count'),
	_('Required performance (vps)'),
	_('Hosts')
));

foreach ($this->data['proxies'] as $proxy) {
	$hosts = array();

	if (!empty($proxy['hosts'])) {
		foreach ($proxy['hosts'] as $host) {
			if ($host['status'] == HOST_STATUS_MONITORED) {
				$style = 'off';
			}
			elseif ($host['status'] == HOST_STATUS_TEMPLATE) {
				$style = 'unknown';
			}
			else {
				$style = 'on';
			}

			$hosts[] = new CLink($host['name'], 'hosts.php?form=update&hostid='.$host['hostid'], $style);
			$hosts[] = ', ';
		}

		array_pop($hosts);
	}

	$lastAccess = '-';
	if (isset($proxy['lastaccess'])) {
		$lastAccess = ($proxy['lastaccess'] == 0) ? '-' : zbx_date2age($proxy['lastaccess']);
	}

	$proxyTable->addRow(array(
		new CCheckBox('hosts['.$proxy['proxyid'].']', null, null, $proxy['proxyid']),
		isset($proxy['host']) ? new CLink($proxy['host'], 'proxies.php?form=update&proxyid='.$proxy['proxyid']) : '',
		(isset($proxy['status']) && $proxy['status'] == HOST_STATUS_PROXY_ACTIVE) ? _('Active') : _('Passive'),
		$lastAccess,
		isset($proxy['host']) ? count($proxy['hosts']) : '',
		isset($proxy['item_count']) ? $proxy['item_count'] : 0,
		isset($proxy['perf']) ? $proxy['perf'] : '-',
		new CCol((empty($hosts) ? '-' : $hosts), 'wraptext')
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable hosts monitored by selected proxies?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable hosts monitored by selected proxies?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected proxies?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

// append table to form
$proxyForm->addItem(array($this->data['paging'], $proxyTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$proxyWidget->addItem($proxyForm);

return $proxyWidget;
