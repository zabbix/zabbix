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


$screenWidget = new CWidget();

$form = new CFilter('web.hostscreen.filter.state');
$form->addVar('fullscreen', $this->data['fullscreen']);
$form->addNavigator();

$screenWidget->addItem($form);

if (empty($this->data['screen']) || empty($this->data['host'])) {
	$screenWidget->setTitle(_('Screens'));
	$screenWidget->addItem(BR());
	$screenWidget->addItem(new CTableInfo());

	$screenBuilder = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screenBuilder->timeline
	));
}
else {
	$screenWidget->setTitle(array($this->data['screen']['name'], SPACE, _('on'), SPACE, new CSpan($this->data['host']['name'], ZBX_STYLE_ORANGE)));

	$controls = new CList();

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

		$controls->addItem($screenComboBox);
		$controls->addItem(get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen'])));
		$screenWidget->setControls($controls);
	}

	// append screens to widget
	$screenBuilder = new CScreenBuilder(array(
		'screen' => $this->data['screen'],
		'mode' => SCREEN_MODE_PREVIEW,
		'hostid' => $this->data['hostid'],
		'period' => $this->data['period'],
		'stime' => $this->data['stime'],
		'profileIdx' => 'web.screens',
		'profileIdx2' => $this->data['screen']['screenid']
	));
	$screenWidget->addItem($screenBuilder->show());

	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screenBuilder->timeline,
		'profileIdx' => $screenBuilder->profileIdx
	));
}

return $screenWidget;
