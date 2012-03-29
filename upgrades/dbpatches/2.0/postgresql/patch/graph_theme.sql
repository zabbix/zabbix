ALTER TABLE ONLY graph_theme ALTER graphthemeid DROP DEFAULT,
			     ALTER noneworktimecolor SET DEFAULT 'CCCCCC';
ALTER TABLE ONLY graph_theme RENAME noneworktimecolor TO nonworktimecolor;

UPDATE graph_theme SET theme = 'darkblue' WHERE theme = 'css_bb.css';
UPDATE graph_theme SET theme = 'originalblue' WHERE theme = 'css_ob.css';