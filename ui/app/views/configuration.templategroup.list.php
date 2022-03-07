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
 * @var array $data
 */

if ($data['uncheck']) {
	uncheckTableRows('templategroup');
}

$widget = (new CWidget())
	->setTitle(_('Template groups'))
	->setControls((new CTag('nav', true, (new CList())
		->addItem(CWebUser::getType() == USER_TYPE_SUPER_ADMIN
			? new CRedirectButton(_('Create template group'), (new CUrl('zabbix.php'))
				->setArgument('action', 'templategroup.edit')
				->getUrl()
			)
			: (new CSubmit('form', _('Create template group').' '._('(Only super admins can create groups)')))
				->setEnabled(false)
		)
	))->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'templategroup.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addItem([
					new CLabel(_('Name'), 'filter_name'),
					new CFormField(
						(new CTextBox('filter_name', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
							->setAttribute('autofocus', 'autofocus')
					)
				])
		])
		->addVar('action', 'templategroup.list');

// create form
$form = (new CForm())
	->setId('templategroup-list')
	->setName('templategroup_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'templategroup.list')
	->getUrl();

// create table
$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_groups'))
				->onClick("checkAll('".$form->getName()."', 'all_groups', 'groups');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder'], $view_url),
		_('Templates'),
		(new CColHeader(_('Info')))->addClass(ZBX_STYLE_CELL_WIDTH)
	]);

$current_time = time();

foreach ($this->data['groups'] as $group) {
	$templatesOutput = [];
	$n = 0;

	foreach ($group['templates'] as $template) {
		$n++;

		if ($n > $data['config']['max_in_table']) {
			$templatesOutput[] = ' &hellip;';

			break;
		}

		if ($n > 1) {
			$templatesOutput[] = ', ';
		}

		if ($data['allowed_ui_conf_templates']) {
			$templatesOutput[] = (new CLink($template['name'], (new CUrl('templates.php'))
				->setArgument('form', 'update')
				->setArgument('templateid', $template['templateid'])))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$templatesOutput[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}
	}

	$templateCount = $this->data['groupCounts'][$group['groupid']]['templates'];

	// name
	$name = [];

	$name[] = (new CLink($group['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'templategroup.edit')
		->setArgument('groupid', $group['groupid'])
	));

	if ($templateCount > 0) {
		$count = new CLink($templateCount, (new CUrl('templates.php'))
			->setArgument('filter_set', '1')
			->setArgument('filter_groups', [$group['groupid']]));
		$count->addClass(ZBX_STYLE_ICON_COUNT);
	}
	else {
		$count = (new CSpan(''));
	}

	$table->addRow([
		new CCheckBox('groupids['.$group['groupid'].']', $group['groupid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			$count,
			empty($templatesOutput) ? '' : $templatesOutput
		],
		''
	]);
}

// append table to form
$form->addItem([
	$table,
	$this->data['paging'],
	new CActionButtonList('action', 'groupids', [
		'templategroup.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected template groups?'), 'templategroup']
	])
]);

// append filter and form to widget
$widget
	->addItem($filter)
	->addItem($form);

$widget->show();
