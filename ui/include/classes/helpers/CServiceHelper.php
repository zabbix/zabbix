<?php declare(strict_types = 1);
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CServiceHelper {

	public static function getAlgorithmNames() {
		return [
			SERVICE_ALGORITHM_MAX => _('Problem, if at least one child has a problem'),
			SERVICE_ALGORITHM_MIN => _('Problem, if all children have problems'),
			SERVICE_ALGORITHM_NONE => _('Do not calculate')
		];
	}

	public static function makeProblemTagsHtml(array $problem_tags): array {
		$all_elements = [];

		foreach ($problem_tags as $problem_tag) {
			$title = $problem_tag['value'] === ''
				? $problem_tag['tag']
				: $problem_tag['tag'].': '.$problem_tag['value'];

			$all_elements[] = (new CSpan($title))
				->addClass(ZBX_STYLE_TAG)
				->setHint($title);
		}

		if (count($all_elements) <= ZBX_TAG_COUNT_DEFAULT) {
			return $all_elements;
		}

		$inline_elements = [];

		for ($i = 0; $i < ZBX_TAG_COUNT_DEFAULT; $i++) {
			$inline_elements[] = clone $all_elements[$i];
		}

		$inline_elements[] = (new CSpan())
			->addClass(ZBX_STYLE_REL_CONTAINER)
			->addItem(
				(new CButton(null))
					->addClass(ZBX_STYLE_ICON_WZRD_ACTION)
					->setHint($all_elements, ZBX_STYLE_HINTBOX_WRAP)
			);

		return $inline_elements;
	}
}
