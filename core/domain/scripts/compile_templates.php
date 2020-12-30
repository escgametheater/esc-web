<?php

// Change current work directory to project root
chdir(dirname(__FILE__).'/..');

// Does initialisation
require "./init.php";

chdir(get_setting('templ')['dir']);
$templates_str = shell_exec("find . -name '*.twig'");
chdir(get_setting('project_dir'));

$templates_list = explode("\n", $templates_str);

$template = new Template();
$template->add_filter('name', $context ==> {});
$template->add_filter('bool_field', $context ==> {});
$template->add_filter('parse_bb', $context ==> {});
$template->add_function('get_language_name', $context ==> {});

foreach ($templates_list as $template_path) {
    if ($template_path) {
        // echo "compiling ${template_path}\n";
        $template->twig->loadTemplate($template_path);
    }
}
