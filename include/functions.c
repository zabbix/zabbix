#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>

#include "common.h"
#include "db.h"
#include "log.h"

#include "functions.h"
#include "expression.h"

/*
 * Evaluate function MIN
 */ 
int	evaluate_MIN(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	char		*field;

	int		now;

	if(item->value_type != 0)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}

/*	*value=strdup(field);*/
	strncpy(value,field,MAX_STRING_LEN);

	DBfree_result(result);

	return SUCCEED;
}

/*
 * Evaluate function MAX
 */ 
int	evaluate_MAX(char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	char		*field;

	int		now;

	if(item->value_type != 0)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select max(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if((result==NULL)||(DBnum_rows(result)==0))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		DBfree_result(result);
		return	FAIL;
	}

	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	
/*	*value=strdup(field);*/
	strncpy(value,field,MAX_STRING_LEN);

	DBfree_result(result);

	return SUCCEED;
}

/*
 * Evaluate function (min,max,prev,last,diff)
 */ 
int	evaluate_FUNCTION(char *value,DB_ITEM *item,char *function,int parameter)
{
	int	ret  = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "Function [%s]",function);

	if(strcmp(function,"last")==0)
	{
		if(item->lastvalue_null==1)
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==0)
			{
			zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 1");
				sprintf(value,"%f",item->lastvalue);
			zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 2");
			}
			else
			{
/*				*value=strdup(item->lastvalue_str);*/
			zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 3 [%s] [%s]",value,item->lastvalue_str);
				strncpy(value,item->lastvalue_str,MAX_STRING_LEN);
			zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_FUNCTION() 4");
			}
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
			if(item->value_type==0)
			{
				sprintf(value,"%f",item->prevvalue);
			}
			else
			{
				strncpy(value,item->prevvalue_str,MAX_STRING_LEN);
			}
		}
	}
	else if(strcmp(function,"min")==0)
	{
		ret = evaluate_MIN(value,item,parameter);
	}
	else if(strcmp(function,"max")==0)
	{
		ret = evaluate_MAX(value,item,parameter);
	}
	else if(strcmp(function,"diff")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==0)
			{
				if(cmp_double(item->lastvalue, item->prevvalue) == 0)
				{
//					*value=strdup("0");
					strcpy(value,"0");
				}
				else
				{
//					*value=strdup("1");
					strcpy(value,"1");
				}
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
/*					*value=strdup("0");*/
					strcpy(value,"0");
				}
				else
				{
/*					*value=strdup("1");*/
					strcpy(value,"1");
				}
			}
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unsupported function:%s",function);
		ret = FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End of evaluate_FUNCTION");
	return ret;
}

/*
 * Re-calculate values of functions related to given ITEM
 */ 
void	update_functions(DB_ITEM *item)
{
	DB_FUNCTION	function;
	DB_RESULT	*result;
	char		sql[MAX_STRING_LEN+1];
	char		value[MAX_STRING_LEN+1];
	int		ret=SUCCEED;
	int		i,rows;

	sprintf(sql,"select function,parameter,itemid from functions where itemid=%d group by 1,2,3 order by 1,2,3",item->itemid);

	result = DBselect(sql);
	rows=DBnum_rows(result);

	if((result==NULL)||(rows==0))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No functions to update.");
		DBfree_result(result);
		return;
	}

	for(i=0;i<rows;i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=atoi(DBget_field(result,i,1));
		function.itemid=atoi(DBget_field(result,i,2));

		zabbix_log( LOG_LEVEL_DEBUG, "ItemId:%d Evaluating %s(%d)\n",function.itemid,function.function,function.parameter);
		zabbix_log( LOG_LEVEL_DEBUG, "ZZZ (%l)\n",value);


		ret = evaluate_FUNCTION(value,item,function.function,function.parameter);
		if( FAIL == ret)	
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Evaluation failed for function:%s\n",function.function);
			continue;
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Result:%f\n",value);
		if (ret == SUCCEED)
		{
			sprintf(sql,"update functions set lastvalue='%s' where itemid=%d and function='%s' and parameter=%d", value, function.itemid, function.function, function.parameter );
			DBexecute(sql);
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
	char	c[MAX_STRING_LEN+1];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(smtp_server);
	if(hp==NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot get IP for mailserver [%s]",smtp_server);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s==0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot create socket");
		return FAIL;
	}
	
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot connect to SMTP server [%s]",smtp_server);
		return FAIL;
	}
		
	sprintf(c,"HELO %s\n",smtp_helo);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending HELO to mailserver.");
		close(s);
		return FAIL;
	}
			
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on HELO request.");
		close(s);
		return FAIL;
	}
			
	sprintf(c,"MAIL FROM: %s\n",smtp_email);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending MAIL FROM to mailserver.");
		close(s);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on MAIL FROM request.");
		close(s);
		return FAIL;
	}
			
	sprintf(c,"RCPT TO: <%s>\n",mailto);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending RCPT TO to mailserver.");
		close(s);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on RCPT TO request.");
		close(s);
		return FAIL;
	}
	
	sprintf(c,"DATA\nSubject: %s\n",mailsubject);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending DATA to mailserver.");
		close(s);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receivng answer on DATA request.");
		close(s);
		return FAIL;
	}
	sprintf(c,"%s\n",mailbody);
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending mail body to mailserver.");
		close(s);
		return FAIL;
	}
	sprintf(c,".\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending . to mailserver.");
		close(s);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receivng answer on . request.");
		close(s);
		return FAIL;
	}
	
	sprintf(c,"\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e ==- 1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending \\n to mailserver.");
		close(s);
		return FAIL;
	}
	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on \\n request.");
		close(s);
		return FAIL;
	}
	
	sprintf(c,"QUIT\n");
	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending QUIT to mailserver.");
		close(s);
		return FAIL;
	}

	close(s);
	
	return SUCCEED;
}

