#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <syslog.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>
#include <syslog.h>

#include "common.h"
#include "db.h"

#include "functions.h"
#include "expression.h"

/*
 * Evaluate function MIN
 */ 
int	evaluate_MIN(float *min,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[256];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select min(value) from history where clock>%d and itemid=%d",now-parameter,itemid);
	syslog(LOG_WARNING, "SQL:%s", c );

	result = DBselect(c);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		syslog(LOG_NOTICE, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		syslog( LOG_NOTICE, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	*min=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

/*
 * Evaluate function MAX
 */ 
int	evaluate_MAX(float *max,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[256];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select max(value) from history where clock>%d and itemid=%d",now-parameter,itemid);

	result = DBselect(c);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		syslog(LOG_NOTICE, "Result for MAX is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		syslog( LOG_NOTICE, "Result for MAX is empty" );
		DBfree_result(result);
		return	FAIL;
	}	
	*max=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

/*
 * Evaluate function (min,max,prev,last,diff)
 */ 
int	evaluate_FUNCTION(float *value,DB_ITEM *item,char *function,int parameter)
{
	int	ret  = SUCCEED;

	if(strcmp(function,"last")==0)
	{
		if(item->lastvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			*value=item->lastvalue;
		}
	}
	else if(strcmp(function,"prev")==0)
	{
		if(item->prevvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			*value=item->prevvalue;
		}
	}
	else if(strcmp(function,"min")==0)
	{
		ret = evaluate_MIN(value,item->itemid,parameter);
	}
	else if(strcmp(function,"max")==0)
	{
		ret = evaluate_MAX(value,item->itemid,parameter);
	}
	else if(strcmp(function,"diff")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(cmp_double(item->lastvalue, item->prevvalue) == 0)
			{
				*value=0;
			}
			else
			{
				*value=1;
			}
		}
	}
	else
	{
		syslog( LOG_WARNING, "Unknown function:%s\n",function);
		ret = FAIL;
	}
	return ret;
}

/*
 * Re-calculate values of functions related to given ITEM
 */ 
void	update_functions(DB_ITEM *item)
{
	DB_FUNCTION	function;
	DB_RESULT	*result;
	char		c[1024];
	float		value;
	int		ret=SUCCEED;
	int		i,rows;

	sprintf(c,"select function,parameter,itemid from functions where itemid=%d group by 1,2,3 order by 1,2,3",item->itemid);

	result = DBselect(c);
	rows=DBnum_rows(result);

	if((result==NULL)||(rows==0))
	{
		syslog( LOG_NOTICE, "No functions to update.");
		DBfree_result(result);
		return;
		/*continue;*/
	}

	for(i=0;i<rows;i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=atoi(DBget_field(result,i,1));
		function.itemid=atoi(DBget_field(result,i,2));

		syslog( LOG_DEBUG, "ItemId:%d Evaluating %s(%d)\n",function.itemid,function.function,function.parameter);

		ret = evaluate_FUNCTION(&value,item,function.function,function.parameter);
		if( FAIL == ret)	
		{
			syslog( LOG_WARNING, "Evaluation failed for function:%s\n",function.function);
			continue;
		}
		syslog( LOG_DEBUG, "Result:%f\n",value);
		if (ret == SUCCEED)
		{
			sprintf(c,"update functions set lastvalue=%f where itemid=%d and function='%s' and parameter=%d", value, function.itemid, function.function, function.parameter );
			DBexecute(c);
		}
	}

	DBfree_result(result);
}

/*
 * Send email
 */ 
int	send_mail(char *smtp_server,char *smtp_helo,char *smtp_email,char *mailto,char *mailsubject,char *mailbody)
{
	int	s;
	int	i,e;
	char	*c;
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	syslog( LOG_DEBUG, "SENDING MAIL");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(smtp_server);
	if(hp==NULL)
	{
		syslog(LOG_ERR, "Cannot get IP for mailserver [%s]",smtp_server);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0)
	{
		syslog(LOG_ERR, "Cannot create socket");
		return FAIL;
	}
	
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		syslog(LOG_ERR, "Cannot connect to SMTP server [%s]",smtp_server);
		return FAIL;
	}
		
	c=(char *)malloc(1024);
	if(c == NULL)
	{
		syslog(LOG_ERR, "Malloc error.");
		close(s);
		return FAIL;
	}

	sprintf(c,"HELO %s\n",smtp_helo);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending HELO to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
			
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receiving answer on HELO request.");
		close(s);
		free(c);
		return FAIL;
	}
			
	sprintf(c,"MAIL FROM: %s\n",smtp_email);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending MAIL FROM to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receiving answer on MAIL FROM request.");
		close(s);
		free(c);
		return FAIL;
	}
			
	sprintf(c,"RCPT TO: <%s>\n",mailto);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending RCPT TO to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receiving answer on RCPT TO request.");
		close(s);
		free(c);
		return FAIL;
	}
	
	sprintf(c,"DATA\nSubject: %s\n",mailsubject);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending DATA to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receivng answer on DATA request.");
		close(s);
		free(c);
		return FAIL;
	}
	sprintf(c,"%s\n",mailbody);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending mail body to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	sprintf(c,".\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending . to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receivng answer on . request.");
		close(s);
		free(c);
		return FAIL;
	}
	
	sprintf(c,"\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e ==- 1)
	{
		syslog(LOG_ERR, "Error sending \\n to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,1023,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		syslog(LOG_ERR, "Error receivng answer on \\n request.");
		close(s);
		free(c);
		return FAIL;
	}
	
	sprintf(c,"QUIT\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		syslog(LOG_ERR, "Error sending QUIT to mailserver.");
		close(s);
		free(c);
		return FAIL;
	}

	close(s);
	free(c); 
	
	return SUCCEED;
}

/*
 * Send message to user. Message will be sent to all medias registered to given user.
 */ 
void	send_to_user(int actionid,int userid,char *smtp_server,char *smtp_helo,char *smtp_email,char *subject,char *message)
{
	DB_MEDIA media;
	char c[1024];
	DB_RESULT *result;

	int	i,rows;
	int	now;

	sprintf(c,"select type,sendto,active from media where active=0 and userid=%d",userid);
	result = DBselect(c);

	rows=DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		media.active=atoi(DBget_field(result,i,2));
		media.type=DBget_field(result,i,0);
		media.sendto=DBget_field(result,i,1);

		if(strcmp(media.type,"EMAIL")==0)
		{
			syslog( LOG_DEBUG, "Email sending to %s %s Subject:%s Message:%s to %d\n", media.type, media.sendto, subject, message, userid );
			if( FAIL == send_mail(smtp_server,smtp_helo,smtp_email,media.sendto,subject,message))
			{
				syslog( LOG_ERR, "Error sending email to '%s' Subject:'%s' to userid:%d\n", media.sendto, subject, userid );
			}
			now = time(NULL);
			sprintf(c,"insert into alerts (alertid,actionid,clock,type,sendto,subject,message) values (NULL,%d,%d,'%s','%s','%s','%s');",actionid,now,media.type,media.sendto,subject,message);
			DBexecute(c);
		} 
		else
		{
			syslog( LOG_WARNING, "Type %s is not supported yet", media.type );
		}
	}
	DBfree_result(result);
}

