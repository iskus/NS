CREATE TABLE `nestedsets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `left_key` int(11) NOT NULL,
  `right_key` int(11) NOT NULL,
  `lvl` int(11) NOT NULL DEFAULT '0',
  `title` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `left_key` (`left_key`,`right_key`,`lvl`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;