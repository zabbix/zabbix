#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include <syslog.h>

/* Required for SNMP support*/
#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	#include <ucd-snmp/ucd-snmp-config.h>
	#include <ucd-snmp/ucd-snmp-includes.h>
	#include <ucd-snmp/system.h>
#endif

#include "common.h"
#include "db.h"
#include "functions.h"
#include "expression.h"

int	sucker_num=0;
int	CONFIG_SUCKER_FORKS		=SUCKER_FORKS;
int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_DBSOCKET		= NULL;


void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
		syslog( LOG_DEBUG, "Timeout while executing operation." );
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		syslog( LOG_ERR, "Got QUIT or INT or TERM signal. Exiting..." );
		exit( FAIL );
	}
}

void	daemon_init(void)
{
	int		i;
	pid_t		pid;
	struct passwd	*pwd;

	/* running as root ?*/
	if((getuid()==0) || (getuid()==0))
	{
		pwd = getpwnam("zabbix");
		if ( pwd == NULL )
		{
			fprintf(stderr,"User zabbix does not exist.\n");
			fprintf(stderr, "Cannot run as root !\n");
			exit(FAIL);
		}

		if( (setgid(pwd->pw_gid) ==-1) || (setuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setgid or setuid to zabbix");
			exit(FAIL);
		}

		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setegid or seteuid to zabbix");
			exit(FAIL);
		}
	}

	if( (pid = fork()) != 0 )
	{
		exit( 0 );
	}
	setsid();

	signal( SIGHUP, SIG_IGN );

	if( (pid = fork()) !=0 )
	{
		exit( 0 );
	}

	chdir("/");

	umask(0);

	for(i=0;i<MAXFD;i++)
	{
		close(i);
	}

	openlog("zabbix_suckerd",LOG_PID,LOG_USER);
/*	setlogmask(LOG_UPTO(LOG_DEBUG));	*/
	setlogmask(LOG_UPTO(LOG_WARNING));
}

