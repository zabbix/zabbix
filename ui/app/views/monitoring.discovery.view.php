<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('layout.mode.js');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Status of discovery'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_DISCOVERY_VIEW))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'discovery.view'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow(
					(new CLabel(_('Discovery rule'), 'filter_druleids__ms')),
					(new CMultiSelect([
						'name' => 'filter_druleids[]',
						'object_name' => 'drules',
						'data' => $data['filter']['drules'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'drules',
								'srcfld1' => 'druleid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_druleids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
		])
		->addVar('action', 'discovery.view')
	);

$discovery_table = CScreenBuilder::getScreen([
	'resourcetype' => SCREEN_RESOURCE_DISCOVERY,
	'mode' => SCREEN_MODE_JS,
	'dataId' => 'discovery',
	'data' => [
		'filter_druleids' => $data['filter']['druleids'],
		'sort' => $data['sort'],
		'sortorder' => $data['sortorder']
	]
])->get();

$widget->addItem($discovery_table)->show();
