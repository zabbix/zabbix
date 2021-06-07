<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */

require_once dirname(__FILE__).'/js/configuration.host.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Hosts'))
	->setControls((new CTag('nav', true, (new CList())
			->addItem(new CRedirectButton(_('Create host'), (new CUrl('hosts.php'))
				->setArgument('form', 'create')
				->setArgument('groupids', array_keys($data['filter']['groups']))
				->getUrl()
			))
			->addItem(
				(new CButton('form', _('Import')))
					->onClick('return PopUp("popup.import", {rules_preset: "host"}, null, this);')
					->removeId()
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

$filter_tags = $data['filter']['tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$filter_tags_table = CTagFilterFieldHelper::getTagFilterField([
	'evaltype' => $data['filter']['evaltype'],
	'tags' => $filter_tags
]);

// filter
$filter = new CFilter(new CUrl('hosts.php'));
$filter
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(
				(new CLabel(_('Host groups'), 'filter_groups__ms')),
				(new CMultiSelect([
					'name' => 'filter_groups[]',
					'object_name' => 'hostGroup',
					'data' => $data['filter']['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => $filter->getName(),
							'dstfld1' => 'filter_groups_',
							'real_hosts' => 1,
							'editable' => 1,
							'enrich_parent_groups' => 1
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(
				(new CLabel(_('Templates'), 'filter_templates__ms')),
				(new CMultiSelect([
					'name' => 'filter_templates[]',
					'object_name' => 'templates',
					'data' => $data['filter']['templates'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'templates',
							'srcfld1' => 'hostid',
							'srcfld2' => 'host',
							'dstfrm' => $filter->getName(),
							'dstfld1' => 'filter_templates_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('Name'),
				(new CTextBox('filter_host', $data['filter']['host']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('DNS'),
				(new CTextBox('filter_dns', $data['filter']['dns']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('IP'),
				(new CTextBox('filter_ip', $data['filter']['ip']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('Port'),
				(new CTextBox('filter_port', $data['filter']['port']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			),
		(new CFormList())
			->addRow(_('Monitored by'),
				(new CRadioButtonList('filter_monitored_by', (int) $data['filter']['monitored_by']))
					->addValue(_('Any'), ZBX_MONITORED_BY_ANY)
					->addValue(_('Server'), ZBX_MONITORED_BY_SERVER)
					->addValue(_('Proxy'), ZBX_MONITORED_BY_PROXY)
					->setModern(true)
			)
			->addRow(
				(new CLabel(_('Proxy'), 'filter_proxyids__ms')),
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
			->addRow(_('Tags'), $filter_tags_table)
	]);

$widget->addItem($filter);

// table hosts
$form = (new CForm())->setName('hosts');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'hosts');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'], 'hosts.php'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web'),
		_('Interface'),
		($data['filter']['monitored_by'] == ZBX_MONITORED_BY_PROXY
				|| $data['filter']['monitored_by'] == ZBX_MONITORED_BY_ANY) ? _('Proxy') : null,
		_('Templates'),
		make_sorting_header(_('Status'), 'status', $data['sortField'], $data['sortOrder'], 'hosts.php'),
		_('Availability'),
		_('Agent encryption'),
		_('Info'),
		_('Tags')
	]);

$current_time = time();

$interface_types = [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI];

foreach ($data['hosts'] as $host) {
	// Select an interface from the list with highest priority.
	$interface = null;
	if ($host['interfaces']) {
		foreach ($interface_types as $interface_type) {
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

	$description[] = new CLink(CHtml::encode($host['name']), (new CUrl('hosts.php'))
				->setArgument('form', 'update')
				->setArgument('hostid', $host['hostid'])
	);

	$maintenance_icon = false;
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

		$statusCaption = _('Enabled');
		$statusClass = ZBX_STYLE_GREEN;
		$confirm_message = _('Disable host?');
		$status_url = (new CUrl('hosts.php'))
			->setArgument('action', 'host.massdisable')
			->setArgument('hosts', [$host['hostid']]);
	}
	else {
		$statusCaption = _('Disabled');
		$status_url = (new CUrl('hosts.php'))
			->setArgument('action', 'host.massenable')
			->setArgument('hosts', [$host['hostid']]);
		$confirm_message = _('Enable host?');
		$statusClass = ZBX_STYLE_RED;
	}

	$status = (new CLink($statusCaption, $status_url->getUrl()))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass($statusClass)
		->addConfirmation($confirm_message)
		->addSID();

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

	$table->addRow([
		new CCheckBox('hosts['.$host['hostid'].']', $host['hostid']),
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
		($data['filter']['monitored_by'] == ZBX_MONITORED_BY_PROXY
				|| $data['filter']['monitored_by'] == ZBX_MONITORED_BY_ANY)
			? ($host['proxy_hostid'] != 0)
				? $data['proxies'][$host['proxy_hostid']]['host']
				: ''
			: null,
		$hostTemplates,
		$status,
		getHostAvailabilityTable($host['interfaces']),
		$encryption,
		makeInformationList($info_icons),
		$data['tags'][$host['hostid']]
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'hosts',
		[
			'host.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected hosts?')],
			'host.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected hosts?')],
			'host.export' => [
				'content' => new CButtonExport('export.hosts',
					(new CUrl('hosts.php'))
						->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
						->getUrl()
				)
			],
			'popup.massupdate.host' => [
				'content' => (new CButton('', _('Mass update')))
					->onClick("return openMassupdatePopup(this, 'popup.massupdate.host');")
					->addClass(ZBX_STYLE_BTN_ALT)
					->removeAttribute('id')
			],
			'host.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected hosts?')]
		]
	)
]);

$widget->addItem($form);

$widget->show();
