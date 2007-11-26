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

/*#define ZABBIX_TEST*/

#include "common.h"

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "zbxgetopt.h"

#include "functions.h"
#include "expression.h"
#include "sysinfo.h"

#include "daemon.h"

#include "alerter/alerter.h"
#include "discoverer/discoverer.h"
#include "httppoller/httppoller.h"
#include "housekeeper/housekeeper.h"
#include "pinger/pinger.h"
#include "poller/poller.h"
#include "poller/checks_snmp.h"
#include "timer/timer.h"
#include "trapper/trapper.h"
#include "nodewatcher/nodewatcher.h"
#include "watchdog/watchdog.h"
#include "utils/nodechange.h"

#define       LISTENQ 1024

#ifdef ZABBIX_TEST
#include <time.h>
#endif

char *progname = NULL;
char title_message[] = "ZABBIX Server (daemon)";
char usage_message[] = "[-hV] [-c <file>] [-n <nodeid>]";

#ifndef HAVE_GETOPT_LONG
char *help_message[] = {
        "Options:",
        "  -c <file>       Specify configuration file",
        "  -h              give this help",
        "  -n <nodeid>     convert database data to new nodeid",
        "  -V              display version number",
        0 /* end of text */
};
#else
char *help_message[] = {
        "Options:",
        "  -c --config <file>       Specify configuration file",
        "  -h --help                give this help",
        "  -n --new-nodeid <nodeid> convert database data to new nodeid",
        "  -V --version             display version number",
        0 /* end of text */
};
#endif

/* COMMAND LINE OPTIONS */

/* long options */

static struct zbx_option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"new-nodeid",	1,	0,	'n'},
	{"version",	0,	0,	'V'},

#if defined (_WINDOWS)

	{"install",	0,	0,	'i'},
	{"uninstall",	0,	0,	'd'},

	{"start",	0,	0,	's'},
	{"stop",	0,	0,	'x'},

#endif /* _WINDOWS */

	{0,0,0,0}
};

/* short options */

static char	shortopts[] = 
	"c:n:hV"
#if defined (_WINDOWS)
	"idsx"
#endif /* _WINDOWS */
	;

/* end of COMMAND LINE OPTIONS*/

pid_t	*threads=NULL;

int	CONFIG_ALERTER_FORKS		= 1;
int	CONFIG_DISCOVERER_FORKS		= 1;
int	CONFIG_HOUSEKEEPER_FORKS	= 1;
int	CONFIG_NODEWATCHER_FORKS	= 1;
int	CONFIG_PINGER_FORKS		= 1;
int	CONFIG_POLLER_FORKS		= 5;
int	CONFIG_HTTPPOLLER_FORKS		= 5;
int	CONFIG_TIMER_FORKS		= 1;
int	CONFIG_TRAPPERD_FORKS		= 5;
int	CONFIG_UNREACHABLE_POLLER_FORKS	= 1;

int	CONFIG_LISTEN_PORT		= 10051;
char	*CONFIG_LISTEN_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= TRAPPER_TIMEOUT;
/**/
/*int	CONFIG_NOTIMEWAIT		=0;*/
int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_PINGER_FREQUENCY		= 60;
/*int	CONFIG_DISABLE_PINGER		= 0;*/
int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
int	CONFIG_UNREACHABLE_PERIOD	= 45;
int	CONFIG_UNREACHABLE_DELAY	= 15;
int	CONFIG_UNAVAILABLE_DELAY	= 60;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
char	*CONFIG_ALERT_SCRIPTS_PATH	= NULL;
char	*CONFIG_EXTERNALSCRIPTS		= NULL;
char	*CONFIG_FPING_LOCATION		= NULL;
char	*CONFIG_DBHOST			= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_DBSOCKET		= NULL;
int	CONFIG_DBPORT			= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;

int	CONFIG_NODEID			= 0;
int	CONFIG_MASTER_NODEID		= 0;
int	CONFIG_NODE_NOEVENTS		= 0;
int	CONFIG_NODE_NOHISTORY		= 0;

/* Global variable to control if we should write warnings to log[] */
int	CONFIG_ENABLE_LOG		= 1;

/* From table config */
int	CONFIG_REFRESH_UNSUPPORTED	= 0;

/* Zabbix server sturtup time */
int     CONFIG_SERVER_STARTUP_TIME      = 0;

/******************************************************************************
 *                                                                            *
 * Function: init_config                                                      *
 *                                                                            *
 * Purpose: parse config file and update configuration parameters             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: will terminate process if parsing fails                          *
 *                                                                            *
 ******************************************************************************/
