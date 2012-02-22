<?php

class CXmlExportWriterA extends XMLWriter implements CExportWriter {

	public function write(array $array) {
		$this->openMemory();
		$this->setIndent(true);
		$this->setIndentString('    ');
		$this->startDocument('1.0', 'UTF-8');

		$this->fromArray($array);

		$this->endDocument();

		return $this->outputMemory();
	}

	private function fromArray(array $array, $parentName = null) {
		foreach ($array as $name => $value) {
			if ($newName = $this->mapName($parentName)) {
				$this->startElement($newName);
			}
			else {
				$this->startElement($name);
			}

			if (is_array($value)) {
				$this->fromArray($value, $name);
			}
			else {
				$this->text($value);
			}

			$this->endElement();
		}
	}

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
