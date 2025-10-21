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

$this->includeJsFile('graph.prototype.list.js.php');

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
					(new CSimpleButton(_('Create graph prototype')))
						->setId('js-create')
						->setAttribute('data-parent_discoveryid',$data['parent_discoveryid'])
						->setEnabled(!$data['is_parent_discovered'])
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('graphs', $data['hostid'], $data['parent_discoveryid']));

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'graph.prototype.list')
	->setArgument('context', $data['context'])
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->getUrl();

// Create form.
$graphs_form = (new CForm('post', $url))
	->setName('graph_form')
	->addVar('context', $data['context'], 'form_context')
	->addVar('parent_discoveryid', $data['parent_discoveryid']);

// Create table.
$discover = make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url);

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
		$discover
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
	$name[] = makeGraphTemplatePrefix($graphid, $data['parent_templates'], ZBX_FLAG_DISCOVERY_PROTOTYPE,
		$data['allowed_ui_conf_templates']
	);

	if ($graph['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
		$name[] = (new CLink($data['source_link_data']['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'graph.prototype.edit')
				->setArgument('parent_discoveryid', $data['source_link_data']['parent_itemid'])
				->setArgument('graphid', $graph['discoveryData']['parent_graphid'])
				->setArgument('context', 'host')
				->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);

		$name[] = NAME_DELIMITER;
	}

	$name[] = new CLink($graph['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'graph.prototype.edit')
		->setArgument('context', $data['context'])
		->setArgument('parent_discoveryid', $data['parent_discoveryid'])
		->setArgument('graphid', $graphid));

	$no_discover = $graph['discover'] == ZBX_PROTOTYPE_NO_DISCOVER;
	$discover_toggle = $data['is_parent_discovered']
		? (new CSpan($no_discover ? _('No') : _('Yes')))
		: (new CLink($no_discover ? _('No') : _('Yes')))
			->setAttribute('data-graphid', $graphid)
			->setAttribute('data-discover', $no_discover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
			->addClass('js-update-discover')
			->addClass(ZBX_STYLE_LINK_ACTION);

	$graphs_table->addRow([
		new CCheckBox('group_graphid['.$graphid.']', $graphid),
		$hosts,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype'],
		$discover_toggle->addClass($no_discover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)
	]);
}

$buttons = new CActionButtonList('action', 'group_graphid', [
	'graph.massdelete' => [
		'content' => (new CSimpleButton(_('Delete')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-no-chkbxrange')
			->setId('js-massdelete-graph-prototype')
	]
], 'graph_prototypes_'.$data['parent_discoveryid']);

$graphs_form->addItem([$graphs_table, $buttons]);

$html_page
	->addItem($graphs_form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'checkbox_hash' => $data['parent_discoveryid'],
		'checkbox_object' => 'group_graphid',
		'context' => $data['context'],
		'parent_discoveryid' => $data['parent_discoveryid'],
		'form_name' => $graphs_form->getName(),
		'token' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('graph')]
	]).');
'))
	->setOnDocumentReady()
	->show();