/*
 * Apply actions if any.
 */ 
void	apply_actions(int triggerid,int good)
{
	DB_RESULT *result;
	
	DB_ACTION action;

	char c[1024];

	char	smtp_server[256],
		smtp_helo[256],
		smtp_email[256];

	int	i,rows;
	int	now;

	if(good==1)
	{
		syslog( LOG_DEBUG, "Check dependencies");

		sprintf(c,"select count(*) from trigger_depends d,triggers t where d.triggerid_down=%d and d.triggerid_up=t.triggerid and t.istrue=1",triggerid);
		syslog( LOG_DEBUG, "SQL:%s",c);
		result = DBselect(c);
		i=atoi(DBget_field(result,0,0));
		syslog( LOG_DEBUG, "I:%d",i);
		DBfree_result(result);
		if(i>0)
		{
			syslog( LOG_DEBUG, "Will not apply actions");
			return;
		}
	}

	syslog( LOG_DEBUG, "Applying actions");

	/* Get smtp_server and smtp_helo from config */
	sprintf(c,"select smtp_server,smtp_helo,smtp_email from config");
	result = DBselect(c);

	strcpy(smtp_server,DBget_field(result,0,0));
	strcpy(smtp_helo,DBget_field(result,0,1));
	strcpy(smtp_email,DBget_field(result,0,2));

	DBfree_result(result);

	now = time(NULL);

	sprintf(c,"select actionid,userid,delay,subject,message from actions where triggerid=%d and good=%d and nextcheck<=%d",triggerid,good,now);
	result = DBselect(c);

	rows = DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		syslog( LOG_DEBUG, "Fetched:%s %s %s %s %s\n",DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2),DBget_field(result,i,3),DBget_field(result,i,4));

		action.actionid=atoi(DBget_field(result,i,0));
		action.userid=atoi(DBget_field(result,i,1));
		action.delay=atoi(DBget_field(result,i,2));
		action.subject=DBget_field(result,i,3);
		action.message=DBget_field(result,i,4);

		substitute_macros(&*action.message);
		substitute_macros(&*action.subject); 

		send_to_user(action.actionid,action.userid,smtp_server,smtp_helo,smtp_email,action.subject,action.message);
		now = time(NULL);
		sprintf(c,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
		DBexecute(c);
	}
	syslog( LOG_DEBUG, "Actions applied for trigger %d %d\n", triggerid, good );
	DBfree_result(result);
}

