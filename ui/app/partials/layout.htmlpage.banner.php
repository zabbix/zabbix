<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * @var CPartial $this
 * @var array $data
 */

$content = $data['banner']['content'];

$banner_content = (new CDiv())
	->addClass(ZBX_STYLE_BANNER_CONTENT)
	->addItem(new CHtmlEntity($content[$data['user']['lang']] ?? $content['all']));

$banner_close = (new CButtonIcon(ZBX_ICON_TIMES))
	->addClass(ZBX_STYLE_BANNER_CLOSE)
	->setAttribute('role', 'button');

(new CDiv([$banner_content, $banner_close]))
	->addClass(ZBX_STYLE_BANNER)
	->show();