void	process_config_file(void)
{
	FILE	*file;
	char	line[1024];
	char	parameter[1024];
	char	*value;
	int	lineno;
	int	i;


	file=fopen("/etc/zabbix/zabbix_suckerd.conf","r");
	if(NULL == file)
	{
		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_suckerd.conf");
		exit(1);
	}

	lineno=1;
	while(fgets(line,1024,file) != NULL)
	{
		if(line[0]=='#')	continue;
		if(strlen(line)==1)	continue;

		strcpy(parameter,line);

		value=strstr(line,"=");

		if(NULL == value)
		{
			syslog( LOG_CRIT, "Error in line [%s] Line %d", line, lineno);
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

		syslog( LOG_DEBUG, "Parameter [%s] Value [%s]", parameter, value);

		if(strcmp(parameter,"StartSuckers")==0)
		{
			i=atoi(value);
			if( (i<2) || (i>255) )
			{
				syslog( LOG_CRIT, "Wrong value of StartAgents in line %d. Should be between 2 and 255.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_SUCKER_FORKS=i;
		}
		else if(strcmp(parameter,"HousekeepingFrequency")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>24) )
			{
				syslog( LOG_CRIT, "Wrong value of HousekeepingFrequency in line %d. Should be between 1 and 24.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_HOUSEKEEPING_FREQUENCY=i;
		}
		else if(strcmp(parameter,"DebugLevel")==0)
		{
			if(strcmp(value,"1") == 0)
			{
				setlogmask(LOG_UPTO(LOG_CRIT));
			}
			else if(strcmp(value,"2") == 0)
			{
				setlogmask(LOG_UPTO(LOG_WARNING));
			}
			else if(strcmp(value,"3") == 0)
			{
				setlogmask(LOG_UPTO(LOG_DEBUG));
			}
			else
			{
				syslog( LOG_CRIT, "Wrong DebugLevel in line %d", lineno);
				fclose(file);
				exit(1);
			}
		}
		else if(strcmp(parameter,"DBName")==0)
		{
			CONFIG_DBNAME=strdup(value);
		}
		else if(strcmp(parameter,"DBUser")==0)
		{
			CONFIG_DBUSER=strdup(value);
		}
		else if(strcmp(parameter,"DBPassword")==0)
		{
			CONFIG_DBPASSWORD=strdup(value);
		}
		else if(strcmp(parameter,"DBSocket")==0)
		{
			CONFIG_DBSOCKET=strdup(value);
		}
		else
		{
			syslog( LOG_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);
			fclose(file);
			exit(1);
		}

		lineno++;
	}
	fclose(file);
	


	if(CONFIG_DBNAME == NULL)
	{
		syslog( LOG_CRIT, "DBName not in config file");
		exit(1);
	}
}

#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
int	get_value_SNMPv1(double *result,DB_ITEM *item)
{
	struct snmp_session session, *ss;
	struct snmp_pdu *pdu;
	struct snmp_pdu *response;

	oid anOID[MAX_OID_LEN];
	size_t anOID_len = MAX_OID_LEN;

	struct variable_list *vars;
	int status;

/*
	Initialize the SNMP library
	init_snmp("zabbix_suckerd");
*/

/*
*      * Initialize a "session" that defines who we're going to talk to
*           */
	snmp_sess_init( &session );                   /* set up defaults */
	session.version = SNMP_VERSION_1;
	session.peername = item->host;
	session.community = item->snmp_community;
	session.community_len = strlen(session.community);

/*
*      * Open the session
*           */
	SOCK_STARTUP;
	ss = snmp_open(&session);                     /* establish the session */

/*
*      * Create the PDU for the data for our request.
*           *   1) We're going to GET the system.sysDescr.0 node.
*                */
	pdu = snmp_pdu_create(SNMP_MSG_GET);
	read_objid(item->snmp_oid, anOID, &anOID_len);
/*    read_objid(".1.3.6.1.2.1.1.1.0", anOID, &anOID_len); */

#if OTHER_METHODS
	get_node("sysDescr.0", anOID, &anOID_len);
	read_objid(".1.3.6.1.2.1.1.1.0", anOID, &anOID_len);
	read_objid("system.sysDescr.0", anOID, &anOID_len);
#endif

	snmp_add_null_var(pdu, anOID, anOID_len);
  
/* Send the Request out */
	status = snmp_synch_response(ss, pdu, &response);

/* Process the response */
	if (status == STAT_SUCCESS && response->errstat == SNMP_ERR_NOERROR)
	{
/* SUCCESS: Print the result variables */

		for(vars = response->variables; vars; vars = vars->next_variable)
		{
			print_variable(vars->name, vars->name_length, vars);
		}

/* manipuate the information ourselves */
		for(vars = response->variables; vars; vars = vars->next_variable)
		{
			int count=1;
/*		if (vars->type == ASN_OCTET_STR)
		syslog( LOG_WARNING, "Type:%d", vars->type);*/
			if(	(vars->type == ASN_INTEGER ) ||
			(vars->type == ASN_UINTEGER ) ||
			(vars->type == ASN_COUNTER ) ||
			(vars->type == ASN_GAUGE )
			)
			{
				char *sp = (char *)malloc(1 + vars->val_len);
				memcpy(sp, vars->val.string, vars->val_len);
				sp[vars->val_len] = '\0';
/*			syslog( LOG_WARNING, "value #%d is a string: %s\n", count++, sp);
			*result=strtod(sp,&e);
			syslog( LOG_WARNING, "Type:%d", vars->type);
			syslog( LOG_WARNING, "Value #%d is an integer: %d", count++, *vars->val.integer);*/
				*result=*vars->val.integer;
				free(sp);
			}
			else
			{
				syslog( LOG_WARNING,"value #%d is NOT an integer!\n", count++);
			}
		}
	}
	else
	{
		if (status == STAT_SUCCESS)
		{
			syslog( LOG_WARNING, "Error in packet\nReason: %s\n",
			snmp_errstring(response->errstat));
		}
		else
		{
			snmp_sess_perror("snmpget", ss);
		}
	}

/*
*      * Clean up:
*      *  1) free the response.
*      *  2) close the session.
*      */
	if (response)
	{
		snmp_free_pdu(response);
	}
	snmp_close(ss);

	SOCK_CLEANUP;
	return SUCCEED;
}
#endif

int	get_value_zabbix(double *result,DB_ITEM *item)
{
	int	s;
	int	i;
	char	c[1024];
	char	*e;

	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	syslog( LOG_DEBUG, "%10s%25s", item->host, item->key );


	servaddr_in.sin_family=AF_INET;
	if(item->useip==1)
	{
		hp=gethostbyname(item->ip);
	}
	else
	{
		hp=gethostbyname(item->host);
	}

	if(hp==NULL)
	{
		syslog( LOG_WARNING, "gethostbyname() failed" );
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(item->port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0)
	{
		syslog( LOG_WARNING, "Cannot create socket" );
		return	FAIL;
	}
 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				syslog( LOG_WARNING, "Timeout while connecting to [%s]",item->host );
				break;
			case EHOSTUNREACH:
				syslog( LOG_WARNING, "No route to host [%s]",item->host );
				break;
			default:
				syslog( LOG_WARNING, "Cannot connect to [%s]. Errno [%d]",item->host,errno);
		} 
		close(s);
		return	NETWORK_ERROR;
	}

	sprintf(c,"%s\n",item->key);
	if( sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				syslog( LOG_WARNING, "Timeout while sending data to [%s]",item->host );
				break;
			default:
				syslog( LOG_WARNING, "Error while sending data to [%s]. Errno [%d]",item->host,errno);
		} 
		close(s);
		return	FAIL;
	} 
	i=sizeof(struct sockaddr_in);

	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		switch (errno)
		{
			case 	EINTR:
					syslog( LOG_WARNING, "Timeout while receiving data from [%s]",item->host );
					break;
			case	ECONNRESET:
					close(s);
					return	NETWORK_ERROR;
			default:
				syslog( LOG_WARNING, "Error while receiving data from [%s]. Errno [%d]",item->host,errno);
		} 
		close(s);
		return	FAIL;
	}
 
	if( close(s)!=0 )
	{
		syslog(LOG_WARNING, "Problem with close" );
	}
	c[i-1]=0;

	syslog(LOG_DEBUG, "Got string:%10s", c );
	*result=strtod(c,&e);

	if( (*result==0) && (c==e) )
	{
		syslog( LOG_WARNING, "Got empty string from [%s]. Parameter [%s]",item->host, item->key);
		syslog( LOG_WARNING, "Assuming that agent dropped connection because of access permissions");
		return	NETWORK_ERROR;
	}
	if( *result<0 )
	{
		if( cmp_double(*result,NOTSUPPORTED) == 0)
		{
			syslog(LOG_WARNING, "NOTSUPPORTED1 [%s]", c );
			return NOTSUPPORTED;
		}
		else
		{
			return	FAIL;
		}
	}

	return SUCCEED;
}

