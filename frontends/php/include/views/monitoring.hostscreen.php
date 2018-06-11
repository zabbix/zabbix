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


$screen_widget = new CWidget();

$filter = new CFilter();

if (empty($data['screen']) || empty($data['host'])) {
	$screen_widget
		->setTitle(_('Screens'))
		->addItem(new CTableInfo());
}
else {
	$screen_widget->setTitle([
		$data['screen']['name'].' '._('on').' ',
		(new CSpan($data['host']['name']))->addClass(ZBX_STYLE_ORANGE)
	]);

	$url = (new CUrl('host_screen.php'))
		->setArgument('hostid', $data['hostid'])
		->setArgument('screenid', $data['screenid'])
		->setArgument('fullscreen', $data['fullscreen'] ? '1' : null);

	// host screen list
	if (!empty($data['screens'])) {
		$screen_combobox = new CComboBox('screenList', $url->toString(),
			'javascript: redirect(this.options[this.selectedIndex].value);'
		);
		foreach ($data['screens'] as $screen) {
			$screen_combobox->addItem(
				$url
					->setArgument('screenid', $screen['screenid'])
					->toString(),
				$screen['name']
			);
		}

		$screen_widget->setControls((new CTag('nav', true,
			(new CForm('get'))
				->setAttribute('aria-label', _('Main filter'))
				->addItem((new CList())
					->addItem($screen_combobox)
					->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
				)
			))
				->setAttribute('aria-label', _('Content controls'))
		);
	}

	// append screens to widget
	$screen_builder = new CScreenBuilder([
		'screen' => $data['screen'],
		'mode' => SCREEN_MODE_PREVIEW,
		'hostid' => $data['hostid'],
		'profileIdx' => $data['profileIdx'],
		'profileIdx2' => $data['profileIdx2'],
		'from' => $data['from'],
		'to' => $data['to']
	]);

	$filter
		->setProfile($data['profileIdx'], $data['profileIdx2'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($screen_builder->timeline['from'], $screen_builder->timeline['to']);

	$screen_widget
		->addItem($filter)
		->addItem(
			(new CDiv($screen_builder->show()))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
		);

	CScreenBuilder::insertScreenStandardJs($screen_builder->timeline);
}

return $screen_widget;
