<?php

class CJsonExportWriter implements CExportWriter {

	public function write(array $array) {
		return json_encode($array);
	}
}
