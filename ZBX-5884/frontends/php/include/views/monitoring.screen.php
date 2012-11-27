<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


require_once dirname(__FILE__).'/js/general.script.confirm.js.php';

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
	$screenWidget->addItem(new CTableInfo(_('No screens defined.')));

	$screenBuilder = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs(array(
		'timeline' => $screenBuilder->timeline
	));
}
elseif (!isset($this->data['screens'][$this->data['elementIdentifier']]) && !$this->data['id_has_been_fetched_from_profile']) {
	// if screen we are searching for does not exist and was not fetched from profile
	$error_msg = $this->data['use_screen_name']
		? _s('Screen with name "%s" does not exist.', $this->data['elementIdentifier'])
		: _s('Screen with ID "%s" does not exist.', $this->data['elementIdentifier']);
	show_error_message($error_msg);
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
			htmlspecialchars(get_node_name_by_elid($dbScreen['screenid'], null, ': ').$dbScreen['name']));
	}
	$headerForm->addItem(array(_('Screens').SPACE, $elementsComboBox));

	if (check_dynamic_items($screen['screenid'], 0)) {
		global $ZBX_WITH_ALL_NODES;

		if (!isset($_REQUEST['hostid'])) {
			$_REQUEST['groupid'] = $_REQUEST['hostid'] = 0;
		}

		$options = array('allow_all_hosts', 'monitored_hosts', 'with_items');
		if (!$ZBX_WITH_ALL_NODES) {
			array_push($options, 'only_current_node');
		}
		$params = array();
		foreach ($options as $option) {
			$params[$option] = 1;
		}

		$PAGE_GROUPS = get_viewed_groups(PERM_READ, $params);
		$PAGE_HOSTS = get_viewed_hosts(PERM_READ, $PAGE_GROUPS['selected'], $params);

		validate_group_with_host($PAGE_GROUPS, $PAGE_HOSTS);

		// groups
		$groupsComboBox = new CComboBox('groupid', $PAGE_GROUPS['selected'], 'javascript: window.flickerfreeScreen.submitForm("'.$headerForm->getName().'");');
		foreach ($PAGE_GROUPS['groups'] as $groupid => $name) {
			$groupsComboBox->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
		}
		$headerForm->addItem(array(SPACE._('Group').SPACE, $groupsComboBox));

		// hosts
		$PAGE_HOSTS['hosts']['0'] = _('Default');
		$hostsComboBox = new CComboBox('hostid', $PAGE_HOSTS['selected'], 'javascript: window.flickerfreeScreen.submitForm("'.$headerForm->getName().'");');
		foreach ($PAGE_HOSTS['hosts'] as $hostid => $name) {
			$hostsComboBox->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$name);
		}
		$headerForm->addItem(array(SPACE._('Host').SPACE, $hostsComboBox));
	}
	$screenWidget->addHeader($screen['name'], $headerForm);

	// append screens to widget
	$screenBuilder = new CScreenBuilder(array(
		'screen' => $screen,
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
