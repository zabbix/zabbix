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


include('include/views/js/configuration.sysmaps.js.php');

$sysmapWidget = new CWidget();
$sysmapWidget->addPageHeader(_('CONFIGURATION OF NETWORK MAPS'));

// create menu
$addIcon = new CIcon(_('Add element'), 'iconplus');
$addIcon->setAttribute('id', 'selementAdd');
$removeIcon = new CIcon(_('Remove element'), 'iconminus');
$removeIcon->setAttribute('id', 'selementRemove');

$addLinkIcon = new CIcon(_('Add link'), 'iconplus');
$addLinkIcon->setAttribute('id', 'linkAdd');
$removeLinkIcon = new CIcon(_('Remove link'), 'iconminus');
$removeLinkIcon->setAttribute('id', 'linkRemove');

$expandMacros = new CSpan(($this->data['sysmap']['expand_macros'] == SYSMAP_EXPAND_MACROS_ON) ? _('On') : _('Off'), 'whitelink');
$expandMacros->setAttribute('id', 'expand_macros');

$gridShow = new CSpan(($this->data['sysmap']['grid_show'] == SYSMAP_GRID_SHOW_ON) ? _('Shown') : _('Hidden'), 'whitelink');
$gridShow->setAttribute('id', 'gridshow');

$gridAutoAlign = new CSpan(($this->data['sysmap']['grid_align'] == SYSMAP_GRID_ALIGN_ON) ? _('On') : _('Off'), 'whitelink');
$gridAutoAlign->setAttribute('id', 'gridautoalign');

$gridSize = new CComboBox('gridsize', $this->data['sysmap']['grid_size']);
$gridSize->addItems(array(
	20 => '20x20',
	40 => '40x40',
	50 => '50x50',
	75 => '75x75',
	100 => '100x100'
));

$gridAlignAll = new CSubmit('gridalignall', _('Align icons'));
$gridAlignAll->setAttribute('id', 'gridalignall');

$gridForm = new CDiv(array($gridSize, $gridAlignAll));
$gridForm->setAttribute('id', 'gridalignblock');

$saveButton = new CSubmit('save', _('Save'));
$saveButton->setAttribute('id', 'sysmap_save');

$menuTable = new CTable(null, 'textwhite');
$menuTable->addRow(array(
	_s('Map "%s"', $this->data['sysmap']['name']),
	SPACE.SPACE,
	_('Icon'), SPACE, $addIcon, SPACE, $removeIcon,
	SPACE.SPACE,
	_('Link'), SPACE, $addLinkIcon, SPACE, $removeLinkIcon,
	SPACE.SPACE,
	_('Expand macros').' [ ', $expandMacros, ' ]',
	SPACE.SPACE,
	_('Grid').SPACE.'[', $gridShow, '|', $gridAutoAlign, ']',
	SPACE,
	$gridForm,
	SPACE.'|'.SPACE,
	$saveButton
));

$sysmapWidget->addPageHeader($menuTable);

// create map
$backgroundImage = new CImg('images/general/tree/zero.gif', 'Sysmap');
$backgroundImage->setAttribute('id', 'sysmap_img', $this->data['sysmap']['width'], $this->data['sysmap']['height']);

$backgroundImageTable = new CTable();
$backgroundImageTable->addRow($backgroundImage);
$sysmapWidget->addItem($backgroundImageTable);

$container = new CDiv();
$container->setAttribute('id', 'sysmap_cnt');
$sysmapWidget->addItem($container);

// create elements
zbx_add_post_js('ZABBIX.apps.map.run("sysmap_cnt", '.CJs::encodeJson(array(
	'sysmap' => $this->data['sysmap'],
	'iconList' => $this->data['iconList'],
	'defaultAutoIconId' => $this->data['defaultAutoIconId'],
	'defaultIconId' => $this->data['defaultIconId'],
	'defaultIconName' => $this->data['defaultIconName']
), true).');');

insert_show_color_picker_javascript();

return $sysmapWidget;
