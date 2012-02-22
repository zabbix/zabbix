<?php

class CExportWriterFactory {
	public static function getWriter($type) {
		switch ($type) {
			case 'DOM':
				return new CDomExportWriter();
			case 'XMLWriter':
				return new CXmlExportWriterA();
			case 'JSON':
				return new CJsonExportWriter();
			default:
				throw new Exception('Incorrect export writer type.');
		}
	}
}
