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


$screenWidget = new CWidget();

$form = (new CFilter('web.hostscreen.filter.state'))->addNavigator();

$screenWidget->addItem($form);

if (empty($this->data['screen']) || empty($this->data['host'])) {
	$screenWidget
		->setTitle(_('Screens'))
		->addItem(new CTableInfo());

	$screenBuilder = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screenBuilder->timeline
	]);
}
else {
	$screenWidget->setTitle([
		$this->data['screen']['name'],
		SPACE,
		_('on'),
		SPACE,
		(new CSpan($this->data['host']['name']))->addClass(ZBX_STYLE_ORANGE)
	]);

	// host screen list
	if (!empty($this->data['screens'])) {
		$screenComboBox = new CComboBox(
			'screenList',
			'host_screen.php?hostid='.$this->data['hostid'].'&screenid='.$this->data['screenid'],
			'javascript: redirect(this.options[this.selectedIndex].value);'
		);
		foreach ($this->data['screens'] as $screen) {
			$screenComboBox->addItem('host_screen.php?hostid='.$this->data['hostid'].'&screenid='.$screen['screenid'], $screen['name']);
		}

		$screenWidget->setControls((new CList())
			->addItem($screenComboBox)
			->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]))
		);
	}

	// append screens to widget
	$screenBuilder = new CScreenBuilder([
		'screen' => $this->data['screen'],
		'mode' => SCREEN_MODE_PREVIEW,
		'hostid' => $this->data['hostid'],
		'period' => $this->data['period'],
		'stime' => $this->data['stime'],
		'profileIdx' => 'web.screens',
		'profileIdx2' => $this->data['screen']['screenid']
	]);

	$screenWidget->addItem(
		(new CDiv($screenBuilder->show()))->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
	);

	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screenBuilder->timeline,
		'profileIdx' => $screenBuilder->profileIdx
	]);
}

return $screenWidget;
