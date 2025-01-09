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

// create form
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('copy')))->removeId());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

switch ($data['element_type']) {
	case 'items':
		$form
			->addVar('itemids', $data['itemids'])
			->addVar('source', 'items');
		$header = _n('Copy %1$s item', 'Copy %1$s items', count($data['itemids']));
		break;

	case 'triggers':
		$form
			->addVar('triggerids', $data['triggerids'])
			->addVar('source', 'triggers');

		if (array_key_exists('src_hostid', $data)) {
			$form->addVar('src_hostid', $data['src_hostid']);
		}

		$header = _n('Copy %1$s trigger', 'Copy %1$s triggers', count($data['triggerids']));
		break;

	case 'graphs':
		$form
			->addVar('graphids', $data['graphids'])
			->addVar('source', 'graphs');
		$header = _n('Copy %1$s graph', 'Copy %1$s graphs', count($data['graphids']));
		break;
}

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Target type'), 'copy_type'),
		new CFormField(
			(new CRadioButtonList('copy_type', COPY_TYPE_TO_TEMPLATE_GROUP))
				->addValue(_('Template groups'), COPY_TYPE_TO_TEMPLATE_GROUP)
				->addValue(_('Host groups'), COPY_TYPE_TO_HOST_GROUP)
				->addValue(_('Templates'), COPY_TYPE_TO_TEMPLATE)
				->addValue(_('Hosts'), COPY_TYPE_TO_HOST)
				->setModern()
				->setName('copy_type')
		)
	])
	->addItem([
		(new CLabel(_('Target'), 'copy_targetids_ms'))->setAsteriskMark(),
		(new CFormField())->setId('copy_targets')
	])
	->addItem(
		(new CScriptTag('
			copy_popup.init('.json_encode([
				'action' => 'copy.create'
			]).');
		'))->setOnDocumentReady()
	);

$form->addItem($form_grid);

$buttons = [
	[
		'title' => _('Copy'),
		'class' => 'js-update',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'copy_popup.submit();'
	]
];

$output = [
	'header' => $header,
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('copy.edit.js.php')
];

echo json_encode($output);
