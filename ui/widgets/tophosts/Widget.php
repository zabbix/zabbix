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


namespace Widgets\TopHosts;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public const DEFAULT_FILL = '#97AAB3';

	public const ORDER_TOP_N = 2;
	public const ORDER_BOTTOM_N = 3;

	public function getDefaultName(): string {
		return _('Top hosts');
	}
}
