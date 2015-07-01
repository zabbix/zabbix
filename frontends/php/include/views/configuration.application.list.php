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

if ($this->data['hostid'] == 0) {
	$create_button = (new CSubmit('form', _('Create application (select host first)')))->setEnabled(false);
}
else {
	$create_button = new CSubmit('form', _('Create application'));
}

$widget = (new CWidget())
	->setTitle(_('Applications'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem([_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()])
			->addItem([_('Host'), SPACE, $this->data['pageFilter']->getHostsCB()])
			->addItem($create_button)
		)
	)
	->addItem(get_header_host_table('applications', $this->data['hostid']));

// create form
$applicationForm = new CForm();

// create table
$applicationTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_applications'))->onClick("checkAll('".$applicationForm->getName()."', 'all_applications', 'applications');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($this->data['hostid'] > 0) ? null : _('Host'),
		make_sorting_header(_('Application'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Items')
	]);

foreach ($this->data['applications'] as $application) {
	// inherited app, display the template list
	if ($application['templateids'] && !empty($application['sourceTemplates'])) {
		$name = [];

		CArrayHelper::sort($application['sourceTemplates'], ['name']);

		foreach ($application['sourceTemplates'] as $template) {
			$name[] = (new CLink($template['name'], 'applications.php?hostid='.$template['hostid']))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
			$name[] = ', ';
		}
		array_pop($name);
		$name[] = NAME_DELIMITER;
		$name[] = $application['name'];
	}
	else {
		$name = new CLink(
			$application['name'],
			'applications.php?'.
				'form=update'.
				'&applicationid='.$application['applicationid'].
				'&hostid='.$application['hostid']
		);
	}

	$applicationTable->addRow([
		new CCheckBox('applications['.$application['applicationid'].']', $application['applicationid']),
		($this->data['hostid'] > 0) ? null : $application['host']['name'],
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(
				_('Items'),
				'items.php?'.
					'hostid='.$application['hostid'].
					'&filter_set=1'.
					'&filter_application='.urlencode($application['name'])
			),
			CViewHelper::showNum(count($application['items']))
		]
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$applicationForm->addItem([
	$applicationTable,
	$this->data['paging'],
	new CActionButtonList('action', 'applications',
		[
			'application.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected applications?')],
			'application.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected applications?')],
			'application.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected applications?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($applicationForm);

return $widget;
