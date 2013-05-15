<?php
//крон-файл для проверки хэшей и пересчёта
//при запуске с параметром recalc будет произведён пересчёт без всяких проверок ( php cron.php recalc )

$webroot=dirname(dirname(dirname(__FILE__)));
require_once ($webroot."/ini.php"); // Файл с настройками
require_once ($webroot."/include/kernel.class.php"); //Ядро
require_once $webroot.'/modules/sentinel/sentinel.class.php';

$kernel = new kernel(PREFIX);
$kernel->priv_module_for_action_set('sentinel');

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);



$sentinel = new sentinel();
if (isset($argv[1]) && $argv[1]=="recalc")
{
    $sentinel->recalc_hashes();
    $total = $sentinel->get_total_hash_recs();
    die("recalc done, recs: ".$total);
}



$sentinel->check_hashes();

if (count($sentinel->new_files)==0 && count($sentinel->changed_files)==0)
    die($kernel->priv_page_textlabels_replace('[#sentinel_results_all_same#]'));

$new_files_label='';
$changed_files_label='';
if (count($sentinel->new_files)>0)
{
    print "new files:\n";
    foreach ($sentinel->new_files as $file)
    {
        $new_files_label.=$file."<br>\n";
    }
}
if (count($sentinel->changed_files)>0)
{
    print "changed files:\n";
    foreach ($sentinel->changed_files as $file)
    {
        $changed_files_label.=$file."<br>\n";
        print $file."\n";
    }
}

$propEmail=$kernel->pub_modul_properties_get('email4notify');

if (!$propEmail || !$kernel->pub_is_valid_email($propEmail['value']))
    die("no email");
$propEmailFrom=$kernel->pub_modul_properties_get('email4notify_from');
if (!$propEmailFrom || !$kernel->pub_is_valid_email($propEmailFrom['value']))
    die("no email from");


$sentinel->set_templates($kernel->pub_template_parse(dirname(__FILE__)."/templates_admin/notify_email.html"));
$body=$sentinel->get_template_block('mail_body');

$subj=trim($sentinel->get_template_block('mail_subj'));
$body=str_replace('%new_files%',$new_files_label,$body);
$body=str_replace('%changed_files%',$changed_files_label,$body);

$body=$kernel->priv_page_textlabels_replace($body);
$subj=$kernel->priv_page_textlabels_replace($subj);

print "sending to email: ".$propEmail['value'].", from email:".$propEmailFrom['value']."\n";

$kernel->pub_mail(array($propEmail['value']),array('admin'),$propEmailFrom['value'],'sentinel',$subj,$body);