void	init_config(void)
{
	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
		{"StartDiscoverers",&CONFIG_DISCOVERER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartHTTPPollers",&CONFIG_HTTPPOLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPingers",&CONFIG_PINGER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPollers",&CONFIG_POLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPollersUnreachable",&CONFIG_UNREACHABLE_POLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartTrappers",&CONFIG_TRAPPERD_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},
		{"PingerFrequency",&CONFIG_PINGER_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},
		{"FpingLocation",&CONFIG_FPING_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"TrapperTimeout",&CONFIG_TRAPPER_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"UnreachablePeriod",&CONFIG_UNREACHABLE_PERIOD,0,TYPE_INT,PARM_OPT,1,3600},
		{"UnreachableDelay",&CONFIG_UNREACHABLE_DELAY,0,TYPE_INT,PARM_OPT,1,3600},
		{"UnavailableDelay",&CONFIG_UNAVAILABLE_DELAY,0,TYPE_INT,PARM_OPT,1,3600},
		{"ListenIP",&CONFIG_LISTEN_IP,0,TYPE_STRING,PARM_OPT,0,0},
		{"ListenPort",&CONFIG_LISTEN_PORT,0,TYPE_INT,PARM_OPT,1024,32768},
/*		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},*/
/*		{"DisablePinger",&CONFIG_DISABLE_PINGER,0,TYPE_INT,PARM_OPT,0,1},*/
		{"DisableHousekeeping",&CONFIG_DISABLE_HOUSEKEEPING,0,TYPE_INT,PARM_OPT,0,1},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&APP_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFileSize",&CONFIG_LOG_FILE_SIZE,0,TYPE_INT,PARM_OPT,0,1024},
		{"AlertScriptsPath",&CONFIG_ALERT_SCRIPTS_PATH,0,TYPE_STRING,PARM_OPT,0,0},
		{"ExternalScripts",&CONFIG_EXTERNALSCRIPTS,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
		{"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPort",&CONFIG_DBPORT,0,TYPE_INT,PARM_OPT,1024,65535},
		{"NodeID",&CONFIG_NODEID,0,TYPE_INT,PARM_OPT,0,65535},
		{"NodeNoEvents",&CONFIG_NODE_NOEVENTS,0,TYPE_INT,PARM_OPT,0,1},
		{"NodeNoHistory",&CONFIG_NODE_NOHISTORY,0,TYPE_INT,PARM_OPT,0,1},
		{0}
	};

	CONFIG_SERVER_STARTUP_TIME = time(NULL);


	parse_cfg_file(CONFIG_FILE,cfg);

	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
	if(APP_PID_FILE == NULL)
	{
		APP_PID_FILE=strdup("/tmp/zabbix_server.pid");
	}
	if(CONFIG_ALERT_SCRIPTS_PATH == NULL)
	{
		CONFIG_ALERT_SCRIPTS_PATH=strdup("/home/zabbix/bin");
	}
	if(CONFIG_FPING_LOCATION == NULL)
	{
		CONFIG_FPING_LOCATION=strdup("/usr/sbin/fping");
	}
	if(CONFIG_EXTERNALSCRIPTS == NULL)
	{
		CONFIG_EXTERNALSCRIPTS=strdup("/etc/zabbix/externalscripts");
	}
#ifndef	HAVE_LIBCURL
	CONFIG_HTTPPOLLER_FORKS = 0;
#endif
}


/******************************************************************************
 *                                                                            *
 * Function: test                                                             *
 *                                                                            *
 * Purpose: test a custom developed functions                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#ifdef ZABBIX_TEST

void test_params()
{

#define ZBX_PARAM struct zbx_param_t

ZBX_PARAM
{
        char	*exp;
        int	num;
	int	test_num;
	int	expected_ret;
	char	*expected_result;
};

ZBX_PARAM expressions[]=
{
		{"1,0",		2,	1,	0,	"1"},
		{"0",		1,	1,	0,	"0"},
		{"0",		1,	2,	1,	""},
		{"\"0\",\"1\"",	2,	2,	0,	"1"},
		{"\"0\",1\"",	2,	2,	0,	"1\""},
		{"\"0\"",	1,	1,	0,	"0"},
		{"\\\"",	1,	1,	0,	"\\\""},
		{"\"0\",\\\"",	2,	2,	0,	"\\\""},
		{NULL}
};

	int result;
	int i;

	char *exp=NULL;
	char str[MAX_STRING_LEN];

	printf("-= Test parameters =-\n\n");

	for(i=0;expressions[i].exp!=NULL;i++)
	{
		printf("Testing get_patam(%d,\"%s\")\n", expressions[i].test_num, expressions[i].exp);

		exp=zbx_malloc(exp,1024);
		zbx_snprintf(exp,1024,"%s",expressions[i].exp);
		str[0]='\0';

		if(num_param(exp) != expressions[i].num)
		{
			printf("Wrong num_param(%s) Got %d Expected %d\n", exp, num_param(exp), expressions[i].num);
		}
		result = get_param(exp, expressions[i].test_num, str, sizeof(str));
		if(result != expressions[i].expected_ret)
		{
			printf("Wrong result of get_param(%s) Got %d Expected %d\n", exp, result, expressions[i].expected_ret);
		}
		else if(strcmp(str, expressions[i].expected_result)!=0)
		{
			printf("Wrong result string of get_param(%d,\"%s\") Got [%s] Expected [%s]\n",
				expressions[i].test_num,
				exp,
				str,
				expressions[i].expected_result);
		}
		zbx_free(exp);
	}
	exit(-1);
}

void test_expressions()
{

#define ZBX_EXP struct zbx_exp_t

ZBX_EXP
{
        char	*exp;
        int	expected_result;
};

ZBX_EXP expressions[]=
{
/* Supported operators /+-*|&#<>= */
		{"1+2",		3},
		{"1-2",		-1},
		{"6/2",		3},
		{"1",		1},
		{"0",		0},
		{"1*1",		1},
		{"2*1-2",	0},
		{"-2*1-2",	-4},
		{"-8*-2-10*3",	-14},
		{"(1+5)*(1+2)",	18},
		{"8/2*2",	8},
		{"8*2/2*2",	16},
		{NULL}
};

	int result;
	int i;

	char *exp=NULL;
	char error[MAX_STRING_LEN];

	printf("-= Test expressions =-\n\n");

	for(i=0;expressions[i].exp!=NULL;i++)
	{
		exp=zbx_malloc(exp,1024);
		zbx_snprintf(exp,1024,"%s",expressions[i].exp);
		if(SUCCEED != evaluate_expression(&result,&exp, 0, error, sizeof(error)-1))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Evaluation of expression [%s] failed [%s]",
				&exp,
				error);
		}
		printf("Testing \"%s\" Expected result %d Got %d Result: %s\n",
			expressions[i].exp,
			expressions[i].expected_result,
			result,
			(expressions[i].expected_result==result)?"OK":"NOT OK");
		zbx_free(exp);
	}
	exit(-1);
}


