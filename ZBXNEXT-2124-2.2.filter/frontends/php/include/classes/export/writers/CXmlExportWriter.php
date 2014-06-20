<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CXmlExportWriter extends CExportWriter {

	/**
	 * @var XMLWriter
	 */
	protected $xmlWriter;

	public function __construct() {
		$this->xmlWriter = new XMLWriter();
	}

	/**
	 * Converts array with export data to XML format.
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	public function write(array $array) {
		$this->xmlWriter->openMemory();
		$this->xmlWriter->setIndent($this->formatOutput);
		$this->xmlWriter->setIndentString('    ');
		$this->xmlWriter->startDocument('1.0', 'UTF-8');

		$this->fromArray($array);

		$this->xmlWriter->endDocument();

		return $this->xmlWriter->outputMemory();
	}

	/**
	 * Recursive function for processing nested arrays.
	 *
	 * @param array $array
	 * @param null  $parentName name of parent node
	 */
	protected function fromArray(array $array, $parentName = null) {
		foreach ($array as $name => $value) {
			if ($newName = $this->mapName($parentName)) {
				$this->xmlWriter->startElement($newName);
			}
			else {
				$this->xmlWriter->startElement($name);
			}

			if (is_array($value)) {
				$this->fromArray($value, $name);
			}
			elseif (!zbx_empty($value)) {
				$this->xmlWriter->text($value);
			}

			$this->xmlWriter->endElement();
		}
	}

	/**
	 * Returns sub node name based on parent node name.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	private function mapName($name) {
		$map = array(
			'groups' => 'group',
			'templates' => 'template',
			'hosts' => 'host',
			'interfaces' => 'interface',
			'applications' => 'application',
			'items' => 'item',
			'discovery_rules' => 'discovery_rule',
			'item_prototypes' => 'item_prototype',
			'trigger_prototypes' => 'trigger_prototype',
			'graph_prototypes' => 'graph_prototype',
			'host_prototypes' => 'host_prototype',
			'group_links' => 'group_link',
			'group_prototypes' => 'group_prototype',
			'triggers' => 'trigger',
			'dependencies' => 'dependency',
			'screen_items' => 'screen_item',
			'macros' => 'macro',
			'screens' => 'screen',
			'images' => 'image',
			'graphs' => 'graph',
			'graph_items' => 'graph_item',
			'maps' => 'map',
			'urls' => 'url',
			'selements' => 'selement',
			'links' => 'link',
			'linktriggers' => 'linktrigger',
		);

		return isset($map[$name]) ? $map[$name] : false;
	}
}
