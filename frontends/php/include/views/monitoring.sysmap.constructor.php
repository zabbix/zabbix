<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


include dirname(__FILE__).'/js/monitoring.sysmaps.js.php';

// create menu
$menu = (new CList())
	->addClass(ZBX_STYLE_OBJECT_GROUP)
	->addItem([
		_('Map element').':&nbsp;',
		(new CButton('selementAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		'&nbsp;/&nbsp;',
		(new CButton('selementRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Shape').':&nbsp;',
		(new CButton('shapeAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		'&nbsp;/&nbsp;',
		(new CButton('shapesRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Link').':&nbsp;',
		(new CButton('linkAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		'&nbsp;/&nbsp;',
		(new CButton('linkRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Expand macros').':&nbsp;',
		(new CButton('expand_macros',
			($this->data['sysmap']['expand_macros'] == SYSMAP_EXPAND_MACROS_ON) ? _('On') : _('Off')
		))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Grid').':&nbsp;',
		(new CButton('gridshow',
			($data['sysmap']['grid_show'] == SYSMAP_GRID_SHOW_ON) ? _('Shown') : _('Hidden')
		))->addClass(ZBX_STYLE_BTN_LINK),
		'&nbsp;/&nbsp;',
		(new CButton('gridautoalign',
			($data['sysmap']['grid_align'] == SYSMAP_GRID_ALIGN_ON) ? _('On') : _('Off')
		))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem(new CComboBox('gridsize', $data['sysmap']['grid_size'], null, [
		20 => '20x20',
		40 => '40x40',
		50 => '50x50',
		75 => '75x75',
		100 => '100x100'
	]))
	->addItem((new CButton('gridalignall', _('Align map elements')))->addClass(ZBX_STYLE_BTN_LINK))
	->addItem((new CSubmit('update', _('Update')))->setId('sysmap_update'));

$container = (new CDiv())->setId(ZBX_STYLE_MAP_AREA);

// create elements
zbx_add_post_js('ZABBIX.apps.map.run("'.ZBX_STYLE_MAP_AREA.'", '.CJs::encodeJson([
	'theme' => $data['theme'],
	'sysmap' => $data['sysmap'],
	'iconList' => $data['iconList'],
	'defaultAutoIconId' => $data['defaultAutoIconId'],
	'defaultIconId' => $data['defaultIconId'],
	'defaultIconName' => $data['defaultIconName']
], true).');');

insert_show_color_picker_javascript();

return (new CWidget())
	->setTitle(_('Network maps'))
	->addItem($menu)
	->addItem(
		(new CDiv(
			(new CDiv())
				->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
				->addItem($container)
		))->addClass('sysmap-scroll-container')
	);
