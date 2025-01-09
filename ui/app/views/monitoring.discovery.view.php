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

$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.discovery.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$html_page = (new CHtmlPage())
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
								'dstfld1' => 'filter_druleids_',
								'enabled_only' => 1
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

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();

$html_page->addItem($discovery_table)->show();
