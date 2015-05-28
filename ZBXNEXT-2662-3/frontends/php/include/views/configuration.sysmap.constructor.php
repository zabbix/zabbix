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

$sysmapWidget = (new CWidget())->setTitle(_('Network maps'));

// create menu
$menu = (new CList([], 'object-group'))
	->addItem([
		_('Icon').':'.SPACE,
		(new CLink(_('Add'), 'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('selementAdd'),
		SPACE.'/'.SPACE,
		(new CLink(_('Remove'), 'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('selementRemove')
	])
	->addItem([
		_('Link').':'.SPACE,
		(new CLink(_('Add'), 'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('linkAdd'),
		SPACE.'/'.SPACE,
		(new CLink(_('Remove'), 'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('linkRemove')
	])
	->addItem([
		_('Expand macros').':'.SPACE,
		(new CLink(($this->data['sysmap']['expand_macros'] == SYSMAP_EXPAND_MACROS_ON) ? _('On') : _('Off'),
			'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('expand_macros')
	])
	->addItem([
		_('Grid').':'.SPACE,
		(new CLink(($this->data['sysmap']['grid_show'] == SYSMAP_GRID_SHOW_ON) ? _('Shown') : _('Hidden'),
			'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('gridshow'),
		SPACE.'/'.SPACE,
		(new CLink(($this->data['sysmap']['grid_align'] == SYSMAP_GRID_ALIGN_ON) ? _('On') : _('Off'),
			'javascript:void(0);', ZBX_STYLE_LINK_DOTTED, null, true))->setId('gridautoalign')
	])
	->addItem(new CComboBox('gridsize', $this->data['sysmap']['grid_size'], null, [
		20 => '20x20',
		40 => '40x40',
		50 => '50x50',
		75 => '75x75',
		100 => '100x100'
	]))
	->addItem((new CSubmit('gridalignall', _('Align icons')))->addClass('btn-alt')->setId('gridalignall'))
	->addItem((new CSubmit('update', _('Update')))->setId('sysmap_update'));

// create map
$backgroundImage = new CImg('images/general/tree/zero.gif', 'Sysmap');
$backgroundImage->setId('sysmap_img', $this->data['sysmap']['width'], $this->data['sysmap']['height']);

$backgroundImageTable = new CTable();
$backgroundImageTable->addRow($backgroundImage);

$container = (new CDiv())->setId('sysmap_cnt');

$sysmapWidget->addItem($menu)
	->addItem((new CDiv(null, 'table-forms-container'))
	->addItem($backgroundImageTable)
	->addItem($container));

// create elements
zbx_add_post_js('ZABBIX.apps.map.run("sysmap_cnt", '.CJs::encodeJson([
	'sysmap' => $this->data['sysmap'],
	'iconList' => $this->data['iconList'],
	'defaultAutoIconId' => $this->data['defaultAutoIconId'],
	'defaultIconId' => $this->data['defaultIconId'],
	'defaultIconName' => $this->data['defaultIconName']
], true).');');

insert_show_color_picker_javascript();

return $sysmapWidget;
