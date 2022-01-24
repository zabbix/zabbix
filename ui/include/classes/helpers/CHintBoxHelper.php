<?php declare(strict_types = 1);
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


class CHintBoxHelper {

	/**
	 * Prepare data for a hint with trigger events and, if defined, trigger description and a clickable URL.
	 *
	 * @param string $triggerid                  Trigger ID to select events.
	 * @param string $eventid_till
	 * @param bool   $show_timeline              (optional) Show time line flag.
	 * @param int    $show_tags                  (optional) Show tags flag. Possible values:
	 *                                             - SHOW_TAGS_NONE;
	 *                                             - SHOW_TAGS_1;
	 *                                             - SHOW_TAGS_2;
	 *                                             - SHOW_TAGS_3 (default).
	 * @param array  $filter_tags                (optional) An array of tag filtering data.
	 * @param string $filter_tags[]['tag']       Tag name.
	 * @param int    $filter_tags[]['operator']  Tag operator.
	 * @param string $filter_tags[]['value']     Tag value.
	 * @param int    $tag_name_format            (optional) Tag name format. Possible values:
	 *                                             - TAG_NAME_FULL (default);
	 *                                             - TAG_NAME_SHORTENED;
	 *                                             - TAG_NAME_NONE.
	 * @param string $tag_priority               (optional) A list of comma-separated tag names.
	 *
	 * @return array
	 */
	public static function getEventList(string $triggerid, string $eventid_till, bool $show_timeline = true,
			int $show_tags = SHOW_TAGS_3, array $filter_tags = [],
			int $tag_name_format = TAG_NAME_FULL, string $tag_priority = ''): array {
		$data = [
			'type' => 'eventlist',
			'data' => [
				'triggerid' => $triggerid,
				'eventid_till' => $eventid_till,
				'show_timeline' => (int) $show_timeline,
				'show_tags' => $show_tags,
				'tag_name_format' => $tag_name_format,
				'tag_priority' => $tag_priority
			]
		];

		if ($filter_tags) {
			$data['data']['filter_tags'] = $filter_tags;
		}

		return $data;
	}
}
