<?php

class CXmlExportWriter extends CExportWriter {

	protected $xmlWriter;

	public function __construct() {
		$this->xmlWriter = new XMLWriter();
	}

	public function write(array $array) {
		$this->xmlWriter->openMemory();
		$this->xmlWriter->setIndent(true);
		$this->xmlWriter->setIndentString('    ');
		$this->xmlWriter->startDocument('1.0', 'UTF-8');

		$this->fromArray($array);

		$this->xmlWriter->endDocument();

		return $this->xmlWriter->outputMemory();
	}

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
			elseif ($value !== null) {
				$this->xmlWriter->text($value);
			}

			$this->xmlWriter->endElement();
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
