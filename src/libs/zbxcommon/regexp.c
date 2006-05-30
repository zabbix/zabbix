#include "config.h"

#include <stdio.h>
#include <stdio.h>
#include <errno.h>
#include <stdlib.h>

#include <sys/types.h>
#include <regex.h>

char	*zbx_regexp_match(const char *string, const char *pattern, int *len)
{
	int	status;
	char	*c;

	regex_t	re;
	regmatch_t match;

	if(len) *len = 0;


	if (regcomp(&re, pattern, REG_EXTENDED | /* REG_ICASE | */ REG_NEWLINE) != 0)
	{
		return(NULL);
	}


	status = regexec(&re, string, (size_t) 1, &match, 0);

	/* Not matched */
	if (status != 0)
	{
		regfree(&re);
		return(NULL);
	}

	c=(char *)string+match.rm_so;
	if(len) *len = match.rm_eo - match.rm_so;
	
	regfree(&re);

	return	c;
}

/*#define ZABBIX_TEST*/

#ifdef ZABBIX_TEST
int main()
{
	int len=2;
	char s[1024];

	printf("[%s]\n", zbx_regexp_match("ABCDEFGH","^F",&len));
	printf("[%d]\n", len);
}
#endif
