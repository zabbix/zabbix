<?php

class CJsonImportReader extends CImportReader {

	public function read($string) {
		$json = new CJSON;
		return $json->decode($string, true);
	}
}
