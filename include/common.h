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

#define SDI(msg)	fprintf(stderr, "%6li:DEBUG INFO: %s\n", zbx_get_thread_id(), msg); fflush(stderr);
#define SDI2(msg,p1)	fprintf(stderr, "%6li:DEBUG INFO: " msg "\n", zbx_get_thread_id(), p1); fflush(stderr);

#if defined(ENABLE_CHECK_MEMOTY)
#	include "crtdbg.h"

#	define REINIT_CHECK_MEMORY() \
		_CrtMemCheckpoint(&oldMemState)

#	define INIT_CHECK_MEMORY() \
		char DumpMessage[0x1FF]; \
		_CrtMemState  oldMemState, newMemState, diffMemState; \
		REINIT_CHECK_MEMORY()

#	define CHECK_MEMORY(fncname, msg) \
		DumpMessage[0] = '\0'; \
		_CrtMemCheckpoint(&newMemState); \
		if(_CrtMemDifference(&diffMemState, &oldMemState, &newMemState)) \
		{ \
			zbx_snprintf(DumpMessage, sizeof(DumpMessage), \
				"%s\n" \
				"free:  %10li bytes in %10li blocks\n" \
				"normal:%10li bytes in %10li blocks\n" \
				"CRT:   %10li bytes in %10li blocks\n", \
				 \
				fncname ": Memory changed! (" msg ")\n", \
				 \
				(long) diffMemState.lSizes[_FREE_BLOCK], \
				(long) diffMemState.lCounts[_FREE_BLOCK], \
				 \
				(long) diffMemState.lSizes[_NORMAL_BLOCK], \
				(long) diffMemState.lCounts[_NORMAL_BLOCK], \
				 \
				(long) diffMemState.lSizes[_CRT_BLOCK], \
				(long) diffMemState.lCounts[_CRT_BLOCK]); \
		} \
		else \
		{ \
			zbx_snprintf(DumpMessage, sizeof(DumpMessage), \
					"%s: Memory OK! (%s)", fncname, msg); \
		} \
		SDI2("MEMORY_LEAK: %s", DumpMessage)
#else
#	define INIT_CHECK_MEMORY() ((void)0)
#	define CHECK_MEMORY(fncname, msg) ((void)0)
#endif

#include "sysinc.h"

#include "zbxtypes.h"

#ifndef va_copy
#	if defined(__va_copy)
#		define va_copy(d, s) __va_copy(d, s)
#	else
#		define va_copy(d, s) memcpy (&d,&s, sizeof(va_list))
#	endif /* __va_copy */
#endif /* va_copy */

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

#ifdef HAVE_ATOLL
#	define zbx_atoui64(str)	((zbx_uint64_t)atoll(str))
#else
#	define zbx_atoui64(str)	((zbx_uint64_t)atol(str))
#endif

#define zbx_atod(str)	strtod(str, (char **)NULL)

#define ON	1
#define OFF	0

#define	APPLICATION_NAME	"ZABBIX Agent"
#define	ZABBIX_REVDATE		"29 May 2007"
#define	ZABBIX_VERSION		"1.4.1"

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

#define ZBX_DM_DELIMITER	'\255'

/* Item types */
typedef enum
{
	ITEM_TYPE_ZABBIX = 0,
	ITEM_TYPE_SNMPv1,
	ITEM_TYPE_TRAPPER,
	ITEM_TYPE_SIMPLE,
	ITEM_TYPE_SNMPv2c,
	ITEM_TYPE_INTERNAL,
	ITEM_TYPE_SNMPv3,
	ITEM_TYPE_ZABBIX_ACTIVE,
	ITEM_TYPE_AGGREGATE,
	ITEM_TYPE_HTTPTEST,
	ITEM_TYPE_EXTERNAL
} zbx_item_type_t;

/* Event sources */
typedef enum
{
	EVENT_SOURCE_TRIGGERS = 0,
	EVENT_SOURCE_DISCOVERY
} zbx_event_source_t;

/* Event objects */
typedef enum
{
	EVENT_OBJECT_TRIGGER = 0,
	EVENT_OBJECT_DHOST,
	EVENT_OBJECT_DSERVICE
} zbx_event_object_t;

typedef enum
{
	DOBJECT_STATUS_UP	= 0,
	DOBJECT_STATUS_DOWN
} zbx_dstatus_t;

/* Item value types */
typedef enum
{
	ITEM_VALUE_TYPE_FLOAT = 0,
	ITEM_VALUE_TYPE_STR,
	ITEM_VALUE_TYPE_LOG,
	ITEM_VALUE_TYPE_UINT64,
	ITEM_VALUE_TYPE_TEXT
} zbx_item_value_type_t;

