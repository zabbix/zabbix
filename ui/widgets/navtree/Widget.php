<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\NavTree;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	// Max depth of navigation tree.
	public const MAX_DEPTH = 10;

	public function getDefaultName(): string {
		return _('Map navigation tree');
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'Add' => _s('Add'),
				'Add child element' => _s('Add child elements'),
				'Add multiple maps' => _s('Add multiple maps'),
				'Apply' => _s('Apply'),
				'Cancel' => _s('Cancel'),
				'Edit' => _s('Edit'),
				'Edit tree element' => _s('Edit tree element'),
				'Remove' => _s('Remove')
			]
		];
	}
}
