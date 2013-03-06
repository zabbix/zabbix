<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

$discoveryRule = $data['discovery_rule'];
$hostPrototype = $data['host_prototype'];

require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';

$widget = new CWidget();
$widget->addPageHeader(_('CONFIGURATION OF HOST PROTOTYPES'));
$widget->addItem(get_header_host_table('hosts', $this->data['parent_hostid'], $discoveryRule['itemid']));

$divTabs = new CTabView(array('remember' => 1));
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$frmHost = new CForm();
$frmHost->setName('hostPrototypeForm.');
$frmHost->addVar('form', get_request('form', 1));
$frmHost->addVar('parent_hostid', $data['parent_hostid']);
$frmHost->addVar('parent_discoveryid', $discoveryRule['itemid']);

$hostList = new CFormList('hostlist');

if ($hostPrototype['templateid'] && $data['parents']) {
	$parents = array();
	foreach (array_reverse($data['parents']) as $parent) {
		$parents[] = new CLink(
			$parent['host']['name'],
			'?form=update&hostid='.$parent['hostid'].'&parent_hostid='.$parent['host']['hostid'].'&parent_discoveryid='.$parent['discoveryRule']['itemid'],
			'highlight underline weight_normal'
		);
		$parents[] = SPACE.RARR.SPACE;
	}
	array_pop($parents);
	$hostList->addRow(_('Parent discovery rules'), $parents);
}

if ($hostPrototype['hostid']) {
	$frmHost->addVar('hostid', $hostPrototype['hostid']);
}

$hostTB = new CTextBox('host', $hostPrototype['host'], ZBX_TEXTBOX_STANDARD_SIZE, (bool) $hostPrototype['templateid']);
$hostTB->setAttribute('maxlength', 64);
$hostTB->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$visiblenameTB = new CTextBox('name', $hostPrototype['name'], ZBX_TEXTBOX_STANDARD_SIZE, (bool) $hostPrototype['templateid']);
$visiblenameTB->setAttribute('maxlength', 64);
$hostList->addRow(_('Visible name'), $visiblenameTB);

$cmbStatus = new CComboBox('status', $hostPrototype['status']);
$cmbStatus->addItem(HOST_STATUS_MONITORED, _('Monitored'));
$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED, _('Not monitored'));

$hostList->addRow(_('Status'), $cmbStatus);

$divTabs->addTab('hostTab', _('Host'), $hostList);

// templates
$tmplList = new CFormList('tmpllist');

if ($hostPrototype['templates']) {
	foreach ($hostPrototype['templates'] as $templateId => $name) {
		$frmHost->addVar('templates['.$templateId.']', $name);
		$row = array($name);
		$tmplList->addRow(
			$name,
			(!$hostPrototype['templateid']) ? new CSubmit('unlink['.$templateId.']', _('Unlink'), null, 'link_menu') : ''
		);
	}
}
// for inherited prototypes with no templates display a text message
elseif ($hostPrototype['templateid']) {
	$tmplList->addRow(_('No templates linked.'), ' ');
}

if (!$hostPrototype['templateid']) {
	$tmplAdd = new CButton('add', _('Add'),
		'return PopUp("popup.php?srctbl=templates&srcfld1=hostid&srcfld2=host'.
			'&dstfrm='.$frmHost->getName().'&dstfld1=new_template&templated_hosts=1'.
			url_param($hostPrototype['templates'], false, 'existed_templates').'", 450, 450)',
		'link_menu'
	);
	$tmplList->addRow($tmplAdd, SPACE);
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

$frmHost->addItem($divTabs);

/*
 * footer
 */
$main = array(new CSubmit('save', _('Save')));
$others = array();
if ($hostPrototype['hostid'] && $_REQUEST['form'] != 'full_clone') {
	$btnDelete = new CButtonDelete(_('Delete selected host prototype?'), url_param('form').url_param('hostid').url_param('parent_hostid').url_param('parent_discoveryid'));
	$btnDelete->setEnabled(!$hostPrototype['templateid']);

	$others[] = new CSubmit('clone', _('Clone'));
	$others[] = new CSubmit('full_clone', _('Full clone'));
	$others[] = $btnDelete;
}
$others[] = new CButtonCancel(url_param('parent_hostid').url_param('parent_discoveryid'));

$frmHost->addItem(makeFormFooter($main, $others));

$widget->addItem($frmHost);

return $widget;
