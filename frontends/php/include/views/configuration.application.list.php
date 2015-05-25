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

$applicationWidget = (new CWidget())->setTitle(_('Applications'));

$createForm = (new CForm('get'))->cleanItems();

$controls = (new CList())->
	addItem([_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()])->
	addItem([_('Host').SPACE, $this->data['pageFilter']->getHostsCB()]);

// append host summary to widget header
if (empty($this->data['hostid'])) {
	$createButton = new CSubmit('form', _('Create application (select host first)'));
	$createButton->setEnabled(false);
	$controls->addItem($createButton);
}
else {
	$controls->addItem(new CSubmit('form', _('Create application')));

}

$applicationWidget->setControls($createForm);


$createForm->addItem($controls);
$applicationWidget->setControls($createForm);

$applicationWidget->addItem(get_header_host_table('applications', $this->data['hostid']));

// create form
$applicationForm = new CForm();
$applicationForm->setName('applicationForm');

// create table
$applicationTable = new CTableInfo();
$applicationTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_applications', null, "checkAll('".$applicationForm->getName()."', 'all_applications', 'applications');")))->
		addClass('cell-width'),
	($this->data['hostid'] > 0) ? null : _('Host'),
	make_sorting_header(_('Application'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Show')
]);

foreach ($this->data['applications'] as $application) {
	// inherited app, display the template list
	if ($application['templateids'] && !empty($application['sourceTemplates'])) {
		$name = [];

		CArrayHelper::sort($application['sourceTemplates'], ['name']);

		foreach ($application['sourceTemplates'] as $template) {
			$name[] = new CLink($template['name'], 'applications.php?hostid='.$template['hostid'], ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY);
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
		new CCheckBox('applications['.$application['applicationid'].']', null, null, $application['applicationid']),
		($this->data['hostid'] > 0) ? null : $application['host']['name'],
		$name,
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
			'application.massdisable' => ['name' => _('Disable'),
				'confirm' => _('Disable selected applications?')
			],
			'application.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected applications?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$applicationWidget->addItem($applicationForm);

return $applicationWidget;
