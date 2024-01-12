<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
