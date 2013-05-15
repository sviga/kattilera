DROP TABLE IF EXISTS `%PREFIX%_stat_index`;
CREATE TABLE `%PREFIX%_stat_index` (
  `IDIndex` bigint(20) unsigned NOT NULL auto_increment,
  `IDRobot` int(10) unsigned NOT NULL default '0',
  `IDUri` bigint(20) unsigned NOT NULL default '0',
  `tstc` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`IDIndex`),
  KEY `IDRobot` (`IDRobot`),
  KEY `IDUri` (`IDUri`),
  KEY `tstc` (`tstc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Статистика';
