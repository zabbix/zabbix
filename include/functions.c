/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
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
#include "security.h"

#include "functions.h"
#include "expression.h"

/*
 * Evaluate function COUNT
 */ 
int	evaluate_COUNT(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select count(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for COUNT is empty" );
		res = FAIL;
	}
	else
	{
		strncpy(value,DBget_field(result,0,0),MAX_STRING_LEN);
	}
	DBfree_result(result);

	return res;
}

/*
 * Evaluate function SUM
 */ 
int	evaluate_SUM(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select sum(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for SUM is empty" );
		res = FAIL;
	}
	else
	{
		strncpy(value,DBget_field(result,0,0),MAX_STRING_LEN);
	}
	DBfree_result(result);

	return res;
}

/*
 * Evaluate function AVG
 */ 
int	evaluate_AVG(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select avg(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for AVG is empty" );
		res = FAIL;
	}
	else
	{
		strncpy(value,DBget_field(result,0,0),MAX_STRING_LEN);
	}
	DBfree_result(result);

	return res;
}

/*
 * Evaluate function MIN
 */ 
int	evaluate_MIN(char *value,DB_ITEM	*item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MIN is empty" );
		res = FAIL;
	}
	else
	{
		strncpy(value,DBget_field(result,0,0),MAX_STRING_LEN);
	}
	DBfree_result(result);

	return res;
}

/*
 * Evaluate function MAX
 */ 
int	evaluate_MAX(char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select max(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		res = FAIL;
	}
	else
	{
		strncpy(value,DBget_field(result,0,0),MAX_STRING_LEN);
	}
	DBfree_result(result);

	return res;
}

/*
 * Evaluate function DELTA
 */ 
int	evaluate_DELTA(char *value,DB_ITEM *item,int parameter)
{
	DB_RESULT	*result;

	char		sql[MAX_STRING_LEN+1];
	int		now;
	int		res = SUCCEED;

	if(item->value_type != ITEM_VALUE_TYPE_FLOAT)
	{
		return	FAIL;
	}

	now=time(NULL);

	sprintf(sql,"select max(value)-min(value) from history where clock>%d and itemid=%d",now-parameter,item->itemid);

	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for DELTA is empty" );
		res = FAIL;
	}
	else
	{
		strncpy(value,DBget_field(result,0,0),MAX_STRING_LEN);
	}
	DBfree_result(result);

	return res;
}

/*
 * Evaluate function (avg,min,max,prev,last,diff,str,change,abschange,delta)
 */ 
