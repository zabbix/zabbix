#include "config.h"

#include <stdio.h>
#include <regex.h>
#include <stdio.h>
#include <errno.h>
#include <stdlib.h>


#define MAX_FILE_LEN	1024*1024


int main (int argc, char *argv[]){

	int x;
	FILE *f;
	char	*buf;

	f=fopen(argv[1],"r");
	if(f==NULL)
	{
		printf("Error fopen(%s)\n", strerror(errno));
		return 0;
	}

	buf=(char *)malloc((size_t)100);

	memset(buf,0,100);

	x=fread(buf, 1, 100, f);

	if(x==0)
	{
		printf("Error fread(%s)\n", strerror(errno));
	}

	printf("Read [%d] bytes\n", x);



	x = match(buf, argv[2]);

	if ( x == 1 ){

		printf("\n\n Match \n\n");
	}

	return(0);
}

char	*zbx_regexp_match(const char *string, char *pattern, int *len)
{
	int	status;
	char	*c;

	regex_t	re;
	regmatch_t match;

	char c[1024];

	*len=0;

	if (regcomp(&re, pattern, REG_EXTENDED | REG_ICASE | REG_NEWLINE) != 0)
	{
		return(NULL);
	}

	status = regexec(&re, string, (size_t) 1, &match, 0);

	/* Not matched */
	if (status != 0)
	{
		return(NULL);
	}

	c=string+match.rm_so;
	*len=match.rm_eo - match.rm_so;
	
	regfree(&re);

	return	c;
}
