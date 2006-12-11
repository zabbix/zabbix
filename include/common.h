/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

#ifndef ZABBIX_COMMON_H
#define ZABBIX_COMMON_H

#include "sysinc.h"

#include "zbxtypes.h"

#undef snprintf


#ifdef snprintf
#undef snprintf
#endif
#define snprintf	ERROR_DO_NOT_USE_SNPRINTF_FUNCTION_TRY_TO_USE_ZBX_SNPRINTF

#ifdef sprintf
#undef sprintf
#endif
#define sprintf		ERROR_DO_NOT_USE_SPRINTF_FUNCTION_TRY_TO_USE_ZBX_SNPRINTF

#ifdef strncpy
#undef strncpy
#endif
#define strncpy		ERROR_DO_NOT_USE_STRNCPY_FUNCTION_TRY_TO_USE_ZBX_STRLCPY

#ifdef vsprintf
#undef vsprintf
#endif
#define vsprintf	ERROR_DO_NOT_USE_VSPRINTF_FUNCTION_TRY_TO_USE_VSNPRINTF
/*#define strncat		ERROR_DO_NOT_USE_STRNCAT_FUNCTION_TRY_TO_USE_ZBX_STRLCAT*/

#define ON	1
#define OFF	0

#define	APPLICATION_NAME	"ZABBIX Agent"
#define	ZABBIX_REVDATE		"11 December 2006"
#define	ZABBIX_VERSION		"1.3.1"

#if defined(_WINDOWS)
/*#	pragma warning (disable: 4100)*/
#endif /* _WINDOWS */

#ifndef HAVE_GETOPT_LONG
	struct option {
		const char *name;
		int has_arg;
		int *flag;
		int val;
	};
#	define  getopt_long(argc, argv, optstring, longopts, longindex) getopt(argc, argv, optstring)
#endif /* ndef HAVE_GETOPT_LONG */

#define ZBX_UNUSED(a) ((void)0)(a)

#define	ZBX_FS_DBL	"%f"

#define MAX_LOG_FILE_LEN (1024*1024)

#define	SUCCEED		0
#define	FAIL		(-1)
#define	NOTSUPPORTED	(-2)
#define	NETWORK_ERROR	(-3)
#define	TIMEOUT_ERROR	(-4)
#define	AGENT_ERROR	(-5)

/*
#define ZBX_POLLER
*/

#ifdef ZBX_POLLER
	#define MAX_STRING_LEN	800
#else
	#define MAX_STRING_LEN	2048
#endif
#define MAX_BUF_LEN	65000

/* Item types */
#define ITEM_TYPE_ZABBIX	0
#define ITEM_TYPE_SNMPv1	1
#define ITEM_TYPE_TRAPPER	2
#define ITEM_TYPE_SIMPLE	3
#define ITEM_TYPE_SNMPv2c	4
#define ITEM_TYPE_INTERNAL	5
#define ITEM_TYPE_SNMPv3	6
#define ITEM_TYPE_ZABBIX_ACTIVE	7
#define ITEM_TYPE_AGGREGATE	8

/* Item value types */
#define ITEM_VALUE_TYPE_FLOAT	0
#define ITEM_VALUE_TYPE_STR	1
#define ITEM_VALUE_TYPE_LOG	2
#define ITEM_VALUE_TYPE_UINT64	3
#define ITEM_VALUE_TYPE_TEXT	4

/* Item snmpv3 security levels */
#define ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV	0
#define ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV	1
#define ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV	2

/* Item multiplier types */
#define ITEM_MULTIPLIER_DO_NOT_USE		0
#define ITEM_MULTIPLIER_USE			1

/* Item delta types */
#define ITEM_STORE_AS_IS		0
#define ITEM_STORE_SPEED_PER_SECOND	1
#define ITEM_STORE_SIMPLE_CHANGE	2

/* Recipient types for actions */
#define RECIPIENT_TYPE_USER	0
#define RECIPIENT_TYPE_GROUP	1

