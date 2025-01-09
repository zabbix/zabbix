<?php
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

$form = (new CForm())->addItem($data['messages']);

if (array_key_exists('recipients', $data)) {
	$emails_sent = [];
	$emails_not_sent = [];

	foreach ($data['recipients'] as $recipient) {
		if ($recipient['status'] == 0) {
			$emails_sent[] = $recipient['recipient'];
		}
		else {
			$emails_not_sent[] = $recipient['recipient'];
		}
	}

	$results = '';

	if ($emails_sent) {
		$results .= _s('Report was successfully sent to: %1$s.', implode(', ', $emails_sent));
	}

	if ($emails_not_sent) {
		if ($emails_sent) {
			$results .= "\n\n";
		}

		$results .= _s('Report sending failed for: %1$s.', implode(', ', $emails_not_sent));
	}

	$form->addItem((new CTextArea('', $results))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->addClass('active-readonly')
		->setReadonly(true)
	);
}

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => null
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
