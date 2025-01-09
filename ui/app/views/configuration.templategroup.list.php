<?php declare(strict_types = 0);
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

$this->includeJsFile('configuration.templategroup.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Template groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TEMPLATE_GROUPS_LIST))
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
	])
	->setPageNavigation($data['paging']);

$current_time = time();

foreach ($data['groups'] as $group) {
	$templates_output = [];
	$n = 0;

	foreach ($group['templates'] as $template) {
		$n++;

		if ($n > $data['config']['max_in_table']) {
			$templates_output[] = [' ', HELLIP()];

			break;
		}

		if ($n > 1) {
			$templates_output[] = ', ';
		}

		if ($data['allowed_ui_conf_templates']) {
			$template_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $template['templateid'])
				->getUrl();

			$templates_output[] = (new CLink($template['name'], $template_url))
				->setAttribute('data-templateid', $template['templateid'])
				->setAttribute('data-action', 'template.edit')
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$templates_output[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}
	}

	$template_count = $data['groupCounts'][$group['groupid']]['templates'];

	$templategroup_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'templategroup.edit')
		->setArgument('groupid', $group['groupid'])
		->getUrl();

	$name = (new CLink($group['name'], $templategroup_url))
		->setAttribute('data-groupid', $group['groupid'])
		->setAttribute('data-action', 'templategroup.edit');

	$count = '';
	if ($template_count > 0) {
		if ($data['allowed_ui_conf_templates']) {
			$count = new CLink($template_count, (new CUrl('zabbix.php'))
				->setArgument('action', 'template.list')
				->setArgument('filter_set', '1')
				->setArgument('filter_groups', [$group['groupid']]));
		}
		else {
			$count = new CSpan($template_count);
		}

		$count->addClass(ZBX_STYLE_ENTITY_COUNT);
	}

	$table->addRow([
		new CCheckBox('groupids['.$group['groupid'].']', $group['groupid']),
		(new CCol($name))
			->addClass(ZBX_STYLE_WORDBREAK)
			->setWidth('15%'),
		(new CCol($count))->addClass(ZBX_STYLE_CELL_WIDTH),
		$templates_output ? (new CCol($templates_output))->addClass(ZBX_STYLE_WORDBREAK) : ''
	]);
}

$form->addItem([
	$table,
	new CActionButtonList('action', 'groupids', [
		'templategroup.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-templategroup')
				->addClass('js-no-chkbxrange')
		]
	], 'templategroup')
]);

$html_page
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('view.init('.json_encode([
	'delete_url' => (new CUrl('zabbix.php'))
		->setArgument('action', 'templategroup.delete')
		->setArgument(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('templategroup'))
		->getUrl()
]).');'))
	->setOnDocumentReady()
	->show();
