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


$widget = (new CWidget())
	->setTitle(_('Images'))
	->setControls((new CForm())
		->cleanItems()
		->addVar('imagetype', $data['imagetype'])
		->addItem((new CList())
			->addItem(makeAdministrationGeneralMenu('adm.images.php'))
			->addItem(
				new CSubmit('form', ($data['imagetype'] == IMAGE_TYPE_ICON) ? _('Create icon') : _('Create background'))
			)
		)
	);

// header
$imageComboBoxForm = (new CForm())
	->addItem([_('Type'), SPACE, new CComboBox('imagetype', $this->data['imagetype'], 'submit();', [
		IMAGE_TYPE_ICON => _('Icon'),
		IMAGE_TYPE_BACKGROUND => _('Background')])
	]);

$imageTable = (new CDiv())
	->addClass(ZBX_STYLE_TABLE)
	->addClass(ZBX_STYLE_ADM_IMG);

$count = 0;
$imageRow = (new CDiv())->addClass(ZBX_STYLE_ROW);
foreach ($this->data['images'] as $image) {
	$img = ($image['imagetype'] == IMAGE_TYPE_BACKGROUND)
		? new CLink(
			new CImg('imgstore.php?width=200&height=200&iconid='.$image['imageid'], 'no image'),
			'image.php?imageid='.$image['imageid']
		)
		: new CImg('imgstore.php?iconid='.$image['imageid'], 'no image');

	$imageRow->addItem(
		(new CDiv())
			->addClass(ZBX_STYLE_CELL)
			->addItem([
				$img,
				BR(),
				new CLink($image['name'], 'adm.images.php?form=update&imageid='.$image['imageid'])
			])
	);

	if ((++$count % 5) == 0) {
		$imageTable->addItem($imageRow);
		$imageRow = (new CDiv())->addClass(ZBX_STYLE_ROW);
	}
}

if (($count % 5) != 0) {
	$imageTable->addItem($imageRow);
}

// form
$imageForm = (new CForm())
	->addItem($imageTable);

$widget
	->addItem($imageComboBoxForm)
	->addItem($imageForm);

return $widget;
