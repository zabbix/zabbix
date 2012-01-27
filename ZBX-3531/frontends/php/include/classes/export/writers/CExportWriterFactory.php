<?php

class CExportWriterFactory {
	public static function getWriter($type) {
		switch ($type) {
			case 'DOM':
				return new CDomExportWriter();
			case 'XMLWriter':
				return new CXmlWriterExportWriter();
			default:
				throw new Exception('Incorrect export writer type.');
		}
	}
}
