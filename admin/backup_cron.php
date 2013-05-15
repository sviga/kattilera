<?php
    if (!defined('STDIN'))
        die ("Error: I can only run in command promt");

    if (!$argv[1])
        die("Error: need rule stringid as 1st parameter");

    $curr_dir = dirname(__FILE__)."/";
    $root_dir = $curr_dir."../";

    chdir($root_dir);

    require_once $root_dir."ini.php";
    require_once $root_dir."include/kernel.class.php";
    require_once $root_dir."include/pub_interface.class.php";
    require_once $curr_dir.'backup.class.php';

    $kernel = new kernel(PREFIX);

    $backup = new backup();

    $stringid = $argv[1];
    //print "string id: ".$stringid."\n";

    $rule = $backup->get_backup_rule_by_stringid($stringid);
    if (!$rule)
        die("Error:Backup rule by string id '".$stringid."' not found");
     if ($backup->backup_run_save($rule, $rule['needcontent'], $rule['needsystem'], $rule['needtables'], $rule['needdesign'], "made by cron"))
         print "backup created\n";
     else
         print "failed to create backup: ".$backup->error_last_get()."\n";