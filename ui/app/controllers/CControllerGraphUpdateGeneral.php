<?php
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


abstract class CControllerGraphUpdateGeneral extends CController {

	protected static function processGraph(array $graph): array {
		$graph['gitems'] = $graph['items'];
		unset($graph['items']);

		if (array_key_exists('visible', $graph)) {
			$graph['percent_left'] = $graph['visible']['percent_left'] && array_key_exists('percent_left', $graph)
				? $graph['percent_left']
				: 0;

			$graph['percent_right'] = $graph['visible']['percent_right'] && array_key_exists('percent_right', $graph)
				? $graph['percent_right']
				: 0;

			unset($graph['visible']);
		}

		return $graph;
	}
}