void test_compress_signs()
{

#define ZBX_SIGN struct zbx_sign_t
ZBX_SIGN
{
        char	*str;
        char	*expected;
};

ZBX_SIGN expressions[]=
{
		{"1",		"1"},
		{"0",		"0"},
		{"1*1",		"1*1"},
		{"2*1-2",	"2*1+N2"},
		{"-2*1-2",	"N2*1+N2"},
		{"--2--3",	"2+3"},
		{"-+2+-3",	"N2+N3"},
		{"++2--3",	"2+3"},
		{"+-+2",	"N2"},
		{"+++2",	"2"},
		{"2/+2",	"2/2"},
		{"2+2",		"2+2"},
		{"-2",		"N2"},
		{"1/-2",	"1/N2"},
		{"2-3+5",	"2+N3+5"},
		{"2-3",		"2+N3"},
		{"+-+123",	"N123"},
		{NULL}
};

	int i;

	char *exp=NULL;

	printf("-= Test compress signs =-\n");

	for(i=0;expressions[i].str!=NULL;i++)
	{
		exp=zbx_malloc(exp,1024);
		zbx_snprintf(exp,1024,"%s",expressions[i].str);
		compress_signs(exp);
		if(strcmp(expressions[i].expected, exp)!=0)
		{
			printf("FAILED \"%s\" Expected result %s Got %s\n",
			expressions[i].str,
			expressions[i].expected,
			exp);
		}
		zbx_free(exp);
	}
	printf("Passed OK\n");
}

void test_db_connection(void)
{
	DB_RESULT sel_res;
	DB_ROW row_val;

	DBconnect(ZBX_DB_CONNECT_EXIT);

	sel_res = DBselect("select userid, alias from users where alias='%s'", "guest");
	row_val = DBfetch(sel_res);

	if( row_val )
	{
		fprintf(stderr, "DB result: [%s] [%s]\n", row_val[0], row_val[1]); 
	}
	else
	{
		fprintf(stderr, "DB FAIL");
	}
	DBfree_result(sel_res);

	DBclose();
}

void test_variable_argument_list(void)
{
//	char incorrect[] = "incorrect";
//	char format_incorrect[] = "%s";
	char correct[] = "correct";
//	char format[] = "%s";

	zabbix_log(LOG_LEVEL_CRIT, "%s", "correct");
	zabbix_log(LOG_LEVEL_CRIT, "%s", correct);
	zabbix_log(LOG_LEVEL_CRIT, "correct");
/*
	zabbix_log(LOG_LEVEL_CRIT, format, "correct");
	zabbix_log(LOG_LEVEL_CRIT, format, correct);
	zabbix_log(LOG_LEVEL_CRIT, incorrect);
	zabbix_log(LOG_LEVEL_CRIT, format_incorrect);
*/
}

