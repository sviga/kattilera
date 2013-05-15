<?php
/**
 * Класс для получение основы из любой словоформы. Русский язык.
 *
 * $stem = new Lingua_Stem_Ru();
 * print $stem->stem_word("говорить");
 *
 */
class Lingua_Stem_Ru
{
    var $VERSION = "0.03";
    public static $use_cache = 1;
    private static $Stem_Cache = array();
    const VOWEL = '/аеиоуыэюя/';
    const PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/';
    const REFLEXIVE = '/(с[яь])$/';
    const ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|ему|ому|их|ых|ую|юю|ая|яя|ою|ею)$/';
    const PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/';
    const VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ен|ило|ыло|ено|ят|ует|уют|ит|ыт|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/';
    const NOUN = '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|иям|ям|ием|ем|ам|ом|о|у|ах|иях|ях|ы|ь|ию|ью|ю|ия|ья|я)$/';
    const RVRE = '/^(.*?[аеиоуыэюя])(.*)$/';
    const DERIVATIONAL = '/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/';

    private static function s(&$s, $re, $to)
    {
        $orig = $s;
        $s = mb_ereg_replace($re, $to, $s);
        return $orig !== $s;
    }

    private static function match($s, $re)
    {
        return mb_ereg_match($re, $s);
    }

    public static function stem_word($word)
    {
        $word = mb_strtolower($word);
        $word = str_replace('ё', 'е', $word); //strtr($word, 'ё', 'е');
        # Check against cache of stemmed words
        if (self::$use_cache && isset(self::$Stem_Cache[$word]))
            return self::$Stem_Cache[$word];
        $stem = $word;
        do
        {
            if (!preg_match(self::RVRE, $word, $p))
                break;
            $start = $p[1];
            $RV = $p[2];
            if (!$RV) break;

            # Step 1
            if (!self::s($RV, self::PERFECTIVEGROUND, ''))
            {
                self::s($RV, self::REFLEXIVE, '');

                if (self::s($RV, self::ADJECTIVE, ''))
                {
                    self::s($RV, self::PARTICIPLE, '');
                }
                else
                {
                    if (!self::s($RV, self::VERB, ''))
                        self::s($RV, self::NOUN, '');
                }
            }

            # Step 2
            self::s($RV, '/и$/', '');

            # Step 3
            if (self::match($RV, self::DERIVATIONAL))
                self::s($RV, '/ость?$/', '');

            # Step 4
            if (!self::s($RV, '/ь$/', ''))
            {
                self::s($RV, '/ейше?/', '');
                self::s($RV, '/нн$/', 'н');
            }

            $stem = $start . $RV;
        }
        while (false);
        if (self::$use_cache)
            self::$Stem_Cache[$word] = $stem;
        return $stem;
    }

    function clear_stem_cache()
    {
        self::$Stem_Cache = array();
    }
}
