!	IF "$(PCREINCDIR)" == ""
!		ERROR Please specify directory containing PCRE header files (e. g. PCREINCDIR=\somepath\include).
!	ENDIF

!	IF ("$(PCRELIBDIR)" == "")
!		ERROR Please specify PCRELIBDIR (e. g. PCRELIBDIR=\somepath\lib).
!	ENDIF

!	IF EXISTS("$(PCREINCDIR)\pcreposix.h")
CFLAGS =	$(CFLAGS) /DPCRE_STATIC
INCS =	$(INCS) /I "$(PCREINCDIR)"
!	ELSE
!		ERROR PCREINCDIR "$(PCREINCDIR)" cannot find "$(PCREINCDIR)\pcreposix.h"
!	ENDIF

PCRELIB = $(PCRELIBDIR)\pcre.lib
PCREPOSIXLIB = $(PCRELIBDIR)\pcreposix.lib

!	IF EXISTS("$(PCRELIB)") && EXISTS("$(PCREPOSIXLIB)")
LIBS = $(LIBS) "$(PCRELIB)" "$(PCREPOSIXLIB)"
!	ELSE
!		ERROR PCRELIBDIR "$(PCRELIBDIR)" cannot find PCRELIB "$(PCRELIB)" or PCREPOSIXLIB "$(PCREPOSIXLIB)"
!	ENDIF
