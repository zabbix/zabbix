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


$screenWidget = (new CWidget())->setTitle(_('Screens'))->
	addItem((new CFilter('web.screens.filter.state'))->addNavigator());

// header form
$headerForm = new CForm();

$controls = new CList();
$controls->addItem(new CComboBox('config', 'screens.php', 'redirect(this.options[this.selectedIndex].value);',
	[
		'screens.php' => _('Screens'),
		'slides.php' => _('Slide shows')
	]
));

if (empty($this->data['screens'])) {
	$headerForm->addItem($controls);
	$screenWidget->setControls($headerForm)->addItem(BR())->addItem(new CTableInfo());

	$screenBuilder = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screenBuilder->timeline
	]);
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

	// append screens combobox to page header
	$headerForm->setName('headerForm');
	$headerForm->addVar('fullscreen', $this->data['fullscreen']);

	$elementsComboBox = new CComboBox('elementid', $screen['screenid'], 'submit()');
	foreach ($this->data['screens'] as $dbScreen) {
		$elementsComboBox->addItem($dbScreen['screenid'],
			htmlspecialchars($dbScreen['name']));
	}
	$controls->addItem([_('Screens').SPACE, $elementsComboBox]);

	if (check_dynamic_items($screen['screenid'], 0)) {
		$pageFilter = new CPageFilter([
			'groups' => [
				'monitored_hosts' => true,
				'with_items' => true
			],
			'hosts' => [
				'monitored_hosts' => true,
				'with_items' => true,
				'DDFirstLabel' => _('not selected')
			],
			'hostid' => getRequest('hostid'),
			'groupid' => getRequest('groupid')
		]);
		$_REQUEST['groupid'] = $pageFilter->groupid;
		$_REQUEST['hostid'] = $pageFilter->hostid;

		$controls->addItem([ _('Group').SPACE, $pageFilter->getGroupsCB()]);
		$controls->addItem([ _('Host').SPACE, $pageFilter->getHostsCB()]);
	}

	// page header
	$controls->addItem(get_icon('favourite', ['fav' => 'web.favorite.screenids', 'elname' => 'screenid', 'elid' => $screen['screenid']]));
	$controls->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));

	$headerForm->addItem($controls);

	$screenWidget->setControls($headerForm);

	// append screens to widget
	$screenBuilder = new CScreenBuilder([
		'screenid' => $screen['screenid'],
		'mode' => SCREEN_MODE_PREVIEW,
		'profileIdx' => 'web.screens',
		'profileIdx2' => $screen['screenid'],
		'groupid' => getRequest('groupid'),
		'hostid' => getRequest('hostid'),
		'period' => $this->data['period'],
		'stime' => $this->data['stime']
	]);
	$screenWidget->addItem($screenBuilder->show());

	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screenBuilder->timeline,
		'profileIdx' => $screenBuilder->profileIdx
	]);

	$screenWidget->addItem(BR());
}

return $screenWidget;
