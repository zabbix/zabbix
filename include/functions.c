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

#include "common.h"
#include "db.h"

int	evaluate_LAST(float *last,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[1024];
	char		*field;

	sprintf(c,"select lastvalue from items where itemid=%d and lastvalue is not null", itemid );

	result = DBselect(c);
	if(result==NULL)
	{
		DBfree_result(result);
		return	FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		DBfree_result(result);
		return	FAIL;
	}
	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}
	*last=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MIN(float *min,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[1024];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select min(value) from history where clock>%d-%d and itemid=%d",now,parameter,itemid);

	result = DBselect(c);
	if(result==NULL)
	{
		syslog(LOG_NOTICE, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		syslog( LOG_NOTICE, "Result for MIN is empty" );
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

int	evaluate_MAX(float *max,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[1024];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select max(value) from history where clock>%d-%d and itemid=%d",now,parameter,itemid);

	result = DBselect(c);
	if(result==NULL)
	{
		DBfree_result(result);
		return	FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		DBfree_result(result);
		return	FAIL;
	}
	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}	
	*max=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_PREV(float *prev,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[1024];
	char		*field;

	sprintf(c,"select prevvalue from items where itemid=%d and prevvalue is not null", itemid );

	result = DBselect(c);
	if(result==NULL)
	{
		DBfree_result(result);
		return	FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		DBfree_result(result);
		return	FAIL;
	}
	field = DBget_field(result,0,0);
	if( field == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}
	*prev=atof(field);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_DIFF(float *diff,int itemid,int parameter)
{
	float	prev,last;
	float	tmp;

	if(evaluate_PREV(&prev,itemid,parameter) == FAIL)
	{
		return FAIL;
	}

	if(evaluate_LAST(&last,itemid,parameter) == FAIL)
	{
		return FAIL;
	}
	
	tmp=last-prev;

	if((tmp<0.000001)&&(tmp>-0.000001))
	{
		*diff=0;
	}
	else
	{
		*diff=1;
	}

	return SUCCEED;
}

int	evaluate_NODATA(float *nodata,int itemid,int parameter)
{
	DB_RESULT	*result;

	char		c[1024];
	char		*field;

	int		now;

	now=time(NULL);

	sprintf(c,"select value from history where itemid=%d and clock>%d-%d limit 1",itemid,now,parameter);

	result = DBselect(c);
	if(result==NULL)
	{
		DBfree_result(result);
		return	FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		DBfree_result(result);
		return	FAIL;
	}
	field = DBget_field(result,0,0);
	*nodata=0;
	if( field == NULL )
	{
		*nodata=1;
	}

	DBfree_result(result);

	return SUCCEED;
}

int	update_functions( int itemid )
{
	FUNCTION	function;
	DB_RESULT	*result;
	char		c[1024];
	float		value;
	int		ret=SUCCEED;
	int		i,rows;

	sprintf(c,"select function,parameter from functions where itemid=%d group by 1,2 order by 1,2",itemid );

	result = DBselect(c);
	if(result==NULL)
	{
		syslog( LOG_NOTICE, "No functions to update.");
		DBfree_result(result);
		return SUCCEED; 
	}

	rows=DBnum_rows(result);
	if(rows == 0)
	{
		syslog( LOG_NOTICE, "No functions to update.");
		DBfree_result(result);
		return SUCCEED; 
	}

	for(i=0;i<rows;i++)
	{
		function.function=DBget_field(result,i,0);
		function.parameter=atoi(DBget_field(result,i,1));
		syslog( LOG_DEBUG, "ItemId:%d Evaluating %s(%d)\n",itemid,function.function,function.parameter);
		if(strcmp(function.function,"last")==0)
		{
			ret = evaluate_LAST(&value,itemid,function.parameter);
		}
		else if(strcmp(function.function,"prev")==0)
		{
			ret = evaluate_PREV(&value,itemid,function.parameter);
		}
		else if(strcmp(function.function,"nodata")==0)
		{
			ret = evaluate_NODATA(&value,itemid,function.parameter);
		}
		else if(strcmp(function.function,"min")==0)
		{
			ret = evaluate_MIN(&value,itemid,function.parameter);
		}
		else if(strcmp(function.function,"max")==0)
		{
			ret = evaluate_MAX(&value,itemid,function.parameter);
		}
		else if(strcmp(function.function,"diff")==0)
		{
			ret = evaluate_DIFF(&value,itemid,function.parameter);
		}
		else
		{
			syslog( LOG_WARNING, "Unknown function:%s\n",function.function);
			DBfree_result(result);
			return FAIL;
		}
		syslog( LOG_DEBUG, "Result:%f\n",value);
		if (ret == SUCCEED)
		{
			sprintf(c,"update functions set lastvalue=%f where itemid=%d and function='%s' and parameter=%d", value, itemid, function.function, function.parameter );
//			printf("%s\n",c);
			DBexecute(c);
		}
	}

	DBfree_result(result);
	return ret;
}
