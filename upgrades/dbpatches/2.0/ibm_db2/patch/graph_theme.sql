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
UPDATE graph_theme SET theme = 'darkblue' WHERE theme = 'css_bb.css'
/
UPDATE graph_theme SET theme = 'originalblue' WHERE theme = 'css_ob.css'
/