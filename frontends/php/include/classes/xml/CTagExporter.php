<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CTagExporter {

	/**
	 * Tag class
	 *
	 * @var CExportXmlTagInterface
	 */
	private $tag;

	public function __construct(CExportXmlTagInterface $tag) {
		$this->tag = $tag;
	}

	public function export(array $data) {
		$handler = $this->tag->getExportHandler();
		if (is_callable($handler)) {
			return call_user_func($handler, $data, $this->tag);
		}

		if ($this->tag instanceof CStringXmlTagInterface && $this->tag->hasConstant()) {
			return $this->tag->getConstantByValue($data[$this->tag->getTag()]);
		}

		return $data[$this->tag->getTag()];
	}
}
