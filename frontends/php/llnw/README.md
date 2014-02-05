# LLNW Custom Zabbix API

[llnw-zbx API](https://confluence.atlas.llnw.com/display/CDNENG/M3+-+llnw-zbx+API+-+provides+custom+Zabbix+interaction)


## [Config](doc/CONFIG.md)


## Contributing

(See also: [Systems Architecture and Engineering: Standards: Systems Development](https://confluence.atlas.llnw.com/display/CDNENG/Systems+Development))

*  Make small logical changes.
*  Provide a meaningful commit message.
*  Check for coding errors with linting and test tools.
*  Publish your changes for review: `https://github.llnw.net/Zabbix/svn.zabbix.com/tree/F-LLNW-API`


### Project layout

*  `api_jsonrpc.php -> app/index.php` - front controller
*  `app` - application specific runtime files
*  `doc` - examples or extended documentation
*  `lib` - namespaced libs
*  `test` - tests to exercize Zabbix/LLNW functionality
*  `vendor` (controlled by: composer.json)
