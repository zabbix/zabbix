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


$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');

(new CWidget())
	->setTitle(_('Web monitoring'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addVar('action', 'web.view')
		->addVar('fullscreen', $data['fullscreen'] ? '1' : null)
		->addItem((new CList())
			->addItem([
				new CLabel(_('Group'), 'groupid'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$data['pageFilter']->getGroupsCB()
			])
			->addItem([
				new CLabel(_('Host'), 'hostid'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$data['pageFilter']->getHostsCB()
			])
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	)
	->addItem(
		CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_HTTPTEST,
			'mode' => SCREEN_MODE_JS,
			'dataId' => 'httptest',
			'groupid' => $data['pageFilter']->groupids,
			'hostid' => $data['pageFilter']->hostid,
			'page' => $data['page'],
			'data' => [
				'hosts_selected' => $data['pageFilter']->hostsSelected,
				'fullscreen' => $data['fullscreen'],
				'sort' => $data['sort'],
				'sortorder' => $data['sortorder'],
				'groupid' => $data['pageFilter']->groupid
			]
		])->get()
	)
	->show();
