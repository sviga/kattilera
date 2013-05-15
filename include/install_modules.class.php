<?php
/**
 * Установка и удаление модулей системы
 *
 * @name install_modules
 * @package install_modules
 * @copyright ArtProm (с) 2001-2007
 * @version 1.0
 */
class install_modules
{
	/**
	 * Имя базвоого модуля, используемое при инсталяции
	 *
	 * @var string
	 */
	protected $modul_name = '';

	/**
	 * id базвого модуля
	 *
	 * @var string
	 */
	protected $modul_id	= '';

	/**
	 * Тип администртивного интерфейса
	 *
	 * Возвращаемые значения
	 * 0 - модули не имеют административного интерфейса (АИ)
	 * 1 - модули имеют один АИ, на базовый модуль
	 * 2 - каждый экземпляр модуля имеет свою админку
	 * @var int
	 */
	protected $type_admin_interfase = 0;

	/**
	 * Массив параметров модуля
	 *
	 * @var array
	 */
	protected $modul_properties = array();

	/**
	 * Массив параметров, которые модуль добовляет к каждой странице
	 *
	 * @var array
	 */
	protected $page_properties = array();

	/**
	 * Массив дополнительных полей, которые будут прописаны к БАЗОВОМУ модулю
	 *
	 * @access private
	 * @var array
	 */
	protected $users_properties_one	= array();

	/**
	 * Массив дополнительных полей, которые будут прописаны к КАЖДОМУ дочернему модулю
	 *
	 * @var array
	 */
	protected $users_properties_multi = array();

	/**
	 * Массив признаков доступа для администраторов сайта
	 *
	 * @var array
	 */
	protected $admin_acces_label = array();

	/**
	 * Массив методов, из которых строятся макросы, с параметрами
	 *
	 * @var array
	 */
	protected $admin_public_metods = array();

	/**
	 * Массив значений параметров модуля, выставляемых при исталяции
	 *
	 * @var array
	 */
	protected $parametrs_def = array();

	/**
	 * Массив, показывающий сколько нужно сделать экземпляров модуля при инсталляции
	 *
	 * @var array
	 */
	public $module_copy = array();

	/**
	 * Определяет возможность обрботки таблиц mysql при реинсталляции
	 *
	 * @var bool
	 */
	protected  $call_reinstall_mysql = true;


	/**
	 * Вызывается при инстялции базовового модуля (один раз)
	 *
	 * @param string $id_module ID базового модуля
	 * @param boolean $reinstall переинсталяция?
	 * @return void
	 */
	function install($id_module, $reinstall = false)
	{
	}


	/**
     * Метод вызывается при деинтсаляции базового модуля. ID базоовго модуля
     * точно известно и определется самим модулем, но он (ID) так же передается в
     * качестве параметра. Здесь необходимо производить удаление каталогов, файлов и таблиц используемых
     * базовым модулем и создаваемых в install
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
	}


   /**
    * Методы вызывается, при деинсталяции каждого дочернего модуля, здесь необходимо
    * удалять таблицы, каталоги, или файлы используемые дочерним модулем.
    *
    * @param string $id_module ID удоляемого дочернего модуля
    */
	function uninstall_children($id_module)
	{
	}



	/**
	 * Добавляет к модулю метод из которого впоследствии может быть сформировано действие
	 *
	 * @param string $name
	 * @param string $caption
	 * @access public
	 * @return void
	 */
	function add_public_metod($name, $caption)
	{
		$this->admin_public_metods[trim($name)]['id'] = trim($name);
		$this->admin_public_metods[trim($name)]['name'] = trim($caption);
		$this->admin_public_metods[trim($name)]['parametr'] = array();
	}

	/**
	 * Добавляет параметры к созданому методу модуля
	 *
	 * @param string $name
	 * @param properties_abstact $param
	 * @access public
	 * @return void
	 */
	function add_public_metod_parametrs($name, $param)
	{
		$this->admin_public_metods[trim($name)]['parametr'][] = $param->get_array();
	}

	//************************************************************************
	/**
     * Возрашает доступные методы для построения макрасов
     *
	 * @access public
     * @return Array
     */
	function get_public_metod()
	{
		return $this->admin_public_metods;
	}


	/**
	 * Добавляет новый "разрез" контроля прав групп администраторов сайта
	 *
	 * @param string $name ID права
	 * @param string $caption Представления имени для администратора
	 * @access public
	 * @return void
	 */
	function add_admin_acces_label($name, $caption)
	{
		$this->admin_acces_label[trim($name)] = trim($caption);
	}

	//************************************************************************
    /**
     * Возврашает сформированный массив id уровней доступа для групп администраторов сайта
	 *
	 * @access public
     * @return Array
     */

    function get_admin_acces_label()
    {
		return $this->admin_acces_label;
    }

    /**
     * Устанавливает имя модуля базового модуля с
     * которым он будет инсталирован в системе
     *
     * @param string $name
	 * @access public
	 * @return void
     */
    function set_name($name)
    {
    	$this->modul_name = trim($name);
    }

