#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <syslog.h>

#include "common.h"
#include "cfg.h"

/*	struct cfg_line
	{
		char	*parameter,
		void	*variable,
		int	type;
		int	mandatory;
		int	min;
		int	max;
	};
*/

/*	struct cfg_line cfg[]=
	{*/
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
/*		{"StartSuckers",&Suckers,0,	0          ,1	      ,2,255},
		{0}
	};*/

int	parse_cfg_file(char *cfg_file,struct cfg_line *cfg)
{
	FILE	*file;
	char	line[MAX_STRING_LEN+1];
	char	parameter[MAX_STRING_LEN+1];
	char	*value;
	int	lineno;
	int	i,var;
	int	*pointer;
	char	*c;


	file=fopen(cfg_file,"r");
	if(NULL == file)
	{
		syslog( LOG_CRIT, "Cannot open config file [%s] [%m]",cfg_file);
		return	FAIL;
	}

	lineno=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		lineno++;

		if(line[0]=='#')	continue;
		if(strlen(line)==1)	continue;

		strncpy(parameter,line,MAX_STRING_LEN);

		value=strstr(line,"=");

		if(NULL == value)
		{
			syslog( LOG_CRIT, "Error in line [%s] Line %d", line, lineno);
			return	FAIL;
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

//		syslog( LOG_WARNING, "Parameter [%s] Value [%s]", parameter, value);

		i=0;
		while(cfg[i].parameter != 0)
		{
			if(strcmp(cfg[i].parameter, parameter) == 0)
			{
				if(cfg[i].type == TYPE_INT)
				{
					var=atoi(value);
					if( (cfg[i].min!=0) || (cfg[i].max!=0))
					{
						if( (var<cfg[i].min) || (var>cfg[i].max) )
						{
							syslog( LOG_CRIT, "Wrong value of [%s] in line %d. Should be between %d and %d.", cfg[i].parameter, lineno, cfg[i].min, cfg[i].max);
							return	FAIL;
						}
						
					}
/* Can this be done without "pointer" ? */ 	
					pointer=(int *)cfg[i].variable;
					*pointer=var;
					syslog( LOG_WARNING, "Parameter [%s] [%d]", parameter, *pointer);
				}
				else
				{
/* Can this be done without "c" ? */ 
	/*				c=(char *)cfg[i].variable;
					syslog( LOG_WARNING, "ZZZ [%d] [%s]", *c, *c);
					*c=strdup(value);
					syslog( LOG_WARNING, "ZZZ [%d] [%s]", c, *c);*/
//					syslog( LOG_WARNING, "Parameter [%s] [%s]", parameter, *c);
				}
			}
			i++;
		}
	}
	return	SUCCEED;
}
