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
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('mediatype')))->removeId())
	->setName('mediatypetest_form')
	->setId('mediatype_test_edit')
	->addVar('action', 'mediatype.test.send')
	->addVar('mediatypeid', $data['mediatypeid'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = (new CFormGrid());

switch ($data['type']) {
	case MEDIA_TYPE_EXEC:
		if ($data['parameters']) {
			foreach ($data['parameters'] as $parameter) {
				$form_grid->addItem([
					$parameter['sortorder'] == 0
						? new CLabel([
							_('Script parameters'),
							makeHelpIcon(_('These parameters will be passed to the script as command-line arguments in the specified order.'))
						])
						: null,
					new CFormField(
						(new CTextBox('parameters['.$parameter['sortorder'].'][value]', $parameter['value']))
							->setAttribute('autofocus', 'autofocus')
							->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					)
				]);
			}
		}
		else {
			$form_grid->addItem([
				new CLabel(_('Script parameters')),
				new CFormField((new CDiv(_('Script does not have parameters.')))->addClass(ZBX_STYLE_GREY))
			]);
		}

		break;

	case MEDIA_TYPE_WEBHOOK:
		$i = 0;

		foreach ($data['parameters'] as $parameter) {
			$form_grid
				->addItem([
					new CLabel($parameter['name'], 'parameters['.$i.'][value]'),
					new CFormField([
						new CVar('parameters['.$i.'][name]', $parameter['name']),
						(new CTextBox('parameters['.$i.'][value]', $parameter['value']))
							->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
					])
				]);
			$i++;
		}

		if ($i == 0) {
			$form_grid->addItem([
				new CLabel(_('Parameters')),
				new CFormField((new CSpan(_('Webhook does not have parameters.')))->addClass(ZBX_STYLE_GREY))
			]);
		}

		$form_grid
			->addItem([
				new CLabel(_('Response')),
				new CFormField([
					(new CTextArea(''))
						->setId('webhook_response_value')
						->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
						->setEnabled(false),
					(new CDiv(''))->setId('webhook_response_type'),
					(new CDiv(
						(new CLinkAction(_('Open log')))
							->setId('mediatypetest_log')
							->addClass(ZBX_STYLE_DISABLED)
					))
				])
			]);
		break;

	default:
		$form_grid
			->addItem([
				(new CLabel(_('Send to'), 'sendto'))->setAsteriskMark(),
				new CFormField(
					(new CTextBox('sendto', $data['sendto'], false, 1024))
						->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
						->setAttribute('autofocus', 'autofocus')
						->setAriaRequired()
						->setEnabled($data['enabled'])
				)
			])
			->addItem([
				new CLabel(_('Subject'), 'subject'),
				new CFormField(
					(new CTextBox('subject', $data['subject'], false, 1024))
						->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
						->setEnabled($data['enabled'])
				)
			])
			->addItem([
				(new CLabel(_('Message'), 'message'))->setAsteriskMark(),
				new CFormField(
					(new CTextArea('message', $data['message'], ['rows' => 10]))
						->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
						->setAriaRequired()
						->setEnabled($data['enabled'])
				)
			]);
}

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('mediatype_test_edit_popup.init('.json_encode([
		]).');'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'script_inline' => getPagePostJs().$this->readJsFile('mediatype.test.edit.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Test'),
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => $data['enabled'],
			'action' => 'mediatype_test_edit_popup.submit();'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