/* Condition types */
#define CONDITION_TYPE_HOST_GROUP	0
#define CONDITION_TYPE_HOST		1
#define CONDITION_TYPE_TRIGGER		2
#define CONDITION_TYPE_TRIGGER_NAME	3
#define CONDITION_TYPE_TRIGGER_SEVERITY	4
#define CONDITION_TYPE_TRIGGER_VALUE	5
#define CONDITION_TYPE_TIME_PERIOD	6

/* Condition operators */
#define	CONDITION_OPERATOR_EQUAL	0
#define	CONDITION_OPERATOR_NOT_EQUAL	1
#define	CONDITION_OPERATOR_LIKE		2
#define	CONDITION_OPERATOR_NOT_LIKE	3
#define	CONDITION_OPERATOR_IN		4
#define	CONDITION_OPERATOR_MORE_EQUAL	5
#define	CONDITION_OPERATOR_LESS_EQUAL	6

/* Special item key used for storing server status */
#define SERVER_STATUS_KEY	"status"
/* Special item key used for ICMP pings */
#define SERVER_ICMPPING_KEY	"icmpping"
/* Special item key used for ICMP ping latency */
#define SERVER_ICMPPINGSEC_KEY	"icmppingsec"
/* Special item key used for internal ZABBIX log */
#define SERVER_ZABBIXLOG_KEY	"zabbix[log]"

/* Alert types */
#define ALERT_TYPE_EMAIL	0
#define ALERT_TYPE_EXEC		1
#define ALERT_TYPE_SMS		2

/* Alert statuses */
#define ALERT_STATUS_NOT_SENT	0
#define ALERT_STATUS_SENT	1

/* Item statuses */
#define ITEM_STATUS_ACTIVE	0
#define ITEM_STATUS_DISABLED	1
/*#define ITEM_STATUS_TRAPPED	2*/
#define ITEM_STATUS_NOTSUPPORTED	3
#define ITEM_STATUS_DELETED	4
#define ITEM_STATUS_NOTAVAILABLE	5

/* Host statuses */
#define HOST_STATUS_MONITORED	0
#define HOST_STATUS_NOT_MONITORED	1
/*#define HOST_STATUS_UNREACHABLE	2*/
#define HOST_STATUS_TEMPLATE	3
#define HOST_STATUS_DELETED	4

/* Host availability */
#define HOST_AVAILABLE_UNKNOWN	0
#define HOST_AVAILABLE_TRUE	1
#define HOST_AVAILABLE_FALSE	2

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

/* Action statuses */
#define ACTION_STATUS_ACTIVE	0
#define ACTION_STATUS_DISABLED	1

/* Action type */
#define ACTION_TYPE_MESSAGE	0
#define ACTION_TYPE_COMMAND	1

/* Algorithms for service status calculation */
#define SERVICE_ALGORITHM_NONE	0
#define SERVICE_ALGORITHM_MAX	1
#define SERVICE_ALGORITHM_MIN	2

/* Types of nodes check sums */
#define	NODE_CKSUM_TYPE_OLD	0
#define	NODE_CKSUM_TYPE_NEW	1

/* Types of operation in config log */
#define	NODE_CONFIGLOG_OP_UPDATE	0
#define	NODE_CONFIGLOG_OP_ADD		1
#define	NODE_CONFIGLOG_OP_DELETE	2

#define	ZBX_TYPE_INT	0
#define	ZBX_TYPE_CHAR	1
#define	ZBX_TYPE_FLOAT	2
#define	ZBX_TYPE_BLOB	3
#define	ZBX_TYPE_TEXT	4
#define	ZBX_TYPE_UINT	5
#define	ZBX_TYPE_ID	6

/* Flags for node history exchange */
#define	ZBX_TABLE_HISTORY	0
#define	ZBX_TABLE_HISTORY_UINT	1
#define	ZBX_TABLE_HISTORY_STR	2
#define	ZBX_TABLE_HISTORY_LOG	3
#define	ZBX_TABLE_HISTORY_TEXT	4

