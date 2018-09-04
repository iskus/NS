CREATE TABLE nstree (
id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
left_key INT(10) NOT NULL DEFAULT 0,
right_key INT(10) NOT NULL DEFAULT 0,
lvl INT(10) NOT NULL DEFAULT 0,
title VARCHAR(150) NOT NULL DEFAULT '',
KEY left_key (left_key, right_key, lvl)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO nstree (left_key,right_key,lvl,title) VALUES (1, 2, 0, 'root');
