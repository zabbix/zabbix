<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

if ($data['error'] !== '') {
	$body = (new CTableInfo())->setNoDataMessage($data['error']);
}
else {
	// To do: remove dummy data.
	$tmp = [
		// top row with one element
		new CDiv(
			// Description can span across multiple lines.
			(new CDiv('Dummy text'))
				->addClass('description')
				->addClass('left')
				->addClass('top')
				// ->addStyle('color: #9E9B8F;')
				->addStyle('--item-font: 0.05;')
		),
		// middle row with one element
		new CDiv(
			(new CDiv([
				// units (can be here if before or above value)
				// (new CDiv('Kbps'))
					// ->addClass('units'),
					// ->addClass('top'), // above value
					// ->addClass('before')
				// value (whole part) or it can be long text (trucanted) without decimals and without units.
				(new CDiv('205'))
					->addClass('value')
					->addClass('bold')
					// ->addStyle('color: #F0E4B3;')
					->addStyle('--item-font: 0.25;'),
				// decimals (fractional part)
				(new CDiv('.23'))
					->addClass('decimals')
					->addClass('bold')
					// ->addStyle('color: #F0E4B3;')
					->addStyle('--item-font: 0.10;'),
				// change indicator
				(new CDiv(
					(new CSvgArrow(['up' => true, 'fill_color' => '3DC51D']))
						->setId('change-indicator-up')
						// Size should be set in css (see below).
						//->setSize(14, 20)
				))
					->addClass('change-indicator')
					// add same size as value
					->addStyle('--item-font: 0.25;'),
				// units (can be here if after or below value)
				(new CDiv('Kbps'))
					->addClass('units')
					// ->addClass('below')
					->addClass('right') // after value
					->addClass('bold')
					// ->addStyle('color: #FF7B20;')
					->addStyle('--item-font: 0.25;')
			]))
				->addClass('item-value')
				->addClass('middle')
				->addClass('center')
		),
		// bottom row with one element
		new CDiv(
			(new CDiv('2021-11-11 11:11:11'))
				->addClass('time')
				->addClass('right')
				->addClass('bottom')
				// ->addStyle('color: #9E9B8F;')
				->addStyle('--item-font: 0.05;')
		)
	];

	$body = (new CDiv($tmp))
		// To do: add these styles to widget-item css class.
		// ->addStyle('width: 100%; height: 100%;');
		->addClass('widget-item');

	if ($data['data']['widget_config']['bg_color'] !== '') {
		$body->addStyle('background-color: #'.$data['data']['widget_config']['bg_color']);
	}
}

$output = [
	'name' => $data['name'],
	'body' => $body->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
