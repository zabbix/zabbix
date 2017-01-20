<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


include('include/views/js/monitoring.sysmaps.js.php');

// create menu
$menu = (new CList())
	->addClass(ZBX_STYLE_OBJECT_GROUP)
	->addItem([
		_('Icon').':'.SPACE,
		(new CButton('selementAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		SPACE.'/'.SPACE,
		(new CButton('selementRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Link').':'.SPACE,
		(new CButton('linkAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		SPACE.'/'.SPACE,
		(new CButton('linkRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Expand macros').':'.SPACE,
		(new CButton('expand_macros', ($this->data['sysmap']['expand_macros'] == SYSMAP_EXPAND_MACROS_ON) ? _('On') : _('Off')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Grid').':'.SPACE,
		(new CButton('gridshow', ($this->data['sysmap']['grid_show'] == SYSMAP_GRID_SHOW_ON) ? _('Shown') : _('Hidden')))->addClass(ZBX_STYLE_BTN_LINK),
		SPACE.'/'.SPACE,
		(new CButton('gridautoalign', ($this->data['sysmap']['grid_align'] == SYSMAP_GRID_ALIGN_ON) ? _('On') : _('Off')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem(new CComboBox('gridsize', $this->data['sysmap']['grid_size'], null, [
		20 => '20x20',
		40 => '40x40',
		50 => '50x50',
		75 => '75x75',
		100 => '100x100'
	]))
	->addItem((new CButton('gridalignall', _('Align icons')))->addClass(ZBX_STYLE_BTN_LINK))
	->addItem((new CSubmit('update', _('Update')))->setAttribute('id', 'sysmap_update'));

// create map
$backgroundImage = (new CImg('images/general/tree/zero.gif', 'Sysmap'))
	->setId('sysmap_img', $this->data['sysmap']['width'], $this->data['sysmap']['height']);

$backgroundImageTable = new CTable();
$backgroundImageTable->addRow($backgroundImage);

$container = (new CDiv())->setId(ZBX_STYLE_MAP_AREA);

// create elements
zbx_add_post_js('ZABBIX.apps.map.run("'.ZBX_STYLE_MAP_AREA.'", '.CJs::encodeJson([
	'sysmap' => $this->data['sysmap'],
	'iconList' => $this->data['iconList'],
	'defaultAutoIconId' => $this->data['defaultAutoIconId'],
	'defaultIconId' => $this->data['defaultIconId'],
	'defaultIconName' => $this->data['defaultIconName']
], true).');');

insert_show_color_picker_javascript();

return (new CWidget())
	->setTitle(_('Network maps'))
	->addItem($menu)
	->addItem(
		(new CDiv())
			->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
			->addItem($backgroundImageTable)
			->addItem($container)
	);
