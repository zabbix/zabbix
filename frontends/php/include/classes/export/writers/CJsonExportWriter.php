<?php

class CJsonExportWriter implements CExportWriter {

	public function write(array $array) {
		$json = new CJSON();
		return $json->encode($array);
	}
}
