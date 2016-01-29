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


// header
$imageComboBox = new CComboBox('imagetype', $this->data['imagetype'], 'submit();');
$imageComboBox->addItem(IMAGE_TYPE_ICON, _('Icon'));
$imageComboBox->addItem(IMAGE_TYPE_BACKGROUND, _('Background'));
$imageComboBoxForm = new CForm();
$imageComboBoxForm->addItem(_('Type').SPACE);
$imageComboBoxForm->addItem($imageComboBox);
$this->data['widget']->addHeader(_('Images'), $imageComboBoxForm);

// form
$imageForm = new CForm();
$imageForm->setName('imageForm');
$imageForm->addItem(BR());

$imageTable = new CTable(_('No images found.'), 'header_wide padding_standard');

$count = 0;
$imageRow = new CRow();
foreach ($this->data['images'] as $image) {
	$img = ($image['imagetype'] == IMAGE_TYPE_BACKGROUND)
		? new CLink(new CImg('imgstore.php?width=200&height=200&iconid='.$image['imageid'], 'no image'), 'image.php?imageid='.$image['imageid'])
		: new CImg('imgstore.php?iconid='.$image['imageid'], 'no image');

	$nodeName = $this->data['displayNodes'] ? new CSpan($image['nodename'], 'unknown') : null;
	$name = new CLink($image['name'], 'adm.images.php?form=update&imageid='.$image['imageid']);

	$imgColumn = new CCol();
	$imgColumn->setAttribute('align', 'center');
	$imgColumn->addItem(array($img, BR(), $nodeName, $name), 'center');
	$imageRow->addItem($imgColumn);

	$count++;
	if (($count % 4) == 0) {
		$imageTable->addRow($imageRow);
		$imageRow = new CRow();
	}
}

if ($count > 0) {
	while (($count % 4) != 0) {
		$imageRow->addItem(SPACE);
		$count++;
	}
	$imageTable->addRow($imageRow);
}

$imageForm->addItem($imageTable);

return $imageForm;
