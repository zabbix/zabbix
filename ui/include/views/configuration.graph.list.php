<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('configuration.graph.list.js.php');

if (!empty($this->data['parent_discoveryid'])) {
	$html_page = (new CHtmlPage())
		->setTitle(_('Graph prototypes'))
		->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
			? CDocHelper::DATA_COLLECTION_HOST_GRAPH_PROTOTYPE_LIST
			: CDocHelper::DATA_COLLECTION_TEMPLATES_GRAPH_PROTOTYPE_LIST
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
	$html_page = (new CHtmlPage())
		->setTitle(_('Graphs'))
		->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
			? CDocHelper::DATA_COLLECTION_HOST_GRAPH_LIST
			: CDocHelper::DATA_COLLECTION_TEMPLATE_GRAPH_LIST
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
		$html_page->setNavigation(getHostNavigation('graphs', $this->data['hostid']));
	}

	// Add filter tab.
	$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

	$html_page->addItem(
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
								'filter_preselect' => [
									'id' => 'filter_groupids_',
									'submit_as' => $data['context'] === 'host' ? 'groupid' : 'templategroupid'
								],
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
	->addVar('hostid', $data['hostid'])
	->addVar('context', $data['context'], 'form_context');

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
	])
	->setPageNavigation($data['paging']);

$csrf_token = CCsrfTokenHelper::get('graphs.php');

foreach ($data['graphs'] as $graph) {
	$hosts = null;
	$graphid = $graph['graphid'];

	if ($this->data['hostid'] == 0) {
		foreach ($graph['hosts'] as $host) {
			if ($hosts) {
				$hosts[] = ', ';
			}

			$host_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', $data['context'] === 'host' ? 'host.edit' : 'template.edit')
				->setArgument($data['context'] === 'host' ? 'hostid' : 'templateid', $host['hostid'])
				->getUrl();

			$host_link = new CLink($host['name'], $host_url);

			if ($data['context'] ==='host') {
				$hosts[] = in_array($host['hostid'], $data['editable_hosts']) ? $host_link : $host['name'];
			}
			else {
				$hosts[] = $host_link;
			}
		}
	}

	$flag = ($data['parent_discoveryid'] === null) ? ZBX_FLAG_DISCOVERY_NORMAL : ZBX_FLAG_DISCOVERY_PROTOTYPE;
	$name = [];
	$name[] = makeGraphTemplatePrefix($graphid, $data['parent_templates'], $flag, $data['allowed_ui_conf_templates']);

	if ($graph['discoveryRule'] && $data['parent_discoveryid'] === null) {
		$name[] = (new CLink($graph['discoveryRule']['name'],
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

	$name[] = new CLink($graph['name'], $url);
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
					->setArgument('backurl',
						(new CUrl('graphs.php'))
							->setArgument('parent_discoveryid', $data['parent_discoveryid'])
							->setArgument('context', $data['context'])
							->getUrl()
					)
					->getUrl()
			))
				->addCsrfToken($csrf_token)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);
	}
	elseif ($graph['graphDiscovery'] && $graph['graphDiscovery']['status'] == ZBX_LLD_STATUS_LOST) {
		$info_icons[] = getGraphLifetimeIndicator(time(), (int) $graph['graphDiscovery']['ts_delete']);
	}

	$graphTable->addRow([
		new CCheckBox('group_graphid['.$graphid.']', $graphid),
		$hosts,
		(new CCol($name))->addClass(ZBX_STYLE_WORDBREAK),
		$graph['width'],
		$graph['height'],
		$graph['graphtype'],
		$discover,
		($info_column === null) ? null : makeInformationList($info_icons)
	]);
}

// buttons
$buttons = [];

if (!$this->data['parent_discoveryid']) {
	$buttons['graph.masscopyto'] = [
		'content' => (new CSimpleButton(_('Copy')))
			->addClass('js-copy')
			->addClass(ZBX_STYLE_BTN_ALT)
			->removeId()
	];
}

$buttons['graph.massdelete'] = [
	'name' => _('Delete'),
	'confirm_singular' => $this->data['parent_discoveryid']
		? _('Delete selected graph prototype?')
		: _('Delete selected graph?'),
	'confirm_plural' => $this->data['parent_discoveryid']
		? _('Delete selected graph prototypes?')
		: _('Delete selected graphs?'),
	'csrf_token' => $csrf_token
];

// append table to form
$graphForm->addItem([
	$graphTable,
	new CActionButtonList('action', 'group_graphid', $buttons, $data['parent_discoveryid'] ?: $data['hostid'])
]);

(new CScriptTag('
	view.init('.json_encode([
		'checkbox_hash' => $data['parent_discoveryid'] ?? $data['hostid'],
		'checkbox_object' => 'group_graphid',
		'context' => $data['context'],
		'parent_discoveryid' => $data['parent_discoveryid'],
		'form_name' => $graphForm->getName()
	]).');
'))
	->setOnDocumentReady()
	->show();

$html_page
	->addItem($graphForm)
	->show();
