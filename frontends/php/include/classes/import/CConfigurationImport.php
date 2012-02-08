<?php

class CConfigurationImport {

	/**
	 * @var CImportReader
	 */
	protected $reader;

	/**
	 * @var array
	 */
	protected $options;

	public function __construct($file, $options = array()) {
		$this->options = array(
			'groups' => array('missed' => true),
			'hosts' => array('exist' => true, 'missed' => true),
			'templates' => array('exist' => true, 'missed' => true),
			'template_linkages' => array('exist' => true, 'missed' => true),
			'items' => array('exist' => true, 'missed' => true),
			'discoveryrules' => array('exist' => true, 'missed' => true),
			'triggers' => array('exist' => true, 'missed' => true),
			'graphs' => array('exist' => true, 'missed' => true),
			'screens' => array('exist' => true, 'missed' => true),
			'maps' => array('exist' => true, 'missed' => true),
			'images' => array('exist' => false, 'missed' => false),
		);
		$this->options = array_merge($this->options, $options);

		$ext = pathinfo($file['name'], PATHINFO_EXTENSION);

		$this->reader = $this->getReader($ext);

		$this->data = $this->reader->read($file['tmp_name']);

		$this->formatter = $this->getFormatter($this->getImportVersion());
	}

	public function import() {
		$this->formatter->setData($this->data);

		if ($this->options['groups']['missed']) {
			$this->processGroups();
		}
		if ($this->options['hosts']['exist'] || $this->options['hosts']['missed']) {
			$this->processHosts();
		}

	}

	private function processGroups() {
		$groups = $this->formatter->getGroups();
		$groupNames = zbx_objectValues($groups, 'name');
		$dbGroups = API::HostGroup()->get(array(
			'filter' => array('name' => $groupNames),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$sepGroups = zbx_array_diff($groups, $dbGroups, 'name');

		if ($this->options['groups']['missed']) {
			API::HostGroup()->create($sepGroups['first']);
		}

	}

	private function processHosts() {
		$hosts = $this->formatter->getHosts();

		$hostNames = zbx_objectValues($hosts, 'host');
		$dbHosts = API::Host()->get(array(
			'filter' => array('host' => $hostNames),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		$sepHosts = zbx_array_diff($hosts, $dbHosts, 'host');

		if ($this->options['hosts']['missed'] && $sepHosts['first']) {
			API::Host()->create($sepHosts['first']);
		}
		if ($this->options['hosts']['exist'] && $sepHosts['both']) {
			API::Host()->update(array_values($sepHosts['both']));
		}

	}

	private function getReader($ext) {
		switch ($ext) {
			case 'xml':
				return new CXmlImportReader();

			default:
				throw new InvalidArgumentException('Unknown import file extension.');
		}

	}

	private function getFormatter($version) {
		switch ($version) {
			case '1.8':
				return new C18ImportFormatter;

			case '2.0':
				return new C20ImportFormatter;

			default:
				throw new InvalidArgumentException('Unknown import version.');
		}

	}

	private function getImportVersion() {
		return $this->data['zabbix_export']['version'];

	}

	private static function validate($schema) {
		libxml_use_internal_errors(true);

		$result = self::$xml->relaxNGValidate($schema);

		if (!$result) {
			$errors = libxml_get_errors();
			libxml_clear_errors();

			foreach ($errors as $error) {
				$text = '';

				switch ($error->level) {
					case LIBXML_ERR_WARNING:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Warning '.$error->code.': ';
						break;
					case LIBXML_ERR_ERROR:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Error '.$error->code.': ';
						break;
					case LIBXML_ERR_FATAL:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Fatal Error '.$error->code.': ';
						break;
				}

				$text .= trim($error->message).' [ Line: '.$error->line.' | Column: '.$error->column.' ]';
				throw new Exception($text);
			}
		}
		return true;
	}

}
