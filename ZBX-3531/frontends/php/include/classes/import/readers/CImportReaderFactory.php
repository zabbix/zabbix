<?php

class CImportReaderFactory {

	const XML = 'xml';
	const JSON = 'json';

	public static function getReader($type) {
		switch ($type) {
			case 'xml':
				return new CXmlImportReader();
			case 'json':
				return new CJsonImportReader();
			default:
				throw new Exception('Incorrect import reader type.');
		}
	}
}
