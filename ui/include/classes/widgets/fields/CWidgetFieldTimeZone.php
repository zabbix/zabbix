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


namespace Zabbix\Widgets\Fields;

use CTimezoneHelper;

class CWidgetFieldTimeZone extends CWidgetFieldSelect {

	public const DEFAULT_VIEW = \CWidgetFieldTimeZoneView::class;
	public const DEFAULT_VALUE = '';

	public function __construct(string $name, ?string $label = null, ?array $values = null) {
		parent::__construct($name, $label, $values === null
			? [
				ZBX_DEFAULT_TIMEZONE => CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(),
					_('System default')
				),
				TIMEZONE_DEFAULT_LOCAL => _('Local default')
			] + CTimezoneHelper::getList()
			: null
		);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
	}
}