int	evaluate_FUNCTION(char *value,DB_ITEM *item,char *function,char *parameter)
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
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
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
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
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
		ret = evaluate_MIN(value,item,atoi(parameter));
	}
	else if(strcmp(function,"max")==0)
	{
		ret = evaluate_MAX(value,item,atoi(parameter));
	}
	else if(strcmp(function,"avg")==0)
	{
		ret = evaluate_AVG(value,item,atoi(parameter));
	}
	else if(strcmp(function,"sum")==0)
	{
		ret = evaluate_SUM(value,item,atoi(parameter));
	}
	else if(strcmp(function,"count")==0)
	{
		ret = evaluate_COUNT(value,item,atoi(parameter));
	}
	else if(strcmp(function,"delta")==0)
	{
		ret = evaluate_DELTA(value,item,atoi(parameter));
	}
	else if(strcmp(function,"nodata")==0)
	{
		strcpy(value,"0");
	}
	else if(strcmp(function,"abschange")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				sprintf(value,"%f",(float)abs(item->lastvalue-item->prevvalue));
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"change")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				sprintf(value,"%f",item->lastvalue-item->prevvalue);
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"diff")==0)
	{
		if((item->lastvalue_null==1)||(item->prevvalue_null==1))
		{
			ret = FAIL;
		}
		else
		{
			if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
			{
				if(cmp_double(item->lastvalue, item->prevvalue) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
			else
			{
				if(strcmp(item->lastvalue_str, item->prevvalue_str) == 0)
				{
					strcpy(value,"0");
				}
				else
				{
					strcpy(value,"1");
				}
			}
		}
	}
	else if(strcmp(function,"str")==0)
	{
		if(item->value_type==ITEM_VALUE_TYPE_STR)
		{
			if(strstr(item->lastvalue_str, parameter) == NULL)
			{
				strcpy(value,"0");
			}
			else
			{
				strcpy(value,"1");
			}

		}
		else
		{
			ret = FAIL;
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unsupported function:%s",function);
		ret = FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End of evaluate_FUNCTION. Result [%s]",value);
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
	int		i;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_finctions(%d)",item->itemid);

	sprintf(sql,"select function,parameter,itemid from functions where itemid=%d group by 1,2,3 order by 1,2,3",item->itemid);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=DBget_field(result,i,1);
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
			sprintf(sql,"update functions set lastvalue='%s' where itemid=%d and function='%s' and parameter='%s'", value, function.itemid, function.function, function.parameter );
			DBexecute(sql);
		}
	}

	DBfree_result(result);
}

/*
 * Send email
 */ 
int	send_email(char *smtp_server,char *smtp_helo,char *smtp_email,char *mailto,char *mailsubject,char *mailbody)
{
	int	s;
	int	i,e;
	char	c[MAX_STRING_LEN+1];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	char	*OK_220="220";
	char	*OK_250="250";
	char	*OK_354="354";

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(smtp_server);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL2");
	if(hp==NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot get IP for mailserver [%s]",smtp_server);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL3");

/*	if(hp==NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot get IP for mailserver [%s]",smtp_server);
		return FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port=htons(25);*/

	s=socket(AF_INET,SOCK_STREAM,0);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL4");
	if(s == -1)
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
		close(s);
		return FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL5");

	memset(c,0,MAX_STRING_LEN+1);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL6");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving initial string from SMTP server.");
		close(s);
		return FAIL;
	}
	if(strncmp(OK_220,c,strlen(OK_220)) != 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "No welcome message 220* [%s]", c);
		close(s);
		return FAIL;
	}

	if(strlen(smtp_helo) != 0)
	{
		memset(c,0,MAX_STRING_LEN+1);
		sprintf(c,"HELO %s\r\n",smtp_helo);
/*		e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
		e=write(s,c,strlen(c)); 
		zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL7");
		if(e == -1)
		{
			zabbix_log(LOG_LEVEL_ERR, "Error sending HELO to mailserver.");
			close(s);
			return FAIL;
		}
				
		memset(c,0,MAX_STRING_LEN+1);
/*		i=sizeof(struct sockaddr_in);
		i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
		i=read(s,c,MAX_STRING_LEN);
		zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL8");
		if(i == -1)
		{
			zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on HELO request.");
			close(s);
			return FAIL;
		}
		if(strncmp(OK_250,c,strlen(OK_250)) != 0)
		{
			zabbix_log(LOG_LEVEL_ERR, "Wrong answer on HELO [%s]",c);
			close(s);
			return FAIL;
		}
	}
			
	memset(c,0,MAX_STRING_LEN+1);
/*	sprintf(c,"MAIL FROM: %s\r\n",smtp_email);*/
	sprintf(c,"MAIL FROM: <%s>\r\n",smtp_email);
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL9");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending MAIL FROM to mailserver.");
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN+1);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL10");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on MAIL FROM request.");
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Wrong answer on MAIL FROM [%s]", c);
		close(s);
		return FAIL;
	}
			
	memset(c,0,MAX_STRING_LEN+1);
	sprintf(c,"RCPT TO: <%s>\r\n",mailto);
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL11");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending RCPT TO to mailserver.");
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN+1);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL12");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receiving answer on RCPT TO request.");
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Wrong answer on RCPT TO [%s]", c);
		close(s);
		return FAIL;
	}
	
	memset(c,0,MAX_STRING_LEN+1);
	sprintf(c,"DATA\r\n");
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL13");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending DATA to mailserver.");
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN+1);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL14");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receivng answer on DATA request.");
		close(s);
		return FAIL;
	}
	if(strncmp(OK_354,c,strlen(OK_354)) != 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Wrong answer on DATA [%s]", c);
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN+1);
/*	sprintf(c,"Subject: %s\r\n%s",mailsubject, mailbody);*/
	sprintf(c,"From:<%s>\r\nTo:<%s>\r\nSubject: %s\r\n\r\n%s",smtp_email,mailto,mailsubject, mailbody);
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending mail subject and body to mailserver.");
		close(s);
		return FAIL;
	}

	memset(c,0,MAX_STRING_LEN+1);
	sprintf(c,"\r\n.\r\n");
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL15");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending . to mailserver.");
		close(s);
		return FAIL;
	}
	memset(c,0,MAX_STRING_LEN+1);
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,c,MAX_STRING_LEN,0,(struct sockaddr *)&servaddr_in,&i);*/
	i=read(s,c,MAX_STRING_LEN);
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL16");
	if(i == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error receivng answer on . request.");
		close(s);
		return FAIL;
	}
	if(strncmp(OK_250,c,strlen(OK_250)) != 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Wrong answer on end of data [%s]", c);
		close(s);
		return FAIL;
	}
	
	memset(c,0,MAX_STRING_LEN+1);
	sprintf(c,"QUIT\r\n");
