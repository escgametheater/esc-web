<?php

echo "Starting check...\n";
Modules::uses(Modules::TASKS);

function run_task($task)
{
    echo 'checking '.$task['id'].' func '.$task['func'].' args: '.$task['args']."\n";

    // Try to lock the task
    srand(getmypid() + rand());
    $rand = rand();
    TasksManager::objects()
        ->filter(Q::Eq(DBField::ID, $task['id']))
        ->limit(1)
        ->update(['running' => $rand]);

    $check = TasksManager::objects()
        ->filter(Q::Eq(DBField::ID, $task['id']))
        ->get('running');

    // Check lock
    if ($check['running'] == strval($rand)) { // lock succeded
        // Run task
        try {
            // add task to queue
            $args = unserialize($task['args']);
            if (!array_key_exists('taskid', $args))
                $args['taskid'] = $task['id'];
            echo "adding task to the queue\n";
            elog('adding task to the queue: '.$task['id']."\n".dump($args, 'args', false));
            TasksManager::add($task['func'], $args, false);

            // Check for next task
            return true;
        } catch (Exception $e) {
            // Mark task as failed
            TasksManager::objects()
                ->filter(Q::Eq(DBField::ID, $task['id']))
                ->limit(1)
                ->update(['running' => 0, 'failed' => 1]);
            echo $msg;
            elog($e->getMessage());
        }
    } else { // lock failed
        // We lost the race to this task
        // check for other tasks
        echo "lock failed\n";
        return true;
    }

    return false;
}

$timecut = (int)microtime(true) - 86400;

// Fetch Task
$tasks = TasksManager::objects()
    //->filter(Q::Eq('failed', 0))
    //->filter(Q::Eq('running', 0))
    ->filter(Q::Lt('post_date', date(SQL_DATETIME, $timecut)))
    ->order_by('-priority')
    ->get_list('id', 'file', 'func', 'args');

foreach ($tasks as $t) {
    try {
        run_task($t);
    } catch (ObjectNotFound $e) {}
}

echo "Finished check...\n";
