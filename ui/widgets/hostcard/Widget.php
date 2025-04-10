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


namespace Widgets\HostCard;

use Zabbix\Core\CWidget;

use Widgets\HostCard\Includes\CWidgetFieldHostSections;

class Widget extends CWidget {

	public const ZBX_STYLE_CLASS = 'host-card';
	public const ZBX_STYLE_SECTIONS = 'sections';
	public const ZBX_STYLE_SECTION = 'section';
	public const ZBX_STYLE_SECTION_NAME = 'section-name';
	public const ZBX_STYLE_SECTION_BODY = 'section-body';

	public function getDefaultName(): string {
		return _('Host card');
	}

	public function getInitialFieldsValues(): array {
		return [
			'sections' => [
				CWidgetFieldHostSections::SECTION_MONITORING,
				CWidgetFieldHostSections::SECTION_AVAILABILITY,
				CWidgetFieldHostSections::SECTION_MONITORED_BY
			]
		];
	}
}