/*	e=sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)); */
	e=write(s,c,strlen(c)); 
	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL18");
	if(e == -1)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error sending QUIT to mailserver.");
		close(s);
		return FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL19");
	close(s);

	zabbix_log( LOG_LEVEL_DEBUG, "SENDING MAIL. END.");
	
	return SUCCEED;
}

/*
 * Send message to user. Message will be sent to all medias registered to given user.
 */ 
void	send_to_user(int actionid,int userid,char *subject,char *message)
{
	DB_MEDIA media;
	char sql[MAX_STRING_LEN+1];
	DB_RESULT *result;

	int	i;

	sprintf(sql,"select type,sendto,active from media where active=%d and userid=%d",MEDIA_STATUS_ACTIVE,userid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		media.active=atoi(DBget_field(result,i,2));
		media.type=DBget_field(result,i,0);
		media.sendto=DBget_field(result,i,1);

		DBadd_alert(actionid,media.type,media.sendto,subject,message);
	}
	DBfree_result(result);
}

/*
 * Translate all %s to host names
 */ 
void	substitute_hostname(int triggerid,char *s)
{
	char tmp[MAX_STRING_LEN+1];	
	char sql[MAX_STRING_LEN+1];
	DB_RESULT *result;

	zabbix_log( LOG_LEVEL_DEBUG, "In substitute_hostname([%d],[%s])",triggerid,s);

	if(strstr(s,"%s") != 0)
	{
		sprintf(sql,"select distinct t.description,h.host from triggers t, functions f,items i, hosts h where t.triggerid=%d and f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid", triggerid);
		result = DBselect(sql);

		if(DBnum_rows(result) == 0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No hostname in substitute_hostname()");
			DBfree_result(result);
			return;
		}
		strcpy(tmp,s);
		sprintf(s,tmp,DBget_field(result,0,1));

		DBfree_result(result);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End of substitute_hostname() Result [%s]",s);
}

/*
 * Apply actions if any.
 */ 
/*void	apply_actions(int triggerid,int good)*/
void	apply_actions(DB_TRIGGER *trigger,int good)
{
	DB_RESULT *result,*result2;
	
	DB_ACTION action;

	char sql[MAX_STRING_LEN+1];

	int	i;
	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In apply_actions(%d,%d)",trigger->triggerid, good);

	if(good==1)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		sprintf(sql,"select count(*) from trigger_depends d,triggers t where d.triggerid_down=%d and d.triggerid_up=t.triggerid and t.value=%d",trigger->triggerid, TRIGGER_VALUE_TRUE);
		result = DBselect(sql);
		if(DBnum_rows(result) == 1)
		{
			if(atoi(DBget_field(result,0,0))>0)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Will not apply actions");
				DBfree_result(result);
				return;
			}
		}
		DBfree_result(result);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Applying actions");

	now = time(NULL);

	sprintf(sql,"select actionid,userid,delay,subject,message,scope,severity from actions where (scope=%d and triggerid=%d and good=%d and nextcheck<=%d) or (scope=%d) or (scope=%d)",ACTION_SCOPE_TRIGGER,trigger->triggerid,good,now,ACTION_SCOPE_HOST,ACTION_SCOPE_HOSTS);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{

		zabbix_log( LOG_LEVEL_DEBUG, "i=[%d]",i);
/*		zabbix_log( LOG_LEVEL_DEBUG, "Fetched:%s %s %s %s %s\n",DBget_field(result,i,0),DBget_field(result,i,1),DBget_field(result,i,2),DBget_field(result,i,3),DBget_field(result,i,4));*/

		action.actionid=atoi(DBget_field(result,i,0));
		action.userid=atoi(DBget_field(result,i,1));
		action.delay=atoi(DBget_field(result,i,2));
		strncpy(action.subject,DBget_field(result,i,3),MAX_STRING_LEN);
		strncpy(action.message,DBget_field(result,i,4),MAX_STRING_LEN);
		action.scope=atoi(DBget_field(result,i,5));
		action.severity=atoi(DBget_field(result,i,6));

		if(ACTION_SCOPE_TRIGGER==action.scope)
		{
			substitute_hostname(trigger->triggerid,action.message);
			substitute_hostname(trigger->triggerid,action.subject);
		
			substitute_macros(action.message);
			substitute_macros(action.subject);
		}
		else if(ACTION_SCOPE_HOST==action.scope)
		{
			sprintf(sql,"select * from actions a,triggers t,hosts h,functions f,items i where a.triggerid=t.triggerid and f.triggerid=t.triggerid and i.itemid=f.itemid and h.hostid=i.hostid and a.scope=%d",ACTION_SCOPE_HOST);
			result2 = DBselect(sql);
			if(DBnum_rows(result2)==0)
			{
				DBfree_result(result2);
				continue;
			}
			DBfree_result(result2);
			strncpy(action.subject,trigger->description,MAX_STRING_LEN);
			if(0==good)
			{
				strncat(action.subject," (ON)", MAX_STRING_LEN);
			}
			else
			{
				strncat(action.subject," (OFF)", MAX_STRING_LEN);
			}
			strncpy(action.message,action.subject,MAX_STRING_LEN);
		}
		else if(ACTION_SCOPE_HOSTS==action.scope)
		{
			strncpy(action.subject,trigger->description,MAX_STRING_LEN);
			if(0==good)
			{
				strncat(action.subject," (ON)", MAX_STRING_LEN);
			}
			else
			{
				strncat(action.subject," (OFF)", MAX_STRING_LEN);
			}
			strncpy(action.message,action.subject,MAX_STRING_LEN);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unsupported scope [%d] for actionid [%d]", action.scope, action.actionid);
		}

		send_to_user(action.actionid,action.userid,action.subject,action.message);
		now = time(NULL);
		sprintf(sql,"update actions set nextcheck=%d where actionid=%d",now+action.delay,action.actionid);
		DBexecute(sql);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "Actions applied for trigger %d %d", trigger->triggerid, good );
	DBfree_result(result);
}

