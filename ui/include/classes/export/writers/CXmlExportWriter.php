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
				// Value containing only whitespace characters should be saved as CDATA.
				if (trim($value) === '') {
					$this->xmlWriter->writeCData($value);
				}
				else {
					$this->xmlWriter->text($value);
				}
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
		$map = [
			'conditions' => 'condition',
			'dashboards' => 'dashboard',
			'dependencies' => 'dependency',
			'discovery_rules' => 'discovery_rule',
			'elements' => 'element',
			'fields' => 'field',
			'graph_items' => 'graph_item',
			'graph_prototypes' => 'graph_prototype',
			'graphs' => 'graph',
			'group_links' => 'group_link',
			'group_prototypes' => 'group_prototype',
			'host_groups' => 'host_group',
			'template_groups' => 'template_group',
			'groups' => 'group',
			'headers' => 'header',
			'host_prototypes' => 'host_prototype',
			'hosts' => 'host',
			'httptests' => 'httptest',
			'images' => 'image',
			'interfaces' => 'interface',
			'item_prototypes' => 'item_prototype',
			'items' => 'item',
			'lines' => 'line',
			'links' => 'link',
			'linktriggers' => 'linktrigger',
			'lld_macro_paths' => 'lld_macro_path',
			'macros' => 'macro',
			'mappings' => 'mapping',
			'maps' => 'map',
			'media_types' => 'media_type',
			'message_templates' => 'message_template',
			'operations' => 'operation',
			'overrides' => 'override',
			'pages' => 'page',
			'parameters' => 'parameter',
			'posts' => 'post_field',
			'preprocessing' => 'step',
			'query_fields' => 'query_field',
			'selements' => 'selement',
			'shapes' => 'shape',
			'steps' => 'step',
			'tags' => 'tag',
			'templates' => 'template',
			'tls_accept' => 'option',
			'trigger_prototypes' => 'trigger_prototype',
			'triggers' => 'trigger',
			'urls' => 'url',
			'valuemaps' => 'valuemap',
			'variables' => 'variable',
			'widgets' => 'widget'
		];

		return isset($map[$name]) ? $map[$name] : false;
	}
}
