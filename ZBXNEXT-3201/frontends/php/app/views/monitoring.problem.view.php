<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$this->addJsFile('js/gtlc.js');
$this->addJsFile('js/flickerfreescreen.js');

(new CWidget())
	->setTitle(_('Problems'))
	->setControls(
		(new CForm('get'))
			->addVar('action', 'problem.view')
			->addVar('fullscreen', $data['fullscreen'])
			->addItem(
				(new CList())
					->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
			)
	)
	->addItem(
		CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_PROBLEM,
			'mode' => SCREEN_MODE_JS,
			'dataId' => 'problem',
			'data' => [
				'fullscreen' => $data['fullscreen'],
				'sort' => $data['sort'],
				'sortorder' => $data['sortorder']
			]
		])->get()
	)
	->show();

// activating blinking
$this->addPostJS('jqBlink.blink();');
