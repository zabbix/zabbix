<?php
namespace LLNW\Zabbix;

use Symfony\Component\Config\FileLocator;

class Config
{
    /**
     * Locates config file (namely {zabbix.conf.php, zabbix-llnw.conf.php}) in
     * system directory (/etc/zabbix) then in a typical /var/www install.
     * Additional lookup in current user's home directory is also supported for
     * overrides.
     * @param  string $filename
     * @param  array  $configDirectories
     * @return string config path info
     */
    public static function locateconfig(
        $filename='config.php',
        $configDirectories=array('/etc/zabbix', '/var/www/zabbix/conf')) {
        // Load current users' home directory for config overrides
        $home = NULL;
        if (($home = getmyuid()) && ($home = posix_getpwuid($home)) && !empty($home['dir'])) {
            array_unshift($configDirectories, $home['dir']);
        }

        $locator = new FileLocator($configDirectories);

        return $locator->locate($filename, null, true);
    }

    public static function includeZabbix()
    {
        require_once(self::locateconfig('zabbix.conf.php'));
    }

    public static function includeLLNWZabbix()
    {
        require_once(self::locateconfig('zabbix-llnw.conf.php'));
    }
}
