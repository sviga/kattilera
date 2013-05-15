TRUNCATE `%PREFIX%_admin`;

TRUNCATE `%PREFIX%_admin_cross_group`;

TRUNCATE `%PREFIX%_admin_group`;

TRUNCATE `%PREFIX%_admin_group_access`;

TRUNCATE `%PREFIX%_admin_trace`;

TRUNCATE `%PREFIX%_search1_docs`;

TRUNCATE `%PREFIX%_search1_index`;

TRUNCATE `%PREFIX%_search1_words`;

TRUNCATE `%PREFIX%_stat_domain`;

TRUNCATE `%PREFIX%_stat_host`;

TRUNCATE `%PREFIX%_stat_index`;

TRUNCATE `%PREFIX%_stat_referer`;

TRUNCATE `%PREFIX%_stat_uri`;

TRUNCATE `%PREFIX%_stat_word`;

TRUNCATE `%PREFIX%_user`;

TRUNCATE `%PREFIX%_user_cross_group`;

TRUNCATE `%PREFIX%_user_fields`;

TRUNCATE `%PREFIX%_user_fields_value`;

UPDATE `%PREFIX%_modules` SET `module_setings`='a:0:{}' WHERE `id`='kernel';