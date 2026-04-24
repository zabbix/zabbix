<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$id = 'cep';

if ($data['cep_rule']['cep_ruleid'] === null) {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Update'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		],
		[
			'title' => _('Clone'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
			'keepOpen' => true,
			'isSubmit' => false
		],
		[
			'title' => _('Delete'),
			'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
			'keepOpen' => true,
			'isSubmit' => false
		]
	];
}

$form = (new CForm())
	->setId($id)
	->addItem((new CFormGrid())
		->addItem([
			(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('name', $data['cep_rule']['name']))
					->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			)
		])
		/* ->addItem(new CPartial('cep.filter', [ */
		/* 	'id' => $id_filter, */
		/* 	'filter' => $ceprule->filter */
		/* ])) */
		/* ->addItem(new CPartial('cep.timewindow', [ */
		/* 	'id' => $id_timewindow, */
		/* 	'type' => $ceprule->window->type, */
		/* 	'window' => $ceprule->window */
		/* ])) */
		/* ->addItem(new CPartial('cep.operations', [ */
		/* 	'id' => $id_operations, */
		/* 	'operations' => $ceprule->operations */
		/* ])) */
		->addItem([
			new CLabel(_('Stop processing'), 'stop'),
			new CFormField((new CCheckBox('stop'))
				->setChecked($ceprule->stop == ZBX_CEP_EXECUTION_STOP)
				->setUncheckedValue(ZBX_CEP_EXECUTION_CONTINUE)
			)
		])
		->addItem([
			(new CLabel(_('Sort order'), 'sortorder'))->setAsteriskMark(),
			new CFormField((new CTextBox('sortorder', $ceprule->sortorder)))
		])
		->addItem([
			new CLabel(_('Description'), 'description'),
			new CFormField(
				(new CTextArea('description', $ceprule->description))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Enabled'), 'status'),
			new CFormField(
				(new CCheckBox('status', ZBX_CEP_STATUS_ENABLED))
					->setChecked($ceprule->status == ZBX_CEP_STATUS_ENABLED)
					->setUncheckedValue(ZBX_CEP_STATUS_DISABLED)
			)
		])
	);


$output = [
	'header' => $data['cep_rule']['cep_ruleid'] === null ? _('New complex event processing') : _('Complex event processing'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CEPRULE_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => $this->readJsFile('ceprule.edit.js.php'),
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
