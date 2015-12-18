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
?>
<?php
$applicationWidget = new CWidget();
$applicationWidget->addPageHeader(_('CONFIGURATION OF APPLICATIONS'));

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$applicationWidget->addItem(get_header_host_table('applications', $this->data['hostid']));
}

// create form
$applicationForm = new CForm();
$applicationForm->setName('applicationForm');
$applicationForm->addVar('form', $this->data['form']);
$applicationForm->addVar('groupid', $this->data['groupid']);
$applicationForm->addVar('hostid', $this->data['hostid']);
$applicationForm->addVar('apphostid', $this->data['apphostid']);
if (!empty($this->data['applicationid'])) {
	$applicationForm->addVar('applicationid', $this->data['applicationid']);
}

// create form list
$applicationFormList = new CFormList('applicationFormList');
if (empty($this->data['applicationid'])) {
	$applicationFormList->addRow(_('Host'), array(
		new CTextBox('hostname', $this->data['hostname'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
		new CButton('btn1', _('Select'),
			'return PopUp("popup.php?srctbl=hosts_and_templates&srcfld1=hostid&srcfld2=name'.
				'&dstfrm='.$applicationForm->getName().'&dstfld1=apphostid&dstfld2=hostname'.
				'&noempty=1", 450, 450);',
			'formlist'
		)
	));
}
else {
	// cannot change host for existing application
	$applicationFormList->addRow(_('Host'), array(
		new CTextBox('hostname', $this->data['hostname'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes'),
	));
}
$applicationFormList->addRow(_('Name'), new CTextBox('appname', $this->data['appname'], ZBX_TEXTBOX_STANDARD_SIZE));

// append tabs to form
$applicationTab = new CTabView();
$applicationTab->addTab('applicationTab', _('Application'), $applicationFormList);
$applicationForm->addItem($applicationTab);

// append buttons to form
if (!empty($this->data['applicationid'])) {
	$applicationForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete application?'), url_param('config').url_param('hostid').url_param('groupid').url_param('form').url_param('applicationid')),
			new CButtonCancel(url_param('config').url_param('hostid').url_param('groupid'))
		)
	));
}
else {
	$applicationForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(new CButtonCancel(url_param('config').url_param('hostid').url_param('groupid')))
	));
}

// append form to widget
$applicationWidget->addItem($applicationForm);
return $applicationWidget;
?>
