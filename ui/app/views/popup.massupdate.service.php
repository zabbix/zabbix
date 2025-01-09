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
 */

// create form
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('service')))->removeId())
	->setId('massupdate-form')
	->addVar('action', 'popup.massupdate.service')
	->addVar('ids', $data['ids'])
	->addVar('update', '1')
	->addVar('location_url', $data['location_url']);

$tags_form_grid = (new CFormGrid())
	->addItem([
		(new CVisibilityBox('visible[tags]', 'tags-field', _('Original')))->setLabel(_('Tags')),
		new CFormField(
			(new CDiv([
				(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
					->addValue(_('Add'), ZBX_ACTION_ADD)
					->addValue(_('Replace'), ZBX_ACTION_REPLACE)
					->addValue(_('Remove'), ZBX_ACTION_REMOVE)
					->setModern(true)
					->addStyle('margin-bottom: 5px;'),
				renderTagTable([['tag' => '', 'value' => '']])
					->setHeader([_('Name'), _('Value'), ''])
					->addClass('tags-table'),
				(new CTemplateTag('tag-row-tmpl'))
					->addItem(renderTagTableRow('#{rowNum}', ['tag' => '', 'value' => ''], ['add_post_js' => false]))
			]))
				->setId('tags-field')
				->addClass(ZBX_STYLE_TABLE_FORMS)
		)
	]);

$form->addItem($tags_form_grid);

$output = [
	'header' => $data['title'],
	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_MASSUPDATE_SERVICE),
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