/*
 * Send message to user. Message will be sent to all medias registered to given user.
 */ 
void	send_to_user(int actionid,int userid,char *smtp_server,char *smtp_helo,char *smtp_email,char *subject,char *message)
{
	DB_MEDIA media;
	char sql[MAX_STRING_LEN+1];
	DB_RESULT *result;

	int	i,rows;
	int	now;

	sprintf(sql,"select type,sendto,active from media where active=0 and userid=%d",userid);
	result = DBselect(sql);

	rows=DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		media.active=atoi(DBget_field(result,i,2));
		media.type=DBget_field(result,i,0);
		media.sendto=DBget_field(result,i,1);

		if(strcmp(media.type,"EMAIL")==0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Email sending to %s %s Subject:%s Message:%s to %d\n", media.type, media.sendto, subject, message, userid );
			if( FAIL == send_mail(smtp_server,smtp_helo,smtp_email,media.sendto,subject,message))
			{
				zabbix_log( LOG_LEVEL_ERR, "Error sending email to '%s' Subject:'%s' to userid:%d\n", media.sendto, subject, userid );
			}
			now = time(NULL);
			sprintf(sql,"insert into alerts (alertid,actionid,clock,type,sendto,subject,message) values (NULL,%d,%d,'%s','%s','%s','%s');",actionid,now,media.type,media.sendto,subject,message);
			DBexecute(sql);
		} 
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Type %s is not supported yet", media.type );
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

	char sql[MAX_STRING_LEN+1];

	char	smtp_server[MAX_STRING_LEN+1],
		smtp_helo[MAX_STRING_LEN+1],
		smtp_email[MAX_STRING_LEN+1];

	int	i,rows;
	int	now;

	if(good==1)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		sprintf(sql,"select count(*) from trigger_depends d,triggers t where d.triggerid_down=%d and d.triggerid_up=t.triggerid and t.istrue=1",triggerid);
		zabbix_log( LOG_LEVEL_DEBUG, "SQL:%s",sql);
		result = DBselect(sql);
		i=atoi(DBget_field(result,0,0));
		zabbix_log( LOG_LEVEL_DEBUG, "I:%d",i);
		DBfree_result(result);
		if(i>0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Will not apply actions");
			return;
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Applying actions");

	/* Get smtp_server and smtp_helo from config */
	sprintf(sql,"select smtp_server,smtp_helo,smtp_email from config");
	result = DBselect(sql);

	strncpy(smtp_server,DBget_field(result,0,0), MAX_STRING_LEN);
	strncpy(smtp_helo,DBget_field(result,0,1), MAX_STRING_LEN);
	strncpy(smtp_email,DBget_field(result,0,2), MAX_STRING_LEN);

	DBfree_result(result);

	now = time(NULL);

	sprintf(sql,"select actionid,userid,delay,subject,message from actions where triggerid=%d and good=%d and nextcheck<=%d",triggerid,good,now);
	result = DBselect(sql);

	rows = DBnum_rows(result);

	for(i=0;i<rows;i++)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Fetched:%s %s %s %s %s\n",DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2),DBget_field(result,i,3),DBget_field(result,i,4));

		action.actionid=atoi(DBget_field(result,i,0));
		action.userid=atoi(DBget_field(result,i,1));
		action.delay=atoi(DBget_field(result,i,2));
		action.subject=DBget_field(result,i,3);
		action.message=DBget_field(result,i,4);

		substitute_macros(&*action.message);
		substitute_macros(&*action.subject); 

		send_to_user(action.actionid,action.userid,smtp_server,smtp_helo,smtp_email,action.subject,action.message);
		now = time(NULL);
		sprintf(sql,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
		DBexecute(sql);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Actions applied for trigger %d %d\n", triggerid, good );
	DBfree_result(result);
}

/*
 * Recursive function!
 */
void	update_services(int triggerid, int istrue)
{
	char	sql[MAX_STRING_LEN+1];
	int	i,rows;

	DB_RESULT *result;


	sprintf(sql,"select serviceupid from services_links where servicedownid=%d", triggerid);

	result = DBselect(sql);

	rows = DBnum_rows(result);

	if(rows == 0)
	{
		DBfree_result(result);
		return;
	}

	for(i=0;i<rows;i++)
	{
		update_services(atoi(DBget_field(result,i,0)), istrue);
	}

	DBfree_result(result);
	return;
}

/*
 * Re-calculate values of triggers
 */ 
void	update_triggers( int suckers, int flag, int sucker_num, int lastclock )
{
	char sql[MAX_STRING_LEN+1];
	char exp[MAX_STRING_LEN+1];
	int b;
	DB_TRIGGER trigger;
	DB_RESULT *result;

	int	i,rows;
	int	now;

	if(flag == 0)
	{
		now=time(NULL);
/* Added table hosts to eliminate unnecessary update of triggers */
		sprintf(sql,"select t.triggerid,t.expression,t.istrue,t.dep_level from triggers t,functions f,items i,hosts h where i.hostid=h.hostid and i.status<>3 and i.itemid=f.itemid and i.lastclock<=%d and t.istrue!=2 and f.triggerid=t.triggerid and f.itemid%%%d=%d and (h.status=0 or (h.status=2 and h.disable_until<%d)) group by t.triggerid,t.expression,t.istrue,t.dep_level",lastclock,suckers-1,sucker_num-1,now);
	}
	else
	{
		sprintf(sql,"select t.triggerid,t.expression,t.istrue,t.dep_level from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and t.istrue!=2 and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.istrue,t.dep_level",sucker_num);
	}

	result = DBselect(sql);

	rows = DBnum_rows(result);

	if(rows == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No triggers to update" );
		DBfree_result(result);
		return;
	}
	for(i=0;i<rows;i++)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Fetched: TrId[%s] Exp[%s] IsTrue[%s]\n", DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2));
		trigger.triggerid=atoi(DBget_field(result,i,0));
		trigger.expression=DBget_field(result,i,1);
		trigger.istrue=atoi(DBget_field(result,i,2));
		strncpy(exp, trigger.expression, MAX_STRING_LEN);
		if( evaluate_expression(&b, exp) != 0 )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Expression [%s] - SUX.",trigger.expression);
			continue;
		}

		if((b==1)&&(trigger.istrue==TRIGGER_STATUS_FALSE))
		{
			now = time(NULL);
			sprintf(sql,"update triggers set istrue=%d, lastchange=%d where triggerid=%d",TRIGGER_STATUS_TRUE,now,trigger.triggerid);
			DBexecute(sql);

			now = time(NULL);
			sprintf(sql,"insert into alarms(triggerid,clock,istrue) values(%d,%d,%d)",trigger.triggerid,now,TRIGGER_STATUS_TRUE);
			DBexecute(sql);

			apply_actions(trigger.triggerid,1);

			sprintf(sql,"update actions set nextcheck=0 where triggerid=%d and good=0",trigger.triggerid);
			DBexecute(sql);

			update_services(trigger.triggerid, 1);
		}

		if((b==0)&&(trigger.istrue==TRIGGER_STATUS_TRUE))
		{
			now = time(NULL);
			sprintf(sql,"update triggers set istrue=%d, lastchange=%d where triggerid=%d",TRIGGER_STATUS_FALSE,now,trigger.triggerid);
			DBexecute(sql);

			now = time(NULL);
			sprintf(sql,"insert into alarms(triggerid,clock,istrue) values(%d,%d,%d)",trigger.triggerid,now,TRIGGER_STATUS_FALSE);
			DBexecute(sql);

			apply_actions(trigger.triggerid,0);

			sprintf(sql,"update actions set nextcheck=0 where triggerid=%d and good=1",trigger.triggerid);
			DBexecute(sql);

			update_services(trigger.triggerid, 0);
		}
	}
	DBfree_result(result);
}

