/*#define TESTTEST*/

#define	IT_HELPDESK

#ifndef ZABBIX_COMMON_H
#define ZABBIX_COMMON_H
 
#define	SUCCEED		0
#define	FAIL		(-1)
#define	NOTSUPPORTED	(-2)
#define	NETWORK_ERROR	(-3)
#define	TIMEOUT_ERROR	(-4)

#define	MAXFD	64
#define MAX_STRING_LEN	4096

/* Item types */
#define ITEM_TYPE_ZABBIX	0
#define ITEM_TYPE_SNMP		1

/* Item value types */
#define ITEM_VALUE_TYPE_FLOAT	0
#define ITEM_VALUE_TYPE_STR	1

/* Special item key used for storing server status */
#define SERVER_STATUS_KEY	"status"

/* Alert types */
#define ALERT_TYPE_EMAIL	"EMAIL"

/* Item statuses */
#define ITEM_STATUS_ACTIVE	0
#define ITEM_STATUS_DISABLED	1
#define ITEM_STATUS_TRAPPED	2
#define ITEM_STATUS_NOTSUPPORTED	3

/* Host statuses */
#define HOST_STATUS_MONITORED	0
#define HOST_STATUS_NOT_MONITORED	1
#define HOST_STATUS_UNREACHABLE	2

/* Trigger statuses */
/*#define TRIGGER_STATUS_FALSE	0
#define TRIGGER_STATUS_TRUE	1
#define TRIGGER_STATUS_DISABLED	2
#define TRIGGER_STATUS_UNKNOWN	3
#define TRIGGER_STATUS_NOTSUPPORTED	4*/

/* Trigger statuses */
#define TRIGGER_STATUS_ENABLED	0
#define TRIGGER_STATUS_DISABLED	1

/* Trigger values */
#define TRIGGER_VALUE_FALSE	0
#define TRIGGER_VALUE_TRUE	1
#define TRIGGER_VALUE_UNKNOWN	2

/* Media statuses */
#define MEDIA_STATUS_ACTIVE	0
#define MEDIA_STATUS_DISABLED	1

/* Algorithms for service status calculation */
#define SERVICE_ALGORITHM_NONE	0
#define SERVICE_ALGORITHM_MAX	1

#define	AGENTD_FORKS	5

#define AGENT_MAX_USER_COMMANDS	512

#define	TRAPPERD_FORKS	5

#define	SUCKER_FORKS	11
#define	SUCKER_DELAY	60

#define	SUCKER_TIMEOUT	5
/* Delay on network failure*/
#define DELAY_ON_NETWORK_FAILURE 60

#define	AGENT_TIMEOUT	3

#define	SENDER_TIMEOUT	5
#define	TRAPPER_TIMEOUT	5

#endif