void test_zbx_gethost(void)
{
/*        struct hostent* host;

	char hostname[]="194.8.11.69";

	host = zbx_gethost_by_ip(hostname);

	printf("Host1 [%s]\n", host->h_name);*/
}

void test_templates()
{
	DBconnect(ZBX_DB_CONNECT_EXIT);

	DBsync_host_with_template(10096, 10004);
	
	DBclose();
}

/*
void test_calc_timestamp()
{
#define ZBX_TEST_TIME struct zbx_test_time_t
ZBX_TEST_TIME
{
	char	*line;
	char	*format;
	char	*expected;
};

ZBX_TEST_TIME expressions[]=
{
		{"2006/11/10 10:20:56 Long file record....",	"yyyy MM dd hh mm ss",	"2006/11/10 10:20:56"},
		{"2007/01/02 11:22:33 Long file record....",	"yyyy MM dd hh mm ss",	"2007/01/02 11:22:33"},
		{"2007/12/01 11:22:00 Long file record....",	"yyyy MM dd hh mm ss",	"2007/12/01 11:22:00"},
		{"2000/01/01 00:00:00 Long file record....",	"yyyy MM dd hh mm ss",	"2000/01/01 00:00:00"},
		{"2000/01/01 00:00:00 Long file record....",	"yyyy MM dd hh mm",	"2000/01/01 00:00:00"},
		{NULL}
};
	int	i;
	int	t;
	time_t	time;
	char	str_time[MAX_STRING_LEN];
	struct	tm *local_time = NULL;

	printf("-= Test calc_timestamp =-\n");

	for(i=0;expressions[i].line!=NULL;i++)
	{
		calc_timestamp(expressions[i].line,&t, expressions[i].format);
		time = (time_t)t;
		local_time = localtime(&time);
		strftime( str_time, MAX_STRING_LEN, "%Y/%m/%d %H:%M:%S", local_time );
		printf("format [%s] expected [%s] got [%s]\n",
			expressions[i].format,
			expressions[i].expected,
			str_time);
		if(strcmp(expressions[i].expected, str_time)!=0)
		{
			printf("FAILED!\n");
			exit(-1);
		}
	}
	printf("Passed OK\n");
}
*/
/*
void	test_email()
{
	char str_error[MAX_STRING_LEN];

	alarm(5);
	if ( FAIL == send_email(
			"mail.apollo.lv",
			"",
			"",
			"",
			"This is a TEST message",
			"Big message\r\n"
			" 1 Line\n"
			" 2 line\r\n"
			" 3 Line\n"
			" 4 Line\n"
			" 5 Line\n"
			" 6 Line\n"
			" 7 Line\n"
			" 8 Line\n"
			" 9 Line\n"
			" 10 Line\n\n\n"
			" 11 Line\n"
			" 12 Line\n"
			" 13 Line\n"
			" 14 Line\n"
			" 15 Line\n"
			" 16 Line\n"
			" 17 Line\n\n\n"
			" 18 Line\n",
			str_error,
			sizeof(str_error)
			
		  ) )
		printf("ERROR: %s\n", str_error);
	else
		printf("OK\n");
	alarm(0);

}
*/

/*
void test_extract_numbers(void)
{
	char simple_expression[] = "{44444444}>0&{4444}<=12K&(123*12K>999)|({444}&{4}>(12314-3213))";

	char	**numbers;
	int	count, i;

	printf("PARSE: %s\n", simple_expression);

	numbers = extract_numbers(simple_expression, &count);

	printf("FOUNDED: %i numbers\n", count);

	for ( i = 0; i < count; i++ )
	{
		printf("%i: %s\n", i, numbers[i]);
	}

	zbx_free_numbers(&numbers, count);
}
*/
/*
void test_trigger_description()
{
	char *data = NULL;

	DBconnect(ZBX_DB_CONNECT_EXIT);

	data = strdup("!!!test $0 $1 $2 $3 $5 $6 $7 $8 $9 $10 $11");

	printf("Descriptioni (before): [%s]\n", data); 

	expand_trigger_description_simple(&data, 100000000012896);

	printf("Description  (after) : [%s]\n", data); 

	zbx_free(data);
	
	DBclose();
}
*/

