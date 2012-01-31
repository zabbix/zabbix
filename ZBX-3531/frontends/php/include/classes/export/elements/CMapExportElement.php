<?php

class CMapExportElement extends CExportElement {

	private $references = array();


	public function __construct(array $map) {
		parent::__construct('map', $map);

		$this->references = array(
			'num' => 1,
			'refs' => array()
		);

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
			$refNum = $this->references['num']++;
			$referenceKey = 'sel'.$refNum;
			$selement['selement_ref'] = $referenceKey;

			$this->references['refs'][$selement['selementid']] = $referenceKey;

			$mapSelementsElement->addElement(new CMapSelementExportElement($selement));
		}
		$this->addElement($mapSelementsElement);
	}

	protected function addLinks(array $links) {
		$mapLinksElement = new CExportElement('links');
		foreach ($links as $link) {
			$link['selement_ref1'] = $this->references['refs'][$link['selementid1']];
			$link['selement_ref2'] = $this->references['refs'][$link['selementid2']];

			$mapLinksElement->addElement(new CMapLinkExportElement($link));
		}
		$this->addElement($mapLinksElement);
	}

}
