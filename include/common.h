#ifndef MON_COMMON_H
#define MON_COMMON_H
 
#define	SUCCEED		0
#define	FAIL		(-1)
#define	NOTSUPPORTED	(-2)

#define	MAXFD	64

/* Item types */

#define ITEM_TYPE_ZABBIX 0
#define ITEM_TYPE_SNMP   1

#define	SUCKER_FORKS	11
#define	SUCKER_DELAY	30

/* Housekeeping frequency */
#define	SUCKER_HK	3600

#define	SUCKER_TIMEOUT	5
#define	AGENT_TIMEOUT	3

#define	SENDER_TIMEOUT	5
#define	TRAPPER_TIMEOUT	5

#endif
