<?php

/* @var $hostsWidget CWidget */
$hostsWidget = $this->data['hostsWidget'];

/* @var $pageFilter CPageFilter */
$pageFilter = $this->data['pageFilter'];

$paging = $this->data['paging'];
$filter = $this->data['filter'];
$hosts = $this->data['hosts'];
$sortField = $this->data['sortField'];
$sortOrder = $this->data['sortOrder'];
$groupId = $this->data['groupId'];

$config = $this->data['config'];


$frmForm = new CForm();
$frmForm->cleanItems();
$frmForm->addItem(new CDiv(array(
		new CSubmit('start_add', _('Create host')),
		new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=host")')
	)));
$frmForm->addItem(new CVar('groupid', getRequest('groupid'), 'filter_groupid_id'));

$hostsWidget->addPageHeader(_('CONFIGURATION OF HOSTS'), $frmForm);

$frmGroup = new CForm('get');
$frmGroup->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB()));

$hostsWidget->addHeader(_('Hosts'), $frmGroup);
$hostsWidget->addHeaderRowNumber();
$hostsWidget->setRootClass('host-list');

// filter
$filterTable = new CTable('', 'filter filter-center');
$filterTable->addRow(array(
		array(array(bold(_('Name')), SPACE._('like').NAME_DELIMITER), new CTextBox('filter_host', $filter['host'], 20)),
		array(array(bold(_('DNS')), SPACE._('like').NAME_DELIMITER), new CTextBox('filter_dns', $filter['dns'], 20)),
		array(array(bold(_('IP')), SPACE._('like').NAME_DELIMITER), new CTextBox('filter_ip', $filter['ip'], 20)),
		array(bold(_('Port').NAME_DELIMITER), new CTextBox('filter_port', $filter['port'], 20))
	));

$filterButton = new CSubmit('filter_set', _('Filter'), 'chkbxRange.clearSelectedOnFilterChange();');
$filterButton->useJQueryStyle('main');

$resetButton = new CSubmit('filter_rst', _('Reset'), 'chkbxRange.clearSelectedOnFilterChange();');
$resetButton->useJQueryStyle();

$divButtons = new CDiv(array($filterButton, SPACE, $resetButton));
$divButtons->setAttribute('style', 'padding: 4px 0;');

$filterTable->addRow(new CCol($divButtons, 'controls', 4));

$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addItem($filterTable);

$hostsWidget->addFlicker($filterForm, CProfile::get('web.hosts.filter.state', 0));

// table hosts
$form = new CForm();
$form->setName('hosts');

$table = new CTableInfo(_('No hosts found.'));
$table->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$form->getName()."', 'all_hosts', 'hosts');"),
	make_sorting_header(_('Name'), 'name', $sortField, $sortOrder),
	_('Applications'),
	_('Items'),
	_('Triggers'),
	_('Graphs'),
	_('Discovery'),
	_('Web'),
	_('Interface'),
	_('Templates'),
	make_sorting_header(_('Status'), 'status', $sortField, $sortOrder),
	_('Availability')
));

foreach ($hosts as $host) {
	$interface = reset($host['interfaces']);

	$applications = array(new CLink(_('Applications'), 'applications.php?groupid='.$groupId.'&hostid='.$host['hostid']),
						' ('.$host['applications'].')');
	$items = array(new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$host['hostid']),
						' ('.$host['items'].')');
	$triggers = array(new CLink(_('Triggers'), 'triggers.php?groupid='.$groupId.'&hostid='.$host['hostid']),
						' ('.$host['triggers'].')');
	$graphs = array(new CLink(_('Graphs'), 'graphs.php?groupid='.$groupId.'&hostid='.$host['hostid']),
						' ('.$host['graphs'].')');
	$discoveries = array(new CLink(_('Discovery'), 'host_discovery.php?&hostid='.$host['hostid']),
						' ('.$host['discoveries'].')');
	$httpTests = array(new CLink(_('Web'), 'httpconf.php?&hostid='.$host['hostid']),
						' ('.$host['httpTests'].')');

	$description = array();

	if (isset($proxies[$host['proxy_hostid']])) {
		$description[] = $proxies[$host['proxy_hostid']]['host'].NAME_DELIMITER;
	}
	if ($host['discoveryRule']) {
		$description[] = new CLink($host['discoveryRule']['name'], 'host_prototypes.php?parent_discoveryid='.$host['discoveryRule']['itemid'], 'parent-discovery');
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(CHtml::encode($host['name']), 'hosts.php?start_update=1&hostid='.$host['hostid'].url_param('groupid'));

	$hostInterface = ($interface['useip'] == INTERFACE_USE_IP) ? $interface['ip'] : $interface['dns'];
	$hostInterface .= empty($interface['port']) ? '' : NAME_DELIMITER.$interface['port'];

	$statusScript = null;

	if ($host['status'] == HOST_STATUS_MONITORED) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$statusCaption = _('In maintenance');
			$statusClass = 'orange';
		}
		else {
			$statusCaption = _('Enabled');
			$statusClass = 'enabled';
		}

		$statusScript = 'return Confirm('.CJs::encodeJson(_('Disable host?')).');';
		$statusUrl = 'hosts.php?hosts[]='.$host['hostid'].'&action=host.massdisable'.url_param('groupid');
	}
	else {
		$statusCaption = _('Disabled');
		$statusUrl = 'hosts.php?hosts[]='.$host['hostid'].'&action=host.massenable'.url_param('groupid');
		$statusScript = 'return Confirm('.CJs::encodeJson(_('Enable host?')).');';
		$statusClass = 'disabled';
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

			if ($i > $config['max_in_table']) {
				$hostTemplates[] = ' &hellip;';

				break;
			}

			$caption = array(new CLink(
				CHtml::encode($template['name']),
				'templates.php?form=update&templateid='.$template['templateid'],
				'unknown'
			));

			if (!empty($templates[$template['templateid']]['parentTemplates'])) {
				order_result($templates[$template['templateid']]['parentTemplates'], 'name');

				$caption[] = ' (';
				foreach ($templates[$template['templateid']]['parentTemplates'] as $tpl) {
					$caption[] = new CLink(CHtml::encode($tpl['name']),'templates.php?form=update&templateid='.$tpl['templateid'], 'unknown');
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
		getAvailabilityTable($host)
	));
}

$goBox = new CComboBox('action');

$goBox->addItem('host.export', _('Export selected'));

$goBox->addItem('host.massupdateform', _('Mass update'));
$goOption = new CComboItem('host.massenable', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected hosts?'));
$goBox->addItem($goOption);

$goOption = new CComboItem('host.massdisable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected hosts?'));
$goBox->addItem($goOption);

$goOption = new CComboItem('host.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected hosts?'));
$goBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

$form->addItem(array($paging, $table, $paging, get_table_header(array($goBox, $goButton))));

return $form;
