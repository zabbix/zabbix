<?php
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
 */

$this->includeJsFile('configuration.graph.list.js.php');

if (!empty($this->data['parent_discoveryid'])) {
	$widget = (new CWidget())
		->setTitle(_('Graph prototypes'))
		->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
			? CDocHelper::CONFIGURATION_HOST_GRAPH_PROTOTYPE_LIST
			: CDocHelper::CONFIGURATION_TEMPLATES_GRAPH_PROTOTYPE_LIST
		))
		->setControls(
			(new CTag('nav', true,
				(new CList())
					->addItem(
						new CRedirectButton(_('Create graph prototype'),
							(new CUrl('graphs.php'))
								->setArgument('form', 'create')
								->setArgument('parent_discoveryid', $data['parent_discoveryid'])
								->setArgument('context', $data['context'])
						)
					)
			))->setAttribute('aria-label', _('Content controls'))
		)
		->setNavigation(getHostNavigation('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
}
else {
	$widget = (new CWidget())
		->setTitle(_('Graphs'))
		->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
			? CDocHelper::CONFIGURATION_HOST_GRAPH_LIST
			: CDocHelper::CONFIGURATION_TEMPLATE_GRAPH_LIST
		))
		->setControls(
			(new CTag('nav', true,
				(new CList())
					->addItem(
						$data['hostid'] != 0
							? new CRedirectButton(_('Create graph'),
								(new CUrl('graphs.php'))
									->setArgument('hostid', $data['hostid'])
									->setArgument('form', 'create')
									->setArgument('context', $data['context'])
							)
							: (new CButton('form',
								$data['context'] === 'host'
									? _('Create graph (select host first)')
									: _('Create graph (select template first)')
							))->setEnabled(false)
					)
			))->setAttribute('aria-label', _('Content controls'))
		);

	if (!empty($this->data['hostid'])) {
		$widget->setNavigation(getHostNavigation('graphs', $this->data['hostid']));
	}

	// Add filter tab.
	$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

	$widget->addItem(
		(new CFilter())
			->setResetUrl((new CUrl('graphs.php'))->setArgument('context', $data['context']))
			->setProfile($data['profileIdx'])
			->setActiveTab($data['active_tab'])
			->addvar('context', $data['context'])
			->addFilterTab(_('Filter'), [
				(new CFormList())
					->addRow(
						new CLabel($data['context'] === 'host' ? _('Host groups') : _('Template groups'),
							'filter_groupids__ms'
						),
						(new CMultiSelect([
							'name' => 'filter_groupids[]',
							'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
							'data' => $data['filter']['groups'],
							'popup' => [
								'parameters' => [
									'srctbl' =>  $data['context'] === 'host' ? 'host_groups' : 'template_groups',
									'srcfld1' => 'groupid',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_groupids_',
									'editable' => true,
									'enrich_parent_groups' => true
								] + $hg_ms_params
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(
						(new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostids__ms')),
						(new CMultiSelect([
							'name' => 'filter_hostids[]',
							'object_name' => $data['context'] === 'host' ? 'hosts' : 'templates',
							'data' => $data['filter']['hosts'],
							'popup' => [
								'filter_preselect_fields' => $data['context'] === 'host'
									? ['hostgroups' => 'filter_groupids_']
									: ['templategroups' => 'filter_groupids_'],
								'parameters' => [
									'srctbl' => $data['context'] === 'host' ? 'hosts' : 'templates',
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

$url = (new CUrl('graphs.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$graphForm = (new CForm('post', $url))
	->setName('graphForm')
	->addVar('hostid', $data['hostid']);

if (!empty($this->data['parent_discoveryid'])) {
	$graphForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}

// create table
$discover = null;
$info_column = null;

if ($data['parent_discoveryid']) {
	$discover = make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url);
}
else {
	$info_column = ($data['context'] === 'host') ? _('Info') : null;
}

$graphTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_graphs'))->onClick("checkAll('".$graphForm->getName()."', 'all_graphs', 'group_graphid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($data['hostid'] == 0) ? ($data['context'] === 'host') ? _('Host') : _('Template') : null,
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
				->setArgument('context', $data['context'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$name[] = NAME_DELIMITER;
	}

	$url = (new CUrl('graphs.php'))
		->setArgument('form', 'update')
		->setArgument('parent_discoveryid', $data['parent_discoveryid'])
		->setArgument('graphid', $graphid)
		->setArgument('context', $data['context']);

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
					->setArgument('context', $data['context'])
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
	$data['paging'],
	new CActionButtonList('action', 'group_graphid', $buttonsArray,
		$data['parent_discoveryid']
			? $data['parent_discoveryid']
			: $data['hostid']
	)
]);

// append form to widget
$widget->addItem($graphForm);

$widget->show();
