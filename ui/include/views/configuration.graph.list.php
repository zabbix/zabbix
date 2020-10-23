<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

if (!empty($this->data['parent_discoveryid'])) {
	$widget = (new CWidget())
		->setTitle(_('Graph prototypes'))
		->setControls(
			(new CTag('nav', true,
				(new CList())->addItem(new CRedirectButton(_('Create graph prototype'),
					(new CUrl('graphs.php'))
						->setArgument('form', 'create')
						->setArgument('parent_discoveryid', $data['parent_discoveryid'])
						->getUrl()
				))
			))->setAttribute('aria-label', _('Content controls'))
		)
		->addItem(get_header_host_table('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
}
else {
	$widget = (new CWidget())
		->setTitle(_('Graphs'))
		->setControls(
			(new CTag('nav', true, ($data['hostid'] == 0)
				? (new CButton('form', _('Create graph (select host first)')))->setEnabled(false)
				: new CRedirectButton(_('Create graph'), (new CUrl('graphs.php'))
					->setArgument('hostid', $data['hostid'])
					->setArgument('form', 'create')
					->getUrl()
				)
			))
				->setAttribute('aria-label', _('Content controls'))
		);

	if (!empty($this->data['hostid'])) {
		$widget->addItem(get_header_host_table('graphs', $this->data['hostid']));
	}

	// Add filter tab.
	$widget->addItem(
		(new CFilter(new CUrl('graphs.php')))
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
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_groups_',
									'with_hosts_and_templates' => 1,
									'editable' => 1,
									'enrich_parent_groups' => true
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(
						(new CLabel(_('Hosts'), 'filter_hosts__ms')),
						(new CMultiSelect([
							'name' => 'filter_hostids[]',
							'object_name' => 'host_templates',
							'data' => $data['filter']['hosts'],
							'popup' => [
								'filter_preselect_fields' => [
									'hostgroups' => 'filter_groups_'
								],
								'parameters' => [
									'srctbl' => 'host_templates',
									'srcfld1' => 'hostid',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_hostids_',
									'editable' => 1
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
			])
	);
}

// create form
$graphForm = (new CForm())
	->setName('graphForm')
	->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['parent_discoveryid'])) {
	$graphForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}

// create table
$url = (new CUrl('graphs.php'))->getUrl();
$discover = null;
$info_column = null;

if ($data['parent_discoveryid']) {
	$discover = make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url);
}
else {
	$info_column = _('Info');
}

$graphTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_graphs'))->onClick("checkAll('".$graphForm->getName()."', 'all_graphs', 'group_graphid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		!empty($this->data['hostid']) ? null : _('Hosts'),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder'], $url),
		_('Width'),
		_('Height'),
		make_sorting_header(_('Graph type'), 'graphtype', $this->data['sort'], $this->data['sortorder'], $url),
		$discover,
		$info_column
	]);

foreach ($data['graphs'] as $graph) {
	$graphid = $graph['graphid'];

	$hostList = null;
	if (empty($this->data['hostid'])) {
		$hostList = [];
		foreach ($graph['hosts'] as $host) {
			$hostList[$host['name']] = $host['name'];
		}

		foreach ($graph['templates'] as $template) {
			$hostList[$template['name']] = $template['name'];
		}
		$hostList = implode(', ', $hostList);
	}

	$flag = ($data['parent_discoveryid'] === null) ? ZBX_FLAG_DISCOVERY_NORMAL : ZBX_FLAG_DISCOVERY_PROTOTYPE;
	$name = [];
	$name[] = makeGraphTemplatePrefix($graphid, $data['parent_templates'], $flag, $data['allowed_ui_conf_templates']);

	if ($graph['discoveryRule'] && $data['parent_discoveryid'] === null) {
		$name[] = (new CLink(CHtml::encode($graph['discoveryRule']['name']),
			(new CUrl('host_discovery.php'))
				->setArgument('form', 'update')
				->setArgument('itemid', $graph['discoveryRule']['itemid'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$name[] = NAME_DELIMITER;
	}

	$url = (new CUrl('graphs.php'))
		->setArgument('form', 'update')
		->setArgument('parent_discoveryid', $data['parent_discoveryid'])
		->setArgument('graphid', $graphid);

	if ($data['parent_discoveryid'] === null) {
		$url->setArgument('filter_hostids', [$data['hostid']]);
	}

	$name[] = new CLink(CHtml::encode($graph['name']), $url);
	$info_icons = [];
	$discover = null;

	if ($data['parent_discoveryid']) {
		$nodiscover = ($graph['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
		$discover = (new CLink($nodiscover ? _('No') : _('Yes'),
				(new CUrl('graphs.php'))
					->setArgument('action', 'graph.updatediscover')
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->setArgument('graphid', $graphid)
					->setArgument('discover', $nodiscover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
					->getUrl()
			))
				->addSID()
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);
	}
	else if (array_key_exists('ts_delete', $graph['graphDiscovery']) && $graph['graphDiscovery']['ts_delete'] > 0) {
		$info_icons[] = getGraphLifetimeIndicator(time(), $graph['graphDiscovery']['ts_delete']);
	}

	$graphTable->addRow([
		new CCheckBox('group_graphid['.$graphid.']', $graphid),
		$hostList,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype'],
		$discover,
		($info_column === null) ? null : makeInformationList($info_icons)
	]);
}

// buttons
$buttonsArray = [];
if (!$this->data['parent_discoveryid']) {
	$buttonsArray['graph.masscopyto'] = ['name' => _('Copy')];
}
$buttonsArray['graph.massdelete'] = ['name' => _('Delete'), 'confirm' => $this->data['parent_discoveryid']
	? _('Delete selected graph prototypes?')
	: _('Delete selected graphs?')
];

// append table to form
$graphForm->addItem([
	$graphTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_graphid', $buttonsArray,
		$this->data['parent_discoveryid']
			? $this->data['parent_discoveryid']
			: $this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($graphForm);

$widget->show();
