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

$data['form_name'] = 'host-wizard-form';

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('host')))->removeId())
	->setId($data['form_name'])
	->setName($data['form_name'])
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', $data['form_action'])
		->getUrl()
	)
	->addVar('hostid', $data['hostid'])
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN)); // TODO VM: do we need this?




$output = [
	'header' => _('Host Wizard'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_WIZARD),
	'body' => $form->toString(),
	'script_inline' => getPagePostJs().
		$this->readJsFile('host.wizard.edit.js.php').
		'host_wizard_edit.init('.json_encode([
			'templates' => $data['templates'],
			'linked_templates' => $data['linked_templates'],
			'old_template_count' => $data['old_template_count'],
			'wizard_hide_welcome' => $data['wizard_hide_welcome']
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