/*
 * Re-calculate values of triggers
 */ 
void	update_triggers( int flag, int sucker_num, int lastclock )
{
	char c[1024];
	char exp[8192];
	int b;
	DB_TRIGGER trigger;
	DB_RESULT *result;

	int	i,rows;
	int	now;

	if(flag == 0)
	{
		sprintf(c,"select t.triggerid,t.expression,t.istrue,t.dep_level from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and i.lastclock<=%d and t.istrue!=2 and f.triggerid=t.triggerid and f.itemid%%%d=%d group by t.triggerid,t.expression,t.istrue,t.dep_level",lastclock,SUCKER_FORKS-1,sucker_num-1);
	}
	else
	{
		sprintf(c,"select t.triggerid,t.expression,t.istrue,t.dep_level from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and t.istrue!=2 and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.istrue,t.dep_level",sucker_num);
	}

	result = DBselect(c);

	rows = DBnum_rows(result);

	if(rows == 0)
	{
		syslog( LOG_NOTICE, "No triggers to update" );
		DBfree_result(result);
		return;
	}
	for(i=0;i<rows;i++)
	{
		syslog( LOG_DEBUG, "Fetched: TrId[%s] Exp[%s] IsTrue[%s]\n", DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2));
		trigger.triggerid=atoi(DBget_field(result,i,0));
		trigger.expression=DBget_field(result,i,1);
		trigger.istrue=atoi(DBget_field(result,i,2));
		strcpy(exp, trigger.expression);
		if( evaluate_expression(&b, exp) != 0 )
		{
			syslog( LOG_WARNING, "Expression %s - SUX.",trigger.expression);
			continue;
		}

/*		now = time(NULL);
		sprintf(c,"update triggers set lastcheck=%d where triggerid=%d",now,trigger.triggerid);
		DBexecute(c);*/

		if((b==1)&&(trigger.istrue!=1))
		{
			now = time(NULL);
			sprintf(c,"update triggers set IsTrue=1, lastchange=%d where triggerid=%d",now,trigger.triggerid);
			DBexecute(c);

			now = time(NULL);
			sprintf(c,"insert into alarms(triggerid,clock,istrue) values(%d,%d,1)",trigger.triggerid,now);
			DBexecute(c);

			apply_actions(trigger.triggerid,1);

			sprintf(c,"update actions set nextcheck=0 where triggerid=%d and good=0",trigger.triggerid);
			DBexecute(c);
		}

		if((b==0)&&(trigger.istrue!=0))
		{
			now = time(NULL);
			sprintf(c,"update triggers set IsTrue=0, lastchange=%d where triggerid=%d",now,trigger.triggerid);
			DBexecute(c);

			now = time(NULL);
			sprintf(c,"insert into alarms(triggerid,clock,istrue) values(%d,%d,0)",trigger.triggerid,now);
			DBexecute(c);

			apply_actions(trigger.triggerid,0);

			sprintf(c,"update actions set nextcheck=0 where triggerid=%d and good=1",trigger.triggerid);
			DBexecute(c);
		}
	}
	DBfree_result(result);
}

