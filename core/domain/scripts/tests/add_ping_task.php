<?php

// Change current work directory to project root
chdir('../../..');

// Does initialisation
require "./init.php";

TasksManager::add('ping', ['text' => 'hello']);
