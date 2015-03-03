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


$hostWidget = new CWidget(null, 'host-list');
$hostWidget->setTitle(_('Hosts'));

$frmForm = new CForm();
$frmForm->cleanItems();

$controls = new CList();

$controls->addItem(new CSubmit('form', _('Create host')));
$controls->addItem(new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=host")'));
$controls->addItem(array(_('Group').SPACE, $data['pageFilter']->getGroupsCB()));

$frmForm->addItem($controls);
$hostWidget->setControls($frmForm);

// Filter
$filter = new CFilter('web.hosts.filter.state');
$filterColumn1 = new CFormList();
$filterColumn1->addRow(_('Name like'), new CTextBox('filter_host', $data['filter']['host'], 20));
$filter->addColumn($filterColumn1);
$filterColumn2 = new CFormList();
$filterColumn2->addRow(_('DNS like'), new CTextBox('filter_dns', $data['filter']['dns'], 20));
$filter->addColumn($filterColumn2);
$filterColumn3 = new CFormList();
$filterColumn3->addRow(_('IP like'), new CTextBox('filter_ip', $data['filter']['ip'], 20));
$filter->addColumn($filterColumn3);
$filterColumn4 = new CFormList();
$filterColumn4->addRow(_('Port like'), new CTextBox('filter_port', $data['filter']['port'], 20));
$filter->addColumn($filterColumn4);

$hostWidget->addItem($filter);

// table hosts
$form = new CForm();
$form->setName('hosts');

$table = new CTableInfo(_('No hosts found.'));
$table->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$form->getName()."', 'all_hosts', 'hosts');"),
	make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder']),
	_('Applications'),
	_('Items'),
	_('Triggers'),
	_('Graphs'),
	_('Discovery'),
	_('Web'),
	_('Interface'),
	_('Templates'),
	make_sorting_header(_('Status'), 'status', $data['sortField'], $data['sortOrder']),
	_('Availability')
));

$currentTime = time();

foreach ($data['hosts'] as $host) {
	$interface = reset($host['interfaces']);

	$applications = array(new CLink(_('Applications'),
		'applications.php?groupid='.$data['groupId'].'&hostid='.$host['hostid']),
		CViewHelper::showNum($host['applications']));
	$items = array(new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$host['hostid']),
		CViewHelper::showNum($host['items']));
	$triggers = array(new CLink(_('Triggers'),
		'triggers.php?groupid='.$data['groupId'].'&hostid='.$host['hostid']),
		CViewHelper::showNum($host['triggers']));
	$graphs = array(new CLink(_('Graphs'), 'graphs.php?groupid='.$data['groupId'].'&hostid='.$host['hostid']),
		CViewHelper::showNum($host['graphs']));
	$discoveries = array(new CLink(_('Discovery'), 'host_discovery.php?&hostid='.$host['hostid']),
		CViewHelper::showNum($host['discoveries']));
	$httpTests = array(new CLink(_('Web'), 'httpconf.php?&hostid='.$host['hostid']),
		CViewHelper::showNum($host['httpTests']));

	$description = array();

	if (isset($data['proxies'][$host['proxy_hostid']])) {
		$description[] = $data['proxies'][$host['proxy_hostid']]['host'].NAME_DELIMITER;
	}
	if ($host['discoveryRule']) {
		$description[] = new CLink($host['discoveryRule']['name'],
			'host_prototypes.php?parent_discoveryid='.$host['discoveryRule']['itemid'], ZBX_STYLE_ORANGE_DOTTED
		);
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(CHtml::encode($host['name']),
		'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid')
	);

	$hostInterface = ($interface['useip'] == INTERFACE_USE_IP) ? $interface['ip'] : $interface['dns'];
	$hostInterface .= empty($interface['port']) ? '' : NAME_DELIMITER.$interface['port'];

	$statusScript = null;

	if ($host['status'] == HOST_STATUS_MONITORED) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$statusCaption = _('In maintenance');
			$statusClass = ZBX_STYLE_ORANGE_DOTTED;
		}
		else {
			$statusCaption = _('Enabled');
			$statusClass = ZBX_STYLE_GREEN_DOTTED;
		}

		$statusScript = 'return Confirm('.CJs::encodeJson(_('Disable host?')).');';
		$statusUrl = 'hosts.php?hosts[]='.$host['hostid'].'&action=host.massdisable'.url_param('groupid');
	}
	else {
		$statusCaption = _('Disabled');
		$statusUrl = 'hosts.php?hosts[]='.$host['hostid'].'&action=host.massenable'.url_param('groupid');
		$statusScript = 'return Confirm('.CJs::encodeJson(_('Enable host?')).');';
		$statusClass = ZBX_STYLE_RED_DOTTED;
	}

	$status = new CLink($statusCaption, $statusUrl, $statusClass, $statusScript);

	if (empty($host['parentTemplates'])) {
		$hostTemplates = '-';
	}
	else {
		order_result($host['parentTemplates'], 'name');

		$hostTemplates = array();
		$i = 0;

		foreach ($host['parentTemplates'] as $template) {
			$i++;

			if ($i > $data['config']['max_in_table']) {
				$hostTemplates[] = ' &hellip;';

				break;
			}

			$caption = array(new CLink(
				CHtml::encode($template['name']),
				'templates.php?form=update&templateid='.$template['templateid'],
				ZBX_STYLE_GREY_DOTTED
			));

			$parentTemplates = $data['templates'][$template['templateid']]['parentTemplates'];
			if ($parentTemplates) {
				order_result($parentTemplates, 'name');

				$caption[] = ' (';
				foreach ($parentTemplates as $parentTemplate) {
					$caption[] = new CLink(CHtml::encode($parentTemplate['name']),
						'templates.php?form=update&templateid='.$parentTemplate['templateid'], ZBX_STYLE_GREY_DOTTED
					);
					$caption[] = ', ';
				}
				array_pop($caption);

				$caption[] = ')';
			}

			if ($hostTemplates) {
				$hostTemplates[] = ', ';
			}

			$hostTemplates[] = $caption;
		}
	}

	$table->addRow(array(
		new CCheckBox('hosts['.$host['hostid'].']', null, null, $host['hostid']),
		$description,
		$applications,
		$items,
		$triggers,
		$graphs,
		$discoveries,
		$httpTests,
		$hostInterface,
		new CCol($hostTemplates, 'wraptext'),
		$status,
		getAvailabilityTable($host, $currentTime)
	));
}

$form->addItem(array(
	$data['paging'],
	$table,
	$data['paging'],
	new CActionButtonList('action', 'hosts',
		array(
			'host.massenable' => array('name' => _('Enable'), 'confirm' => _('Enable selected hosts?')),
			'host.massdisable' => array('name' => _('Disable'), 'confirm' =>  _('Disable selected hosts?')),
			'host.export' => array('name' => _('Export')),
			'host.massupdateform' => array('name' => _('Mass update')),
			'host.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected hosts?'))
		)
	)
));

$hostWidget->addItem($form);

return $hostWidget;