/*
 The fuction is used to evaluate macros for email notifications
*/
int	get_lastvalue(char *value,char *host,char *key,char *function,char *parameter)
{
	DB_ITEM	item;
	DB_RESULT *result;

        char	sql[MAX_STRING_LEN+1];
        int	rows;
	int	parm;
	char	*s;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_lastvalue()" );

	sprintf(sql, "select i.itemid,i.prevvalue,i.lastvalue from items i,hosts h where h.host='%s' and h.hostid=i.hostid and i.key_='%s'", host, key );
	result = DBselect(sql);
        rows = DBnum_rows(result);

	if((result == NULL)||(rows==0))
	{
        	DBfree_result(result);
		zabbix_log(LOG_LEVEL_WARNING, "Query [%s] returned empty result" );
		return FAIL;	
	}

        item.itemid=atoi(DBget_field(result,0,0));
	s=DBget_field(result,0,1);
	if(s==NULL)
	{
		item.prevvalue_null=1;
	}
	else
	{
		item.prevvalue_null=0;
		item.prevvalue_str=s;
		item.prevvalue=atof(s);
	}
	s=DBget_field(result,0,2);
	if(s==NULL)
	{
		item.lastvalue_null=1;
	}
	else
	{
		item.lastvalue_null=0;
		item.lastvalue_str=s;
		item.lastvalue=atof(s);
	}



	zabbix_log(LOG_LEVEL_DEBUG, "Itemid:%d", item.itemid );
        DBfree_result(result);

	parm=atoi(parameter);
	zabbix_log(LOG_LEVEL_DEBUG, "Before evaluate_FUNCTION()" );

	return evaluate_FUNCTION(value,&item,function,parm);
}

