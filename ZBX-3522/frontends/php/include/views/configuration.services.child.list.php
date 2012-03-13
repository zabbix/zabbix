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
include('include/views/js/configuration.services.edit.js.php');

$servicesChildWidget = new CWidget();
$servicesChildWidget->addPageHeader(_('IT SERVICES: CHILDS'));

// create form
$servicesChildForm = new CForm();
$servicesChildForm->setName('servicesForm');
if (!empty($this->data['service'])) {
	$servicesChildForm->addVar('serviceid', $this->data['service']['serviceid']);
}

// create table
$servicesChildTable = new CTableInfo();
$servicesChildTable->setHeader(array(_('Service'), _('Status calculation'), _('Trigger')));

$prefix = null;
foreach ($this->data['db_cservices'] as $db_service) {
	$description = new CLink(
		$db_service['name'], '#', null,
		'window.opener.add_child_service('.zbx_jsvalue($db_service['name']).','.zbx_jsvalue($db_service['serviceid'])
			.','.zbx_jsvalue($db_service['trigger']).','.zbx_jsvalue($db_service['triggerid']).'); self.close(); return false;'
	);
	$servicesChildTable->addRow(array(array($prefix, $description), algorithm2str($db_service['algorithm']), $db_service['trigger']));
}
$column = new CCol(new CButton('cancel', _('Cancel'), 'javascript: self.close();'));
$column->setAttribute('style', 'text-align:right;');
$servicesChildTable->setFooter($column);

// append table to form
$servicesChildForm->addItem($servicesChildTable);

// append form to widget
$servicesChildWidget->addItem($servicesChildForm);
return $servicesChildWidget;
?>
