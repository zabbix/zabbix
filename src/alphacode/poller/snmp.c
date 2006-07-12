/*
 * NET-SNMP demo
 *
 * This program demonstrates different ways to query a list of hosts
 * for a list of variables.
 *
 * It would of course be faster just to send one query for all variables,
 * but the intention is to demonstrate the difference between synchronous
 * and asynchronous operation.
 *
 * Niels Baggesen (Niels.Baggesen@uni-c.dk), 1999.
 */

#include <string.h>
#include <stdio.h>
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>

#include "mysql.h"
#include "errmsg.h"
#include "mysqld_error.h"

MYSQL	mysql;

#define CONFIG_DBHOST ""
#define CONFIG_DBNAME "demo"
#define CONFIG_DBUSER "root"
#define CONFIG_DBPASSWORD ""

/*
 * a list of hosts to query
 */
struct host {
  char *hostname;
  char *community;
} hosts[10000] = {
//  { "192.168.1.60",		"public" },
  { NULL }
};

/*
 * a list of variables to query for
 */
struct oid {
  const char *hostname;
  const char *Name;
  oid Oid[MAX_OID_LEN];
  int OidLen;
} oids[10000] = {
//  { "192.168.1.5", ".1.3.6.1.2.1.1.6.5" },
  { NULL }
};

char	*DBget_field(MYSQL_RES *result, int rownum, int fieldnum)
{
	MYSQL_ROW	row;

	mysql_data_seek(result, rownum);
	row=mysql_fetch_row(result);
	if(row == NULL)
	{
		printf("Error while mysql_fetch_row():Error [%s] Rownum [%d] Fieldnum [%d]\n", mysql_error(&mysql), rownum, fieldnum );
		exit(-1);
	}
	return row[fieldnum];
}

int	DBnum_rows(MYSQL_RES *result)
{
	int rows;

	if(result == NULL)
	{
		return	0;
	}
/* Order is important ! */
	rows = mysql_num_rows(result);
	if(rows == 0)
	{
		return	0;
	}
	
/* This is necessary to exclude situations like
 * atoi(DBget_field(result,0,0). This leads to coredump.
 */
/* This is required for empty results for count(*), etc */
	if(DBget_field(result,0,0) == 0)
	{
		return	0;
	}
	return rows;
}

MYSQL_RES *DBselect(char *query)
{
	while(mysql_query(&mysql,query) != 0)
	{
		printf("Query:%s\n",query);
		printf("Query failed:%s [%d]\n", mysql_error(&mysql), mysql_errno(&mysql) );

		if( (ER_SERVER_SHUTDOWN   != mysql_errno(&mysql)) && 
            (CR_SERVER_GONE_ERROR != mysql_errno(&mysql)) &&
            (CR_CONNECTION_ERROR  != mysql_errno(&mysql)))
		{
			exit(-1);
		}
	}

	return	mysql_store_result(&mysql);
}

void load_oids(void)
{
	char sql[1024];
	char *hostname;
	char *community;
	char *oid;
	MYSQL_RES       *result;
	int i;

	struct host *h;
	struct oid *o;

	zbx_snprintf(sql, sizeof(sql), "select h.ip,i.snmp_community,i.snmp_oid from hosts h,items i where i.hostid=h.hostid and i.type=1 and i.status=0 and h.status=0 and h.useip=1");

	result=DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
//		printf("[%s] [%s] [%s]\n",DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2));

		hostname = DBget_field(result,i,0);
		community = DBget_field(result,i,1);
		oid = DBget_field(result,i,2);

		h = hosts;
		while(h->hostname)
		{
			if(strcmp(h->hostname,hostname)==0)	break;
			h++;
		}

		if(!h->hostname)
		{
			h->hostname=strdup(hostname);
			h->community=strdup(community);
			h++;
			h->hostname = NULL;
		}

		o = oids;
		while(o->hostname)
		{
			o++;
		}
		o->hostname=strdup(hostname);
		o->Name=strdup(oid);
		o++;
		o->hostname = NULL;
	}

	h = hosts;
	i = 0;
	while(h->hostname)
	{
		i++;
		h++;
	}
	printf("Loaded [%d] hosts\n", i);
	o = oids;
	i = 0;
	while(o->hostname)
	{
		i++;
		o++;
	}
	printf("Loaded [%d] OIDs\n", i);
}

