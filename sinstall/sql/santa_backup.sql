DROP TABLE IF EXISTS `%PREFIX%_backup`;
CREATE TABLE `%PREFIX%_backup` (
  `id` int(8) NOT NULL auto_increment,
  `real_filename` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `date` datetime NOT NULL,
  `content` enum('0','1') NOT NULL default '0',
  `system` enum('0','1') NOT NULL default '0',
  `mysql` enum('0','1') NOT NULL default '0',
  `design` enum('0','1') NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
