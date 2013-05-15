<?php

/**
 * Вызывается при инсталяции модуля "Вопросы и ответы"
 * @copyright ArtProm (с) 2001-2011
 * @author Александр Ильин [Comma] mecomayou@mail.ru , s@nchez s@nchez.me
 * @version 2.0
 */

// Основные параметры модуля
class faq_install extends install_modules
{
    /**
     * Функция инсталляции базового модуля
     */
    function install($id_module, $reinstall = false)
    {
    }

    /**
     * Удаление модуля из системы
     * @param string $id_module ID удаляемого базового модуля
     */
    function uninstall($id_module)
    {
    }
    /**
     * Методы вызывается, при инсталяции каждого дочернего модуля, здесь необходимо
     * создавать таблицы каталоги, или файлы используемые дочерним модулем. Уникальность создаваемых
     * объектов обеспечивается с помощью передвавемого ID модуля
     *
     * @param string $id_module ID вновь создаваемого дочернего модуля
     * @param boolean $reinstall переинсталяция?
     */
    function install_children($id_module, $reinstall = false)
    {
        global $kernel;

        // Создаем таблицу для хранения перечня разделов
        $sql = " CREATE TABLE IF NOT EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_partitions` (
                `id` int(11) unsigned NOT NULL auto_increment COMMENT 'ID элемента',
                `name` varchar(255) collate utf8_unicode_ci NOT NULL COMMENT 'Название раздела',
                 PRIMARY KEY  (`id`), KEY `name` (`name`)
                 ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица разделов FAQ';";

        $kernel->runSQL($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$kernel->pub_prefix_get().'_'.$id_module.'_content` ('
        . ' `id` int(11) unsigned NOT NULL auto_increment,'
        . ' `pid` int(11) unsigned NOT NULL default \'0\','
        . ' `question` varchar(255) NOT NULL default \'\','
        . ' `description` mediumtext NOT NULL,'
        . ' `answer` TEXT DEFAULT NULL,'
        . ' `user` varchar(255) NOT NULL default \'\','
        . ' `email` varchar(255) NOT NULL default \'\','
        . ' `added` datetime NOT NULL ,'
        . ' PRIMARY KEY (`id`),'
        . ' KEY `pid_a` (`pid`,`answer`(255),`added`),'
        . ' KEY `answer` (`answer`(255),`added`)'
        . ' ) ENGINE = MYISAM DEFAULT CHARSET = utf8 COMMENT="Таблица вопросов-ответов FAQ";';

        $kernel->runSQL($sql);
    }

    /**
    * Методы вызывается, при деинсталяции каждого дочернего модуля, здесь необходимо
    * удалять таблицы, каталоги, или файлы используемые дочерним модулем.
    *
    * @param string $id_module ID удоляемого дочернего модуля
    */
    function uninstall_children($id_module)
    {
        global $kernel;
        // Удаляем таблицу разделов
        $sql = "DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_partitions`;";
        $kernel->runSQL($sql);

        // Удаляем таблиу вопросов и ответов
        $sql = " DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_content`;";
        $kernel->runSQL($sql);
    }
}

$install = new faq_install();

// Основные данные модуля
$install->set_name('[#faq_modul_base_name#]');
$install->set_id_modul('faq');
$install->set_admin_interface(2);

// ==================================================
// Параметры модуля
// ==================================================



//email админа для уведомления о новых вопросах и отправки с него ответов юзерам
$param = new properties_string();
$param->set_id("email");
$param->set_caption("[#faq_modul_param2_name#]");
$param->set_default("admin@".$_SERVER['HTTP_HOST']);
$install->add_modul_properties($param);

//тема письма для ответа юзерам
$param = new properties_string();
$param->set_id("answer_email_subj");
$param->set_caption("[#faq_modul_param_answer_mail_subj#]");
$param->set_default("Ответ на ваш вопрос на сайте ".$_SERVER['HTTP_HOST']);
$install->add_modul_properties($param);

//тема письма для админов
$param = new properties_string();
$param->set_id("new_question_email_subj");
$param->set_caption("[#faq_modul_param_new_question_email_subj#]");
$param->set_default("Новый вопрос на сайте ".$_SERVER['HTTP_HOST']);
$install->add_modul_properties($param);

// ==================================================
// Публичные методы
// ==================================================

// Метод отображения формы
$install->add_public_metod('pub_form', '[#faq_modul_metod_pub_form#]');
// Файл шаблона
$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#faq_module_label_propertes1#]');
$param->set_patch("modules/faq/templates_user");
$param->set_mask("html,htm");
$param->set_default('modules/faq/templates_user/faq.html');
$install->add_public_metod_parametrs('pub_form', $param);

// Метод отображения списка вопросов
$install->add_public_metod('pub_faq', '[#faq_modul_metod_pub_faq#]');

$param = new properties_checkbox();
$param->set_id("faq_ask_form");
$param->set_caption("[#faq_modul_param1_name#]");
$install->add_public_metod_parametrs('pub_faq', $param);
// Файл шаблона
$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#faq_module_label_propertes1#]');
$param->set_patch("modules/faq/templates_user");
$param->set_mask("html,htm");
$param->set_default('modules/faq/templates_user/faq.html');
$install->add_public_metod_parametrs('pub_faq', $param);
// макс. кол-во выводимых вопросов
$param = new properties_string();
$param->set_caption('[#faq_limit_questions#]');
$param->set_default(0);
$param->set_id('limit');
$install->add_public_metod_parametrs('pub_faq', $param);
// Станица для вывода вопросов-ответов
$param = new properties_pagesite();
$param->set_caption('[#faq_modul_param_list_pagename#]');
$param->set_default('');
$param->set_id('faq_answers_page');
$install->add_public_metod_parametrs('pub_faq', $param);

// Метод отображения разделов
$install->add_public_metod('pub_show_partitions', '[#faq_modul_metod_show_partitions#]');
// Станица для вывода вопросов-ответов
$param = new properties_pagesite();
$param->set_caption('[#faq_modul_param_list_pagename#]');
$param->set_default('');
$param->set_id('faq_answers_page');
$install->add_public_metod_parametrs('pub_show_partitions', $param);
// Файл шаблона
$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#faq_module_label_propertes1#]');
$param->set_patch("modules/faq/templates_user");
$param->set_mask("html,htm");
$param->set_default('modules/faq/templates_user/faq.html');
$install->add_public_metod_parametrs('pub_show_partitions', $param);


// ==================================================
// То, что ставится автоматически при инсталяции базового модуля пока оставим так, как есть...
// Теперь можно прописать дочерние модули, которые будут автоматически созданы при
// инсталяции модуля а так же макросы и свойства, каждого из дочерних модулей.
// Свойства модуля
// ==================================================

$install->module_copy[0]['name'] = 'faq_modul_base_name1';

$install->module_copy[0]['action'][0]['caption'] = 'Показать список вопросов-ответов';
$install->module_copy[0]['action'][0]['id_metod'] = 'pub_faq';
$install->module_copy[0]['action'][0]['param']['faq_ask_form'] = 0;
$install->module_copy[0]['action'][0]['param']['template'] = 'modules/faq/templates_user/faq.html';
$install->module_copy[0]['action'][0]['param']['limit'] = 0;
$install->module_copy[0]['action'][0]['param']['faq_answers_page'] = '';

$install->module_copy[0]['action'][1]['caption'] = 'Показать список разделов вопросов-ответов';
$install->module_copy[0]['action'][1]['id_metod'] = 'pub_show_partitions';
$install->module_copy[0]['action'][1]['param']['faq_answers_page'] = '';
$install->module_copy[0]['action'][1]['param']['template'] = 'modules/faq/templates_user/faq.html';

?>