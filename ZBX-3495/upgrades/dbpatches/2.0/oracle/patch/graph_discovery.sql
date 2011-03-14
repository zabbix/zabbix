CREATE TABLE graph_discovery (
	graphdiscoveryid         number(20)                                NOT NULL,
	graphid                  number(20)                                NOT NULL,
	parent_graphid           number(20)                                NOT NULL,
	name                     nvarchar2(128)  DEFAULT ''                ,
	PRIMARY KEY (graphdiscoveryid)
);
CREATE UNIQUE INDEX graph_discovery_1 on graph_discovery (graphid,parent_graphid);
ALTER TABLE graph_discovery ADD CONSTRAINT c_graph_discovery_1 FOREIGN KEY (graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
ALTER TABLE graph_discovery ADD CONSTRAINT c_graph_discovery_2 FOREIGN KEY (parent_graphid) REFERENCES graphs (graphid) ON DELETE CASCADE;
