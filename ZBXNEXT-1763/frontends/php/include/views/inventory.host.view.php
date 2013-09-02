<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$hostInventoryWidget = new CWidget();

$hostInventoryWidget->addPageHeader(_('HOST INVENTORY'), SPACE);

$hostInventoriesForm = new CForm();

/*
 * Overview tab
 */
$overviewFormList = new CFormList('hostinventoriesOverviewFormList');

$hostSpan = new CSpan($this->data['overview']['host']['host'], 'link_menu menu-host');
$scripts = $this->data['hostScripts'][$this->data['overview']['host']['hostid']];
$hostSpan->setAttribute('data-menu', hostMenuData($this->data['overview']['host'], $scripts));

$overviewFormList->addRow(_('Host name'), $hostSpan);

if ($this->data['overview']['host']['host'] != $this->data['overview']['host']['name']) {
	$overviewFormList->addRow(_('Visible name'), $this->data['overview']['host']['name']);
}
if ($this->data['overview']['host']['ip']) {
	$overviewFormList->addRow(_('IP'), $this->data['overview']['host']['ip']);
}
if ($this->data['overview']['host']['dns']) {
	$overviewFormList->addRow(_('DNS'), $this->data['overview']['host']['dns']);
}
// interface (OS, Hardware, Software)
foreach ($this->data['tableValues'] as $key => $value) {
	if (($this->data['tableTitles'][$key]['title'] == 'OS' || $this->data['tableTitles'][$key]['title'] == 'Hardware'
			|| $this->data['tableTitles'][$key]['title'] == 'Software') && !zbx_empty($value)) {
		$overviewFormList->addRow($this->data['tableTitles'][$key]['title'], new CSpan(zbx_str2links($value), 'pre'));
	}
}

// latest data
$latestArray = array(
	new CLink(_('Latest data'), 'latest.php?form=1&select=&show_details=1&filter_set=Filter&hostid='.
		$this->data['overview']['host']['hostid'].url_param('groupid')),
	SPACE.SPACE,
	new CLink(_('Web'), 'httpmon.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid')),
	SPACE.SPACE,
	new CLink(_('Graphs'), 'httpmon.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid')),
	SPACE.SPACE,
	new CLink(_('Screens'), 'screens.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid')),
	SPACE.SPACE,
	new CLink(_('Triggers status'),
		'tr_status.php?show_triggers=2&ack_status=1&show_events=1&show_events=0&show_details=1'.
		'&txt_select=&show_maintenance=0&hostid='.$this->data['overview']['host']['hostid'].url_param('groupid')),
	SPACE.SPACE,
	new CLink(_('Events'), 'events.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'))
);

$overviewFormList->addRow(_('Latest data'), $latestArray);

// configuration
if ($this->data['rwHost']) {
	$hostLink = new CLink(_('Host'),
		'hosts.php?form=update&hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
	$applicationsLink = new CLink(_('Application'),
		'applications.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
	$itemsLink = new CLink(_('Items'), 'items.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
	$triggersLink = new CLink(_('Triggers'), 'triggers.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
	$graphsLink = new CLink(_('Graphs'), 'graphs.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
	$discoveryLink = new CLink(_('Discovery'),
		'host_discovery.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
	$webLink = new CLink(_('Web'), 'httpconf.php?hostid='.$this->data['overview']['host']['hostid'].url_param('groupid'));
}
else {
	$hostLink = _('Host');
	$applicationsLink = _('Application');
	$itemsLink = _('Items');
	$triggersLink = _('Triggers');
	$graphsLink = _('Graphs');
	$discoveryLink = _('Discovery');
	$webLink = _('Web');
}

$configurationArray = array(
	$hostLink,
	SPACE.SPACE,
	$applicationsLink,
	SPACE,
	'('.$this->data['overview']['host']['applications'].')',
	SPACE.SPACE,
	$itemsLink,
	SPACE,
	'('.$this->data['overview']['host']['items'].')',
	SPACE.SPACE,
	$triggersLink,
	SPACE,
	'('.$this->data['overview']['host']['triggers'].')',
	SPACE.SPACE,
	$graphsLink,
	SPACE,
	'('.$this->data['overview']['host']['graphs'].')',
	SPACE.SPACE,
	$discoveryLink,
	SPACE,
	'('.$this->data['overview']['host']['discoveries'].')',
	SPACE.SPACE,
	$webLink,
	SPACE,
	'('.$this->data['overview']['host']['httpTests'].')'
);

$overviewFormList->addRow(_('Configuration'), $configurationArray);

$hostInventoriesTab = new CTabView(array('remember' => true));
$hostInventoriesTab->setSelected(0);

$hostInventoriesTab->addTab('overviewTab', _('Overview'), $overviewFormList);

/*
 * Details tab
 */
$detailsFormList = new CFormList('hostinventoriesDetailsFormList');

$inventoryValues = false;
foreach ($this->data['tableValues'] as $key => $value) {
	if (!zbx_empty($value)) {
		$detailsFormList->addRow($this->data['tableTitles'][$key]['title'], new CSpan(zbx_str2links($value), 'pre'));
		$inventoryValues = true;
	}
}
if (!$inventoryValues) {
	$hostInventoriesTab->setDisabled(1);
}

$hostInventoriesTab->addTab('detailsTab', _('Details'), $detailsFormList);

// append tabs and form
$hostInventoriesForm->addItem($hostInventoriesTab);
$hostInventoriesForm->addItem(makeFormFooter(
	null,
	new CButtonCancel(url_param('groupid'))
));
$hostInventoryWidget->addItem($hostInventoriesForm);

return $hostInventoryWidget;
