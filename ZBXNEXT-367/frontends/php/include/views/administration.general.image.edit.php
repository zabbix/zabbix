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


$imageForm = new CForm('post', null, 'multipart/form-data');
$imageForm->setName('imageForm');
$imageForm->addVar('form', $this->data['form']);
$imageForm->addVar('imageid', $this->data['imageid']);

$imageComboBox = new CComboBox('imagetype', $this->data['imagetype']);
$imageComboBox->addItem(IMAGE_TYPE_ICON, _('Icon'));
$imageComboBox->addItem(IMAGE_TYPE_BACKGROUND, _('Background'));

// append form list
$imageFormList = new CFormList('imageFormList');
$nameTextBox = new CTextBox('name', $this->data['imagename'], 64, 'no', 64);
$nameTextBox->attr('autofocus', 'autofocus');
$imageFormList->addRow(_('Name'), $nameTextBox);
$imageFormList->addRow(_('Type'), $imageComboBox);
$imageFormList->addRow(_('Upload'), new CFile('image'));

if (!empty($this->data['imageid'])) {
	if ($data['imagetype'] == IMAGE_TYPE_BACKGROUND) {
		$imageFormList->addRow(_('Image'), new CLink(new CImg('imgstore.php?width=200&height=200&iconid='.$this->data['imageid'], 'no image'), 'image.php?imageid='.$this->data['imageid']));
	}
	else {
		$imageFormList->addRow(_('Image'), new CImg('imgstore.php?iconid='.$this->data['imageid'], 'no image', null));
	}
}

// append tab
$imageTab = new CTabView();
$imageTab->addTab('imageTab', _('Image'), $imageFormList);
$imageForm->addItem($imageTab);

// append buttons
if (empty($this->data['imageid'])) {
	$imageForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), new CButtonCancel()));
}
else {
	$imageForm->addItem(makeFormFooter(
		new CSubmit('save', _('Save')),
		array(
			new CButtonDelete(_('Delete selected image?'), url_param('form').url_param('imageid')),
			new CButtonCancel()
		)
	));
}

return $imageForm;