int	get_value(double *result,DB_ITEM *item)
{
	int res;

	struct	sigaction phan;

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(SUCKER_TIMEOUT);

	if(item->type == ITEM_TYPE_ZABBIX)
	{
		res=get_value_zabbix(result,item);
	}
#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	else if(item->type == ITEM_TYPE_SNMP)
	{
		res=get_value_SNMPv1(result,item);
	}
#endif
	else
	{
		syslog(LOG_WARNING, "Not supported item type:%d",item->type);
		res=NOTSUPPORTED;
	}
	alarm(0);
	return res;
}

int get_minnextcheck(int now)
{
	char		c[1024];

	DB_RESULT	*result;

	int		res;
	int		count;

	sprintf(c,"select count(*),min(nextcheck) from items i,hosts h where i.status=0 and (h.status=0 or (h.status=2 and h.disable_until<%d)) and h.hostid=i.hostid and i.status=0 and i.itemid%%%d=%d",now,CONFIG_SUCKER_FORKS-1,sucker_num-1);
	result = DBselect(c);

	if( (result==NULL) || (DBnum_rows(result)==0) )
	{
		syslog(LOG_DEBUG, "No items to update for minnextcheck.");
		DBfree_result(result);
		return FAIL; 
	}

	count = atoi(DBget_field(result,0,0));

	if( count == 0 )
	{
		syslog( LOG_DEBUG, "No records for get_minnextcheck");
		DBfree_result(result);
		return	FAIL;
	}

	res = atoi(DBget_field(result,0,1));

	DBfree_result(result);
	return	res;
}

