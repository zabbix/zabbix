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
$screenWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.screens.filter.state', 1));

// header form
$configComboBox = new CComboBox('config', 'screens.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('screens.php', _('Screens'));
$configComboBox->addItem('slides.php', _('Slide shows'));
$headerForm = new CForm();
$headerForm->addItem($configComboBox);

if (empty($this->data['screens'])) {
	$screenWidget->addPageHeader(_('SCREENS'), $headerForm);
	$screenWidget->addItem(BR());
	$screenWidget->addItem(new CTableInfo(_('No screens found.')));

	$screenBuilder = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screenBuilder->timeline
	));
}
else {
	if (!isset($this->data['screens'][$this->data['elementIdentifier']])) {
		// this means id was fetched from profile and this screen does not exist
		// in this case we need to show the first one
		$screen = reset($this->data['screens']);
	}
	else {
		$screen = $this->data['screens'][$this->data['elementIdentifier']];
	}

	// if elementid is used to fetch an element, saving it in profile
	if (!$this->data['use_screen_name']) {
		CProfile::update('web.screens.elementid', $screen['screenid'] , PROFILE_TYPE_ID);
	}

	// page header
	$screenWidget->addPageHeader(_('SCREENS'), array(
		$headerForm,
		SPACE,
		get_icon('favourite', array('fav' => 'web.favorite.screenids', 'elname' => 'screenid', 'elid' => $screen['screenid'])),
		SPACE,
		get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))
	));
	$screenWidget->addItem(BR());

	// append screens combobox to page header
	$headerForm = new CForm('get');
	$headerForm->setName('headerForm');
	$headerForm->addVar('fullscreen', $this->data['fullscreen']);

	$elementsComboBox = new CComboBox('elementid', $screen['screenid'], 'submit()');
	foreach ($this->data['screens'] as $dbScreen) {
		$elementsComboBox->addItem($dbScreen['screenid'],
			htmlspecialchars(get_node_name_by_elid($dbScreen['screenid'], null, NAME_DELIMITER).$dbScreen['name']));
	}
	$headerForm->addItem(array(_('Screens').SPACE, $elementsComboBox));

	if (check_dynamic_items($screen['screenid'], 0)) {
		$pageFilter = new CPageFilter(array(
			'groups' => array(
				'monitored_hosts' => true,
				'with_items' => true
			),
			'hosts' => array(
				'monitored_hosts' => true,
				'with_items' => true,
				'DDFirstLabel' => _('Default')
			),
			'hostid' => get_request('hostid', null),
			'groupid' => get_request('groupid', null)
		));
		$_REQUEST['groupid'] = $pageFilter->groupid;
		$_REQUEST['hostid'] = $pageFilter->hostid;

		$headerForm->addItem(array(SPACE, _('Group'), SPACE, $pageFilter->getGroupsCB(true)));
		$headerForm->addItem(array(SPACE, _('Host'), SPACE, $pageFilter->getHostsCB(true)));
	}

	$screenWidget->addHeader($screen['name'], $headerForm);

	// append screens to widget
	$screenBuilder = new CScreenBuilder(array(
		'screenid' => $screen['screenid'],
		'mode' => SCREEN_MODE_PREVIEW,
		'profileIdx' => 'web.screens',
		'profileIdx2' => $screen['screenid'],
		'groupid' => get_request('groupid'),
		'hostid' => get_request('hostid'),
		'period' => $this->data['period'],
		'stime' => $this->data['stime']
	));
	$screenWidget->addItem($screenBuilder->show());

	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screenBuilder->timeline,
		'profileIdx' => $screenBuilder->profileIdx
	));

	$screenWidget->addItem(BR());
}

return $screenWidget;
