!	IF "$(CURL)" != ""
!		IF "$(CURLINCDIR)" == ""
CURLINCDIR=$(CURL)\include
!		ENDIF
!		IF "$(CURLLIBDIR)" == ""
CURLLIBDIR=$(CURL)\lib
!		ENDIF
!	ENDIF

!	IF "$(CURLINCDIR)" != ""
!		IF EXISTS("$(CURLINCDIR)\curl\curl.h")
CFLAGS =	$(CFLAGS) /DHAVE_LIBCURL /DCURL_STATICLIB
INCS =	$(INCS) /I "$(CURLINCDIR)"
!		ELSE
!			ERROR CURLINCDIR "$(CURLINCDIR)" cannot find "$(CURLINCDIR)\curl\curl.h"
!		ENDIF
!	ENDIF

!	IF "$(CURLLIBDIR)" != ""
!		IF EXISTS("$(CURLLIBDIR)\libcurl.lib")
LIBS = $(LIBS) "$(CURLLIBDIR)\libcurl.lib" Crypt32.lib
!		ELSE
!			ERROR CURLLIBDIR "$(CURLLIBDIR)" cannot find "$(CURLLIBDIR)\libcurl.lib"
!		ENDIF
!	ENDIF
