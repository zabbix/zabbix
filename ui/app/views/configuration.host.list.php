<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('class.tagfilteritem.js');
$this->includeJsFile('configuration.host.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('hosts');
}

$widget = (new CWidget())
	->setTitle(_('Hosts'))
	->setControls((new CTag('nav', true, (new CList())
			->addItem(
				(new CSimpleButton(_('Create host')))
					->onClick('view.createHost()')
			)
			->addItem(
				(new CButton('form', _('Import')))
					->onClick(
						'return PopUp("popup.import", {rules_preset: "host"}, {dialogue_class: "modal-popup-generic"});'
					)
					->removeId()
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

$action_url = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

$filter = (new CFilter())
	->setResetUrl($action_url)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Host groups'), 'filter_groups__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_groups[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groups_',
								'real_hosts' => 1,
								'editable' => 1,
								'enrich_parent_groups' => 1
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Templates'), 'filter_templates__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_templates[]',
						'object_name' => 'templates',
						'data' => $data['filter']['templates'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'templates',
								'srcfld1' => 'hostid',
								'srcfld2' => 'host',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_templates_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Name'), 'filter_host'),
				new CFormField(
					(new CTextBox('filter_host', $data['filter']['host']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('DNS'), 'filter_dns'),
				new CFormField(
					(new CTextBox('filter_dns', $data['filter']['dns']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('IP'), 'filter_ip'),
				new CFormField(
					(new CTextBox('filter_ip', $data['filter']['ip']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Port'), 'filter_port'),
				new CFormField(
					(new CTextBox('filter_port', $data['filter']['port']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Monitored by'), 'filter_monitored_by'),
				new CFormField(
					(new CRadioButtonList('filter_monitored_by', (int) $data['filter']['monitored_by']))
						->addValue(_('Any'), ZBX_MONITORED_BY_ANY)
						->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
						->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
						->setModern(true)
				)
			])
			->addItem([
				new CLabel(_('Proxy'), 'filter_proxyids__ms'),
				new CFormField(
					(new CMultiSelect([
						'name' => 'filter_proxyids[]',
						'object_name' => 'proxies',
						'data' => $data['proxies_ms'],
						'disabled' => ($data['filter']['monitored_by'] != ZBX_MONITORED_BY_PROXY),
						'popup' => [
							'parameters' => [
								'srctbl' => 'proxies',
								'srcfld1' => 'proxyid',
								'srcfld2' => 'host',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_proxyids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Tags')),
				new CFormField(
					CTagFilterFieldHelper::getTagFilterField([
						'evaltype' => $data['filter']['evaltype'],
						'tags' => $data['filter']['tags']
					])
				)
			])
	]);

$widget->addItem($filter);

// table hosts
$form = (new CForm())->setName('hosts');
$header_checkbox = (new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'hostids');");
$show_monitored_by = ($data['filter']['monitored_by'] == ZBX_MONITORED_BY_PROXY
		|| $data['filter']['monitored_by'] == ZBX_MONITORED_BY_ANY);
$header_sortable_name = make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'],
	$action_url->getUrl()
);
$header_sortable_status = make_sorting_header(_('Status'), 'status', $data['sortField'], $data['sortOrder'],
	$action_url->getUrl()
);

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader($header_checkbox))->addClass(ZBX_STYLE_CELL_WIDTH),
		$header_sortable_name,
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web'),
		_('Interface'),
		$show_monitored_by ? _('Proxy') : null,
		_('Templates'),
		$header_sortable_status,
		_('Availability'),
		_('Agent encryption'),
		_('Info'),
		_('Tags')
	]);

$current_time = time();

foreach ($data['hosts'] as $host) {
	// Select an interface from the list with highest priority.
	$interface = null;

	if ($host['interfaces']) {
		foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
			$host_interfaces = array_filter($host['interfaces'], function(array $host_interface) use ($interface_type) {
				return ($host_interface['type'] == $interface_type);
			});

			if ($host_interfaces) {
				$interface = reset($host_interfaces);

				break;
			}
		}
	}

	$description = [];

	if ($host['discoveryRule']) {
		$description[] = (new CLink(CHtml::encode($host['discoveryRule']['name']),
			(new CUrl('host_prototypes.php'))
				->setArgument('parent_discoveryid', $host['discoveryRule']['itemid'])
				->setArgument('context', 'host')
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}
	elseif ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		// Discovered host which does not contain info about parent discovery rule is inaccessible for current user.
		$description[] = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	$description[] = (new CLink(CHtml::encode($host['name']),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'host.edit')
			->setArgument('hostid', $host['hostid'])
	))
		->onClick('view.editHost(event, '.json_encode($host['hostid']).')');

	$maintenance_icon = false;
	$status_toggle_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup.massupdate.host')
		->setArgument('hostids', [$host['hostid']])
		->setArgument('visible[status]', 1)
		->setArgument('update', 1)
		->setArgument('backurl',
			(new CUrl('zabbix.php', false))
				->setArgument('action', 'host.list')
				->setArgument('page', CPagerHelper::loadPage('host.list', null))
				->getUrl()
		);

	if ($host['status'] == HOST_STATUS_MONITORED) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			if (array_key_exists($host['maintenanceid'], $data['maintenances'])) {
				$maintenance = $data['maintenances'][$host['maintenanceid']];
				$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], $maintenance['name'],
					$maintenance['description']
				);
			}
			else {
				$maintenance_icon = makeMaintenanceIcon($host['maintenance_type'], _('Inaccessible maintenance'), '');
			}
		}

		$status_toggle_url->setArgument('status', HOST_STATUS_NOT_MONITORED);
		$toggle_status_link = (new CLink(_('Enabled'), $status_toggle_url->getUrl()))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addConfirmation(_('Disable host?'))
			->addSID();
	}
	else {
		$status_toggle_url->setArgument('status', HOST_STATUS_MONITORED);
		$toggle_status_link = (new CLink(_('Disabled'), $status_toggle_url->getUrl()))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addConfirmation(_('Enable host?'))
			->addSID();
	}

	if ($maintenance_icon) {
		$description[] = $maintenance_icon;
	}

	order_result($host['parentTemplates'], 'name');

	$hostTemplates = [];
	$i = 0;

	foreach ($host['parentTemplates'] as $template) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$hostTemplates[] = ' &hellip;';

			break;
		}

		if (array_key_exists($template['templateid'], $data['writable_templates'])
				&& $data['allowed_ui_conf_templates']) {
			$caption = [
				(new CLink(CHtml::encode($template['name']),
					(new CUrl('templates.php'))
						->setArgument('form', 'update')
						->setArgument('templateid', $template['templateid'])
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY)
			];
		}
		else {
			$caption = [
				(new CSpan(CHtml::encode($template['name'])))->addClass(ZBX_STYLE_GREY)
			];
		}

		$parent_templates = $data['templates'][$template['templateid']]['parentTemplates'];

		if ($parent_templates) {
			order_result($parent_templates, 'name');

			$caption[] = ' (';

			foreach ($parent_templates as $parent_template) {
				if (array_key_exists($parent_template['templateid'], $data['writable_templates'])
						&& $data['allowed_ui_conf_templates']) {
					$caption[] = (new CLink(CHtml::encode($parent_template['name']),
						(new CUrl('templates.php'))
							->setArgument('form', 'update')
							->setArgument('templateid', $parent_template['templateid'])
					))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_GREY);
				}
				else {
					$caption[] = (new CSpan(CHtml::encode($parent_template['name'])))->addClass(ZBX_STYLE_GREY);
				}

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

	$info_icons = [];

	if ($host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $host['hostDiscovery']['ts_delete'] != 0) {
		$info_icons[] = getHostLifetimeIndicator($current_time, $host['hostDiscovery']['ts_delete']);
	}

	if ($host['tls_connect'] == HOST_ENCRYPTION_NONE
			&& ($host['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE
			&& ($host['tls_accept'] & HOST_ENCRYPTION_PSK) != HOST_ENCRYPTION_PSK
			&& ($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != HOST_ENCRYPTION_CERTIFICATE) {
		$encryption = (new CDiv((new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN)))
			->addClass(ZBX_STYLE_STATUS_CONTAINER);
	}
	else {
		// Incoming encryption.
		if ($host['tls_connect'] == HOST_ENCRYPTION_NONE) {
			$in_encryption = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		elseif ($host['tls_connect'] == HOST_ENCRYPTION_PSK) {
			$in_encryption = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$in_encryption = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}

		// Outgoing encryption.
		$out_encryption = [];

		if (($host['tls_accept'] & HOST_ENCRYPTION_NONE) == HOST_ENCRYPTION_NONE) {
			$out_encryption[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('None')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		if (($host['tls_accept'] & HOST_ENCRYPTION_PSK) == HOST_ENCRYPTION_PSK) {
			$out_encryption[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('PSK')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		if (($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == HOST_ENCRYPTION_CERTIFICATE) {
			$out_encryption[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREEN);
		}
		else {
			$out_encryption[] = (new CSpan(_('CERT')))->addClass(ZBX_STYLE_STATUS_GREY);
		}

		$encryption = (new CDiv([new CSpan($in_encryption), ' ', new CSpan($out_encryption)]))
			->addClass(ZBX_STYLE_STATUS_CONTAINER)
			->addClass(ZBX_STYLE_NOWRAP);
	}

	$monitored_by = null;

	if ($show_monitored_by) {
		$monitored_by = ($host['proxy_hostid'] != 0)
			? $data['proxies'][$host['proxy_hostid']]['host']
			: '';
	}

	$table->addRow([
		new CCheckBox('hostids['.$host['hostid'].']', $host['hostid']),
		(new CCol($description))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(_('Items'),
				(new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['items'])
		],
		[
			new CLink(_('Triggers'),
				(new CUrl('triggers.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['triggers'])
		],
		[
			new CLink(_('Graphs'),
				(new CUrl('graphs.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['graphs'])
		],
		[
			new CLink(_('Discovery'),
				(new CUrl('host_discovery.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['discoveries'])
		],
		[
			new CLink(_('Web'),
				(new CUrl('httpconf.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$host['hostid']])
					->setArgument('context', 'host')
			),
			CViewHelper::showNum($host['httpTests'])
		],
		getHostInterface($interface),
		$monitored_by,
		$hostTemplates,
		$toggle_status_link,
		getHostAvailabilityTable($host['interfaces']),
		$encryption,
		makeInformationList($info_icons),
		$data['tags'][$host['hostid']]
	]);
}

$status_toggle_url =  (new CUrl('zabbix.php'))
	->setArgument('action', 'popup.massupdate.host')
	->setArgument('visible[status]', 1)
	->setArgument('update', 1)
	->setArgument('backurl',
		(new CUrl('zabbix.php', false))
			->setArgument('action', 'host.list')
			->setArgument('page', CPagerHelper::loadPage('host.list', null))
			->getUrl()
	);

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'hostids', [
		'enable-hosts' => [
			'name' => _('Enable'),
			'confirm' => _('Enable selected hosts?'),
			'redirect' => $status_toggle_url
				->setArgument('status', HOST_STATUS_MONITORED)
				->getUrl()
		],
		'disable-hosts' => [
			'name' => _('Disable'),
			'confirm' => _('Disable selected hosts?'),
			'redirect' => $status_toggle_url
				->setArgument('status', HOST_STATUS_NOT_MONITORED)
				->getUrl()
		],
		'host.export' => [
			'content' => new CButtonExport('export.hosts', $action_url
				->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
				->getUrl()
			)
		],
		'popup.massupdate.host' => [
			'content' => (new CButton('', _('Mass update')))
				->onClick(
					"openMassupdatePopup('popup.massupdate.host', {}, {
						dialogue_class: 'modal-popup-static',
						trigger_element: this
					});"
				)
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
		],
		'host.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected hosts?'))
				->onClick('view.massDeleteHosts(this);')
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('no-chkbxrange')
				->removeAttribute('id')
		]
	], 'hosts')
]);

$widget
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'applied_filter_groupids' => array_keys($data['filter']['groups'])
	]).');
'))
	->setOnDocumentReady()
	->show();
