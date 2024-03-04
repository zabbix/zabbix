<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

$form = (new CForm('post'))
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('proxygroup')))->removeId())
	->setId('proxy-group-form')
	->setName('proxy_group_form')
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['proxy_groupid'] !== null) {
	$form->addVar('proxy_groupid', $data['proxy_groupid']);
}

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('proxy_group', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Failover period'), 'failover_delay'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('failover_delay', $data['form']['failover_delay'], false,
				DB::getFieldLength('proxy_group', 'failover_delay')
			))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		)
	])
	->addItem([
		(new CLabel(_('Minimum number of proxies'), 'min_online'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('min_online', $data['form']['min_online'], false,
				DB::getFieldLength('proxy_group', 'min_online')
			))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description', $data['form']['description']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('proxy_group', 'description'))
		)
	]);

if ($data['proxies']) {
	$proxies = [];

	foreach ($data['proxies'] as $proxy) {
		$proxies[] = $data['user']['can_edit_proxies']
			? (new CLink($proxy['name']))
				->addClass('js-edit-proxy')
				->setAttribute('data-proxyid', $proxy['proxyid'])
			: $proxy['name'];
		$proxies[] = ', ';
	}

	array_pop($proxies);

	if ($data['proxy_count_total'] > count($data['proxies'])) {
		$proxies[] = [' ', HELLIP()];
	}

	$form_grid->addItem([
		(new CLabel(_('Proxies')))->addClass('js-field-proxies'),
		(new CFormField(
			(new CDiv($proxies))
				->addClass(ZBX_STYLE_WORDBREAK)
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-field-proxies')
	]);
}

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('proxy_group_edit_popup.init('.json_encode([
			'proxy_groupid' => $data['proxy_groupid']
		]).');'))->setOnDocumentReady()
	);

if ($data['proxy_groupid'] !== null) {
	$title = _('Proxy group');
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-update',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'proxy_group_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'proxy_group_edit_popup.clone('.json_encode([
				'title' => _('New proxy group'),
				'buttons' => [
					[
						'title' => _('Add'),
						'class' => 'js-add',
						'keepOpen' => true,
						'isSubmit' => true,
						'action' => 'proxy_group_edit_popup.submit();'
					],
					[
						'title' => _('Cancel'),
						'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
						'cancel' => true,
						'action' => ''
					]
				]
			]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete selected proxy group?'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'proxy_group_edit_popup.delete();'
		]
	];
}
else {
	$title = _('New proxy group');
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'proxy_group_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $title,
	'doc_url' => CDocHelper::getUrl(CDocHelper::ADMINISTRATION_PROXY_GROUP_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.proxygroup.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
