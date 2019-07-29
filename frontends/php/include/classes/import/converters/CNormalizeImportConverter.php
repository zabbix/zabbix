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
class CNormalizeImportConverter extends CConverter {

	/**
	 * Schema builder
	 *
	 * @var CXmlSchemaBuilder
	 */
	private $builder;

	public function convert($data) {
		if ($data['zabbix_export']['version'] < 4.4) {
			return $data;
		}

		$this->builder = new CXmlSchemaBuilder;

		$schema = $this->builder->getFullSchema();

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
			if (!array_key_exists($tag, $data) || $tag_class instanceof CStringXmlTagInterface) {
				$data[$tag] = (new CTagImporter($tag_class))->import($data);
				continue;
			}

			if ($tag_class instanceof CIndexedArrayXmlTagInterface) {
				$data[$tag] = $this->convertIndexedArray($tag_class, $data[$tag]);
			}
			if ($tag_class instanceof CArrayXmlTagInterface) {
				$data[$tag] = $this->convertArray($tag_class, $data[$tag]);
			}
		}

		return $data;
	}

	protected function convertIndexedArray(CXmlTagInterface $class, array $data) {
		$class_tag = $class->getTag();
		$schema = $this->builder->build($class);

		// If it indexed array getting child tag schema.
		if ($class instanceof CIndexedArrayXmlTagInterface) {
			$schema = $this->builder->build($class);
			$class_tag = CXmlConstantValue::$subtags[$class->getTag()];
			$schema = $this->builder->build($schema[$class_tag]);
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

	protected function convertArray(CXmlTagInterface $class, array $data) {
		$schema = $this->builder->build($class);

		return $this->replaceValue($schema[$class->getTag()], $data);
	}

	protected function converter(CXmlTagInterface $class, array $data) {
		$schema = $this->builder->build($class);

		foreach($schema as $tag_class) {
			return $this->convertIndexedArray($tag_class, $data);
		}

		return $data;
	}
}
