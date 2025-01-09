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


namespace Widgets\Clock;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	// Clock type.
	public const TYPE_ANALOG = 0;
	public const TYPE_DIGITAL = 1;

	// Clock time zone format.
	public const TIMEZONE_SHORT = 0;
	public const TIMEZONE_FULL = 1;

	// Clock time format.
	public const HOUR_24 = 0;
	public const HOUR_12 = 1;

	// Form blocks.
	public const SHOW_DATE = 1;
	public const SHOW_TIME = 2;
	public const SHOW_TIMEZONE = 3;

	public function getDefaultName(): string {
		return _('Clock');
	}
}
