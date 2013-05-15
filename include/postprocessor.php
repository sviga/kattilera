<?php
/**
 * Абстрактный класс-постпроцессор
 * Все реальные постпроцессоры должны наследоваться от него
 */
abstract class postprocessor
{
    /**
     * Основной метод. Ему передаётся содержимое метки.
     * Постпроцессор обрабатывает это содержимое и возвращает изменённое значение
     * @abstract
     * @param $s   содержимое метки
     * @return mixed
     */
    abstract public function do_postprocessing($s);

    /**
     * Должен вернуть название для отображения в админ-интерфейсе
     * @abstract
     * @param $lang язык
     * @return mixed
     */
    abstract public function get_name($lang);

    /**
     * Должен вернуть описание для отображения в админ-интерфейсе
     * @abstract
     * @param $lang язык
     * @return mixed
     */
    abstract public function get_description($lang);
}