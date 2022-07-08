<?php
//declare(strict_types=0);
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

$output = [];
// create form
$form = (new CForm())
	->setName('elements_form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

if (array_key_exists('itemids', $data)) {
	$form->addVar('itemids', $data['itemids']);
	$header = _n('Copy %1$s item', 'Copy %1$s items', count($data['itemids']));
	$action = 'copy.items';
}
elseif (array_key_exists('triggerids', $data)) {
	$form->addVar('triggerids', $data['triggerids']);
	$header = _n('Copy %1$s trigger', 'Copy %1$s triggers', count($data['triggerids']));
	$action = 'copy.triggers';
}
elseif (array_key_exists('graphids', $data)) {
	$form->addVar('graphids', $data['graphids']);
	$header = _n('Copy %1$s graph', 'Copy %1$s graphs', count($data['graphids']));
	$action = 'copy.graphs';
}

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Target type'), 'copy_type')),
		new CFormField(
			(new CRadioButtonList('copy_type', COPY_TYPE_TO_HOST_GROUP))
				->addValue(_('Host groups'), COPY_TYPE_TO_HOST_GROUP)
				->addValue(_('Hosts'), COPY_TYPE_TO_HOST)
				->addValue(_('Templates'), COPY_TYPE_TO_TEMPLATE)
				->addValue(_('Template groups'), COPY_TYPE_TO_TEMPLATE_GROUP)
				->setModern(true)
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
				'form_name' => $form->getName(),
				'action' => $action
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
	'script_inline' => getPagePostJs().$this->readJsFile('popup.copy.js.php')
];

echo json_encode($output);
