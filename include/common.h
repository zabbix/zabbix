/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/


/*#define TESTTEST*/

#define	IT_HELPDESK

#ifndef ZABBIX_COMMON_H
#define ZABBIX_COMMON_H
 
#define	SUCCEED		0
#define	FAIL		(-1)
#define	NOTSUPPORTED	(-2)
#define	NETWORK_ERROR	(-3)
#define	TIMEOUT_ERROR	(-4)
#define	AGENT_ERROR	(-5)

#define	MAXFD	64
#define MAX_STRING_LEN	4096

/* Item types */
#define ITEM_TYPE_ZABBIX	0
#define ITEM_TYPE_SNMPv1	1
#define ITEM_TYPE_TRAPPER	2
#define ITEM_TYPE_SIMPLE	3
#define ITEM_TYPE_SNMPv2c	4
#define ITEM_TYPE_INTERNAL	5

/* Item value types */
#define ITEM_VALUE_TYPE_FLOAT	0
#define ITEM_VALUE_TYPE_STR	1

/* Recipient types for actions */
#define RECIPIENT_TYPE_USER	0
#define RECIPIENT_TYPE_GROUP	1

/* Special item key used for storing server status */
#define SERVER_STATUS_KEY	"status"
/* Special item key used for ICMP pings */
#define SERVER_ICMPPING_KEY	"icmpping"
/* Special item key used for ICMP ping latency */
#define SERVER_ICMPPINGSEC_KEY	"icmppingsec"

/* Alert types */
#define ALERT_TYPE_EMAIL	0
#define ALERT_TYPE_EXEC		1

/* Item statuses */
#define ITEM_STATUS_ACTIVE	0
#define ITEM_STATUS_DISABLED	1
/*#define ITEM_STATUS_TRAPPED	2*/
#define ITEM_STATUS_NOTSUPPORTED	3
#define ITEM_STATUS_DELETED	4

/* Host statuses */
#define HOST_STATUS_MONITORED	0
#define HOST_STATUS_NOT_MONITORED	1
#define HOST_STATUS_UNREACHABLE	2
#define HOST_STATUS_TEMPLATE	3
#define HOST_STATUS_DELETED	4

/* Use host IP or host name */
#define HOST_USE_HOSTNAME	0
#define HOST_USE_IP		1

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
#define SERVICE_ALGORITHM_MIN	2

/* Scope of action */
#define	ACTION_SCOPE_TRIGGER	0
#define	ACTION_SCOPE_HOST	1
#define	ACTION_SCOPE_HOSTS	2

#define	AGENTD_FORKS	5

#define AGENT_MAX_USER_COMMANDS	512

#define	TRAPPERD_FORKS	5

#define	SUCKER_FORKS	11
#define	SUCKER_DELAY	60

#define	SUCKER_TIMEOUT	5
/* Delay on network failure*/
#define DELAY_ON_NETWORK_FAILURE 60

#define	AGENT_TIMEOUT	3

#define	SENDER_TIMEOUT		5
#define	TRAPPER_TIMEOUT		5
#define	SNMPTRAPPER_TIMEOUT	5

/* Secure string copy */
#define strscpy(x,y) { strncpy(x,y,sizeof(x)); x[sizeof(x)-1]=0; }

#endif