/* HTTP test states */
typedef enum
{
	HTTPTEST_STATE_IDLE = 0,
	HTTPTEST_STATE_BUSY
} zbx_httptest_state_type_t;

/* Service supported by discoverer */
typedef enum
{
	SVC_SSH = 0,
	SVC_LDAP,
	SVC_SMTP,
	SVC_FTP,
	SVC_HTTP,
	SVC_POP,
	SVC_NNTP,
	SVC_IMAP,
	SVC_TCP,
	SVC_AGENT,
	SVC_SNMPv1,
	SVC_SNMPv2c
} zbx_dservice_type_t;


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

/* Object types for operations */
#define OPERATION_OBJECT_USER	0
#define OPERATION_OBJECT_GROUP	1

/* Condition types */
typedef enum
{
	ACTION_EVAL_TYPE_AND_OR	= 0,
	ACTION_EVAL_TYPE_AND,
	ACTION_EVAL_TYPE_OR
}	zbx_action_eval_type_t;

/* Condition types */
typedef enum
{
	CONDITION_TYPE_HOST_GROUP = 0,
	CONDITION_TYPE_HOST,
	CONDITION_TYPE_TRIGGER,
	CONDITION_TYPE_TRIGGER_NAME,
	CONDITION_TYPE_TRIGGER_SEVERITY,
	CONDITION_TYPE_TRIGGER_VALUE,
	CONDITION_TYPE_TIME_PERIOD,
	CONDITION_TYPE_DHOST_IP,
	CONDITION_TYPE_DSERVICE_TYPE,
	CONDITION_TYPE_DSERVICE_PORT,
	CONDITION_TYPE_DSTATUS,
	CONDITION_TYPE_DUPTIME,
	CONDITION_TYPE_DVALUE
} zbx_condition_type_t;

/* Condition operators */
typedef enum
{
	CONDITION_OPERATOR_EQUAL = 0,
	CONDITION_OPERATOR_NOT_EQUAL,
	CONDITION_OPERATOR_LIKE,
	CONDITION_OPERATOR_NOT_LIKE,
	CONDITION_OPERATOR_IN,
	CONDITION_OPERATOR_MORE_EQUAL,
	CONDITION_OPERATOR_LESS_EQUAL
} zbx_condition_op_t;

typedef enum
{
	SYSMAP_ELEMENT_TYPE_HOST = 0,
	SYSMAP_ELEMENT_TYPE_MAP,
	SYSMAP_ELEMENT_TYPE_TRIGGER,
	SYSMAP_ELEMENT_TYPE_HOST_GROUP
} zbx_sysmap_element_types_t;

/* Special item key used for storing server status */
#define SERVER_STATUS_KEY	"status"
/* Special item key used for ICMP pings */
#define SERVER_ICMPPING_KEY	"icmpping"
/* Special item key used for ICMP ping latency */
#define SERVER_ICMPPINGSEC_KEY	"icmppingsec"
/* Special item key used for internal ZABBIX log */
#define SERVER_ZABBIXLOG_KEY	"zabbix[log]"

/* Alert types */
typedef enum
{
	ALERT_TYPE_EMAIL = 0,
	ALERT_TYPE_EXEC,
	ALERT_TYPE_SMS,
	ALERT_TYPE_JABBER
} zbx_alert_type_t;

/* Alert statuses */
typedef enum
{
	ALERT_STATUS_NOT_SENT = 0,
	ALERT_STATUS_SENT
} zbx_alert_status_t;

/* Item statuses */
typedef enum
{
	ITEM_STATUS_ACTIVE = 0,
	ITEM_STATUS_DISABLED,
/*ITEM_STATUS_TRAPPED	2*/
	ITEM_STATUS_NOTSUPPORTED = 3,
	ITEM_STATUS_DELETED,
	ITEM_STATUS_NOTAVAILABLE
} zbx_item_status_t;

/* HTTP Tests statuses */
#define HTTPTEST_STATUS_MONITORED	0
#define HTTPTEST_STATUS_NOT_MONITORED	1

/* DIscovery rule */
#define DRULE_STATUS_MONITORED		0
#define DRULE_STATUS_NOT_MONITORED	1

/* Host statuses */
#define HOST_STATUS_MONITORED		0
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

/* Trigger severity */
#define TRIGGER_SEVERITY_NOT_CLASSIFIED	0
#define TRIGGER_SEVERITY_INFORMATION	1
#define TRIGGER_SEVERITY_WARNING	2
#define TRIGGER_SEVERITY_AVERAGE	3
#define TRIGGER_SEVERITY_HIGH		4
#define TRIGGER_SEVERITY_DISASTER	5

