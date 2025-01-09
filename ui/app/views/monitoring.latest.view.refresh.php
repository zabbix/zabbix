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
 * @var array $data
 */

$output = ['body' => (new CPartial('monitoring.latest.view.html', $data['results']))->getOutput()];

if ($data['results']['mandatory_filter_set'] && $data['results']['items'] || $data['results']['subfilter_set']) {
	$output['subfilter'] = (new CPartial('monitoring.latest.subfilter',
		array_intersect_key($data, array_flip(['subfilters', 'subfilters_expanded']))
	))->getOutput();
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

echo json_encode($output);