void test_zbx_tcp_connect(void)
{
#define ZBX_TEST_TCP_CONNECT struct zbx_test_tcp_connect_t
ZBX_TEST_TCP_CONNECT
{
        char           *hostname;
        unsigned short  port;
};

ZBX_TEST_TCP_CONNECT expressions[]=
{
	{"127.0.00.1",   80},
	{"www.iscentrs.lv",	80},/*81.198.60.94*/
	{"81.171.84.52",	80},
	{"64.233.183.103",	80},/*nf-in-f103.google.com*/
	{"::1",   80},
	{"::1",   22},
	{"12fc::5",	80},
	{"192.168.3.5",	80},
	{NULL}
};

	int		i;
	zbx_sock_t	s;
	char		host[MAX_STRING_LEN];
	char		ip_list[] = "81.171.84.52,nf-in-f103.google.com,127.000.0.1";

	for(i = 0; expressions[i].hostname != NULL; i ++)
	{
		zbx_gethost_by_ip(expressions[i].hostname, host, sizeof(host));
		printf("[%25s]:%-5d %-30s ", expressions[i].hostname, expressions[i].port, host);

		alarm(5);
		switch(zbx_tcp_connect(&s, expressions[i].hostname, expressions[i].port)) 
		{
			case SUCCEED : 
				printf("Succeed");

				if(FAIL == zbx_tcp_check_security(&s, ip_list, 0))
				{
					printf(" \n%s", zbx_tcp_strerror());
				}
				zbx_tcp_close(&s);
				break;
			case FAIL    : 
				printf("Fail %s\n", zbx_tcp_strerror());
				break;
		}
		alarm(0);
		printf("\n");
	}
}
/*
static void test_child_signal_handler(int sig)
{
	printf( "sdfsdfsdfsf" );
}
*/

void test_ip_in_list()
{
#define ZBX_TEST_IP struct zbx_test_ip_t
ZBX_TEST_IP
{
	char	*list;
	char	*ip;
	int	result;
};

ZBX_TEST_IP expressions[]=
{
		{"10.0.0.1-29",						"10.0.0.30",		FAIL},
		{"192.168.0.1-255,192.168.1.1-255",			"192.168.2.201",	FAIL},
		{"172.16.0.0,172.16.0.1,172.16.0.2,172.16.0.44-250",	"172.16.0.201",		SUCCEED},
		{"172.31.255.43-55",					"172.31.255.47",	SUCCEED},
		{"86.57.15.94",						"86.57.15.95",		FAIL},
		{"86.57.15.94",						"86.57.15.94",		SUCCEED},
#if defined(HAVE_IPV6)
		{"2312:333::32-64,12fc::1-fffc",			"12fc::ffff",		FAIL},
		{"2312:333::32-64,12fc::1-fffc",			"2312:333::44",		SUCCEED},
		{"::a:b:a,::a:b:b,::a:b:c-e",				"::a:b:f",		FAIL},
		{"192.168.200.1,::a:b:a,::a:b:b,::a:b:c-e,10.0.0.2",	"::a:b:d",		SUCCEED},
		{"192.168.200.1,::a:b:a,::a:b:b,::a:b:c-e,10.0.0.2",	"10.0.0.2",		SUCCEED},
		{"192.168.200.1,::a:b:a,::a:b:b,::a:b:c-,10.0.0.2",	"::a:b:d",		FAIL},
		{"a:b:c::-f",						"a:b:c::3",		SUCCEED},
#endif /*HAVE_IPV6*/
		{NULL}
};
	int	i;
	int	result;
	char    list[MAX_STRING_LEN];

	printf("-= Test ip_in_list =-\n");

	for(i=0;expressions[i].list!=NULL;i++)
	{
		strcpy(list, expressions[i].list);
		result = ip_in_list(list, expressions[i].ip);
			
		printf("list [%50s] ip [%20s] expected [%7s] got [%7s]\n",
			expressions[i].list,
			expressions[i].ip,
			expressions[i].result == SUCCEED ? "SUCCEED" : "FAIL",
			result == SUCCEED ? "SUCCEED" : "FAIL");
		if(expressions[i].result!=result)
		{
			printf("FAILED!\n");
			exit(-1);
		}
	}
	printf("Passed OK\n");
}