int	get_lastvalue(float *Result,char *host,char *key,char *function,char *parameter)
{
	DB_ITEM	item;
	DB_RESULT *result;

        char	c[128];
        int	rows;
	int	parm;

	sprintf( c, "select i.itemid from items i,hosts h where h.host='%s' and h.hostid=i.hostid and i.key_='%s'", host, key );
	result = DBselect(c);
        rows = DBnum_rows(result);

	if((result == NULL)||(rows==0))
	{
        	DBfree_result(result);
		syslog(LOG_WARNING, "Query failed" );
		return FAIL;	
	}

        item.itemid=atoi(DBget_field(result,0,0));
	syslog(LOG_DEBUG, "Itemid:%d", item.itemid );
        DBfree_result(result);

	parm=atoi(parameter);
	evaluate_FUNCTION(Result,&item,function,parm);

        return SUCCEED;
}

/* For zabbix_trapper(d) */
int	process_data(char *server,char *key, double value)
{
	char	sql[1024];

	DB_RESULT       *result;
	DB_ITEM	item;
	char	*s;

/*	sprintf(sql,"select i.itemid,i.lastvalue from items i,hosts h where h.status=0 and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=2;",server,key);*/
	sprintf(sql,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue from items i,hosts h where h.status=0 and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=2", server, key);
	result = DBselect(sql);

	if(result==NULL)
	{
		DBfree_result(result);
		return  FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		DBfree_result(result);
		return  FAIL;
	}

	if( DBget_field(result,0,0) == NULL )
	{
		DBfree_result(result);
		return  FAIL;
	}
	
	item.itemid=atoi(DBget_field(result,0,0));
	item.key=DBget_field(result,0,1);
	item.host=DBget_field(result,0,2);
	item.port=atoi(DBget_field(result,0,3));
	item.delay=atoi(DBget_field(result,0,4));
	item.description=DBget_field(result,0,5);
	item.nextcheck=atoi(DBget_field(result,0,6));
	item.type=atoi(DBget_field(result,0,7));
	item.snmp_community=DBget_field(result,0,8);
	item.snmp_oid=DBget_field(result,0,9);
	item.useip=atoi(DBget_field(result,0,10));
	item.ip=DBget_field(result,0,11);
	item.history=atoi(DBget_field(result,0,12));
	s=DBget_field(result,0,13);
	if(s==NULL)
	{
		item.lastvalue_null=1;
	}
	else
	{
		item.lastvalue_null=0;
		item.lastvalue=atof(s);
	}
	s=DBget_field(result,0,14);
	if(s==NULL)
	{
		item.prevvalue_null=1;
	}
	else
	{
		item.prevvalue_null=0;
		item.prevvalue=atof(s);
	}

	process_new_value(&item,value);

	update_triggers( 1, item.itemid, 0 );
 
	DBfree_result(result);

	return SUCCEED;
}

void	process_new_value(DB_ITEM *item,double value)
{
	int 	now;
	char	c[1024];

	now = time(NULL);

	if(item->history>0)
	{
		sprintf(c,"insert into history (itemid,clock,value) values (%d,%d,%g)",item->itemid,now,value);
		DBexecute(c);
	}

	if((item->prevvalue_null == 1) || (cmp_double(value,item->lastvalue) != 0) || (cmp_double(item->prevvalue,item->lastvalue) != 0) )
	{
		sprintf(c,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue=%f,lastclock=%d where itemid=%d",now+item->delay,value,now,item->itemid);
		item->prevvalue=item->lastvalue;
		item->lastvalue=value;
		item->prevvalue_null=0;
		item->lastvalue_null=0;
	}
	else
	{
		sprintf(c,"update items set NextCheck=%d,LastClock=%d where ItemId=%d",now+item->delay,now,item->itemid);
	}
	DBexecute(c);

	update_functions( item );
}
