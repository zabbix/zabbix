#include <stdio.h>
#include <stdlib.h>

#include <stdarg.h>

#include "debug.h"

FILE	*dbg_fd;

int	dbg_level;

void	dbg_init( int level, char *filename )
{
	dbg_fd = fopen( filename,"a" );
 
	if( NULL == dbg_fd )
	{
		fprintf( stderr, "Unable to open debug file.\n" );
		exit( 1 );
	}
 
	dbg_level=level;
}

void	dbg_flush(void)
{
	fflush(dbg_fd);
}

void dbg_write( int level, const char *fmt, ... )
{
	va_list param;
 
	if( level <= dbg_level)
	{
		if( level == dbg_fatal )
		{
			fprintf( dbg_fd, "FATAL: " );
		}
		else if( level == dbg_syserr )
		{
			fprintf( dbg_fd, "ERROR: " );
		}
		else if( level == dbg_syswarn )
		{
			fprintf( dbg_fd, "WARNING: " );
		}
		else
		{
			fprintf( dbg_fd, "INFO: " );
		}

		va_start( param, fmt );
		vfprintf( dbg_fd, fmt, param );
		fprintf( dbg_fd, "\n" );
		fflush( dbg_fd );
		va_end( param );
	}
}
