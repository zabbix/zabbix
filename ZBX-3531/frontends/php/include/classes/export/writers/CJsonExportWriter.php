<?php

class CJsonExportWriter extends CExportWriter {

	public function write(array $array) {
		$json = new CJSON();
		return $json->encode($array);
	}
}
