<?php

class finance_install extends install_modules {
	/**
	 * Инсталляция базового модуля
	 *
	 * @param string $id_module Идентификатор создаваемого базового модуля
	 */
	function install( $id_module ) {
		global $kernel;
	}

	/**
     * Деинсталляция базового модуля
     *
     * @param string $id_module Идентификатор удаляемого базового модуля
     */

	function uninstall( $id_module ) {
		global $kernel;
	}


	/**
     * Инсталляция дочернего модуля
     *
     * @param string $id_module Идентификатор вновь создаваемого дочернего модуля
     */
	function install_children( $id_module ) {
		global $kernel;
	}

	/**
	 * Деинсталляция дочернего модуля
	 *
    * @param string $id_module ID деинсталлируемого дочернего модуля
    */
	function uninstall_children( $id_module ) {
		global $kernel;
	}
}


$install = new finance_install();

$install -> set_name( '[#finance_modul_base_name#]' );
$install -> set_id_modul( 'finance' );
$install -> set_admin_interface(0);


// Публичный метод для отображения графика Yahoo
$install->add_public_metod('pub_show_finance_chart_yahoo', '[#finance_pub_show_chart_yahoo#]');

// Шаблон формы
$property = new properties_file();
$property -> set_caption( '[#finance_pub_show_chart_template#]' );
$property -> set_default( 'modules/finance/templates_user/chart_yahoo.html' );
$property -> set_id( 'template' );
$property -> set_mask( 'htm,html' );
$property -> set_patch( 'modules/finance/templates_user' );
$install  -> add_public_metod_parametrs( 'pub_show_finance_chart_yahoo', $property );

// Адрес данных
$property = new properties_string();
$property->set_caption('[#finance_property_link_to_data#]');
$property->set_default('http://chartapi.finance.yahoo.com/instrument/1.0/aapl/chartdata;type=quote;range=1d/xml');
$property->set_id('link_to_data');
$install->add_public_metod_parametrs('pub_show_finance_chart_yahoo', $property);

// Время задержки обновления в сек
$property->set_caption('[#finance_property_wait_time#]');
$property->set_default('3600');
$property->set_id('wait_time');
$install->add_public_metod_parametrs('pub_show_finance_chart_yahoo', $property);



// Публичный метод для отображения графика Google
$install->add_public_metod('pub_show_finance_chart_google', '[#finance_pub_show_chart_google#]');

// Шаблон формы
$property = new properties_file();
$property -> set_caption( '[#finance_pub_show_chart_template#]' );
$property -> set_default( 'modules/finance/templates_user/chart_google.html' );
$property -> set_id( 'template' );
$property -> set_mask( 'htm,html' );
$property -> set_patch( 'modules/finance/templates_user' );
$install  -> add_public_metod_parametrs( 'pub_show_finance_chart_google', $property );

// Адрес данных
$property = new properties_string();
$property->set_caption('[#finance_property_link_to_data#]');
$property->set_default('https://www.google.com/finance/getprices?q=AAPL&i=60&p=1d&f=d,c,v');
$property->set_id('link_to_data');
$install->add_public_metod_parametrs('pub_show_finance_chart_google', $property);

// Время задержки обновления в сек
$property->set_caption('[#finance_property_wait_time#]');
$property->set_default('3600');
$property->set_id('wait_time');
$install->add_public_metod_parametrs('pub_show_finance_chart_google', $property);

/*Let’s tackle the url string first. We will do it just like the other two previous attempts. We end up with:

    The base url is http://www.google.com/finance/getprices
    q is the symbol (AAPL)
    x is the exchange (NASD)
    i is the interval in seconds (120 = seconds = 2 minutes)
    sessions is the session requested (ext_hours)
    p is the time period (5d = 5 days)
    f is the requested fields (d,c,v,o,h,l)
    df ?? (cpct)
    auto ?? (1)
    ts is potentially a time stamp (1324323553 905)
*/

// При установке сразу создаем действия
$install -> module_copy[ 0 ][ 'name' ]                      = 'finance_modul_base_name';

?>