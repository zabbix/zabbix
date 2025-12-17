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

$this->includeJsFile('graph.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('graph');
}

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
						? (new CSimpleButton(_('Create graph')))
							->setId('js-create')
							->setAttribute('data-hostid', $data['hostid'])
						: (new CButton('form',
							$data['context'] === 'host'
								? _('Create graph (select host first)')
								: _('Create graph (select template first)')
						))->setEnabled(false)
				)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['hostid'] != 0) {
	$html_page->setNavigation(getHostNavigation('graphs', $data['hostid']));
}

$hostgroup_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

$html_page->addItem(
	(new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))
			->setArgument('action', 'graph.list')
			->setArgument('context', $data['context'])
		)
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addVar('action', 'graph.list', 'filter_action')
		->addvar('context', $data['context'], 'filter_context')
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addItem([
					new CLabel(
						$data['context'] === 'host' ? _('Host groups') : _('Template groups'),
						'filter_groupids__ms'
					),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_groupids[]',
							'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
							'data' => $data['filter']['groups'],
							'popup' => [
								'parameters' => [
										'srctbl' => $data['context'] === 'host' ? 'host_groups' : 'template_groups',
										'srcfld1' => 'groupid',
										'dstfrm' => 'zbx_filter',
										'dstfld1' => 'filter_groupids_',
										'editable' => true,
										'enrich_parent_groups' => true
									] + $hostgroup_ms_params
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
				->addItem([
					new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostids__ms'),
					new CFormField(
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
		])
);

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'graph.list')
	->setArgument('context', $data['context'])
	->getUrl();

// Create form.
$graphs_form = (new CForm('post', $url))
	->setName('graph_form')
	->addVar('context', $data['context'], 'form_context');

$info_column = $data['context'] === 'host' ? _('Info') : null;

$graphs_table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_graphs'))
				->onClick("checkAll('".$graphs_form->getName()."', 'all_graphs', 'group_graphid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($data['hostid'] == 0) ? (($data['context'] === 'host') ? _('Host') : _('Template')) : null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Width'),
		_('Height'),
		make_sorting_header(_('Graph type'), 'graphtype', $data['sort'], $data['sortorder'], $url),
		$info_column
	])
	->setPageNavigation($data['paging']);

foreach ($data['graphs'] as $graph) {
	$hosts = null;
	$graphid = $graph['graphid'];

	if ($data['hostid'] == 0) {
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

	$name = [];
	$name[] = makeGraphTemplatePrefix($graphid, $data['parent_templates'], ZBX_FLAG_DISCOVERY_NORMAL,
		$data['allowed_ui_conf_templates']
	);

	if ($graph['discoveryRule']) {
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

	$name[] = new CLink($graph['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'graph.edit')
		->setArgument('graphid', $graph['graphid'])
		->setArgument('context', $data['context'])
	);

	$info_icons = [];

	if ($graph['discoveryData'] && $graph['discoveryData']['status'] == ZBX_LLD_STATUS_LOST) {
		$info_icons[] = getGraphLifetimeIndicator(time(), (int) $graph['discoveryData']['ts_delete']);
	}

	$graphs_table->addRow([
		new CCheckBox('group_graphid['.$graphid.']', $graphid),
		$hosts,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype'],
		$info_column === null ? null : makeInformationList($info_icons)
	]);
}

$buttons = new CActionButtonList('action', 'group_graphid', [
	'graph.masscopyto' => [
		'content' => (new CSimpleButton(_('Copy')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-copy')
	],
	'graph.massdelete' => [
		'content' => (new CSimpleButton(_('Delete')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-no-chkbxrange')
			->setId('js-massdelete-graph')
	]
], 'graphs'.($data['checkbox_hash'] ? '_'.$data['checkbox_hash'] : ''));

$graphs_form->addItem([$graphs_table, $buttons]);

$html_page
	->addItem($graphs_form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'checkbox_hash' => $data['checkbox_hash'],
		'checkbox_object' => 'group_graphid',
		'context' => $data['context'],
		'form_name' => $graphs_form->getName(),
		'token' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('graph')]
	]).');
'))
	->setOnDocumentReady()
	->show();
