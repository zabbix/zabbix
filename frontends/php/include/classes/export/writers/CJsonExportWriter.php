<?php

class CJsonExportWriter implements CExportWriter {

	public function write(CExportElement $elem) {
		return json_encode($elem->toArray());
	}

}
