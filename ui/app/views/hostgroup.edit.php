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

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('hostgroup')))->removeId())
	->setId('hostgroupForm')
	->setName('hostgroupForm')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('groupid', $data['groupid']);

$form_grid = new CFormGrid();

if ($data['groupid'] !== null && $data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	$discovery_rules = [];

	if ($data['discoveryRules']) {
		$lld_rule_count = count($data['discoveryRules']);
		$data['discoveryRules'] = array_slice($data['discoveryRules'], 0, 5);

		foreach ($data['discoveryRules'] as $lld_rule) {
			if ($data['allowed_ui_conf_hosts'] && $lld_rule['is_editable']
					&& array_key_exists($lld_rule['itemid'], $data['ldd_rule_to_host_prototype'])) {
				$discovery_rules[] = (new CLink($lld_rule['name'],
					(new CUrl('host_prototypes.php'))
						->setArgument('form', 'update')
						->setArgument('parent_discoveryid', $lld_rule['itemid'])
						->setArgument('hostid', reset($data['ldd_rule_to_host_prototype'][$lld_rule['itemid']]))
						->setArgument('context', 'host')
				));
			}
			else {
				$discovery_rules[] = new CSpan($lld_rule['name']);
			}

			$discovery_rules[] = ', ';
		}

		if ($lld_rule_count > 5) {
			$discovery_rules[] = '...';
		}
		else {
			array_pop($discovery_rules);
		}
	}
	else {
		$discovery_rules = (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_GREY);
	}

	$form_grid->addItem([[new CLabel(_('Discovered by')), new CFormField($discovery_rules)]]);
}

$form_grid->addItem([
	(new CLabel(_('Group name'), 'name'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('name', $data['name'], $data['groupid'] != 0 && $data['flags'] == ZBX_FLAG_DISCOVERY_CREATED))
			->setAttribute('autofocus', 'autofocus')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
]);

if ($data['groupid'] != 0 && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$form_grid->addItem([
		new CFormField((new CCheckBox('subgroups'))
			->setLabel(_('Apply permissions and tag filters to all subgroups'))
			->setChecked($data['subgroups']))
	]);
}

$tabs = (new CTabView(['id' => 'hostgroup-tabs']))->addTab('hostgroup-tab', _('Host group'), $form_grid);

if (array_key_exists('buttons', $data)) {
	$primary_btn = array_shift($data['buttons']);
	$tabs->setFooter(makeFormFooter($primary_btn, $data['buttons']));
}

$form->addItem($tabs);

if ($data['groupid'] !== null) {
	$title = _('Host group');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'hostgroup_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'enabled' => CWebUser::getType() == USER_TYPE_SUPER_ADMIN,
			'isSubmit' => false,
			'action' => 'hostgroup_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected host group?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'hostgroup_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New host group');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'hostgroup_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOSTGROUPS_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('hostgroup.edit.js.php').
		'hostgroup_edit_popup.init('.json_encode([
			'groupid' => $data['groupid'],
			'name' => $data['name']
		]).');',
	'dialogue_class' => 'modal-popup-static'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
