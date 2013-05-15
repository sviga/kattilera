<?php
/**
 * Крон версия 2, для рассылки с ограничениями, не более N писем за раз
 */

if (!defined('STDIN'))
    die ("Error: I can only run in command promt");

$webRoot= dirname(dirname(dirname(__FILE__)))."/";
chdir($webRoot);

require_once($webRoot."ini.php");
require_once($webRoot."include/kernel.class.php"); //Ядро
require_once(dirname(__FILE__).'/newssubmit.class.php');
require_once($webRoot.'modules/newsi/newsi.class.php');
set_time_limit(0);
$kernel = new kernel(PREFIX);
$mod = new newssubmit('newssubmit1');
$letters = $mod->getCron2Letters4send();

print "sending ".count($letters)." letters ...\n";
foreach ($letters as $letter)
{
    print "sending email to ".$letter['toemail']."\n";
    if ($kernel->pub_mail(array($letter['toemail']),array($letter['toname']), $letter['fromemail'], $letter['fromname'], $letter['subj'], $letter['body'],false,"",false,$letter['fromemail']))
        print " sent OK\n";
    else
       print " send FAILED\n";
    $mod->deleteCron2Letter($letter['id']);//удаляем в любом случае
}