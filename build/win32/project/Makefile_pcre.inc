!	IF "$(PCRE2INCDIR)" != ""
!		IF EXISTS("$(PCRE2INCDIR)\pcre2.h")
CFLAGS =	$(CFLAGS) /DPCRE2_STATIC /DHAVE_PCRE2_H
INCS =	$(INCS) /I "$(PCRE2INCDIR)"
!		ELSE
!			ERROR PCRE2INCDIR "$(PCRE2INCDIR)" cannot find "$(PCRE2INCDIR)\pcre2.h"
!		ENDIF
!	ENDIF

!	IF "$(PCRE2LIBDIR)" != ""
!		IF EXISTS("$(PCRE2LIBDIR)\pcre2-8-static.lib")
LIBS = $(LIBS) "$(PCRE2LIBDIR)\pcre2-8-static.lib"
!		ELSEIF EXISTS("$(PCRE2LIBDIR)\pcre2-8-staticd.lib")
LIBS = $(LIBS) "$(PCRE2LIBDIR)\pcre2-8-staticd.lib"
!		ELSE
!			ERROR PCRE2LIBDIR "$(PCRE2LIBDIR)" cannot find neither "$(PCRE2LIBDIR)\pcre2-8-static.lib" nor "$(PCRE2LIBDIR)\pcre2-8-staticd.lib"
!		ENDIF
!	ENDIF

