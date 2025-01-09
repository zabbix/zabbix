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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('module')))->removeId())
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
	'script_inline' => getPagePostJs().$this->readJsFile('module.edit.js.php'),
	'dialogue_class' => 'modal-popup-medium'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