/*
 * Recursive function!
 */
void	update_serv(int serviceid)
{
	char	sql[MAX_STRING_LEN+1];
	int	i;
	int	status;
	int	serviceupid, algorithm;
	int	now;

	DB_RESULT *result,*result2;

	sprintf(sql,"select l.serviceupid,s.algorithm from services_links l,services s where s.serviceid=l.serviceupid and l.servicedownid=%d",serviceid);
	result=DBselect(sql);
	status=0;
	for(i=0;i<DBnum_rows(result);i++)
	{
		serviceupid=atoi(DBget_field(result,i,0));
		algorithm=atoi(DBget_field(result,i,1));
		if(SERVICE_ALGORITHM_NONE == algorithm)
		{
/* Do nothing */
		}
		else if((SERVICE_ALGORITHM_MAX == algorithm)
			||
			(SERVICE_ALGORITHM_MIN == algorithm))
		{
			/* Why it was so complex ?
			sprintf(sql,"select status from services s,services_links l where l.serviceupid=%d and s.serviceid=l.servicedownid",serviceupid);
			result2=DBselect(sql);
			for(j=0;j<DBnum_rows(result2);j++)
			{
				if(atoi(DBget_field(result2,j,0))>status)
				{
					status=atoi(DBget_field(result2,j,0));
				}
			}
			DBfree_result(result2);*/

			if(SERVICE_ALGORITHM_MAX == algorithm)
			{
				sprintf(sql,"select count(*),max(status) from services s,services_links l where l.serviceupid=%d and s.serviceid=l.servicedownid",serviceupid);
			}
			/* MIN otherwise */
			else
			{
				sprintf(sql,"select count(*),min(status) from services s,services_links l where l.serviceupid=%d and s.serviceid=l.servicedownid",serviceupid);
			}
			result2=DBselect(sql);
			if(atoi(DBget_field(result2,0,0))!=0)
			{
				status=atoi(DBget_field(result2,0,1));
			}
			DBfree_result(result2);

			now=time(NULL);
			DBadd_service_alarm(atoi(DBget_field(result,i,0)),status,now);
			sprintf(sql,"update services set status=%d where serviceid=%d",status,atoi(DBget_field(result,i,0)));
			DBexecute(sql);
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unknown calculation algorithm of service status [%d]", algorithm);
		}
	}
	DBfree_result(result);

	sprintf(sql,"select serviceupid from services_links where servicedownid=%d",serviceid);
	result=DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		update_serv(atoi(DBget_field(result,i,0)));
	}
	DBfree_result(result);
}