/* Flags */
#define	ZBX_SYNC	1

/* Types of nodes */
#define	ZBX_NODE_TYPE_REMOTE	0
#define	ZBX_NODE_TYPE_LOCAL	1

#define	POLLER_DELAY	5

#define	ZBX_POLLER_TYPE_NORMAL		0
#define	ZBX_POLLER_TYPE_UNREACHABLE	1

#define	POLLER_TIMEOUT	5
/* Do not perform more than this number of checks during unavailability period */
/*#define SLEEP_ON_UNREACHABLE		60*/
/*#define CHECKS_PER_UNAVAILABLE_PERIOD	4*/

#define	AGENT_TIMEOUT	3

#define	SENDER_TIMEOUT		5
#define	TRAPPER_TIMEOUT		5
#define	SNMPTRAPPER_TIMEOUT	5

#ifndef MAX
#	define MAX(a, b) ((a)>(b) ? (a) : (b))
#endif

#ifndef MIN					   
#	define MIN(a, b) ((a)<(b) ? (a) : (b))
#endif
				    
/* Secure string copy */
#define strscpy(x,y) zbx_strlcpy(x,y,sizeof(x))

#define zbx_free(ptr) { if(ptr){ free(ptr); ptr = NULL; } }
#define zbx_fclose(f) { if(f){ fclose(f); f = NULL; } }

/* list structure as item of agent return vaile */					 
#define ZBX_LIST_ITEM struct zbx_list_item_s
ZBX_LIST_ITEM {
	char text[MAX_STRING_LEN];
};	

#define ZBX_LIST struct zbx_list_s
ZBX_LIST {
	int 		cnt;
	ZBX_LIST_ITEM 	*item;
};	
					   
/* agent return value */					 
#define AGENT_RESULT struct zbx_result_s
AGENT_RESULT {
	int	 	type;
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
	char		*text;
	char		*msg;
	ZBX_LIST	list;
};

#define ZBX_COND_NODEID " %s>=100000000000000*%d and %s<=(100000000000000*%d+99999999999999) "
#define LOCAL_NODE(fieldid) fieldid, CONFIG_NODEID, fieldid, CONFIG_NODEID
#define ZBX_NODE(fieldid,nodeid) fieldid, nodeid, fieldid, nodeid

/* agent result types */
#define AR_UINT64	1
#define AR_DOUBLE	2
#define AR_STRING	4
#define AR_MESSAGE	8
#define AR_LIST		16
#define AR_TEXT		32


/* SET RESULT */

#define SET_DBL_RESULT(res, val) \
	{ \
	(res)->type |= AR_DOUBLE; \
	(res)->dbl = (double)(val); \
	}

#define SET_UI64_RESULT(res, val) \
	{ \
	(res)->type |= AR_UINT64; \
	(res)->ui64 = (zbx_uint64_t)(val); \
	}

#define SET_STR_RESULT(res, val) \
	{ \
	(res)->type |= AR_STRING; \
	(res)->str = (char*)(val); \
	} 

#define SET_TEXT_RESULT(res, val) \
	{ \
	(res)->type |= AR_TEXT; \
	(res)->text = (char*)(val); \
	} 

#define SET_MSG_RESULT(res, val) \
	{ \
	(res)->type |= AR_MESSAGE; \
	(res)->msg = (char*)(val); \
	}

/* UNSER RESULT */

#define UNSET_DBL_RESULT(res)           \
	{                               \
	(res)->type &= ~AR_DOUBLE;      \
	(res)->dbl = (double)(0);        \
	}

#define UNSET_UI64_RESULT(res)             \
	{                                  \
	(res)->type &= ~AR_UINT64;         \
	(res)->ui64 = (zbx_uint64_t)(0); \
	}

#define UNSET_STR_RESULT(res)                      \
	{                                          \
		if((res)->type & AR_STRING){       \
			free((res)->str);          \
			(res)->str = NULL;         \
			(res)->type &= ~AR_STRING; \
		}                                  \
	}

