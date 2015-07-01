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


if (!empty($this->data['parent_discoveryid'])) {
	$widget = (new CWidget())
		->setTitle(_('Graph prototypes'))
		->setControls((new CForm('get'))
			->cleanItems()
			->addVar('parent_discoveryid', $this->data['parent_discoveryid'])
			->addItem((new CList())->addItem(new CSubmit('form', _('Create graph prototype'))))
		)
		->addItem(get_header_host_table('graphs', $this->data['hostid'], $this->data['parent_discoveryid']));
}
else {
	if (!empty($this->data['hostid'])) {
		$create_button = new CSubmit('form', _('Create graph'));
	}
	else {
		$create_button = (new CSubmit('form', _('Create graph (select host first)')))->setEnabled(false);
	}

	$widget = (new CWidget())
		->setTitle(_('Graphs'))
		->setControls((new CForm('get'))
			->cleanItems()
			->addVar('hostid', $this->data['hostid'])
			->addItem((new CList())
				->addItem([_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()])
				->addItem([_('Host'), SPACE, $this->data['pageFilter']->getHostsCB()])
				->addItem($create_button)
			)
		);

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

foreach ($this->data['graphs'] as $graph) {
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

	$isCheckboxEnabled = true;
	$name = [];
	if (!empty($graph['templateid'])) {
		$realHosts = get_realhosts_by_graphid($graph['templateid']);
		$realHosts = DBfetch($realHosts);
		$name[] = (new CLink($realHosts['name'], 'graphs.php?hostid='.$realHosts['hostid']))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_GREY);
		$name[] = NAME_DELIMITER;
		$name[] = new CLink(
			$graph['name'],
			'graphs.php?'.
				'form=update'.
				'&graphid='.$graphid.url_param('parent_discoveryid').
				'&hostid='.$this->data['hostid']
		);

		if ($graph['discoveryRule']) {
			$isCheckboxEnabled = false;
		}
	}
	elseif (!empty($graph['discoveryRule']) && empty($this->data['parent_discoveryid'])) {
		$name[] = (new CLink(
			$graph['discoveryRule']['name'],
			'host_discovery.php?form=update&itemid='.$graph['discoveryRule']['itemid'])
			)
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_ORANGE);
		$name[] = NAME_DELIMITER;
		$name[] = new CSpan($graph['name']);

		$isCheckboxEnabled = false;
	}
	else {
		$name[] = new CLink(
			$graph['name'],
			'graphs.php?'.
				'form=update'.
				'&graphid='.$graphid.url_param('parent_discoveryid').
				'&hostid='.$this->data['hostid']
		);
	}

	$checkBox = (new CCheckBox('group_graphid['.$graphid.']', $graphid))
		->setEnabled($isCheckboxEnabled);

	$graphTable->addRow([
		$checkBox,
		$hostList,
		$name,
		$graph['width'],
		$graph['height'],
		$graph['graphtype']
	]);
}

if ($this->data['parent_discoveryid']) {
	zbx_add_post_js('cookie.prefix = "'.$this->data['parent_discoveryid'].'";');
}
else {
	zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');
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
		$this->data['parent_discoveryid'] ? $this->data['parent_discoveryid'] : $this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($graphForm);

return $widget;
