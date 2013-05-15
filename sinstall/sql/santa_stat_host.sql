DROP TABLE IF EXISTS `%PREFIX%_stat_host`;
CREATE TABLE `%PREFIX%_stat_host` (
  `IDHost` bigint(20) unsigned NOT NULL auto_increment,
  `IDPartner` int(10) unsigned NOT NULL default '0',
  `IDReferer` bigint(20) unsigned NOT NULL default '0',
  `IDUri` bigint(20) unsigned NOT NULL default '0',
  `IDSearch` int(10) unsigned NOT NULL default '0',
  `IDWord` bigint(20) unsigned NOT NULL default '0',
  `IDSess` varchar(32) NOT NULL default '',
  `ip` varchar(16) NOT NULL default '',
  `iplong` bigint(20) unsigned NOT NULL default '0',
  `tstc` bigint(20) unsigned NOT NULL default '0',
  `f_people` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`IDHost`),
  KEY `IDPartner` (`IDPartner`),
  KEY `IDReferer` (`IDReferer`),
  KEY `IDUri` (`IDUri`),
  KEY `IDSearch` (`IDSearch`),
  KEY `IDWord` (`IDWord`),
  KEY `iplong` (`iplong`),
  KEY `tstc` (`tstc`),
  KEY `IDSess` (`IDSess`),
  KEY `f_people` (`f_people`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Статистика хостов';
