!	IF "$(ARES)" != ""
!		IF "$(ARESINCDIR)" == ""
ARESINCDIR = $(ARES)\include
!		ENDIF
!		IF "$(ARESLIBDIR)" == ""
ARESLIBDIR = $(ARES)\lib
!		ENDIF
!	ENDIF

!	IF "$(ARESINCDIR)" != ""
!		IF EXISTS("$(ARESINCDIR)\ares.h")
CFLAGS = $(CFLAGS) /DHAVE_ARES /DHAVE_ARES_QUERY_CACHE
INCS = $(INCS) /I "$(ARESINCDIR)"
!		ELSE
!			ERROR ARESINCDIR "$(ARESINCDIR)" cannot find "$(ARESINCDIR)\ares.h"
!		ENDIF
!	ENDIF

!	IF "$(ARESLIBDIR)" != ""
!		IF EXISTS("$(ARESLIBDIR)\cares.lib")
LIBS = $(LIBS) "$(ARESLIBDIR)\cares.lib"
!		ELSE
!			ERROR ARESLIBDIR "$(ARESLIBDIR)" cannot find "$(ARESLIBDIR)\ares.lib"
!		ENDIF
!	ENDIF
