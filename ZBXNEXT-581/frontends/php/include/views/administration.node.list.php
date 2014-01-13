<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$nodeWidget = new CWidget();

$configComboBox = new CComboBox('config', 'nodes.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('nodes.php', _('Nodes'));
$configComboBox->addItem('proxies.php', _('Proxies'));

$nodeTable = new CTableInfo(_('Your setup is not configured for distributed monitoring.'));

$createForm = null;
if (ZBX_DISTRIBUTED) {
	$nodeWidget->addHeader(_('Nodes'));

	$createForm = new CForm();
	$createForm->cleanItems();
	$createForm->addItem(new CSubmit('form', _('Create node')));


	$nodeTable = new CTableInfo();
	$nodeTable->setHeader(array(
		make_sorting_header(_('ID'), 'n.nodeid'),
		make_sorting_header(_('Name'), 'n.name'),
		_('Type'),
		make_sorting_header(_('IP').' : '._('Port'), 'n.ip')
	));

	while ($node = DBfetch($this->data['nodes'])) {
		$nodeTable->addRow(array(
			$node['nodeid'],
			array(
				get_node_path($node['masterid']),
				new CLink($node['name'], '?form=update&nodeid='.$node['nodeid'])
			),
			node_type2str($node['nodetype']),
			$node['ip'].' : '.$node['port']
		));
	}
}

$nodeWidget->addPageHeader(_('CONFIGURATION OF NODES'), array($configComboBox, $createForm));
$nodeWidget->addItem($nodeTable);

return $nodeWidget;
