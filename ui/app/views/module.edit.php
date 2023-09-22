<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$form = (new CForm())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('module')))->removeId())
	->setName('module-form')
	->addVar('moduleid', $data['moduleid'])
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->setAttribute('autofocus', 'autofocus');

$module_form = (new CFormGrid())
	->addItem([
		new CLabel(_('Name'), 'name'),
		new CFormField($data['name'])
	])
	->addItem([
		new CLabel(_('Version'), 'version'),
		new CFormField($data['version'])
	])
	->addItem([
		new CLabel(_('Author'), 'author'),
		new CFormField($data['author'] === '' ? '-' : $data['author'])
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		(new CFormField($data['description'] === '' ? '-' : $data['description']))
			->addClass(ZBX_STYLE_WORDBREAK)
	])
	->addItem([
		new CLabel(_('Directory'), 'directory'),
		new CFormField($data['relative_path'])
	])
	->addItem([
		new CLabel(_('Namespace'), 'namespace'),
		new CFormField($data['namespace'])
	])
	->addItem([
		new CLabel(_('URL'), 'url'),
		(new CFormField($data['url'] === ''
			? '-'
			: (new CLink($data['url'], $data['url']))->setTarget('_blank'))
		)
			->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', MODULE_STATUS_ENABLED))
				->setChecked($data['status'] == MODULE_STATUS_ENABLED)
		)
	]);

$form
	->addItem($module_form)
	->addItem((new CScriptTag('module_edit.init();'))->setOnDocumentReady());

$output = [
	'header' => _('Module'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MODULE_EDIT),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'isSubmit' => true,
			'keepOpen' => true,
			'action' => 'module_edit.submit();'
		]
	],
	'script_inline' => getPagePostJs().$this->readJsFile('module.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