void test_binary2hex()
{
#define ZBX_TEST_HEX struct zbx_test_hex_t
ZBX_TEST_HEX
{
	char	*bin;
	char	*hex;
};

ZBX_TEST_HEX expressions[]=
{
	{"a", "61"},
	{"abcd", "61626364"},
	{"abcdefghijk^^^", "6162636465666768696a6b5e5e5e"},
	{"abcdefghijk***", "6162636465666768696a6b2a2a2a"},
	{"\xffwabcdefghijkTUVabcdefghijk", "ff776162636465666768696a6b5455566162636465666768696a6b"},
	{NULL}
};
	int	len, ilen;
	int	i;
	char	*buffer = NULL, tmp[MAX_STRING_LEN];

	printf("-= Test binary_to_hex =-\n");

	len = 1024;
	buffer = zbx_malloc(buffer, len);
	for(i = 0; expressions[i].bin != NULL; i++)
	{
		ilen = strlen(expressions[i].bin);
		zbx_binary2hex((u_char *)expressions[i].bin, ilen, &buffer, &len);
		printf("bin [%s] hex [%s] got [%s] [%s]\n",
			expressions[i].bin,
			expressions[i].hex,
			buffer,
			(strcmp(expressions[i].hex, buffer) == 0 ? "SUCCEED" : "FAIL"));
		if(strcmp(expressions[i].hex, buffer) != 0)
		{
			printf("FAILED!\n");
			exit(-1);
		}

		strcpy(tmp, expressions[i].hex);
		zbx_hex2binary(tmp);
		printf("hex [%s] bin [%s] got [%s] [%s]\n",
			expressions[i].hex,
			expressions[i].bin,
			tmp,
			(strcmp(expressions[i].bin, tmp) == 0 ? "SUCCEED" : "FAIL"));
		if(strcmp(expressions[i].bin, tmp) != 0)
		{
			printf("FAILED!\n");
			exit(-1);
		}
	}
	printf("Passed OK\n");
}

void test_zbx_get_next_field()
{
	char	input[] = {"?11111.;?222222222222222222.;?333333333333.;?4444444444444444."};
	char	*ptr, *buffer = NULL;
	int	len;

	printf("-= Test binary_to_hex =-\n");

	len = 1;
	buffer = zbx_malloc(buffer, len);
	ptr = input;


	ptr = zbx_get_next_field( ptr, &buffer, &len, ';');
printf("test_zbx_get_next_field() (1) [input:%s] [buffer:%s]\n", ptr, buffer);
	ptr = zbx_get_next_field( ptr, &buffer, &len, ';');
printf("test_zbx_get_next_field() (2) [input:%s] [buffer:%s]\n", ptr, buffer);
	ptr = zbx_get_next_field( ptr, &buffer, &len, ';');
printf("test_zbx_get_next_field() (3) [input:%s] [buffer:%s]\n", ptr, buffer);
	ptr = zbx_get_next_field( ptr, &buffer, &len, ';');
printf("test_zbx_get_next_field() (4) [input:%s] [buffer:%s]\n", ptr, buffer);
}

void test_regexp()
{
	int len;

	if(zbx_regexp_match("A  B C A", "(B+.*C+)|(C+.*B+)", &len) != NULL)
	{
		printf("Matched\n");
	}
	else
	{
		printf("Not matched\n");
	}
}

void test()
{

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_UNDEFINED,LOG_LEVEL_DEBUG,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,LOG_LEVEL_DEBUG,CONFIG_LOG_FILE);
	}

	zabbix_log( LOG_LEVEL_WARNING, "Starting zabbix_server. ZABBIX %s.", ZABBIX_VERSION);

	printf("-= Test Started =-\n\n");

/*	test_params();*/
/*	test_compress_signs(); */
/*	test_expressions(); */
/*	test_db_connection(); */
/*	test_variable_argument_list(); */
/*	test_templates();*/
/*	test_calc_timestamp();*/
/*	test_zbx_gethost();*/
/*	test_email(); */
/*	test_extract_numbers(); */
/*	test_trigger_description(); */
/*	test_zbx_tcp_connect( );*/
/*	test_ip_in_list(); */
/*	test_binary2hex();*/
/*	test_zbx_get_next_field();*/
	test_regexp();

	printf("\n-= Test completed =-\n");
}

#endif /* ZABBIX_TEST */

/******************************************************************************
 *                                                                            *
 * Function: main                                                             *
 *                                                                            *
 * Purpose: executes server processes                                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int main(int argc, char **argv)
{
	zbx_task_t	task  = ZBX_TASK_START;
	char    ch      = '\0';

	int	nodeid;

	progname = argv[0];

	/* Parse the command-line. */
	while ((ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts,NULL)) != (char)EOF)
	switch (ch) {
		case 'c':
			CONFIG_FILE = strdup(zbx_optarg);
			break;
		case 'h':
			help();
			exit(-1);
			break;
		case 'n':
			nodeid=0;
			if(zbx_optarg)	nodeid = atoi(zbx_optarg);
			task = ZBX_TASK_CHANGE_NODEID;
			break;
		case 'V':
			version();
			exit(-1);
			break;
		default:
			usage();
			exit(-1);
			break;
        }

	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE=strdup("/etc/zabbix/zabbix_server.conf");
	}

	/* Required for simple checks */
	init_metrics();

	init_config();

	switch (task) {
		case ZBX_TASK_CHANGE_NODEID:
			change_nodeid(0,nodeid);
			exit(-1);
			break;
		default:
			break;
	}

