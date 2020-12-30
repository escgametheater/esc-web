<?php
/**
 * Tasks (via gearman)
 *
 * @package tasks
 */

Modules::uses(Modules::MANAGERS);

class InterruptException extends Exception {}

class InterruptedTaskException extends Exception
{
    /**
     * @var string task func name
     */
    protected $task_name;

    /**
     * @var array task args
     */
    protected $task_args;

    /**
     * InterruptedTaskException constructor.
     * @param string $task_name
     * @param array $task_args
     */
    public function __construct($task_name, $task_args)
    {
        $this->task_name = $task_name;
        $this->task_args = $task_args;
    }

    public function get_task_name()
    {
        return $this->task_name;
    }

    public function get_task_args()
    {
        return $this->task_args;
    }
}

/**
 * Worker wrapper
 */
class TaskWorker
{
    /**
     * Wrapped worker
     * @var $worker Net_Gearman_Worker
     */
    protected $worker;

    /**
     * Path to log file
     * @var string
     */
    protected $log_file;


    public function __construct($servers, $log_file = null)
    {
        try {
            $this->worker = new Net_Gearman_Worker($servers);
        } catch (Net_Gearman_Exception $e) {
            std_log('Failed to start: '.$e->getMessage());
            exit(1);
        }

        $options = getopt("", ["log-file:"]);
        $this->log_file = array_get($options, 'log-file', $log_file);
    }

    public function add_ability($name)
    {
        return $this->worker->addAbility($name);
    }

    /**
     * Signal handler function
     */
    public static function sig_handler($signo)
    {
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
                throw new InterruptException($signo);
                break;
        }
    }


    public function run()
    {
        std_log('starting...', 'INFO', $this->log_file);

        try {
            // setup signal handlers
            pcntl_signal(SIGTERM, [__CLASS__, "sig_handler"]);
            pcntl_signal(SIGINT,  [__CLASS__, "sig_handler"]);
            $this->worker->beginWork();
        } catch (InterruptException $e) {
            std_log('interrupted, exiting...', 'INFO');
        } catch (InterruptedTaskException $e) {
            // handle shutdown tasks
            if ($e->get_task_name() !== null) {
                std_log('readding task: '.$e->get_task_name(), 'INFO');
                $args = $e->get_task_args();
                $args['timestamp'] = time();
                TasksManager::add($e->get_task_name(), $args, /*task is already in db*/false);
            }
            std_log('exiting...', 'INFO');
        }
    }

}

/**
 * Task Wrapper
 */
abstract class Task extends Net_Gearman_Job_Common
{
    const ARG_TASKID = 'taskid';

    protected $name;
    protected $args;

    /** @var TasksManager $tasksManager */
    protected $tasksManager;

    public function run($args)
    {
        global $CONFIG;

        ini_set('memory_limit', '1024M');

        $this->args = $args;
//        if ($this->name == TasksManager::TASK_EXTRACT_RELEASE)
//            $log_file = $CONFIG['extract_worker_log_file'];
//        else
            $log_file = $CONFIG['tasks_worker_log_file'];

        std_log("setting logger to ${log_file}", 'INFO', $log_file);

        $request = make_cli_request($CONFIG);

        $this->tasksManager = $request->managers->tasks();
        $taskId = $this->getArg(self::ARG_TASKID);
        // unset($this->args[self::ARG_TASKID]);
        DB::inst()->ping();
        std_log("db ping ok", 'INFO', $log_file);

        $this->tasksManager->startTask($request, $taskId);

        $caught_exception = null;

        try {
            $this->process($request, $args);
            $success = true;

        } catch (InterruptException $e) {
            std_log("* interrupted by signal ".$e->getMessage(), 'INFO');
            $this->cleanup($request, $args, $e);

            $this->tasksManager->stopTask($request, $taskId);

            throw new InterruptedTaskException($this->name, $args);

        } catch (Exception $e) {

            log_exception_in_sentry($e, $request);
            std_log(Debug::get_stack_string($e), 'ERROR');
            $error_msg = get_class($e).'("'.$e->getMessage().'")';
            std_log("* failed: ".$error_msg, 'ERROR');
            $success = false;
            $caught_exception = $e;
        }

        try {
            $this->cleanup($request, $args, $caught_exception);
        } catch (Exception $e) {
            log_exception_in_sentry($e, $request);
            std_log(Debug::get_stack_string($e), 'ERROR');
            $error_msg = get_class($e).'("'.$e->getMessage().'")';
            std_log("* failed cleanup: ".$error_msg, 'ERROR');
        }

        if ($success) {
            std_log("done");
            if ($taskId) {
                $this->tasksManager->completeTask($request, $taskId);
                std_log("deleted task ${taskId} from db");
            }
        } else {
            $this->tasksManager->failTask($request, $taskId);
            global $CONFIG;
            $server_name = array_get($_ENV, "FCGI_WEB_SERVER_ADDRS", 'local');
            $headers = 'From: '.$CONFIG['admins_email'];
            $title = $this->name.' '.$error_msg;
            $body  = var_export($args, true);
            mail($CONFIG['admins_email'], "[".$CONFIG[ESCConfiguration::WEBSITE_NAME]."] Error: ".$title, $body, $headers);
        }
    }

