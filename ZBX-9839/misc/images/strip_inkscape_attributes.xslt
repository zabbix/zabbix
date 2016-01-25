<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0"
 xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
 xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape">
<xsl:output method="xml" encoding='UTF-8' standalone="no"/>

<xsl:template match="node()|@*">
  <xsl:copy>
    <xsl:apply-templates select="node()|@*"/>
  </xsl:copy>
</xsl:template>
<xsl:template match="@*[starts-with(name(), 'inkscape:export')]"/>

</xsl:stylesheet>
