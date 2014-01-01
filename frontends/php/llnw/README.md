# LLNW Custom Zabbix API

[llnw-zbx API](https://confluence.atlas.llnw.com/display/CDNENG/M3+-+llnw-zbx+API+-+provides+custom+Zabbix+interaction)


### Configuration conventions

Configuration is managed by directory conventions (starting with system locations).


#### Refs

*  http://symfony.com/doc/current/components/config/resources.html


## Contributing

(See also: [Systems Architecture and Engineering: Standards: Systems Development](https://confluence.atlas.llnw.com/display/CDNENG/Systems+Development))

*  Make small logical changes.
*  Provide a meaningful commit message.
*  Check for coding errors with linting and test tools.
*  Publish your changes for review: `https://github.llnw.net/Zabbix/svn.zabbix.com/tree/F-LLNW-API`


### Project layout

*  `api_jsonrpc.php -> index.php` - front controller
*  `config.php` - ?? (TODO: push into lib dir)
*  `data` - data for setup or runtime
*  `doc` - examples or extended documentation
*  `lib` - namespaced libs
*  `tests` - tests to exercize Zabbix/LLNW functionality
*  `vendor` (controlled by: composer.json)
