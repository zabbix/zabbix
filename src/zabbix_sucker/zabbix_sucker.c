#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include <syslog.h>

#include "config.h"

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

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
		syslog( LOG_WARNING, "Timeout while executing operation." );
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		syslog( LOG_ERR, "\nGot QUIT or INT or TERM signal. Exiting..." );
		exit( FAIL );
	}
 
	return;
}

void	daemon_init(void)
{
	int	i;
	pid_t	pid;

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
	init_snmp("zabbix_sucker");
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
//		if (vars->type == ASN_OCTET_STR)
//		syslog( LOG_WARNING, "Type:%d", vars->type);
			if(	(vars->type == ASN_INTEGER ) ||
			(vars->type == ASN_UINTEGER ) ||
			(vars->type == ASN_COUNTER ) ||
			(vars->type == ASN_GAUGE )
			)
			{
				char *sp = (char *)malloc(1 + vars->val_len);
				memcpy(sp, vars->val.string, vars->val_len);
				sp[vars->val_len] = '\0';
//			syslog( LOG_WARNING, "value #%d is a string: %s\n", count++, sp);
//			*result=strtod(sp,&e);
//			syslog( LOG_WARNING, "Type:%d", vars->type);
//			syslog( LOG_WARNING, "Value #%d is an integer: %d", count++, *vars->val.integer);
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
	void	*sigfunc;

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
		syslog( LOG_WARNING, "Problem with gethostbyname" );
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(item->port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0)
	{
		syslog( LOG_WARNING, "Problem with socket" );
		return	FAIL;
	}
 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	sigfunc = signal( SIGALRM, signal_handler );

	alarm(SUCKER_TIMEOUT);

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		syslog( LOG_WARNING, "Problem with connect" );
		close(s);
		return	FAIL;
	}
	alarm(0);
	signal( SIGALRM, sigfunc );

	sprintf(c,"%s\n",item->key);
	if( sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		syslog(LOG_WARNING, "Problem with sendto" );
		close(s);
		return	FAIL;
	} 
	i=sizeof(struct sockaddr_in);

	sigfunc = signal( SIGALRM, signal_handler );
	alarm(SUCKER_TIMEOUT);

	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i==-1)
	{
		syslog( LOG_WARNING, "Problem with recvfrom [%d]",errno );
		close(s);
		return	FAIL;
	}
	alarm(0);
	signal( SIGALRM, sigfunc );
 
	if( close(s)!=0 )
	{
		syslog(LOG_WARNING, "Problem with close" );
	}
	c[i-1]=0;

	syslog(LOG_DEBUG, "Got string:%10s", c );
	*result=strtod(c,&e);

	if( (*result==0) && (c==e) )
	{
		return	FAIL;
	}
	if( *result<0 )
	{
		if( *result == NOTSUPPORTED)
		{
			return SUCCEED;
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
	if(item->type == ITEM_TYPE_ZABBIX)
	{
		return get_value_zabbix(result,item);
	}
#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	else if(item->type == ITEM_TYPE_SNMP)
	{
		return get_value_SNMPv1(result,item);
	}
#endif
	else
	{
		syslog(LOG_WARNING, "Not supported item type:%d",item->type);
		*result=NOTSUPPORTED;
		return SUCCEED;
	}
}

int get_minnextcheck(void)
{
	char		c[1024];

	DB_RESULT	*result;

	int		res;
	int		count;

	sprintf(c,"select count(*),min(nextcheck) from items i,hosts h where i.status=0 and h.status=0 and h.hostid=i.hostid and i.status=0 and i.itemid%%%d=%d",SUCKER_FORKS,sucker_num);
	result = DBselect(c);

	if(result==NULL)
	{
		syslog(LOG_DEBUG, "No items to update for minnextcheck.");
		DBfree_result(result);
		return FAIL; 
	}

	if(DBnum_rows(result)==0)
	{
		syslog( LOG_DEBUG, "Num_rows is 0.");
		DBfree_result(result);
		return	FAIL;
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
	DB_ITEM		item;
 
	DB_RESULT	*result;

	int		i,rows;
	int		now;

	now = time(NULL);

	sprintf(c,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.history,i.lastdelete,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip from items i,hosts h where i.nextcheck<=%d and i.status=0 and h.status=0 and h.hostid=i.hostid and i.itemid%%%d=%d order by i.nextcheck", now, SUCKER_FORKS,sucker_num);
	result = DBselect(c);

	if(result==NULL)
	{
		syslog( LOG_DEBUG, "No items to update.");
		DBfree_result(result);
		return SUCCEED; 
	}
	rows = DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.key=DBget_field(result,i,1);
		item.host=DBget_field(result,i,2);
		item.port=atoi(DBget_field(result,i,3));
		item.delay=atoi(DBget_field(result,i,4));
		item.description=DBget_field(result,i,5);
		item.history=atoi(DBget_field(result,i,6));
		item.lastdelete=atoi(DBget_field(result,i,7));
		item.nextcheck=atoi(DBget_field(result,i,8));
		item.type=atoi(DBget_field(result,i,9));
		item.snmp_community=DBget_field(result,i,10);
		item.useip=atoi(DBget_field(result,i,12));
		item.ip=DBget_field(result,i,13);

		if( get_value(&value,&item) == SUCCEED )
		{
			if( value == NOTSUPPORTED)
			{
				sprintf(c,"update items set status=3 where itemid=%d",item.itemid);
				DBexecute(c);
			}
			else
			{
				now = time(NULL);
				sprintf(c,"insert into history (itemid,clock,value) values (%d,%d,%g)",item.itemid,now,value);
				DBexecute(c);

				sprintf(c,"update items set NextCheck=%d+%d,PrevValue=LastValue,LastValue=%f,LastClock=%d where ItemId=%d",now,item.delay,value,now,item.itemid);
				DBexecute(c);

				if( update_functions( item.itemid ) == FAIL)
				{
					syslog( LOG_WARNING, "Updating simple functions failed" );
				}

				update_triggers( item.itemid );
			}
		}
		else
		{
			syslog( LOG_WARNING, "Wrong value from host [HOST:%s KEY:%s VALUE:%f]", item.host, item.key, value );
			syslog( LOG_WARNING, "The value is not stored in database.");
		}

		if(item.lastdelete+3600<time(NULL))
		{
			now = time(NULL);
			sprintf	(c,"delete from history where ItemId=%d and Clock<%d-%d",item.itemid,now,item.history);
			DBexecute(c);
	
			now = time(NULL);
			sprintf(c,"update items set LastDelete=%d where ItemId=%d",now,item.itemid);
			DBexecute(c);
		}

	}
	DBfree_result(result);
	return SUCCEED;
}

int main_loop()
{
	int	now;
	int	nextcheck,sleeptime;

	for(;;)
	{
		now=time(NULL);
		get_values();

		syslog( LOG_DEBUG, "Spent %d seconds while updating values", (int)time(NULL)-now );

		nextcheck=get_minnextcheck();
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
	int 	ret;
	int	i;

	daemon_init();

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );

	for(i=1;i<SUCKER_FORKS;i++)
	{
		if(fork() == 0)
		{
			sucker_num=i;
			break;
		}
	}

	openlog("zabbix_sucker",LOG_PID,LOG_USER);
//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));

	syslog( LOG_WARNING, "zabbix_sucker #%d started",sucker_num);

#ifdef HAVE_UCD_SNMP_UCD_SNMP_CONFIG_H
	init_snmp("zabbix_sucker");
#endif

	DBconnect();

	main_loop();

	return SUCCEED;
}