void	update_services(int triggerid, int status)
{
	char	sql[MAX_STRING_LEN+1];
	int	i;

	DB_RESULT *result;

	sprintf(sql,"update services set status=%d where triggerid=%d",status,triggerid);
	DBexecute(sql);


	sprintf(sql,"select serviceid from services where triggerid=%d", triggerid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		update_serv(atoi(DBget_field(result,i,0)));
	}

	DBfree_result(result);
	return;
}

/*
* Re-calculate values of triggers
*/ 
void	update_triggers(int itemid)
{
	char	sql[MAX_STRING_LEN+1];
	char	exp[MAX_STRING_LEN+1];
	int	b;
	int	now;
	DB_TRIGGER	trigger;
	DB_RESULT	*result;

	int	i;
/*	int	prevvalue;*/

	zabbix_log( LOG_LEVEL_DEBUG, "In update_triggers (%d)", itemid);

/* Does not work for PostgreSQL */
/*		sprintf(sql,"select t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value from triggers t,functions f,items i where i.status<>3 and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d group by t.triggerid,t.expression,t.dep_level",TRIGGER_STATUS_ENABLED,sucker_num);*/
/* Is it correct SQL? */
	sprintf(sql,"select distinct t.triggerid,t.expression,t.status,t.dep_level,t.priority,t.value from triggers t,functions f,items i where i.status<>%d and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=%d",ITEM_STATUS_NOTSUPPORTED, TRIGGER_STATUS_ENABLED, itemid);

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		trigger.triggerid=atoi(DBget_field(result,i,0));
		trigger.expression=DBget_field(result,i,1);
		trigger.status=atoi(DBget_field(result,i,2));
		trigger.priority=atoi(DBget_field(result,i,4));

		trigger.value=atoi(DBget_field(result,i,5));
		strncpy(exp, trigger.expression, MAX_STRING_LEN);
		if( evaluate_expression(&b, exp) != 0 )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Expression [%s] cannot be evaluated.",trigger.expression);
			continue;
		}

