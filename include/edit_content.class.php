<?php
/**
 * Управляет редактора контента
 *
 * Производит формирование HTML кода для возможности редактирования переданного
 * контента средствами визуального HTML редактора. При формировании HTML кода использует
 * форма (тег <form>). Она может быть внешенй (в случае если редактор используется для изменения
 * одного /нескольких полей сложной формы) или внутренней, когда форма уже включена в формируемый контент.
 * Так же может быть указано имя файла, в который будет автоматически записан отредактированный контент.
 * Пример использования класса:
 * <code>
 *      global $kernel;
 *
 *      $content = new edit_content();
 *      $content->set_close_editor(true); //Устанавливает признак того что бы закрывать окно, после завершения редактирования
 *      $kernel->priv_output($content->start());
 *
 *      // На кнопку Submit формы, на событие по клику так же нужно повесить вызов функции form_submit_include_content();
 * </code>
 * @name edit_content
 * @package  PublicFunction
 * @copyright ArtProm (с) 2001-2007
 * @version 1.0
 */

class edit_content
{

    /**
     * Определяет необходимость использования полной HTML формы для вывода редактора контента
     *
     * @var boolean
     * @access private
     */
    var $use_full_form = false;
    var $use_nothtml_form = false;

    /**
     * Определяет необходимость использования простой схемы редактора, когда есть только
     * минимальный набор функций по работе с текстом
     *
     * @var boolean
     * @access private
     */

    var $use_simle_theme = false;

    /**
	 * Непосредственно редактируемый контент
	 *
	 * Содержит HTML код редактируемого документа для вставки его в редактор
	 * @var string
	 * @access private
	 */
	var $content = '';

	/**
	 * Файл, используемый для получения или сохранения контента
	 *
	 * Имя файла вместе с путем до него
	 * @var string
	 * @access private
	 */
	var $file_name = '';

	/**
	 * Определяет необходимость закрытия окна редактора после сохранения изменений
	 *
	 * @var boolean
	 * @access private
	 */
	var $close_on_save = false;

	/**
	 * Имя объекта TEXTAREA в HTML форме, используемой для редактирования контента
	 *
	 * @var string
	 * @access private
	 */
	var $edit_name = 'content_html';

	/**
	 * Флаг запуска в режиме IFRAME
	 *
	 * @var boolean
	 * @access private
	 */
	var $iframe = false;

	/**
	 * Конструктор
	 *
	 * При созданее объекта никаких параметров в него передавать не надо.
     * @param boolean $iframe Используется ТОЛЬКО ядром для определения вызова через IFRAME
     * @return edit_content
     */
	function edit_content($iframe = false)
    {
        $this->iframe = $iframe;

        if (($this->iframe) && (isset($_SESSION['edit_content_iframe'])))
            $this->content = $_SESSION['edit_content_iframe'];
    }


    /**
     * Устанавливает в качестве формы вывода редактора полную HTML форму
     *
     * Под полной HTML формой подразумевается законченная страница пригодная для самостоятельного
     * и полноценного отображения браузером. Данный метод необходимо использовать в том случае, если
     * редактор контента будет открываться в отдельном и самостоятельном окне.
     * Сейчас такой способ открытия редактора контента использует только ядро.
     * @param boolean
     * @return void
     * @access public
     */
    function set_full_form($value = true)
    {
        $this->use_full_form = $value;
    }

    function set_form_nothtml($value = true)
    {
    	$this->use_nothtml_form = $value;
    }

    /**
     * Устанавливает в качестве источника контенета определенный файл.
     *
     * Устанавливает в качестве источника редактируемого контента определённый файл.
     * Автоматического сохранения отредактированного контента в этот же файл не
     * происходит. Если файла с таким именем нет, то он автоматически создается.
     * Создание каталога автоматически не происходит.
     * @param string Имя файла
     * @return boolean true - если файл принят и false в противном случае
     * @access public
     */
    function set_file($name)
    {
    	if (empty($name))
    	   return false;
        $this->file_name = $name;
    	$this->content = '';
    	return true;
    }

    /**
     * Устанавливает контент, подлежащий редактированию
     *
     * Используется для передачи контента подлежащего редактированию в тех случаях,
     * когда он хранится не в отдельном файле а каким либо другим образом, например
     * в базе mySQL
     * @param string
     * @access public
     * @return void
     */
    function set_content($html)
    {
    	$this->content = $html;
    }

    /**
     * Устанавливает имя тега TEXTAREA в HTML форме.
     *
     * В объекте TEXTAREA, с установленным именем и будет производится редактирование
     * контента. В дальнейшем, в массиве, полученном с помощью функции $kernel->pub_httppost_get(),
     * можно будет обратиться к отредактированному контенту по этому имени
     * Если данный метод не используется, то область контента называется <i>content</i>
     * @param string Имя области для редактирования контента
     * @access public
     * @return void
     */
    function set_edit_name($value)
    {
        if (!empty($value))
            $this->edit_name = trim($value);

    }



    /**
     * Устанавливает простую форму функциональных возможностей редактора контента.
     *
     * Используется в тех случаях, когда необходимо дать только самые минимальные возможности
     * при работе с редактором. Сюда входит лишь выделение текста и использование списков
     * @param boolean $value
     * @return void
     */
    function set_simple_theme($value = true)
    {
        $this->use_simle_theme = $value;
    }


    /**
     * Создает HTML код для возможности редактирования контента средствами встроенного редактора
     *
     * Собирает все параметры класса и в соответствии с ним формирует HTML код, который
     * встраивается в страницу и позволяет редактировать переданный контент средствами HTML
     * редактора
     * @return HTML
     * @access public
     */
    function create()
    {
    	global $kernel;

    	clearstatcache();

    	//Сейчас, для усечённой формы всё должно быть немного подругому
    	//сначала мы выводим ифрейм, в который уже и подгружается редактор контента
    	//а сам контент берётся из сессии

    	if ($this->use_nothtml_form)
    		$html = file_get_contents('admin/templates/default/editor_html_textarea.html');
    	else
    	{
	        if (!$this->use_full_form)
	           $html = file_get_contents('admin/templates/default/editor_html_simple.html');
	        else
	    	   $html = file_get_contents('admin/templates/default/editor_html.html');
    	}

        if (defined("CLOSE_WINDOWS_ON_SAVE") && CLOSE_WINDOWS_ON_SAVE)
            $html = str_replace('[#close_on_save#]', "true", $html);
        else
            $html = str_replace('[#close_on_save#]', "false", $html);

    	$html = str_replace('[#file_name#]', $this->file_name, $html);
    	$html = str_replace('[#name_edit#]', $this->edit_name, $html);
    	$html = str_replace('[#name_title#]', $this->file_name, $html);
    	if ($this->use_simle_theme)
    	   $html = str_replace('[#use_theme#]', "simple", $html);
    	else
    	   $html = str_replace('[#use_theme#]', "advanced", $html);

        $name_file = $kernel->pub_site_root_get().$kernel->pub_path_for_content().$this->file_name;
    	if (!empty($this->file_name) && file_exists($name_file))
   			$html = str_replace('[#edit_content#]', htmlspecialchars(file_get_contents($name_file)), $html);
   		else
			$html = str_replace('[#edit_content#]', htmlspecialchars($this->content), $html);
	    return $html;
    }


}
?>