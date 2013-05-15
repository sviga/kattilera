<?php
class html_encode extends postprocessor
{
    public function do_postprocessing($s)
    {
        return htmlspecialchars($s);
    }

    public function get_name($lang)
    {
        return "html escape";
    }

    public function get_description($lang)
    {
        return "Вызов htmlspecialchars на содержимом";
    }

}