/* Oprimise a little bit */
/*		prevvalue=DBget_prev_trigger_value(trigger.triggerid);*/

		if(b==1)
		{
			if(trigger.value != TRIGGER_VALUE_TRUE)
			{
				now = time(NULL);
				DBupdate_trigger_value(trigger.triggerid,TRIGGER_VALUE_TRUE,now);
			}
			if((trigger.value == TRIGGER_VALUE_FALSE)
			||
			(
			 (trigger.value == TRIGGER_VALUE_UNKNOWN) &&
/* Oprimise a little bit */
/*			 (prevvalue == TRIGGER_VALUE_FALSE)*/
			 (DBget_prev_trigger_value(trigger.triggerid) == TRIGGER_VALUE_FALSE)
			))
			{
				now = time(NULL);
/*				apply_actions(trigger.triggerid,1);*/
				apply_actions(&trigger,1);
	
				sprintf(sql,"update actions set nextcheck=0 where triggerid=%d and good=0",trigger.triggerid);
				DBexecute(sql);

				update_services(trigger.triggerid, trigger.priority);
			}
		}

		if(b==0)
		{
			if(trigger.value != TRIGGER_VALUE_FALSE)
			{
				now = time(NULL);
				DBupdate_trigger_value(trigger.triggerid,TRIGGER_VALUE_FALSE,now);
			}

			if((trigger.value == TRIGGER_VALUE_TRUE)
			||
			(
			 (trigger.value == TRIGGER_VALUE_UNKNOWN) &&
/* Oprimise a little bit */
/*			 (prevvalue == TRIGGER_VALUE_TRUE)*/
			 (DBget_prev_trigger_value(trigger.triggerid) == TRIGGER_VALUE_TRUE)
			))
			{
/*				apply_actions(trigger.triggerid,0);*/
				apply_actions(&trigger,0);

				sprintf(sql,"update actions set nextcheck=0 where triggerid=%d and good=1",trigger.triggerid);
				DBexecute(sql);

				update_services(trigger.triggerid, 0);
			}
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
	char	*s;
	int	res;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_lastvalue()" );

	sprintf(sql, "select i.itemid,i.prevvalue,i.lastvalue,i.value_type from items i,hosts h where h.host='%s' and h.hostid=i.hostid and i.key_='%s'", host, key );
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
        	DBfree_result(result);
		zabbix_log(LOG_LEVEL_WARNING, "Query [%s] returned empty result", sql );
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
        item.value_type=atoi(DBget_field(result,0,3));

	zabbix_log(LOG_LEVEL_DEBUG, "Itemid:%d", item.itemid );

	zabbix_log(LOG_LEVEL_DEBUG, "Before evaluate_FUNCTION()" );

	res = evaluate_FUNCTION(value,&item,function,parameter);

/* Cannot call DBfree_result until evaluate_FUNC */
	DBfree_result(result);
	return res;
}

/* For zabbix_trapper(d) */
/* int	process_data(char *server,char *key, double value)*/
int	process_data(int sockfd,char *server,char *key,char *value)
{
	char	sql[MAX_STRING_LEN+1];

	DB_RESULT       *result;
	DB_ITEM	item;
	char	*s;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_data()");

	sprintf(sql,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.value_type,i.trapper_hosts from items i,hosts h where h.status in (0,2) and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=%d and i.type=%d", server, key, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		DBfree_result(result);
		return  FAIL;
	}

	item.trapper_hosts=DBget_field(result,0,16);
	if(check_security(sockfd,item.trapper_hosts,1) == FAIL)
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

	update_triggers(item.itemid);
 
	DBfree_result(result);

	return SUCCEED;
}

void	process_new_value(DB_ITEM *item,char *value)
{
	int 	now;
	char	sql[MAX_STRING_LEN+1];
	double	value_double;
	char	*e;


	zabbix_log( LOG_LEVEL_DEBUG, "In process_new_value()");
	value_double=strtod(value,&e);

	if(item->history>0)
	{
		if(item->value_type==ITEM_VALUE_TYPE_FLOAT)
		{
			DBadd_history(item->itemid,value_double);
		}
		else
		{
			DBadd_history_str(item->itemid,value);
		}
	}

	now = time(NULL);

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