    /**
     * @param $field
     * @param array $args
     * @return null
     */
    protected function getArg($field, $default = null)
    {
        return array_get($this->args, $field, $default);
    }

    protected function cleanup($request, $args, $exception = null) {}
}

/**
 * Tasks Manager
 */
Entities::uses('tasks');
class TasksManager extends BaseEntityManager
{
    // Add Tasks
    const TASK_ADD_SEND_EMAIL = 'send_email';
    const TASK_ADD_SEND_SMS = 'send_sms';
    const TASK_SLURPS_WORKER = 'slurps_worker';
    const TASK_CHANNEL_SLURPER = 'channel_slurper';
    const TASK_COIN_GIRP_PROCESSOR = 'coin_girp_processor';

    // Asynchronous Tasks Handlers

    protected $entityClass = TaskEntity::class;
    protected static $right_required = RightsManager::RIGHT_ADMIN_PANEL;
    protected $table = Table::Tasks;
    protected $table_alias = TableAlias::Tasks;
    protected $root = '/admin/tasks/';
    protected $pk = DBField::TASK_ID;

    public static $fields = [
        DBField::TASK_ID,
        DBField::FILE,
        DBField::FUNC,
        DBField::ARGS,
        DBField::POST_DATE,
        DBField::RUNNING,
        DBField::FAILED,
        DBField::PRIORITY
    ];

    public function convert_data(&$data)
    {
        if (array_key_exists('args', $data))
            $data['args'] = serialize($data['args']);
    }

    /**
     * @param Request $request
     * @return TaskEntity[]
     */
    public function getAllTasks(Request $request, $page = 1, $perPage = 50, $type = null, $reverse = true)
    {
        $queryBuilder = $this->query($request->db)->paging($page, $perPage);

        if ($reverse)
            $queryBuilder->sort_desc($this->createPkField());

        if ($type)
            $queryBuilder->filter($this->filters->Eq(DBField::FUNC, $type));

        return $queryBuilder->get_entities($request);
    }

    /**
     * @param Request $request
     * @return int
     */
    public function getAllTasksCount(Request $request, $type = null)
    {
        $queryBuilder = $this->query($request->db);

        if ($type)
            $queryBuilder->filter($this->filters->Eq(DBField::FUNC, $type));

        return $queryBuilder->count($this->createPkField());
    }


    /**
     * @param Request $request
     * @param $taskid
     * @return TaskEntity
     * @throws BaseEntityException
     */
    public function getTaskById(Request $request, $taskid)
    {
        return $this->getEntityByPk($taskid, $request);
    }