/* For zabbix_trapper(d) */
/* int	process_data(char *server,char *key, double value)*/
int	process_data(char *server,char *key,char *value)
{
	char	sql[MAX_STRING_LEN+1];

	DB_RESULT       *result;
	DB_ITEM	item;
	char	*s;

	sprintf(sql,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.value_type from items i,hosts h where h.status in (0,2) and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=%d", server, key, ITEM_TYPE_TRAPPER);
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
		item.lastvalue_str=s;
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
		item.prevvalue_str=s;
		item.prevvalue=atof(s);
	}
	item.value_type=atoi(DBget_field(result,0,15));

	process_new_value(&item,value);

	update_triggers(0, 1, item.itemid, 0 );
 
	DBfree_result(result);

	return SUCCEED;
}

void	process_new_value(DB_ITEM *item,char *value)
{
	int 	now;
	char	sql[MAX_STRING_LEN+1];
	double	value_double;
	char	*e;

	now = time(NULL);

	value_double=strtod(value,&e);

	if(item->history>0)
	{
		if(item->value_type==0)
			sprintf(sql,"insert into history (itemid,clock,value) \
				values (%d,%d,%g)",item->itemid,now,value_double);
		else
			sprintf(sql,"insert into history_str (itemid,clock,value) 
				values (%d,%d,'%s')",item->itemid,now,value);
		DBexecute(sql);
	}

//	if((item->prevvalue_null == 1) || (cmp_double(value_double,item->lastvalue) != 0) || (cmp_double(item->prevvalue,item->lastvalue) != 0) )
	if((item->prevvalue_null == 1) || (strcmp(value,item->lastvalue_str) != 0) || (strcmp(item->prevvalue_str,item->lastvalue_str) != 0) )
	{
		sprintf(sql,"update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d where itemid=%d",now+item->delay,value,now,item->itemid);
		item->prevvalue=item->lastvalue;
		item->lastvalue=value_double;
		item->prevvalue_str=item->lastvalue_str;
/* Risky !!!*/
		item->lastvalue_str=value;
		item->prevvalue_null=item->lastvalue_null;
		item->lastvalue_null=0;
	}
	else
	{
		sprintf(sql,"update items set nextcheck=%d,lastclock=%d where itemid=%d",now+item->delay,now,item->itemid);
	}
	DBexecute(sql);

	update_functions( item );
}
