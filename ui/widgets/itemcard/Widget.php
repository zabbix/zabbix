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


namespace Widgets\ItemCard;

use Zabbix\Core\CWidget;

use Widgets\ItemCard\Includes\CWidgetFieldItemSections;

class Widget extends CWidget {

	public const ZBX_STYLE_CLASS = 'item-card';
	public const ZBX_STYLE_SECTIONS = 'sections';
	public const ZBX_STYLE_SECTION = 'section';
	public const ZBX_STYLE_SECTION_NAME = 'section-name';
	public const ZBX_STYLE_SECTION_BODY = 'section-body';

	public function getDefaultName(): string {
		return _('Item card');
	}

	public function getInitialFieldsValues(): array {
		return [
			'sections' => [
				CWidgetFieldItemSections::SECTION_INTERVAL_AND_STORAGE,
				CWidgetFieldItemSections::SECTION_TYPE_OF_INFORMATION,
				CWidgetFieldItemSections::SECTION_HOST_INTERFACE,
				CWidgetFieldItemSections::SECTION_TYPE
			]
		];
	}

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'Empty value.' => _('Empty value.'),
				'Image loading error.' => _('Image loading error.')
			]
		];
	}
}
