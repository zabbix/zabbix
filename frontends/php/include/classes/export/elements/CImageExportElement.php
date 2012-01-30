<?php

class CImageExportElement extends CExportElement{

	public function __construct(array $image) {
		parent::__construct('image', $image);
	}

	protected function requiredFields() {
		return array('encodedImage', 'name', 'imagetype');
	}

}