/*
 * initialize
 */
void initialize (void)
{
	struct oid *op;
  
	init_snmp("asynchapp");

	load_oids();

/* parse the oids */
	op = oids;
	while (op->hostname) {
//		printf("[%s]\n",op->hostname);
		op->OidLen = sizeof(op->Oid)/sizeof(op->Oid[0]);
		if (!read_objid(op->Name, op->Oid, &op->OidLen)) {
			snmp_perror("read_objid");
			exit(1);
		}
		op++;
	}
	printf("Press Enter to start polling");
	getc(stdin);
}

/*
 * simple printing of returned data
 */
int print_result (int status, struct snmp_session *sp, struct snmp_pdu *pdu)
{
	char buf[1024];
	struct variable_list *vp;
	int ix;
	struct timeval now;
	struct timezone tz;
	struct tm *tm;

	gettimeofday(&now, &tz);
	tm = localtime(&now.tv_sec);
	fprintf(stdout, "%.2d:%.2d:%.2d.%.6d ", tm->tm_hour, tm->tm_min, tm->tm_sec,
		now.tv_usec);
	switch (status) {
		case STAT_SUCCESS:
			vp = pdu->variables;
			if (pdu->errstat == SNMP_ERR_NOERROR) {
				while (vp) {
					snprint_variable(buf, sizeof(buf), vp->name, vp->name_length, vp);
					fprintf(stdout, "%s: %s\n", sp->peername, buf);
					vp = vp->next_variable;
				}
			}
			else {
				for (ix = 1; vp && ix != pdu->errindex; vp = vp->next_variable, ix++)
				;
				if (vp) snprint_objid(buf, sizeof(buf), vp->name, vp->name_length);
				else strcpy(buf, "(none)");
				fprintf(stdout, "%s: %s: %s\n",
				sp->peername, buf, snmp_errstring(pdu->errstat));
			}
			return 1;
	case STAT_TIMEOUT:
		fprintf(stdout, "%s: Timeout\n", sp->peername);
		return 0;
	case STAT_ERROR:
		snmp_perror(sp->peername);
		return 0;
	}
	return 0;
}

/*****************************************************************************/

/*
 * poll all hosts in parallel
 */
struct session {
	struct snmp_session *sess;		/* SNMP session data */
	struct oid *current_oid;		/* How far in our poll are we */
} sessions[sizeof(hosts)/sizeof(hosts[0])];

int active_hosts;			/* hosts that we have not completed */

/*
 * response handler
 */
int asynch_response(int operation, struct snmp_session *sp, int reqid,
		    struct snmp_pdu *pdu, void *magic)
{
	struct session *host = (struct session *)magic;
	struct snmp_pdu *req;
	struct oid *op;

	if (operation == NETSNMP_CALLBACK_OP_RECEIVED_MESSAGE) {
		if (print_result(STAT_SUCCESS, host->sess, pdu)) {
//			host->current_oid++;			/* send next GET (if any) */
			op = host->current_oid;
			op++;
			while(op->hostname)
			{
//				printf("[%s] [%s]\n",op->hostname, host->current_oid->hostname);
				if(strcmp(op->hostname,host->current_oid->hostname)==0) {
					host->current_oid = op;
					break;
				}
				op++;
			}

			if (op->hostname && host->current_oid->Name) {
				req = snmp_pdu_create(SNMP_MSG_GET);
				snmp_add_null_var(req, host->current_oid->Oid, host->current_oid->OidLen);
				if (snmp_send(host->sess, req))
					return 1;
				else {
					snmp_perror("snmp_send");
					snmp_free_pdu(req);
				}
			}
			else
			{
//				printf("No more OIDs for [%s]\n", host->current_oid->hostname);
			}
		}
	}
	else
		print_result(STAT_TIMEOUT, host->sess, pdu);

/* something went wrong (or end of variables) 
* this host not active any more
*/
	active_hosts--;
	return 1;
}