    /**
     * @param Request $request
     * @param $taskIds
     * @return TaskEntity[]
     */
    public function getTasksById(Request $request, $taskIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($taskIds))
            ->get_entities($request);
    }

    public static function add($name, $args = [], $db = true)
    {
        // Add task to queue
        // needed to be sure all tasks are executed
        if ($db) {
            $taskid = TasksManager::objects()
                ->add([
                    'func'    => $name,
                    'args'    => $args,
                    'running' => 0
            ]);
            $args['taskid'] = $taskid;
        }

        global $CONFIG;

        $client = new Net_Gearman_Client($CONFIG['gearman']);

        $task = new Net_Gearman_Task($name, $args);
        $task->type = Net_Gearman_Task::JOB_BACKGROUND;

        $set = new Net_Gearman_Set();
        $set->addTask($task);
        $client->runSet($set);


    }

    /**
     * @param TaskEntity $task
     */
    public function run(Request $request, TaskEntity $task)
    {
        global $CONFIG;

        $task->updateField(DBField::FAILED, 0)->saveEntityToDb($request);

        $client = new Net_Gearman_Client($CONFIG['gearman']);

        $args = unserialize($task->getArgs());
        $args['taskid'] = $task->getPk();

        $task = new Net_Gearman_Task($task->getFunc(), $args);
        $task->type = Net_Gearman_Task::JOB_BACKGROUND;

        $set = new Net_Gearman_Set();
        $set->addTask($task);
        $client->runSet($set);

    }

    /**
     * @param Request $request
     * @param $taskId
     * @return int
     */
    public function startTask(Request $request, $taskId)
    {
        return $this->query($request->db)
            ->filter($taskId)
            ->limit(1)
            ->update([
                DBField::FAILED => false,
                DBField::RUNNING => true,
            ]);
    }

    /**
     * @param Request $request
     * @param $taskId
     * @param bool|false $failed
     * @return int
     */
    public function stopTask(Request $request, $taskId)
    {
        $taskData = [
            DBField::RUNNING => false
        ];

        return $this->query($request->db)
            ->filter($taskId)
            ->limit(1)
            ->update($taskData);
    }

    /**
     * @param Request $request
     * @param $taskId
     * @param bool|false $failed
     * @return int
     */
    public function failTask(Request $request, $taskId)
    {
        $taskData = [
            DBField::RUNNING => false,
            DBField::FAILED => true
        ];

        return $this->query($request->db)
            ->filter($taskId)
            ->limit(1)
            ->update($taskData);
    }

    /**
     * @param Request $request
     * @param $taskId
     * @return TaskHistoryEntity
     * @throws ObjectNotFound
     */
    public function completeTask(Request $request, $taskId)
    {
        $tasksHistoryManager = $request->managers->tasksHistory();

        /** @var TaskEntity $task */
        $task = $this->query($request->db)
            ->filter($this->filters->byPk($taskId))
            ->get_entity($request);

        if ($task) {
            try {
                $tasksHistoryManager->insertTaskHistory($request, $task);
            } catch (DBDuplicateKeyException $e) {
                std_log("Duplicate Key for taskid: {$task->getPk()}");
            }

        }

        $this->query($request->db)
            ->filter($taskId)
            ->limit(1)
            ->delete();
    }
}

class TasksHistoryManager extends BaseEntityManager {

    protected $entityClass = TaskHistoryEntity::class;
    protected static $right_required = RightsManager::RIGHT_ADMIN_PANEL;
    protected $table = Table::TasksHistory;
    protected $table_alias = TableAlias::TasksHistory;
    protected $root = '/admin/tasks/history/';
    protected $pk = DBField::TASK_HISTORY_ID;

    public static $fields = [
        DBField::TASK_HISTORY_ID,
        DBField::TASK_ID,
        DBField::FILE,
        DBField::FUNC,
        DBField::ARGS,
        DBField::POST_DATE,
        DBField::RUNNING,
        DBField::FAILED,
        DBField::PRIORITY,
        DBField::UPDATE_DATE
    ];

    /**
     * @param Request $request
     * @param TaskEntity $task
     * @return TaskHistoryEntity
     */
    public function insertTaskHistory(Request $request, TaskEntity $task)
    {
        $data = [
            DBField::TASK_ID => $task->getPk(),
            DBField::FILE => $task->getFile(),
            DBField::FUNC => $task->getFunc(),
            DBField::ARGS => $task->getArgs(),
            DBField::POST_DATE => $task->getPostDate(),
            DBField::RUNNING => $task->getRunning(),
            DBField::FAILED => $task->getFailed(),
            DBField::PRIORITY => $task->getPriority(),
            DBField::UPDATE_DATE => $request->getCurrentSqlTime()
        ];
        return $this->query()->createNewEntity($request, $data);
    }
}


function make_cli_request($config)
{
    $_SERVER['REMOTE_ADDR'] = '';
    $_SERVER['HTTP_HOST'] = $config[ESCConfiguration::WEBSITE_DOMAIN];
    $_SERVER['HTTPS'] = 'https';

    $request = new Request($_SERVER ?? [], $_POST ?? [], $_COOKIE ?? [], $_FILES ?? [], $config);
    Http::set_request($request);

    // Process request with middleware
    foreach (Http::$registeredMiddlewareClasses as $middleware)
        $middleware->process_request($request);

    return $request;
}