/* Media statuses */
#define MEDIA_STATUS_ACTIVE	0
#define MEDIA_STATUS_DISABLED	1

/* Action statuses */
#define ACTION_STATUS_ACTIVE	0
#define ACTION_STATUS_DISABLED	1

/* Operation types */
#define OPERATION_TYPE_MESSAGE		0
#define OPERATION_TYPE_COMMAND		1
#define OPERATION_TYPE_HOST_ADD		2
#define OPERATION_TYPE_HOST_REMOVE	3
#define OPERATION_TYPE_GROUP_ADD	4
#define OPERATION_TYPE_GROUP_REMOVE	5
#define OPERATION_TYPE_TEMPLATE_ADD	6
#define OPERATION_TYPE_TEMPLATE_REMOVE	7

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

/* HTTP item types */
typedef enum
{
	ZBX_HTTPITEM_TYPE_RSPCODE = 0,
	ZBX_HTTPITEM_TYPE_TIME,
	ZBX_HTTPITEM_TYPE_SPEED,
	ZBX_HTTPITEM_TYPE_LASTSTEP
} zbx_httpitem_type_t;

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
#define strnscpy(x,y,n) zbx_strlcpy(x,y,n);

#define	zbx_malloc(old, size) zbx_malloc2(__FILE__,__LINE__,old , size)

void    *zbx_malloc2(char *filename, int line, void *old, size_t size);
void    *zbx_realloc(void *src, size_t size);

#define zbx_free(ptr) { if(ptr){ free(ptr); ptr = NULL; } }
	
#define zbx_fclose(f) { if(f){ fclose(f); f = NULL; } }

#define ZBX_COND_NODEID " %s>=100000000000000*%d and %s<=(100000000000000*%d+99999999999999) "
#define LOCAL_NODE(fieldid) fieldid, CONFIG_NODEID, fieldid, CONFIG_NODEID
#define ZBX_NODE(fieldid,nodeid) fieldid, nodeid, fieldid, nodeid

#define MIN_ZABBIX_PORT 1024u
#define MAX_ZABBIX_PORT 65535u

extern char *progname;
extern char title_message[];
extern char usage_message[];
extern char *help_message[];

void	help();
void	usage();
void	version();

/* MAX Length of base64 data */
#define ZBX_MAX_B64_LEN 16*1024

char* get_programm_name(char *path);

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

char *string_replace(char *str, char *sub_str1, char *sub_str2);

void	del_zeroes(char *s);
int	find_char(char *str,char c);
int	is_double_prefix(char *str);
int	is_double(char *c);
int	is_uint(char *c);
void	zbx_rtrim(char *str, const char *charlist);
void	zbx_ltrim(register char *str, const char *charlist);
void	lrtrim_spaces(char *c);
void	compress_signs(char *str);
void	ltrim_spaces(char *c);
void	rtrim_spaces(char *c);
void	delete_reol(char *c);
int	get_param(const char *param, int num, char *buf, int maxlen);
int	num_param(const char *param);
int	calculate_item_nextcheck(zbx_uint64_t itemid, int item_type, int delay, char *delay_flex, time_t now);
int	check_time_period(const char *period, time_t now);
void	zbx_setproctitle(const char *fmt, ...);

#define ZBX_JAN_1970_IN_SEC   2208988800.0        /* 1970 - 1900 in seconds */
double	zbx_time(void);
double	zbx_current_time (void);

void	zbx_error(const char *fmt, ...);
int	zbx_snprintf(char* str, size_t count, const char *fmt, ...);
int	zbx_vsnprintf(char* str, size_t count, const char *fmt, va_list args);
void	zbx_snprintf_alloc(char **str, int *alloc_len, int *offset, int max_len, const char *fmt, ...);

size_t	zbx_strlcpy(char *dst, const char *src, size_t siz);
size_t	zbx_strlcat(char *dst, const char *src, size_t siz);

char* zbx_dvsprintf(char *dest, const char *f, va_list args);
char* zbx_dsprintf(char *dest, const char *f, ...);
char* zbx_strdcat(char *dest, const char *src);
char* zbx_strdcatf(char *dest, const char *f, ...);

int	replace_param(const char *cmd, const char *param, char *out, int outlen);

int	xml_get_data(char *xml,char *tag, char *data, int maxlen);

char*	comms_create_request(
	const char		*host,
	const char		*key,
	const char		*data,
	long			*lastlogsize,
	unsigned long	*timestamp,
	const char		*source,
	unsigned short	*severity
	);

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

int	int_in_list(char *list, int value);
int	ip_in_list(char *list, char *ip);

int MAIN_ZABBIX_ENTRY(void);


#endif
