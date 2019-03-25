<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		->setControls(new CList([
			(new CForm('get'))
				->cleanItems()
				->setAttribute('aria-label', _('Main filter'))
				->addItem((new CList())
					->addItem([
						new CLabel(_('Group'), 'groupid'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$this->data['pageFilter']->getGroupsCB()
					])
					->addItem([
						new CLabel(_('Host'), 'hostid'),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$this->data['pageFilter']->getHostsCB()
					])
				),
			(new CTag('nav', true, ($data['hostid'] == 0)
				? (new CButton('form', _('Create graph (select host first)')))->setEnabled(false)
				: new CRedirectButton(_('Create graph'), (new CUrl('graphs.php'))
					->setArgument('hostid', $data['hostid'])
					->setArgument('form', 'create')
					->getUrl()
				)
			))
				->setAttribute('aria-label', _('Content controls'))
		]));

	if (!empty($this->data['hostid'])) {
		$widget->addItem(get_header_host_table('graphs', $this->data['hostid']));
	}
}

// create form
$graphForm = (new CForm())
	->setName('graphForm')
	->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['parent_discoveryid'])) {
	$graphForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
}

// create table
$graphTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_graphs'))->onClick("checkAll('".$graphForm->getName()."', 'all_graphs', 'group_graphid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		!empty($this->data['hostid']) ? null : _('Hosts'),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Width'),
		_('Height'),
		make_sorting_header(_('Graph type'), 'graphtype', $this->data['sort'], $this->data['sortorder'])
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

	$name = [];
	$name[] = makeGraphTemplatePrefix($graphid, $data['parent_templates'], ($data['parent_discoveryid'] === null)
		? ZBX_FLAG_DISCOVERY_NORMAL
		: ZBX_FLAG_DISCOVERY_PROTOTYPE
	);

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
		$url->setArgument('hostid', $this->data['hostid']);
	}

	$name[] = new CLink(CHtml::encode($graph['name']), $url);

	$graphTable->addRow([
		new CCheckBox('group_graphid['.$graphid.']', $graphid),
		$hostList,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype']
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

return $widget;