int get_values(void)
{
	double		value;
	char		c[1024];
 
	DB_RESULT	*result;

	int		i,rows;
	int		now;
	int		res;
	DB_ITEM		item;
	char		*s;

	int	host_status;

	now = time(NULL);

	sprintf(c,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status from items i,hosts h where i.nextcheck<=%d and i.status=0 and (h.status=0 or (h.status=2 and h.disable_until<%d)) and h.hostid=i.hostid and i.itemid%%%d=%d order by i.nextcheck", now, now, CONFIG_SUCKER_FORKS-1,sucker_num-1);
	result = DBselect(c);

	rows = DBnum_rows(result);
	if( (result==NULL) || (rows == 0))
	{
		syslog( LOG_DEBUG, "No items to update.");
		DBfree_result(result);
		return SUCCEED; 
	}

	for(i=0;i<rows;i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.key=DBget_field(result,i,1);
		item.host=DBget_field(result,i,2);
		item.port=atoi(DBget_field(result,i,3));
		item.delay=atoi(DBget_field(result,i,4));
		item.description=DBget_field(result,i,5);
		item.nextcheck=atoi(DBget_field(result,i,6));
		item.type=atoi(DBget_field(result,i,7));
		item.snmp_community=DBget_field(result,i,8);
		item.snmp_oid=DBget_field(result,i,9);
		item.useip=atoi(DBget_field(result,i,10));
		item.ip=DBget_field(result,i,11);
		item.history=atoi(DBget_field(result,i,12));
		s=DBget_field(result,i,13);
		if(s==NULL)
		{
			item.lastvalue_null=1;
		}
		else
		{
			item.lastvalue_null=0;
			item.lastvalue=atof(s);
		}
		s=DBget_field(result,i,14);
		if(s==NULL)
		{
			item.prevvalue_null=1;
		}
		else
		{
			item.prevvalue_null=0;
			item.prevvalue=atof(s);
		}
		item.hostid=atoi(DBget_field(result,i,15));
		host_status=atoi(DBget_field(result,i,16));

		res = get_value(&value,&item);
		
		if(res == SUCCEED )
		{
			process_new_value(&item,value);
			if(2 == host_status)
			{
				host_status=0;
				syslog( LOG_WARNING, "Enabling host [%s]", item.host );
				sprintf(c,"update hosts set status=0 where hostid=%d",item.hostid);
				DBexecute(c);

				break;
			}
		}
		else if(res == NOTSUPPORTED)
		{
			syslog( LOG_WARNING, "Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			sprintf(c,"update items set status=3 where itemid=%d",item.itemid);
			DBexecute(c);
			if(2 == host_status)
			{
				host_status=0;
				syslog( LOG_WARNING, "Enabling host [%s]", item.host );
				sprintf(c,"update hosts set status=0 where hostid=%d",item.hostid);
				DBexecute(c);

				break;
			}
		}
		else if(res == NETWORK_ERROR)
		{
			syslog( LOG_WARNING, "Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
			now=time(NULL);
			sprintf(c,"update hosts set status=2,disable_until=%d where hostid=%d",now+DELAY_ON_NETWORK_FAILURE,item.hostid);
			DBexecute(c);

			break;
		}
		else
		{
			syslog( LOG_WARNING, "Getting value of [%s] from host [%s] failed", item.key, item.host );
			syslog( LOG_WARNING, "The value is not stored in database.");
		}
	}

	update_triggers( CONFIG_SUCKER_FORKS, 0, sucker_num, now );

	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_items(int now)
{
	char		c[1024];
	DB_ITEM		item;

	DB_RESULT	*result;

	int		i,rows;

	sprintf(c,"select i.itemid,i.lastdelete,i.history from items i where i.lastdelete<=%d", now);
	result = DBselect(c);

	if(result==NULL)
	{
		syslog( LOG_DEBUG, "No items to delete.");
		DBfree_result(result);
		return SUCCEED; 
	}
	rows = DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.lastdelete=atoi(DBget_field(result,i,1));
		item.history=atoi(DBget_field(result,i,2));

		sprintf	(c,"delete from history where ItemId=%d and Clock<%d",item.itemid,now-item.history);
		DBexecute(c);
	
		sprintf(c,"update items set LastDelete=%d where ItemId=%d",now,item.itemid);
		DBexecute(c);
	}
	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_alerts(int now)
{
	char		c[1024];
	int		alert_history;
	DB_RESULT	*result;

	sprintf(c,"select alert_history from config");
	result = DBselect(c);

	alert_history=atoi(DBget_field(result,0,0));

	sprintf	(c,"delete from alerts where clock<%d",now-alert_history);
	DBexecute(c);

	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_alarms(int now)
{
	char		c[1024];
	int		alarm_history;
	DB_RESULT	*result;

	sprintf(c,"select alarm_history from config");
	result = DBselect(c);

	alarm_history=atoi(DBget_field(result,0,0));

	sprintf	(c,"delete from alarms where clock<%d",now-alarm_history);
	DBexecute(c);
	
	DBfree_result(result);
	return SUCCEED;
}

int main_housekeeping_loop()
{
	int	now;

	now = time(NULL);

	for(;;)
	{
		housekeeping_items(now);
		housekeeping_alarms(now);
		housekeeping_alerts(now);
		syslog( LOG_DEBUG, "Sleeping for %d hours", CONFIG_HOUSEKEEPING_FREQUENCY);
		sleep(3600*CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}

int main_sucker_loop()
{
	int	now;
	int	nextcheck,sleeptime;

	for(;;)
	{
		now=time(NULL);
		get_values();

		syslog( LOG_DEBUG, "Spent %d seconds while updating values", (int)time(NULL)-now );

		nextcheck=get_minnextcheck(now);
		syslog( LOG_DEBUG, "Nextcheck:%d Time:%d", nextcheck, (int)time(NULL) );

		if( FAIL == nextcheck)
		{
			sleeptime=SUCKER_DELAY;
		}
		else
		{
			sleeptime=nextcheck-time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > SUCKER_DELAY)
			{
				sleeptime = SUCKER_DELAY;
			}
			syslog( LOG_DEBUG, "Sleeping for %d seconds", sleeptime );
			sleep( sleeptime );
		}
		else
		{
			syslog( LOG_DEBUG, "No sleeping" );
		}
	}
}

int main(int argc, char **argv)
{
	int	i;

	struct	sigaction phan;

	daemon_init();

	phan.sa_handler = &signal_handler; /* set up sig handler using sigaction() */
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);

	process_config_file();

	for(i=1;i<CONFIG_SUCKER_FORKS;i++)
	{
		if(fork() == 0)
		{
			/* Do not start all processes at once */
			sleep(1);
			sucker_num=i;
			break;
		}
	}

	syslog( LOG_WARNING, "zabbix_suckerd #%d started",sucker_num);

#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	init_snmp("zabbix_suckerd");
#endif

	DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);

	if(sucker_num == 0)
	{
		main_housekeeping_loop();
	}
	else
	{
		main_sucker_loop();
	}

	return SUCCEED;
}