#define UNSET_TEXT_RESULT(res)                   \
	{                                        \
		if((res)->type & AR_TEXT){       \
			free((res)->text);       \
			(res)->text = NULL;      \
			(res)->type &= ~AR_TEXT; \
		}                                \
	}

#define UNSET_MSG_RESULT(res)                       \
	{                                           \
		if((res)->type & AR_MESSAGE){       \
			free((res)->msg);           \
			(res)->msg = NULL;          \
			(res)->type &= ~AR_MESSAGE; \
		}                                   \
	}


extern char *progname;
extern char title_message[];
extern char usage_message[];
extern char *help_message[];

void	help();
void	usage();
void	version();

/* MAX Length of base64 data */
#define ZBX_MAX_B64_LEN 16*1024

typedef enum
{
	ZBX_TASK_START = 0,
	ZBX_TASK_SHOW_HELP,
	ZBX_TASK_SHOW_VERSION,
	ZBX_TASK_PRINT_SUPPORTED,
	ZBX_TASK_TEST_METRIC,
	ZBX_TASK_SHOW_USAGE,
	ZBX_TASK_INSTALL_SERVICE,
	ZBX_TASK_UNINSTALL_SERVICE,
	ZBX_TASK_START_SERVICE,
	ZBX_TASK_STOP_SERVICE,
	ZBX_TASK_CHANGE_NODEID
} zbx_task_t;

void   	init_result(AGENT_RESULT *result);
int    	copy_result(AGENT_RESULT *src, AGENT_RESULT *dist);
void   	free_result(AGENT_RESULT *result);

char	*string_replace(char *str, const char *sub_str1, const char *sub_str2);
void	del_zeroes(char *s);
int	find_char(char *str,char c);
int	is_double_prefix(char *str);
int	is_double(char *c);
int	is_uint(char *c);
void	lrtrim_spaces(char *c);
void	ltrim_spaces(char *c);
void	rtrim_spaces(char *c);
void	delete_reol(char *c);
int	get_param(const char *param, int num, char *buf, int maxlen);
int	num_param(const char *param);
int	calculate_item_nextcheck(int itemid, int item_type, int delay, char *delay_flex, time_t now);
int	check_time_period(const char *period, time_t now);
void	zbx_setproctitle(const char *fmt, ...);

#define ZBX_JAN_1970_IN_SEC   2208988800.0        /* 1970 - 1900 in seconds */
double	zbx_time(void);
double	zbx_current_time (void);

void	zbx_error(const char *fmt, ...);
int	zbx_snprintf(char* str, size_t count, const char *fmt, ...);
int	zbx_vsnprintf(char* str, size_t count, const char *fmt, va_list args);
void	zbx_snprintf_alloc(char **str, int *alloc_len, int *offset, int max_len, const char *fmt, ...);

int	set_result_type(AGENT_RESULT *result, int value_type, char *c);
size_t	zbx_strlcpy(char *dst, const char *src, size_t siz);
size_t	zbx_strlcat(char *dst, const char *src, size_t siz);

int	replace_param(const char *cmd, const char *param, char *out, int outlen);

int	xml_get_data(char *xml,char *tag, char *data, int maxlen);
int	comms_create_request(char *host, char *key, char *data, char *lastlogsize, char *request,int maxlen);
int	comms_parse_response(char *xml,char *host,char *key, char *data, char *lastlogsize, char *timestamp,
		               char *source, char *severity, int maxlen);

int 	parse_command(const char *command, char *cmd, int cmd_max_len, char *param, int param_max_len);

/* Regular expressions */
char    *zbx_regexp_match(const char *string, const char *pattern, int *len);

/* Misc functions */
int	cmp_double(double a,double b);
int     zbx_get_field(char *line, char *result, int num, char delim);

void	zbx_on_exit();

int	get_nodeid_by_id(zbx_uint64_t id);

int       SYSTEM_LOCALTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

int MAIN_ZABBIX_ENTRY(void);

#endif
