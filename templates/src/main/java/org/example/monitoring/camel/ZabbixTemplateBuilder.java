package org.example.monitoring.camel;

import org.apache.camel.builder.RouteBuilder;
import org.springframework.stereotype.Component;

@Component
public class ZabbixTemplateBuilder extends RouteBuilder {
 
  @Override
  public void configure() throws Exception {
    from("file:bin/in?noop=true&delay=30000&idempotentKey=${file:name}-${file:modified}")
    .log("Loading file: ${in.headers.CamelFileNameOnly}")
    .multicast().parallelProcessing().to("direct:zbx3.2", "direct:zbx3.4");
    
    from("direct:zbx3.2")
    		//.filter().xpath("//node()[@zbx_ver = 3.4]") //if there are nodes with zbx_ver flags
    		.log("Going to do 3.2 template")
    		//strip metrics marked with zbx_ver not 3.2
	    	.setHeader("zbx_ver", simple("3.2", Double.class)).to("xslt:templates/to_metrics_zbx_ver.xsl?saxon=true")
	    	.to("direct:merge");
    
    from("direct:zbx3.4")
    	//.filter().xpath("//node()[@zbx_ver ='3.4']") // only if there are attributes zbx_ver=3.4
    	.log("Going to do 3.4 template")
    	.setHeader("zbx_ver", simple("3.4", Double.class)).to("xslt:templates/to_metrics_zbx_ver.xsl?saxon=true")
		.to("direct:merge");
    
    from("direct:merge")
    	.setHeader("template_ver", simple("0.7", String.class))
    	.to("xslt:templates/to_metrics_add_name_placeholder.xsl?saxon=true") //will add _SNMP_PLACEHOLDER and generator ver
	    .to("xslt:templates/to_metrics.xsl?saxon=true")
	    .to("xslt:templates/to_metrics_add_trigger_desc.xsl?saxon=true") // adds Default trigger description. See inside 
	    .to("file:bin/merged")
	    .to("validator:templates/metrics.xsd")
		.multicast().parallelProcessing().to("direct:RU", "direct:EN");
  
    from("direct:RU")
	    .filter().xpath("//node()[@lang='RU']")
	    .log("Going to do Russian template")
		.setHeader("lang", simple("RU", String.class)).to("xslt:templates/to_metrics_lang.xsl?saxon=true")
		.to("log:result?level=DEBUG").multicast().parallelProcessing().to("direct:snmpv1", "direct:snmpv2");
	    
    from("direct:EN")
	    .log("Going to do English template")
		.setHeader("lang", simple("EN", String.class)).to("xslt:templates/to_metrics_lang.xsl?saxon=true")
		.to("log:result?level=DEBUG").multicast().parallelProcessing().to("direct:snmpv1", "direct:snmpv2");
	    
    //zabbix types: 4- snmpv2, 1-snmpv2 <xsl:variable name="snmp_item_type">4</xsl:variable>
    from("direct:snmpv1")
    	.setHeader("snmp_item_type", simple("1", String.class))
    	.setHeader("template_suffix", simple("SNMPv1", String.class))
    	.to("xslt:templates/to_zabbix_export.xsl?saxon=true")
    	.to("direct:zabbix_export");
    
    
    from("direct:snmpv2")
	    .setHeader("snmp_item_type", simple("4", String.class))
	    .setHeader("template_suffix", simple("SNMPv2", String.class))
		.to("xslt:templates/to_zabbix_export.xsl?saxon=true")
		.to("direct:zabbix_export");
    
    from("direct:zabbix_export")
		//with lang.setBody(body().regexReplaceAll("_SNMP_PLACEHOLDER", simple(" ${in.headers.template_suffix} ${in.headers.lang}")))
		.setBody(body().regexReplaceAll("_SNMP_PLACEHOLDER", simple(" ${in.headers.template_suffix}"))) //w/o lang
		.setHeader("subfolder",simple("${in.headers.CamelFileName.split('_')[1]}",String.class))
		
		
		
		.choice()
		    .when(header("zbx_ver").isEqualTo("3.4"))
		    	.setHeader("CamelOverruleFileName",simple("${in.headers.subfolder}/${in.headers.CamelFileName.replace('.xml','')}_${in.headers.template_suffix}_${in.headers.lang}.xml"))
		    	.to("file:bin/out/")
		    	.to("validator:templates/zabbix_export_3.4.xsd")
			.when(header("zbx_ver").isEqualTo("3.2"))
				.setHeader("CamelOverruleFileName",simple("${in.headers.subfolder}/${in.headers.zbx_ver}/${in.headers.CamelFileName.replace('.xml','')}_${in.headers.template_suffix}_${in.headers.lang}.xml"))
				.to("file:bin/out/")
				.to("validator:templates/zabbix_export_3.2.xsd")
			.otherwise()
			    .log("Unknown zbx_ver provided")
	    .end();
  } 
}
