#ifndef ZABBIX_LOG_H
#define ZABBIX_LOG_H

#define LOG_LEVEL_EMPTY		0
#define LOG_LEVEL_CRIT		1
#define LOG_LEVEL_ERR		2
#define LOG_LEVEL_WARNING	3
#define LOG_LEVEL_DEBUG		4

#define LOG_TYPE_UNDEFINED	0
#define LOG_TYPE_SYSLOG		1
#define LOG_TYPE_FILE		2

/* Type - 0 (syslog), 1 - file */
int zabbix_open_log(int type,int level, const char *filename);
void zabbix_log(int level, const char *fmt, ...);

#endif
