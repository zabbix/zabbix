!	IF "$(PCREINCDIR)" == "" && "$(PCRE2INCDIR)" == ""
!		ERROR Please specify directory containing PCRE or PCRE2 header files (e. g. PCREINCDIR=\somepath\include or PCRE2INCDIR=\somepath\include).
!	ENDIF

!	IF "$(PCRELIBDIR)" == "" && "$(PCRE2LIBDIR)" == ""
!		ERROR Please specify either PCRELIBDIR or PCRE2LIBDIR (e. g. PCRELIBDIR=\somepath\lib or PCRE2LIBDIR=\somepath\lib).
!	ENDIF

!	IF ("$(PCREINCDIR)" != "" && "$(PCRE2INCDIR)" != "") || ("$(PCRELIBDIR)" != "" && "$(PCRE2LIBDIR)" != "")
!		ERROR Cannot use both PCRE and PCRE2 at the same time!
!	ENDIF

!	IF "$(PCREINCDIR)" != ""
!		IF EXISTS("$(PCREINCDIR)\pcre.h")
CFLAGS =	$(CFLAGS) /DPCRE_STATIC /DHAVE_PCRE_H
INCS =	$(INCS) /I "$(PCREINCDIR)"
!		ELSE
!			ERROR PCREINCDIR "$(PCREINCDIR)" cannot find "$(PCREINCDIR)\pcre.h"
!		ENDIF
!	ENDIF

!	IF "$(PCRE2INCDIR)" != ""
!		IF EXISTS("$(PCRE2INCDIR)\pcre2.h")
CFLAGS =	$(CFLAGS) /DPCRE2_STATIC /DHAVE_PCRE2_H
INCS =	$(INCS) /I "$(PCRE2INCDIR)"
!		ELSE
!			ERROR PCRE2INCDIR "$(PCRE2INCDIR)" cannot find "$(PCRE2INCDIR)\pcre2.h"
!		ENDIF
!	ENDIF

!	IF "$(PCRELIBDIR)" != ""
!		IF EXISTS("$(PCRELIBDIR)\pcre.lib")
LIBS = $(LIBS) "$(PCRELIBDIR)\pcre.lib"
!		ELSE
!			ERROR PCRELIBDIR "$(PCRELIBDIR)" cannot find "$(PCRELIBDIR)\pcre.lib"
!		ENDIF
!	ENDIF

!	IF "$(PCRE2LIBDIR)" != ""
!		IF EXISTS("$(PCRE2LIBDIR)\pcre2-8-static.lib")
LIBS = $(LIBS) "$(PCRE2LIBDIR)\pcre2-8-static.lib"
!		ELSEIF EXISTS("$(PCRE2LIBDIR)\pcre2-8-staticd.lib")
LIBS = $(LIBS) "$(PCRE2LIBDIR)\pcre2-8-staticd.lib"
!		ELSEIF EXISTS("$(PCRE2LIBDIR)\pcre2-8.lib") # else if PCRE2 was installed with vcpkg
LIBS = $(LIBS) "$(PCRE2LIBDIR)\pcre2-8.lib"
!		ELSE
!			ERROR PCRE2LIBDIR "$(PCRE2LIBDIR)" cannot find neither "$(PCRE2LIBDIR)\pcre2-8-static.lib", "$(PCRE2LIBDIR)\pcre2-8-staticd.lib" nor "$(PCRE2LIBDIR)\pcre2-8.lib"
!		ENDIF
!	ENDIF

