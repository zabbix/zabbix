ALTER TABLE graph_theme ALTER COLUMN graphthemeid SET WITH DEFAULT NULL
/
REORG TABLE graph_theme
/
ALTER TABLE graph_theme ALTER COLUMN noneworktimecolor SET DEFAULT 'CCCCCC'
/
REORG TABLE graph_theme
/
ALTER TABLE graph_theme RENAME COLUMN noneworktimecolor TO nonworktimecolor
/
REORG TABLE graph_theme
/