    /**
     * Возвращает имя базового модуля
     *
	 * @access public
     * @return string
     */
    function get_name()
    {
    	return $this->modul_name;
    }

    /**
     * Устанавливает ID базавого модуля
     *
     * @param string $id
	 * @access public
	 * @return void
     */
    function set_id_modul($id)
    {
    	$this->modul_id = trim($id);
    }

    /**
     * Возвращает ID базового модуля
     *
	 * @access public
     * @return string
     */
    function get_id_modul()
    {
    	return $this->modul_id;
    }

    /**
     * Устанавливает тип административного интерфейса для модуля
     *  0 - модули не имеют административного интерфейса (АИ)
	 *  1 - модули имеют один АИ, на базовый модуль
	 *  2 - каждый экземпляр модуля имеет свою админку
	 *
     * @param integer $type_in Тип интерфейса
	 * @access public
	 * @return void
     */
    function set_admin_interface($type_in)
    {
  		$this->type_admin_interfase  = intval($type_in);
    }

    /**
     * Возвращает тип административного интерфейса для модуля
     *
     *  Возвращаемые значения:
     *  0 - модули не имеют административного интерфейса (АИ)
	 *  1 - модули имеют один АИ, на базовый модуль
	 *  2 - каждый экземпляр модуля имеет свою админку
	 * @access public
     * @return integer
     */
    function get_admin_interface()
    {
    	return $this->type_admin_interfase;
    }

    /**
     * Добавляет новый параметр модуля
     *
     * @param properties_abstact $param Объект одного из "типов propertie_*"
	 * @access public
	 * @return void
     */
    function add_modul_properties($param)
    {
    	if (is_object($param))
    	{
    		$arr = $param->get_array();
  			$this->modul_properties[] = $arr;
  			if (!empty($arr['default']))
  				$this->parametrs_def[$arr['name']] = $arr['default'];
    	}

    }

    /**
     * Возврашает массив параметров модуля для проведения инсталяции
     *
	 * @access public
     * @return Array
     */
	function get_modul_properties()
	{
		return $this->modul_properties;
	}

    /**
     * Добавляет новый параметр, прописываемый модулем к каждой странице сайта
     *
     * @param properties_abstact $param Объект одного из "типов propertie_*"
	 * @access public
	 * @return void
     */
    function add_page_properties($param)
    {
    	if (is_object($param))
  			$this->page_properties[] = $param->get_array();
    }

    /**
     * Возврашает массив параметров модуля для проведения инсталяции
     *
	 * @access public
     * @return Array
     */
	function page_properties_get()
	{
		return $this->page_properties;
	}


	/**
	 * Добовляет новое свойтсво к пользовтаелю сайта. Пока поддерживаются свойства только строкового значения
	 *
	 * @param properties_abstact $param Объект типа  propertie_string
	 * @param boolean $multi Если <i>TRUE</i> - то этот параметр будет прописываться каждым экземпляром дочернего модуля, в противном случае только базовым модулем
	 * @param boolean $admin Если <i>TRUE</i> - то значит доступ к этому парметру пользователя должен иметь только администратор, в противном случае и сам пользователь имеет доступ к этому свойству
	 * @access public
	 * @return void
	 */
	function add_user_properties($param, $multi = false, $admin = false)
	{
		$arr = $param->get_array();
		$arr['admin'] = $admin;

		if ($multi)
			$this->users_properties_multi[] = $arr;
		else
			$this->users_properties_one[] = $arr;

	}

    /**
     * Возвращает массив дополнительных полей, которые будут прописаны к БАЗОВОМУ модулю
     *
	 * @access public
     * @return array
     */
    function get_users_properties_one()
    {
		return $this->users_properties_one;
    }


    /**
     * Возвращает массив дополнительных полей, которые будут прописаны к КАЖДОМУ дочернему модулю
     *
	 * @access public
     * @return array
     */
    function get_users_properties_multi()
    {
		return $this->users_properties_multi;
    }



    /**
     * Включает/выключает обработку таблиц mySql при переинсталляции модуля
     *
     * @param boolean $value
     */
    function set_call_reinstall_mysql($value = true)
    {
        $this->call_reinstall_mysql = $value;
    }

    function get_call_reinstall_mysql()
    {
        return $this->call_reinstall_mysql;
    }

    //РАЗОБРАТСЯ с ЭТИМИ ФУНКЦИЯМИ


    //************************************************************************
	/**
     * Возвращает массив установленных параметров модуля (только название и значение)
     *
	 * @access public
     * @return  Array
     */
	function return_default_properties()
	{
		return $this->parametrs_def;
	}


	/**
	 * Возвращает массив, показывающий сколько нужно сделать экземпляров модуля при инсталляции
	 *
	 * @access public
	 * @return array
	 */
	function get_module_copy()
	{
		return $this->module_copy;

	}



}
?>