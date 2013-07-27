<?php

/**
 * Инсталлятор для модуля «Гостевая книга»
 * 
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0 beta
 */


class feedback_install extends install_modules
{
	/**
	 * Инсталляция базового модуля
	 *
	 * @param string $id_module Идентификатор создаваемого базового модуля
	 */
	function install( $id_module )
	{
		global $kernel;

	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_feedback` ( '
        . ' `id` int(11) unsigned NOT NULL auto_increment, '
        . ' `author` varchar(255) NULL, '
        . ' `email` varchar(255) NOT NULL, '
        . ' `message` text NOT NULL, '
        . ' `date` DATETIME NOT NULL, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `author` (`author`) '
        . ' ) ENGINE=MyISAM AUTO_INCREMENT=1';

        $kernel -> runSQL( $query );
	}

	/**
     * Деинсталляция базового модуля
     *
     * @param string $id_module Идентификатор удаляемого базового модуля
     */

	function uninstall( $id_module )
	{
		global $kernel;

		$query = 'DROP TABLE `'.PREFIX.'_feedback`';
		$kernel -> runSQL( $query );
	}


	/**
     * Инсталляция дочернего модуля
     *
     * @param string $id_module Идентификатор вновь создаваемого дочернего модуля
     */
	function install_children( $id_module )
	{
		global $kernel;
	}

	/**
	 * Деинсталляция дочернего модуля
	 *
    * @param string $id_module ID деинсталлируемого дочернего модуля
    */
	function uninstall_children( $id_module )
	{
		global $kernel;
	}
}


$install = new feedback_install();

$install -> set_name( '[#feedback_modul_base_name#]' );
$install -> set_id_modul( 'feedback' );
$install -> set_admin_interface( 1 );



/**
 * Здесь свойства модуля
 */
//E-mail администратора
$property =  new properties_string();
$property -> set_id( 'email' );
$property -> set_caption( '[#feedback_property_email#]' );
$property -> set_default( '' );
$install  -> add_modul_properties( $property );

//Использовать капчу
$property =  new properties_checkbox();
$property -> set_id( 'captcha' );
$property -> set_caption( '[#feedback_property_captcha#]' );
$property -> set_default( 'true' );
$install  -> add_modul_properties( $property );



// Публичный метод для отображения формы
$install->add_public_metod('pub_show_feedback_form', '[#feedback_pub_show_form#]');

// Шаблон формы
$property = new properties_file();
$property -> set_caption( '[#feedback_pub_show_form_template#]' );
$property -> set_default( 'modules/feedback/templates_user/form.html' );
$property -> set_id( 'template' );
$property -> set_mask( 'htm,html' );
$property -> set_patch( 'modules/feedback/templates_user' );
$install  -> add_public_metod_parametrs( 'pub_show_feedback_form', $property );


// При установке сразу создаем действия
$install -> module_copy[ 0 ][ 'name' ]                      = 'feedback_modul_base_name';
$install -> module_copy[ 0 ][ 'action' ][ 0 ][ 'caption' ]  = '[#feedback_pub_show_form#]';
$install -> module_copy[ 0 ][ 'action' ][ 0 ][ 'id_metod' ] = 'pub_show_feedback_form';
?>