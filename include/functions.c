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

int	evaluate_LAST(float *Result,int ItemId,int Parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select lastvalue from items where itemid=%d and lastvalue is not null", ItemId );
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
	*Result=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MIN(float *Result,int ItemId,int Parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select min(value) from history where clock>unix_timestamp()-%d and itemid=%d",Parameter,ItemId);
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
	*Result=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_MAX(float *Result,int ItemId,int Parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select max(value) from history where clock>unix_timestamp()-%d and itemid=%d",Parameter,ItemId);
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
	*Result=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_PREV(float *Result,int ItemId,int Parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select prevvalue from items where itemid=%d and prevvalue is not null", ItemId );
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
	*Result=atof(row[0]);

	DBfree_result(result);

	return SUCCEED;
}

int	evaluate_DIFF(float *Result,int ItemId,int Parameter)
{
	float	prev,last;
	float	diff;

	if(evaluate_PREV(&prev,ItemId,Parameter) == FAIL)
	{
		return FAIL;
	}

	if(evaluate_LAST(&last,ItemId,Parameter) == FAIL)
	{
		return FAIL;
	}
	
	diff=last-prev;

	if((diff<0.000001)&&(diff>-0.000001))
	{
		*Result=0;
	}
	else
	{
		*Result=1;
	}

	return SUCCEED;
}

int	evaluate_NODATA(float *Result,int ItemId,int Parameter)
{
	DB_RESULT	*result;
	DB_ROW		row;

	char		c[1024];

	sprintf(c,"select value from history where itemid=%d and clock>unix_timestamp()-%d limit 1",ItemId,Parameter);
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
	*Result=0;
	if(row == NULL)
	{
		*Result=1;
	}

	DBfree_result(result);

	return SUCCEED;
}

int	updateFunctions( int ItemId )
{
	FUNCTION	Function;
	DB_RESULT	*result;
	DB_ROW		row;
	char		c[1024];
	float		value;
	int		ret=SUCCEED;

	sprintf(c,"select function,parameter from functions where itemid=%d group by 1,2 order by 1,2",ItemId );
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
		Function.Function=row[0];
		Function.Parameter=atoi(row[1]);
		dbg_write( dbg_proginfo, "ItemId:%d Evaluating %s(%d)\n",ItemId,Function.Function,Function.Parameter);
		if(strcmp(Function.Function,"last")==0)
		{
			ret = evaluate_LAST(&value,ItemId,Function.Parameter);
		}
		else if(strcmp(Function.Function,"prev")==0)
		{
			ret = evaluate_PREV(&value,ItemId,Function.Parameter);
		}
		else if(strcmp(Function.Function,"nodata")==0)
		{
			ret = evaluate_NODATA(&value,ItemId,Function.Parameter);
		}
		else if(strcmp(Function.Function,"min")==0)
		{
			ret = evaluate_MIN(&value,ItemId,Function.Parameter);
		}
		else if(strcmp(Function.Function,"max")==0)
		{
			ret = evaluate_MAX(&value,ItemId,Function.Parameter);
		}
		else if(strcmp(Function.Function,"diff")==0)
		{
			ret = evaluate_DIFF(&value,ItemId,Function.Parameter);
		}
		else
		{
			dbg_write( dbg_syswarn, "Unknown function:%s\n",Function.Function);
			DBfree_result(result);
			return FAIL;
		}
		dbg_write( dbg_proginfo, "Result:%f\n",value);
		if (ret == SUCCEED)
		{
			sprintf(c,"update functions set lastvalue=%f where itemid=%d and function='%s' and parameter=%d", value, ItemId, Function.Function, Function.Parameter );
//			printf("%s\n",c);
			DBexecute(c);
		}
	}

	DBfree_result(result);
	return ret;
}
