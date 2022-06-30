<?php declare(strict_types = 0);
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

$this->includeJsFile('configuration.templategroup.list.js.php');

$widget = (new CWidget())
	->setTitle(_('Template groups'))
	->setControls((new CTag('nav', true, (new CList())
		->addItem(CWebUser::getType() == USER_TYPE_SUPER_ADMIN
			? (new CSimpleButton(_('Create template group')))
				->addClass('js-create-templategroup')
			: (new CSimpleButton(_('Create template group').' '._('(Only super admins can create groups)')))
				->setEnabled(false)
		)
		))->setAttribute('aria-label', _('Content controls')));

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

$form = (new CForm())
	->setId('templategroup-list')
	->setName('templategroup_list');

$view_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'templategroup.list')
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_groups'))
				->onClick("checkAll('".$form->getName()."', 'all_groups', 'groupids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
		(new CColHeader(_('Templates')))->setColSpan(2)
	]);

$current_time = time();

foreach ($data['groups'] as $group) {
	$templates_output = [];
	$n = 0;

	foreach ($group['templates'] as $template) {
		$n++;

		if ($n > $data['config']['max_in_table']) {
			$templates_output[] = ' &hellip;';

			break;
		}

		if ($n > 1) {
			$templates_output[] = ', ';
		}

		if ($data['allowed_ui_conf_templates']) {
			$templates_output[] = (new CLink($template['name'], (new CUrl('templates.php'))
				->setArgument('form', 'update')
				->setArgument('templateid', $template['templateid'])))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$templates_output[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}
	}

	$template_count = $data['groupCounts'][$group['groupid']]['templates'];

	$name = (new CLink(CHtml::encode($group['name']),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'templategroup.edit')
			->setArgument('groupid', $group['groupid'])
	))
		->addClass('js-edit-templategroup')
		->setAttribute('data-groupid', $group['groupid']);

	$count = '';
	if ($template_count > 0) {
		if ($data['allowed_ui_conf_templates']) {
			$count = new CLink($template_count, (new CUrl('templates.php'))
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$group['groupid']]));
		}
		else {
			$count = new CSpan($template_count);
		}

		$count->addClass(ZBX_STYLE_ICON_COUNT);
	}

	$table->addRow([
		new CCheckBox('groupids['.$group['groupid'].']', $group['groupid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol($count))->addClass(ZBX_STYLE_CELL_WIDTH),
		$templates_output ? $templates_output : ''
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'groupids', [
		'templategroup.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-templategroup')
				->addClass('no-chkbxrange')
		]
	], 'templategroup')
]);

$widget
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init('.json_encode([
	'delete_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'templategroup.delete')
		->setArgumentSID()
		->getUrl()
]).');'))
	->setOnDocumentReady()
	->show();
