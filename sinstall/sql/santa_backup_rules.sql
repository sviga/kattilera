DROP TABLE IF EXISTS `%PREFIX%_backup_rules`;
CREATE TABLE `%PREFIX%_backup_rules` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `stringid` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ftphost` varchar(255) default NULL,
  `ftpuser` varchar(255) default NULL,
  `ftppass` varchar(255) default NULL,
  `ftpdir` varchar(255) default NULL,
  `needcontent` tinyint(1) unsigned NOT NULL default '0',
  `needsystem` tinyint(1) unsigned NOT NULL default '0',
  `needtables` tinyint(1) unsigned NOT NULL default '0',
  `needdesign` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `stringid` (`stringid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_backup_rules` VALUES ('2','contab','Таблицы и контент','','','','/','1','0','1','0');
