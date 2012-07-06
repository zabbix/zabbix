<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
// header
$imageCb = new CComboBox('imagetype', $this->data['imagetype'], 'submit();');
$imageCb->addItem(IMAGE_TYPE_ICON, _('Icon'));
$imageCb->addItem(IMAGE_TYPE_BACKGROUND, _('Background'));
$imageCbForm = new CForm();
$imageCbForm->addItem(_('Type').SPACE);
$imageCbForm->addItem($imageCb);
$this->data['widget']->addHeader(_('Images'), $imageCbForm);

// form
$imageForm = new CForm();
$imageForm->setName('imageForm');
$imageForm->addItem(BR());

$imageTable = new CTable(_('No images defined.'), 'header_wide');

$count = 0;
$imageRow = new CRow();
foreach ($this->data['images'] as $number => $image) {
	if ($image['imagetype'] == IMAGE_TYPE_BACKGROUND) {
		$img = new CLink(new CImg('imgstore.php?width=200&height=200&iconid='.$image['imageid'], 'no image'), 'image.php?imageid='.$image['imageid']);
	}
	else {
		$img = new CImg('imgstore.php?iconid='.$image['imageid'], 'no image');
	}

	$name = new CLink($image['name'], 'adm.images.php?form=update'.'&imageid='.$image['imageid']);

	$imgColumn = new CCol();
	$imgColumn->setAttribute('align', 'center');
	$imgColumn->addItem(array($img, BR(), $name), 'center');

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
?>
