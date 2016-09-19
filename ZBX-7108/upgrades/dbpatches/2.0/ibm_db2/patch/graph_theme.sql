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
-- Insert new graph theme
INSERT INTO graph_theme (graphthemeid, description, theme, backgroundcolor, graphcolor, graphbordercolor, gridcolor,maingridcolor, gridbordercolor, textcolor, highlightcolor, leftpercentilecolor, rightpercentilecolor, nonworktimecolor, gridview, legendview) SELECT (SELECT MAX(graphthemeid) FROM graph_theme) + 1 AS graphthemeid, 'Dark orange' AS description, 'darkorange' AS theme,'333333' AS backgroundcolor, '0A0A0A' AS graphcolor, '888888' AS graphbordercolor, '222222' AS gridcolor, '4F4F4F' AS maingridcolor, 'EFEFEF' AS gridbordercolor, 'DFDFDF' AS textcolor, 'FF5500' AS highlightcolor, 'FF5500' AS leftpercentilecolor, 'FF1111' AS rightpercentilecolor, '1F1F1F' AS nonworktimecolor, 1 AS gridview, 1 AS legendview FROM DUAL WHERE EXISTS (SELECT NULL FROM graph_theme)
/
INSERT INTO graph_theme (graphthemeid, description, theme, backgroundcolor, graphcolor, graphbordercolor, gridcolor, maingridcolor, gridbordercolor, textcolor, highlightcolor, leftpercentilecolor, rightpercentilecolor, nonworktimecolor, gridview, legendview) SELECT (SELECT MAX(graphthemeid) FROM graph_theme) + 1 AS graphthemeid, 'Classic' AS description, 'classic' AS theme, 'F0F0F0' AS backgroundcolor, 'FFFFFF' AS graphcolor, '333333' AS graphbordercolor, 'CCCCCC' AS gridcolor, 'AAAAAA' AS maingridcolor, '000000' AS gridbordercolor, '222222' AS textcolor, 'AA4444' AS highlightcolor, '11CC11' AS leftpercentilecolor, 'CC1111' AS rightpercentilecolor, 'E0E0E0' AS nonworktimecolor, 1 AS gridview, 1 AS legendview FROM DUAL WHERE EXISTS (SELECT NULL FROM graph_theme)
/
DELETE FROM ids WHERE table_name = 'graph_theme'
/
