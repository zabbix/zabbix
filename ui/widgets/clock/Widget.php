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