void asynchronous(void)
{
	struct session *hs;
	struct host *hp;
	struct oid *op;

/* startup all hosts */

	for (hs = sessions, hp = hosts; hp->hostname; hs++, hp++) {
		struct snmp_pdu *req;
		struct snmp_session sess;
		snmp_sess_init(&sess);			/* initialize session */
		sess.version = SNMP_VERSION_1;
		//    sess.version = SNMP_VERSION_2c;
		sess.peername = strdup(hp->hostname);
		sess.community = strdup(hp->community);
		sess.community_len = strlen(sess.community);
		sess.callback = asynch_response;		/* default callback */
		sess.callback_magic = hs;
		if (!(hs->sess = snmp_open(&sess))) {
			snmp_perror("snmp_open");
			continue;
		}
		op = oids;
		while(op->hostname)
		{
			if(strcmp(op->hostname,hp->hostname)==0) {
				hs->current_oid = op;
				break;
			}
			op++;
		}
		if(!op->Name) {
			printf("No OIDs for [%s]\n", hp->hostname);
			continue;
		}
//		printf("Sending request [%s] [%s]\n",hp->hostname, hs->current_oid->Name);
		req = snmp_pdu_create(SNMP_MSG_GET);	/* send the first GET */
		snmp_add_null_var(req, hs->current_oid->Oid, hs->current_oid->OidLen);
		if (snmp_send(hs->sess, req))
				active_hosts++;
		else {
			snmp_perror("snmp_send");
			snmp_free_pdu(req);
		}
	}

/* loop while any active hosts */

	while (active_hosts) {
		int fds = 0, block = 1;
		fd_set fdset;
		struct timeval timeout;

		FD_ZERO(&fdset);
		snmp_select_info(&fds, &fdset, &timeout, &block);
		fds = select(fds, &fdset, NULL, NULL, block ? NULL : &timeout);
		if (fds < 0) {
			perror("select failed");
			exit(1);
		}
		if (fds)
			snmp_read(&fdset);
		else
			snmp_timeout();
	}

/* cleanup */

	for (hp = hosts, hs = sessions; hp->hostname; hs++, hp++) {
		if (hs->sess) snmp_close(hs->sess);
	}
}

void	DBclose(void)
{
	mysql_close(&mysql);
}

void    DBconnect(void)
{
	mysql_init(&mysql);

    if( ! mysql_real_connect( &mysql, CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, NULL, NULL,0 ) )
	{
		printf("Failed to connect to database: Error: %s\n",mysql_error(&mysql));
		exit(-1);
	}
	else
	{
		if( mysql_select_db( &mysql, CONFIG_DBNAME ) != 0 )
		{
			printf("Failed to select database: Error: %s\n",mysql_error(&mysql) );
			exit(-1);
		}
	}
}

int	DBexecute(char *query)
{
	while( mysql_query(&mysql,query) != 0)
	{
		printf("Query::%s\n",query);
		printf("Query failed:%s [%d]\n", mysql_error(&mysql), mysql_errno(&mysql) );

		if( (ER_SERVER_SHUTDOWN   != mysql_errno(&mysql)) && 
            (CR_SERVER_GONE_ERROR != mysql_errno(&mysql)) &&
            (CR_CONNECTION_ERROR  != mysql_errno(&mysql)))
		{
			return -1;
		}

        	DBclose();
        	DBconnect();
	}
}



/*****************************************************************************/

int main (int argc, char **argv)
{
	DBconnect();
	initialize();

	printf("---------- asynchronous -----------\n");
	asynchronous();

	return 0;
}
