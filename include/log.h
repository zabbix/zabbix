#ifndef ZABBIX_LOG_H
#define ZABBIX_LOG_H

#define LOG_PRIORITY_DEBUG	0
#define LOG_PRIORITY_NOTICE	1
#define LOG_PRIORITY_WARNING	2
#define LOG_PRIORITY_ERR	3

#define LOG_TYPE_UNDEFINED	0
#define LOG_TYPE_SYSLOG		1
#define LOG_TYPE_FILE		2

/* Type - 0 (syslog), 1 - file */
int zabbix_open_log(int type,int level, const char *filename);
void zabbix_log(int level, const char *fmt, ...);

#endif
