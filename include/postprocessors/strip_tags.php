<?php
class strip_tags extends postprocessor
{
    public function do_postprocessing($s)
    {
        return strip_tags($s);
    }

    public function get_name($lang)
    {
        return "strip_tags";
    }

    public function get_description($lang)
    {
        return "Вызов strip_tags на содержимом";
    }

}