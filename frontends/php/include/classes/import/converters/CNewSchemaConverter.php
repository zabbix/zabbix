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


/**
 * Add missed tags. Convert constant to value.
 * Use new XML schema.
 */
class CNewSchemaConverter extends CConverter {

	public function convert($data) {
		if ($data['zabbix_export']['version'] < 4.4) {
			return $data;
		}

		$schema = include(__DIR__ . '/../../xml/schema.php');

		foreach ($schema as $tag_class) {
			$tag = $tag_class->getTag();

			if (!array_key_exists($tag, $data['zabbix_export'])) {
				continue;
			}

			$data['zabbix_export'][$tag] = $this->converter($tag_class, $data['zabbix_export'][$tag]);
		}

		return $data;
	}

	protected function replaceValue(array $schema, array $data) {
		foreach ($schema as $tag => $tag_class) {
			if (!array_key_exists($tag, $data) || $tag_class instanceof CXmlTagString) {
				$data[$tag] = $tag_class->fromXml($data);
				continue;
			}

			if ($tag_class instanceof CXmlTagIndexedArray) {
				$data[$tag] = $this->convertIndexedArray($tag_class, $data[$tag]);
			}
			if ($tag_class instanceof CXmlTagArray) {
				$data[$tag] = $this->convertArray($tag_class, $data[$tag]);
			}
		}

		return $data;
	}

	protected function convertIndexedArray(CXmlTag $class, array $data) {
		$class_tag = $class->getTag();
		$schema = $class->buildSchema();

		if ($class instanceof CXmlTagIndexedArray) { // FIXME: rewrite this code piece
			$class_tag = CXmlDefine::$subtags[$class->getTag()];
			$schema = $class->getNextSchema();
			$schema = $schema[$class_tag]->buildSchema();
		}

		$count = count($data);
		for ($i = 0; $i < $count; $i++) {
			$data[$class_tag . ($i > 0 ? $i : '')] = $this->replaceValue(
				$schema[$class_tag],
				$data[$class_tag . ($i > 0 ? $i : '')]
			);
		}

		return $data;
	}

	protected function convertArray(CXmlTag $class, array $data) {
		$schema = $class->buildSchema();

		return $this->replaceValue($schema[$class->getTag()], $data);
	}

	protected function converter(CXmlTag $class, array $data) {
		$schema = $class->getNextSchema();

		foreach($schema as $tag_class) {
			if ($class instanceof CXmlTagIndexedArray) {
				return $this->convertIndexedArray($tag_class, $data);
			}
		}

		return $data;
	}
}
