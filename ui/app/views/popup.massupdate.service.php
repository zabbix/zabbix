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
 */

// create form
$form = (new CForm())
	->setId('massupdate-form')
	->addVar('action', 'popup.massupdate.service')
	->addVar('ids', $data['ids'])
	->addVar('update', '1')
	->addVar('location_url', $data['location_url']);

$tags_form_grid = (new CFormGrid())
	->addItem([
		(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))->setLabel(_('Tags')),
		new CFormField(
			(new CDiv([
				(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
					->addValue(_('Add'), ZBX_ACTION_ADD)
					->addValue(_('Replace'), ZBX_ACTION_REPLACE)
					->addValue(_('Remove'), ZBX_ACTION_REMOVE)
					->setModern(true)
					->addStyle('margin-bottom: 5px;'),
				renderTagTable([['tag' => '', 'value' => '']])
					->setHeader([_('Name'), _('Value'), _('Action')])
					->setId('tags-table'),
				(new CScriptTemplate('tag-row-tmpl'))
					->addItem(renderTagTableRow('#{rowNum}', '', '', ['add_post_js' => false]))
			]))
				->setId('tags-div')
				->addClass(ZBX_STYLE_TABLE_FORMS)
		)
	]);

$form->addItem($tags_form_grid);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitPopup(overlay);'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.massupdate.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
