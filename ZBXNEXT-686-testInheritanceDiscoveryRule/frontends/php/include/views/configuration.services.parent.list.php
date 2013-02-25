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

$servicesParentWidget = new CWidget();
$servicesParentWidget->addPageHeader(_('IT service parent'));

// create form
$servicesParentForm = new CForm();
$servicesParentForm->setName('servicesForm');
if (!empty($this->data['service'])) {
	$servicesParentForm->addVar('serviceid', $this->data['service']['serviceid']);
}

// create table
$servicesParentTable = new CTableInfo();
$servicesParentTable->setHeader(array(_('Service'), _('Status calculation'), _('Trigger')));

$prefix = null;

// root
$description = new CLink(_('root'), '#', null, 'javascript:
	jQuery(\'#parent_name\', window.opener.document).val('.zbx_jsvalue(_('root')).');
	jQuery(\'#parentname\', window.opener.document).val('.zbx_jsvalue(_('root')).');
	jQuery(\'#parentid\', window.opener.document).val('.zbx_jsvalue(0).');
	self.close();
	return false;'
);
$servicesParentTable->addRow(array(array($prefix, $description), _('Note'), '-'));

// others
foreach ($this->data['db_pservices'] as $db_service) {
	$description = new CSpan($db_service['name'], 'link');
	$description->setAttribute('onclick', 'javascript:
		jQuery(\'#parent_name\', window.opener.document).val('.zbx_jsvalue($db_service['name']).');
		jQuery(\'#parentname\', window.opener.document).val('.zbx_jsvalue($db_service['name']).');
		jQuery(\'#parentid\', window.opener.document).val('.zbx_jsvalue($db_service['serviceid']).');
		self.close();
		return false;'
	);
	$servicesParentTable->addRow(array(array($prefix, $description), serviceAlgorythm($db_service['algorithm']), $db_service['trigger']));
}
$column = new CCol(new CButton('cancel', _('Cancel'), 'javascript: self.close();'));
$column->setAttribute('style', 'text-align:right;');
$servicesParentTable->setFooter($column);

// append table to form
$servicesParentForm->addItem($servicesParentTable);

// append form to widget
$servicesParentWidget->addItem($servicesParentForm);
return $servicesParentWidget;
?>
