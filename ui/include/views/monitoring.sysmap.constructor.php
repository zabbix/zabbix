<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */

include dirname(__FILE__).'/js/monitoring.sysmaps.js.php';

// create menu
$menu = (new CList())
	->addItem([
		_('Map element'), ':', NBSP(),
		(new CButton('selementAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		NBSP(), '/', NBSP(),
		(new CButton('selementRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Shape'), ':', NBSP(),
		(new CButton('shapeAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		NBSP(), '/', NBSP(),
		(new CButton('shapesRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Link'), ':', NBSP(),
		(new CButton('linkAdd', _('Add')))->addClass(ZBX_STYLE_BTN_LINK),
		NBSP(), '/', NBSP(),
		(new CButton('linkRemove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Expand macros'), ':', NBSP(),
		(new CButton('expand_macros',
			($this->data['sysmap']['expand_macros'] == SYSMAP_EXPAND_MACROS_ON) ? _('On') : _('Off')
		))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem([
		_('Grid'), ':', NBSP(),
		(new CButton('gridshow',
			($data['sysmap']['grid_show'] == SYSMAP_GRID_SHOW_ON) ? _('Shown') : _('Hidden')
		))->addClass(ZBX_STYLE_BTN_LINK),
		NBSP(), '/', NBSP(),
		(new CButton('gridautoalign',
			($data['sysmap']['grid_align'] == SYSMAP_GRID_ALIGN_ON) ? _('On') : _('Off')
		))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addItem((new CSelect('gridsize'))
		->setId('gridsize')
		->setValue($data['sysmap']['grid_size'])
		->addOptions(CSelect::createOptionsFromArray([
			20 => '20x20',
			40 => '40x40',
			50 => '50x50',
			75 => '75x75',
			100 => '100x100'
		]))
	)
	->addItem((new CButton('gridalignall', _('Align map elements')))->addClass(ZBX_STYLE_BTN_LINK))
	->addItem((new CSubmit('update', _('Update')))->setId('sysmap_update'));

$container = (new CDiv())->setId(ZBX_STYLE_MAP_AREA);

// create elements
zbx_add_post_js('ZABBIX.apps.map.run("'.ZBX_STYLE_MAP_AREA.'", '.json_encode([
	'theme' => $data['theme'],
	'sysmap' => $data['sysmap'],
	'iconList' => $data['iconList'],
	'defaultAutoIconId' => $data['defaultAutoIconId'],
	'defaultIconId' => $data['defaultIconId'],
	'defaultIconName' => $data['defaultIconName'],
	'csrf_token' => CCsrfTokenHelper::get('sysmap.php')
], JSON_FORCE_OBJECT).');');

(new CHtmlPage())
	->setTitle(_('Network maps'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_SYSMAP_CONSTRUCTOR))
	->setNavigation($menu)
	->addItem(
		(new CDiv(
			(new CDiv())
				->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
				->addItem($container)
		))->addClass('sysmap-scroll-container')
	)
	->show();
