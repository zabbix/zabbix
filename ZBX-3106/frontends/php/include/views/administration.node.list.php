<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
$nodeWidget = new CWidget();

// create new proxy button
$configComboBox = new CComboBox('config', 'nodes.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('nodes.php', _('Nodes'));
$configComboBox->addItem('proxies.php', _('Proxies'));

$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem($configComboBox);
$createForm->addItem(ZBX_DISTRIBUTED ? new CSubmit('form', _('Create node')) : null);
$nodeWidget->addPageHeader(_('CONFIGURATION OF NODES'), $createForm);

// create form
$nodeForm = new CForm('get');
$nodeForm->setName('nodeForm');
$nodeForm->addItem(BR());

if (ZBX_DISTRIBUTED) {
	$nodeWidget->addHeader(_('Nodes'));

	// create table
	$nodeTable = new CTableInfo(_('No nodes defined.'));
	$nodeTable->setHeader(array(
		make_sorting_header(_('ID'), 'n.nodeid'),
		make_sorting_header(_('Name'), 'n.name'),
		_('Type'),
		make_sorting_header(_('IP').SPACE.':'.SPACE._('Port'), 'n.ip')
	));

	while ($node = DBfetch($this->data['nodes'])) {
		$nodeTable->addRow(array(
			$node['nodeid'],
			array(
				get_node_path($node['masterid']),
				new CLink($node['nodetype'] ? new CSpan($node['name'], 'bold') : $node['name'], '?&form=update&nodeid='.$node['nodeid'])
			),
			node_type2str($node['nodetype']),
			new CSpan($node['ip'].SPACE.':'.SPACE.$node['port'], $node['nodetype'] ? 'bold' : null)
		));
	}
	$nodeForm->addItem($nodeTable);
}
else {
	$nodeForm->addItem(new CTable(new CCol(_('Your setup is not configured for distributed monitoring.'), 'center'), 'formElementTable'));
}

// append form to widget
$nodeWidget->addItem($nodeForm);
return $nodeWidget;
?>
