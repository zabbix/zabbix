# MySQL Docker
The MySQL-related database management system dockers intended for Zabbix agent 2 MySQL plugin integration tests. Each docker folder contains MySQL-related vendor and version specific docker configuration consisting of master (source) and slave (replica) server instances. The instances are pre-configured for Zabbix use.
Each master server instance or service runs on forwared port 33061, slave - 33062. The slave instance is necessary for testing replication status keys. Other keys can be tested using only master instance.

# How To Build
Use Makefile for building and running specific MySQL-related service, e.g. `make  buildup-mysql-9.3`. You cannot run more than one vendor instances simultaniously because the same ports ar used. To stop the instances and delete containers use `make down`.

# How To Connect
Since the ports are forwarded, use localhost or loopback IP 127.0.0.1 in MySQL metric keys, e.g., using zabbix_get utility: `./zabbix_get -s localhost -p 10050 -k mysql.replication.get_slave_status[localhost:33062,zbx_monitor,zabbix]`. Connect databases directly, e.g., using mysql utility: `mysql -h 127.0.0.1 -P 33061 -u zbx_monitor -pzabbix -e "SHOW DATABASES;"`