#ifdef ZABBIX_TEST
/*	struct sigaction  phan;

	phan.sa_handler = test_child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;

	sigaction(SIGINT,       &phan, NULL);
	sigaction(SIGQUIT,      &phan, NULL);
	sigaction(SIGTERM,      &phan, NULL);
	sigaction(SIGPIPE,      &phan, NULL);
	sigaction(SIGCHLD,      &phan, NULL);
*/
	test();

	zbx_on_exit();
	return 0;
#endif /* ZABBIX_TEST */
	
	return daemon_start(CONFIG_ALLOW_ROOT);
}

int MAIN_ZABBIX_ENTRY(void)
{
        DB_RESULT       result;
        DB_ROW          row;

	int	i;
	pid_t	pid;

	zbx_sock_t	listen_sock;

	int		server_num = 0;

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

/*	zabbix_log( LOG_LEVEL_WARNING, "INFO [%s]", ZBX_SQL_MOD(a,%d)); */
	zabbix_log( LOG_LEVEL_WARNING, "Starting zabbix_server. ZABBIX %s.", ZABBIX_VERSION);

	zabbix_log( LOG_LEVEL_WARNING, "**** Enabled features ****");
#ifdef	HAVE_SNMP
	zabbix_log( LOG_LEVEL_WARNING, "SNMP monitoring:       YES");
#else
	zabbix_log( LOG_LEVEL_WARNING, "SNMP monitoring:        NO");
#endif
#ifdef	HAVE_LIBCURL
	zabbix_log( LOG_LEVEL_WARNING, "WEB monitoring:        YES");
#else
	zabbix_log( LOG_LEVEL_WARNING, "WEB monitoring:         NO");
#endif
#ifdef	HAVE_JABBER
	zabbix_log( LOG_LEVEL_WARNING, "Jabber notifications:  YES");
#else
	zabbix_log( LOG_LEVEL_WARNING, "Jabber notifications:   NO");
#endif
#ifdef	HAVE_IPV6
	zabbix_log( LOG_LEVEL_WARNING, "IPv6 support:          YES");
#else
	zabbix_log( LOG_LEVEL_WARNING, "IPv6 support:           NO");
#endif

	zabbix_log( LOG_LEVEL_WARNING, "**************************");

	DBconnect(ZBX_DB_CONNECT_EXIT);

	result = DBselect("select refresh_unsupported from config where " ZBX_COND_NODEID,
		LOCAL_NODE("configid"));

	if (NULL != (row = DBfetch(result)) && DBis_null(row[0]) != SUCCEED)
		CONFIG_REFRESH_UNSUPPORTED = atoi(row[0]);
	DBfree_result(result);

	result = DBselect("select masterid from nodes where nodeid=%d",
		CONFIG_NODEID);
	row = DBfetch(result);

	if( (row != NULL) && DBis_null(row[0]) != SUCCEED)
	{
		CONFIG_MASTER_NODEID = atoi(row[0]);
	}
	DBfree_result(result);

/* Need to set trigger status to UNKNOWN since last run */
/* DBconnect() already made in init_config() */
/*	DBconnect();*/
	DBupdate_triggers_status_after_restart();
	DBclose();

/* To make sure that we can connect to the database before forking new processes */
	DBconnect(ZBX_DB_CONNECT_EXIT);
	DBclose();

/*#define CALC_TREND*/

#ifdef CALC_TREND
	trend();
	return 0;
#endif
	threads = calloc(1+CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS+CONFIG_PINGER_FORKS+CONFIG_ALERTER_FORKS
		+CONFIG_HOUSEKEEPER_FORKS+CONFIG_TIMER_FORKS+CONFIG_UNREACHABLE_POLLER_FORKS
		+CONFIG_NODEWATCHER_FORKS+CONFIG_HTTPPOLLER_FORKS+CONFIG_DISCOVERER_FORKS,
		sizeof(pid_t));

	if(CONFIG_TRAPPERD_FORKS > 0)
	{
		if( FAIL == zbx_tcp_listen(&listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT) )
		{
			zabbix_log(LOG_LEVEL_CRIT, "Listener failed with error: %s.", zbx_tcp_strerror());
			exit(1);
		}
	}

	for(	i=1;
		i<=CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS+CONFIG_PINGER_FORKS+CONFIG_ALERTER_FORKS+CONFIG_HOUSEKEEPER_FORKS+CONFIG_TIMER_FORKS+CONFIG_UNREACHABLE_POLLER_FORKS+CONFIG_NODEWATCHER_FORKS+CONFIG_HTTPPOLLER_FORKS+CONFIG_DISCOVERER_FORKS; 
		i++)
	{
		if((pid = zbx_fork()) == 0)
		{
			server_num = i;
			break; 
		}
		else
		{
			threads[i]=pid;
		}
	}

/*	zabbix_log( LOG_LEVEL_WARNING, "zabbix_server #%d started",server_num); */
	/* Main process */
	if(server_num == 0)
	{
		init_main_process();
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Watchdog]",
			server_num);
		main_watchdog_loop();
