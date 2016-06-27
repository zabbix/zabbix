<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
?>
<?php
include(dirname(__FILE__).'/js/configuration.services.child.list.js.php');

$servicesChildWidget = new CWidget();
$servicesChildWidget->addPageHeader(_('IT service dependencies'));

// create form
$servicesChildForm = new CForm();
$servicesChildForm->setName('servicesForm');
if (!empty($this->data['service'])) {
	$servicesChildForm->addVar('serviceid', $this->data['service']['serviceid']);
}

// create table
$servicesChildTable = new CTableInfo();
$servicesChildTable->setHeader(array(
	new CCheckBox('all_services', null, "javascript: checkAll('".$servicesChildForm->getName()."', 'all_services', 'services');"),
	_('Service'),
	_('Status calculation'),
	_('Trigger')
));

$prefix = null;
foreach ($this->data['db_cservices'] as $service) {
	$description = new CLink($service['name'], '#', 'service-name');
	$description->setAttributes(array(
		'id' => 'service-name-'.$service['serviceid'],
		'data-name' => $service['name'],
		'data-serviceid' => $service['serviceid'],
		'data-trigger' => $service['trigger'],
		'data-triggerid' => $service['triggerid']
	));

	$cb = new CCheckBox('services['.$service['serviceid'].']', null, null, $service['serviceid']);
	$cb->addClass('service-select');

	$servicesChildTable->addRow(array(
		$cb,
		array($prefix, $description),
		serviceAlgorythm($service['algorithm']),
		$service['trigger'])
	);
}
$servicesChildTable->setFooter(new CCol(new CButton('select', _('Select')), 'right'));

// append table to form
$servicesChildForm->addItem($servicesChildTable);

// append form to widget
$servicesChildWidget->addItem($servicesChildForm);
return $servicesChildWidget;
?>
