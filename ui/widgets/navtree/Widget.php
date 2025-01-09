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
				'Add' => _('Add'),
				'Add child element' => _('Add child element'),
				'Add multiple maps' => _('Add multiple maps'),
				'Apply' => _('Apply'),
				'Cancel' => _('Cancel'),
				'Edit' => _('Edit'),
				'Edit tree element' => _('Edit tree element'),
				'Remove' => _('Remove'),
				'root' => _('root')
			]
		];
	}
}