/*		for(;;)	zbx_sleep(3600);*/
	}


	if(server_num <= CONFIG_POLLER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller. SNMP:ON]",
			server_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller. SNMP:OFF]",
			server_num);
#endif
		main_poller_loop(ZBX_POLLER_TYPE_NORMAL, server_num);
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS)
	{
/* Run trapper processes then do housekeeping */
		child_trapper_main(server_num, &listen_sock);

/*		threads[i] = child_trapper_make(i, listenfd, addrlen); */
/*		child_trapper_make(server_num, listenfd, addrlen); */
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS)
	{
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [ICMP pinger]",
			server_num);
		main_pinger_loop(server_num-(CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS));
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS)
	{
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Alerter]",
			server_num);
		main_alerter_loop();
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+CONFIG_HOUSEKEEPER_FORKS)
	{
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Housekeeper]",
			server_num);
		main_housekeeper_loop();
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS)
	{
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Timer]",
			server_num);
		main_timer_loop();
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS)
	{
/*		zabbix_log( LOG_LEVEL_WARNING, "%d<=%d",server_num,  CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS+CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS); */
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller for unreachable hosts. SNMP:ON]",
			server_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller for unreachable hosts. SNMP:OFF]",
			server_num);
#endif
/*		zabbix_log( LOG_LEVEL_WARNING, "Before main_poller_loop(%d,%d)",ZBX_POLLER_TYPE_UNREACHABLE,server_num - (CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS +CONFIG_ALERTER_FORKS+CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS)); */
		main_poller_loop(ZBX_POLLER_TYPE_UNREACHABLE,
				server_num - (CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS+CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS));
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_NODEWATCHER_FORKS)
	{
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Node watcher. Node ID:%d]",
				server_num,
				CONFIG_NODEID);
		main_nodewatcher_loop();
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS)
	{
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [HTTP Poller]",
				server_num);
		main_httppoller_loop(server_num - CONFIG_POLLER_FORKS - CONFIG_TRAPPERD_FORKS -CONFIG_PINGER_FORKS
				- CONFIG_ALERTER_FORKS - CONFIG_HOUSEKEEPER_FORKS - CONFIG_TIMER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_NODEWATCHER_FORKS);
	}
	else if(server_num <= CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Discoverer. SNMP:ON]",
				server_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Discoverer. SNMP:OFF]",
				server_num);
#endif
		main_discoverer_loop(server_num - CONFIG_POLLER_FORKS - CONFIG_TRAPPERD_FORKS -CONFIG_PINGER_FORKS
				- CONFIG_ALERTER_FORKS - CONFIG_HOUSEKEEPER_FORKS - CONFIG_TIMER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_NODEWATCHER_FORKS - CONFIG_HTTPPOLLER_FORKS);
	}

	return SUCCEED;
}

void	zbx_on_exit()
{
#if !defined(_WINDOWS)
	
	int i = 0;

	if(threads != NULL)
	{
		for(i = 1; i <= CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS+CONFIG_PINGER_FORKS+CONFIG_ALERTER_FORKS+CONFIG_HOUSEKEEPER_FORKS+CONFIG_TIMER_FORKS+CONFIG_UNREACHABLE_POLLER_FORKS+CONFIG_NODEWATCHER_FORKS+CONFIG_HTTPPOLLER_FORKS+CONFIG_DISCOVERER_FORKS; i++)
		{
			if(threads[i]) {
				kill(threads[i],SIGTERM);
				threads[i] = (ZBX_THREAD_HANDLE)NULL;
			}
		}
	}
	
#endif /* not _WINDOWS */

#ifdef USE_PID_FILE

	daemon_stop();

#endif /* USE_PID_FILE */

	free_metrics();

	zbx_sleep(2); /* wait for all threads closing */
	
	zabbix_log(LOG_LEVEL_INFORMATION, "ZABBIX Server stopped");
	zabbix_close_log();
	
#ifdef  HAVE_SQLITE3
	php_sem_remove(&sqlite_access);
#endif /* HAVE_SQLITE3 */

	exit(SUCCEED);
}

