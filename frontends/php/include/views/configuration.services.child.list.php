<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


include(dirname(__FILE__).'/js/configuration.services.child.list.js.php');

$servicesChildWidget = (new CWidget())->setTitle(_('IT service dependencies'));

// create form
$servicesChildForm = (new CForm())->setName('servicesForm');
if (!empty($this->data['service'])) {
	$servicesChildForm->addVar('serviceid', $this->data['service']['serviceid']);
}

// create table
$servicesChildTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_services'))->onClick("javascript: checkAll('".$servicesChildForm->getName()."', 'all_services', 'services');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Service'),
		_('Status calculation'),
		_('Trigger')
	]);

$prefix = null;
foreach ($this->data['db_cservices'] as $service) {
	$description = (new CLink($service['name'], '#'))
		->addClass('service-name')
		->setId('service-name-'.$service['serviceid'])
		->setAttribute('data-name', $service['name'])
		->setAttribute('data-serviceid', $service['serviceid'])
		->setAttribute('data-trigger', $service['trigger']);

	$cb = (new CCheckBox('services['.$service['serviceid'].']', $service['serviceid']))
		->addClass('service-select');

	$servicesChildTable->addRow([
		$cb,
		[$prefix, $description],
		serviceAlgorythm($service['algorithm']),
		$service['trigger']]
	);
}
$servicesChildTable->setFooter((new CCol(new CButton('select', _('Select'))))->addClass('right'));

// append table to form
$servicesChildForm->addItem($servicesChildTable);

// append form to widget
$servicesChildWidget->addItem($servicesChildForm);

return $servicesChildWidget;
