#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <time.h>

#include "common.h"
#include "db.h"
#include "debug.h"

int	evaluate_LAST(float *last,int itemid,int parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select lastvalue from items where itemid=%d and lastvalue is not null", itemid );
	DBexecute(c);

	result = DBget_result();
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
	row = DBfetch_row(result);
	if( row[0] == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}
	*last=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MIN(float *min,int itemid,int parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select min(value) from history where clock>unix_timestamp()-%d and itemid=%d",parameter,itemid);
	DBexecute(c);

	result = DBget_result();
	if(result==NULL)
	{
		dbg_write( dbg_proginfo, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		dbg_write( dbg_proginfo, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	row = DBfetch_row(result);
	if( row[0] == NULL )
	{
		dbg_write( dbg_proginfo, "Result for MIN is empty" );
		DBfree_result(result);
		return	FAIL;
	}
	*min=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MAX(float *max,int itemid,int parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select max(value) from history where clock>unix_timestamp()-%d and itemid=%d",parameter,itemid);
	DBexecute(c);

	result = DBget_result();
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
	row = DBfetch_row(result);
	if( row[0] == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}	
	*max=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_PREV(float *prev,int itemid,int parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select prevvalue from items where itemid=%d and prevvalue is not null", itemid );
	DBexecute(c);

	result = DBget_result();
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
	row = DBfetch_row(result);
	if( row[0] == NULL )
	{
		DBfree_result(result);
		return	FAIL;
	}
	*prev=atof(row[0]);

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
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select value from history where itemid=%d and clock>unix_timestamp()-%d limit 1",itemid,parameter);
	DBexecute(c);

	result = DBget_result();
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
	row = DBfetch_row(result);
	*nodata=0;
	if(row == NULL)
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
	DB_ROW		row;
	char		c[1024];
	float		value;
	int		ret=SUCCEED;

	sprintf(c,"select function,parameter from functions where itemid=%d group by 1,2 order by 1,2",itemid );
	DBexecute(c);

	result = DBget_result();
	if(result==NULL)
	{
		dbg_write( dbg_syswarn, "No functions to update.");
		DBfree_result(result);
		return SUCCEED; 
	}

	while ( (row = DBfetch_row(result)) != NULL )
	{
		function.function=row[0];
		function.parameter=atoi(row[1]);
		dbg_write( dbg_proginfo, "ItemId:%d Evaluating %s(%d)\n",itemid,function.function,function.parameter);
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
			dbg_write( dbg_syswarn, "Unknown function:%s\n",function.function);
			DBfree_result(result);
			return FAIL;
		}
		dbg_write( dbg_proginfo, "Result:%f\n",value);
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
