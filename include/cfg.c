#include <stdio.h>
#include <stdlib.h>
#include <string.h>

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
	char	**c;
	int	(*func)();



	file=fopen(cfg_file,"r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open config file [%s] [%m]\n",cfg_file);
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
			fprintf(stderr, "Error in line [%s] Line %d\n", line, lineno);
			return	FAIL;
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

		i=0;
		while(cfg[i].parameter != 0)
		{
			if(strcmp(cfg[i].parameter, parameter) == 0)
			{
				if(cfg[i].function != 0)
				{
					func=cfg[i].function;
					if(func(value)!=SUCCEED)
					{
						fprintf(stderr, "Wrong value of [%s] in line %d.\n", cfg[i].parameter, lineno);
						return	FAIL;
					}
				}
				else
				{
				if(cfg[i].type == TYPE_INT)
				{
					var=atoi(value);
					if( (cfg[i].min!=0) || (cfg[i].max!=0))
					{
						if( (var<cfg[i].min) || (var>cfg[i].max) )
						{
							fprintf(stderr, "Wrong value of [%s] in line %d. Should be between %d and %d.\n", cfg[i].parameter, lineno, cfg[i].min, cfg[i].max);
							return	FAIL;
						}
						
					}
/* Can this be done without "pointer" ? */ 	
					pointer=(int *)cfg[i].variable;
					*pointer=var;
				}
				else
				{
/* Can this be done without "c" ? */ 
					c=(char **)cfg[i].variable;
					*c=(char *)strdup(value);
				}
				}
			}
			i++;
		}
	}

/* Check for mandatory parameters */
	i=0;
	while(cfg[i].parameter != 0)
	{
		if(cfg[i].mandatory ==1)
		{
			if(cfg[i].type == TYPE_INT)
			{
				pointer=(int *)cfg[i].variable;
				if(*pointer==0)
				{
					fprintf(stderr,"Missing mandatory parameter [%s]\n", cfg[i].parameter);
					return	FAIL;
				}
			}
			if(cfg[i].type == TYPE_STRING)
			{
				c=(char **)cfg[i].variable;
				if(*c==NULL)
				{
					fprintf(stderr, "Missing mandatory parameter [%s]\n", cfg[i].parameter);
					return	FAIL;
				}
			}
		}
		i++;
	}

	return	SUCCEED;
}
