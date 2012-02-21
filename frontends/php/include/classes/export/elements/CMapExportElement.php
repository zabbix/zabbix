<?php

class CMapExportElement extends CExportElement {

	public function __construct(array $map) {
		parent::__construct('map', $map);

		$this->addIconMap($map['iconmap']);
		$this->addUrls($map['urls']);
		$this->addSelements($map['selements']);
		$this->addLinks($map['links']);
	}

	protected function requiredFields() {
		return array('name', 'width', 'height', 'label_type', 'label_location', 'highlight', 'expandproblem',
			'markelements', 'show_unack', 'grid_size', 'grid_show', 'grid_align', 'label_format', 'label_type_host',
			'label_type_hostgroup', 'label_type_trigger', 'label_type_map', 'label_type_image', 'label_string_host',
			'label_string_hostgroup', 'label_string_trigger', 'label_string_map', 'label_string_image', 'expand_macros');
	}

	protected function addIconMap(array $iconMap) {
		$this->addElement(new CExportElement('iconmap', $iconMap));
	}

	protected function addUrls(array $urls) {
		$mapUrlsElement = new CExportElement('urls');
		foreach ($urls as $url) {
			$mapUrlsElement->addElement(new CMapUrlExportElement($url));
		}
		$this->addElement($mapUrlsElement);
	}

	protected function addSelements(array $selements) {
		$mapSelementsElement = new CExportElement('selements');
		foreach ($selements as $selement) {
			$mapSelementsElement->addElement(new CMapSelementExportElement($selement));
		}
		$this->addElement($mapSelementsElement);
	}

	protected function addLinks(array $links) {
		$mapLinksElement = new CExportElement('links');
		foreach ($links as $link) {
			$mapLinksElement->addElement(new CMapLinkExportElement($link));
		}
		$this->addElement($mapLinksElement);
	}

}
