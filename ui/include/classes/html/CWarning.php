<?php
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


class CWarning extends Ctag {

	public function __construct($header, $messages = [], $buttons = []) {
		parent::__construct('output', true);

		$this
			->addItem(
				new CSpan($header)
			)
			->addClass(ZBX_STYLE_MSG_GLOBAL)
			->addClass(ZBX_STYLE_MSG_BAD);

		if ($messages) {
			$this
				->addItem(
					(new CDiv(
						(new CList($messages))->addClass(ZBX_STYLE_LIST_DASHED)
					))->addClass(ZBX_STYLE_MSG_DETAILS)
				)
				->addClass(ZBX_STYLE_COLLAPSIBLE);
		}

		$this->addItem(
			(new CDiv($buttons))->addClass('msg-buttons')
		);
	}
}
