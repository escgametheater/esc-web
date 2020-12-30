<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 12/19/18
 * Time: 2:33 PM
 */

Entities::uses('stats');

class DateRangesManager extends BaseEntityManager {

    protected $pk = DBField::DATE_RANGE_ID;
    protected $entityClass = null;
    protected $table = 'date_range';
    protected $table_alias = 'dr';

    protected static $sumField = VField::SUMMARY;
    protected static $avgField = VField::AVG;

    public static $fields = [
        DBField::DATE_RANGE_ID,
        DBField::DATE_RANGE_TYPE_ID,
        DBField::START_TIME,
        DBField::END_TIME
    ];

    /**
     * @return string
     */
    public static function getSumField()
    {
        return static::$sumField;
    }

    /**
     * @return string
     */
    public static function getAvgField()
    {
        return static::$avgField;
    }


    /**
     * @param DBField $startField
     * @param DBField|null $endField
     * @return OrFilter
     */
    public function joinDateRangesFilter(DBField $startField, DBField $endField = null)
    {
        // If there's an end-field, there's a duration and we need to check a few cases. Else, it's a timestamped event.
        if ($endField) {
            // Started During the period
            $startedDuringFilter = $this->filters->And_(
                $this->filters->Gte($startField, $this->field(DBField::START_TIME)),
                $this->filters->Lt($startField, $this->field(DBField::END_TIME))
            );

            // Ended During the period
            $endedDuringFilter = $this->filters->And_(
                $this->filters->Gte($endField, $this->field(DBField::START_TIME)),
                $this->filters->Lt($endField, $this->field(DBField::END_TIME))
            );

            // Started Before, Ended After/Never
            $startedBeforeEndedAfterFilter = $this->filters->And_(
                $this->filters->Lt($startField, $this->field(DBField::START_TIME)),
                $this->filters->Or_(
                    $this->filters->Gt($endField, $this->field(DBField::END_TIME)),
                    $this->filters->IsNull($endField)
                )
            );

            $dateRangesFilter = $this->filters->Or_($startedDuringFilter, $endedDuringFilter, $startedBeforeEndedAfterFilter);

        // This is a timestamped event with only one value to check.
        } else {

            // Happened During the period
            $dateRangesFilter = $this->filters->And_(
                $this->filters->Gte($startField, $this->field(DBField::START_TIME)),
                $this->filters->Lt($startField, $this->field(DBField::END_TIME))
            );
        }

        return $dateRangesFilter;

    }

    /**
     * @param Request $request
     * @param $dateRangeTypeId
     * @param BaseEntityManager $foreignManager
     * @param DBField $summaryField
     * @param $startField
     * @param null $endField
     * @return array
     */
    protected function summarizeMetric(Request $request, $dateRangeTypeId, BaseEntityManager $foreignManager, DBField $summaryField,
                                            $startField, $endField = null, DBFilter $extraFilters = null)
    {
        if (!$startField instanceof DBField)
            $startField = $foreignManager->field($startField);

        if ($endField && !$endField instanceof DBField && $foreignManager->hasField($endField))
            $endField = $foreignManager->field($endField);

        $fields = [
            $this->createPkField(),
            $summaryField
        ];


        $metrics = $this->query($request->db)
            ->set_connection($request->db->get_connection(SQLN_BI))
            ->fields($fields)
            ->left_join($foreignManager, $this->joinDateRangesFilter($startField, $endField))
            ->filter($this->filters->byDateRangeTypeId($dateRangeTypeId))
            ->filter($this->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()))
            ->filter($this->filters->Gte(DBField::START_TIME, '2018-07-01'))
            ->filter($extraFilters)
            ->group_by($this->createPkField())
            ->get_objects($request);

        return $metrics;
    }


    /**
     * @param Request $request
     * @param $dateRangeTypeId
     * @param BaseEntityManager $foreignManager
     * @param $startField
     * @param null $endField
     * @return array
     */
    protected function summarizeCountMetric(Request $request, $dateRangeTypeId, BaseEntityManager $foreignManager,
                                            $startField, $endField = null, DBFilter $extraFilters = null)
    {
        $summaryField = new CountDBField($this->getSumField(), $foreignManager->getPkField(), $foreignManager->getTable());

        return $this->summarizeMetric(
            $request,
            $dateRangeTypeId,
            $foreignManager,
            $summaryField,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param $dateRangeTypeId
     * @param BaseEntityManager $foreignManager
     * @param $startField
     * @param $endField
     * @return array
     */
    protected function summarizeSumTime(Request $request, $dateRangeTypeId, BaseEntityManager $foreignManager,
                                            $startField, $endField, DBFilter $extraFilters = null)
    {
        $connection = $request->db->get_connection();

        $startField = $foreignManager->field($startField);
        $endField = $foreignManager->field($endField);

        $expression = new RawDBField("TIMESTAMPDIFF(SECOND, {$startField->render($connection)}, {$endField->render($connection)})");

        $summaryField = new SumDBField($this->getSumField(), $expression, $foreignManager->getTable());

        return $this->summarizeMetric(
            $request,
            $dateRangeTypeId,
            $foreignManager,
            $summaryField,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param string $countField
     * @return array
     */
    public function summarizeCountDaily(Request $request, BaseEntityManager $foreignManager,
                                        $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                        DBFilter $extraFilters = null)
    {
        return $this->summarizeCountMetric(
            $request,
            DateRangesTypesManager::ID_DAILY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param string $countField
     * @return array
     */
    public function summarizeCountWeekly(Request $request, BaseEntityManager $foreignManager,
                                         $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                         DBFilter $extraFilters = null)
    {
        return $this->summarizeCountMetric(
            $request,
            DateRangesTypesManager::ID_WEEKLY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param string $countField
     * @return array
     */
    public function summarizeCountMonthly(Request $request, BaseEntityManager $foreignManager,
                                          $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                          DBFilter $extraFilters = null)
    {
        return $this->summarizeCountMetric(
            $request,
            DateRangesTypesManager::ID_MONTHLY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param DBFilter|null $extraFilters
     * @return array
     */
    public function summarizeCountYearly(Request $request, BaseEntityManager $foreignManager,
                                          $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                          DBFilter $extraFilters = null)
    {
        return $this->summarizeCountMetric(
            $request,
            DateRangesTypesManager::ID_YEARLY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param DBFilter|null $extraFilters
     * @return array
     */
    public function summarizeCountAllTime(Request $request, BaseEntityManager $foreignManager,
                                          $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                          DBFilter $extraFilters = null)
    {
        return $this->summarizeCountMetric(
            $request,
            DateRangesTypesManager::ID_ALL_TIME,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @return array
     */
    public function summarizeSumTimeDaily(Request $request, BaseEntityManager $foreignManager,
                                          $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                          DBFilter $extraFilters = null)
    {
        return $this->summarizeSumTime(
            $request,
            DateRangesTypesManager::ID_DAILY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @return array
     */
    public function summarizeSumTimeWeekly(Request $request, BaseEntityManager $foreignManager,
                                           $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                           DBFilter $extraFilters = null)
    {
        return $this->summarizeSumTime(
            $request,
            DateRangesTypesManager::ID_WEEKLY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @return array
     */
    public function summarizeSumTimeMonthly(Request $request, BaseEntityManager $foreignManager,
                                            $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                            DBFilter $extraFilters = null)
    {
        return $this->summarizeSumTime(
            $request,
            DateRangesTypesManager::ID_MONTHLY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param DBFilter|null $extraFilters
     * @return array
     */
    public function summarizeSumTimeYearly(Request $request, BaseEntityManager $foreignManager,
                                            $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                            DBFilter $extraFilters = null)
    {
        return $this->summarizeSumTime(
            $request,
            DateRangesTypesManager::ID_YEARLY,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }

    /**
     * @param Request $request
     * @param BaseEntityManager $foreignManager
     * @param string $startField
     * @param string $endField
     * @param DBFilter|null $extraFilters
     * @return array
     */
    public function summarizeSumTimeAllTime(Request $request, BaseEntityManager $foreignManager,
                                            $startField = DBField::START_TIME, $endField = DBField::END_TIME,
                                            DBFilter $extraFilters = null)
    {
        return $this->summarizeSumTime(
            $request,
            DateRangesTypesManager::ID_ALL_TIME,
            $foreignManager,
            $startField,
            $endField,
            $extraFilters
        );
    }
}

class DateRangesXDateRangesManager extends BaseEntityManager {

    protected $pk = DBField::DATE_RANGE_X_DAILY_DATE_RANGE_ID;
    protected $entityClass = null;
    protected $table = 'date_range_x_daily_date_range';
    protected $table_alias = 'drxddr';

    public static $fields = [
        DBField::DATE_RANGE_X_DAILY_DATE_RANGE_ID,
        DBField::DATE_RANGE_ID,
        DBField::DAY_DATE_RANGE_ID,
    ];
}

class DateRangesTypesManager extends BaseEntityManager {

    protected $pk = DBField::DATE_RANGE_TYPE_ID;
    protected $entityClass = null;
    protected $table = 'date_range_type';
    protected $table_alias = 'drt';

    const ID_DAILY = 1;
    const ID_WEEKLY = 2;
    const ID_MONTHLY = 3;
    const ID_YEARLY = 4;
    const ID_ALL_TIME = 5;

    public static $fields = [
        DBField::DATE_RANGE_TYPE_ID,
        DBField::DISPLAY_NAME,
    ];

}

class KpiSummariesManager extends BaseEntityManager {

    protected $pk = DBField::KPI_SUMMARY_ID;
    protected $entityClass = null;
    protected $table = Table::KpiSummary;
    protected $table_alias = TableAlias::KpiSummary;

    public static $fields = [
        DBField::KPI_SUMMARY_ID,
        DBField::DATE_RANGE_ID,
        DBField::KPI_SUMMARY_TYPE_ID,
        DBField::VAL_INT,
        DBField::VAL_FLOAT,
        DBField::VAL_CURRENCY,
    ];

    protected $foreign_managers = [
        DateRangesManager::class => DBField::DATE_RANGE_ID,
        KpiSummariesTypesManager::class => DBField::KPI_SUMMARY_TYPE_ID,
    ];

    /**
     * Truncates the KPI Summary Table
     */
    public function truncateTable()
    {
        DB::inst(SQLN_SITE)->query_write("TRUNCATE TABLE {$this->getTable()};");
    }

    /**
     * @return string
     */
    public function generateKpiProcessingStatusCacheKey()
    {
        return 'bi.kpis.processing-status';
    }

    /**
     * @return string
     */
    public function generateSortedXtdSummariesCacheKey()
    {
        return 'bi.kpis.sorted-xtd-summaries';
    }

    /**
     * @param Request $request
     * @return string|null
     */
    public function getBiProcessingStatus(Request $request)
    {
        try {
            $biProcessingStatus = $request->cache[$this->generateKpiProcessingStatusCacheKey()];
        } catch (CacheEntryNotFound $c) {
            $biProcessingStatus = null;
        }

        return $biProcessingStatus;
    }

    /**
     * @param Request $request
     */
    public function regenerateSummaries(Request $request, $regenType = 'auto')
    {
        $varsManager = $request->managers->vars();
        $dateRangesManager = $request->managers->dateRanges();
        $hostInstancesManager = $request->managers->hostsInstances();
        $gameInstancesManager = $request->managers->gamesInstances();
        $gameInstanceRoundPlayersManager = $request->managers->gamesInstancesRoundsPlayers();
        $gameLicensesManager = $request->managers->gameLicenses();
        $usersManager = $request->managers->users();
        $guestsManager = $request->managers->guestTracking();
        $sessionsManager = $request->managers->sessionTracking();

        $requestsManager = $request->managers->requests();

        $startTime = microtime(true);

        $request->cache->set($this->generateKpiProcessingStatusCacheKey(), '1', ONE_HOUR);

        $this->truncateTable();

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_HOST_LOCATIONS,
            $hostInstancesManager->summarizeUniqueHostLocations($request, DateRangesTypesManager::ID_DAILY),
            $hostInstancesManager->summarizeUniqueHostLocations($request, DateRangesTypesManager::ID_WEEKLY),
            $hostInstancesManager->summarizeUniqueHostLocations($request, DateRangesTypesManager::ID_MONTHLY),
            $hostInstancesManager->summarizeUniqueHostLocations($request, DateRangesTypesManager::ID_YEARLY),
            $hostInstancesManager->summarizeUniqueHostLocations($request, DateRangesTypesManager::ID_ALL_TIME)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_GAME_LICENSES,
            $dateRangesManager->summarizeCountDaily($request, $gameLicensesManager, DBField::CREATE_TIME, null),
            $dateRangesManager->summarizeCountWeekly($request, $gameLicensesManager, DBField::CREATE_TIME, null),
            $dateRangesManager->summarizeCountMonthly($request, $gameLicensesManager, DBField::CREATE_TIME, null),
            $dateRangesManager->summarizeCountYearly($request, $gameLicensesManager, DBField::CREATE_TIME, null),
            $dateRangesManager->summarizeCountAllTime($request, $gameLicensesManager, DBField::CREATE_TIME, null)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_HOST_INSTANCES,
            $dateRangesManager->summarizeCountDaily($request, $hostInstancesManager),
            $dateRangesManager->summarizeCountWeekly($request, $hostInstancesManager),
            $dateRangesManager->summarizeCountMonthly($request, $hostInstancesManager),
            $dateRangesManager->summarizeCountYearly($request, $hostInstancesManager),
            $dateRangesManager->summarizeCountAllTime($request, $hostInstancesManager)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_GAME_INSTANCES,
            $dateRangesManager->summarizeCountDaily($request, $gameInstancesManager),
            $dateRangesManager->summarizeCountWeekly($request, $gameInstancesManager),
            $dateRangesManager->summarizeCountMonthly($request, $gameInstancesManager),
            $dateRangesManager->summarizeCountYearly($request, $gameInstancesManager),
            $dateRangesManager->summarizeCountAllTime($request, $gameInstancesManager)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_PLAYERS,
            $dateRangesManager->summarizeCountDaily($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeCountWeekly($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeCountMonthly($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeCountYearly($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeCountAllTime($request, $gameInstanceRoundPlayersManager)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_GAME_INSTANCE_HOURS,
            $dateRangesManager->summarizeSumTimeDaily($request, $gameInstancesManager),
            $dateRangesManager->summarizeSumTimeWeekly($request, $gameInstancesManager),
            $dateRangesManager->summarizeSumTimeMonthly($request, $gameInstancesManager),
            $dateRangesManager->summarizeSumTimeYearly($request, $gameInstancesManager),
            $dateRangesManager->summarizeSumTimeAllTime($request, $gameInstancesManager)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_PLAYER_HOURS,
            $dateRangesManager->summarizeSumTimeDaily($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeSumTimeWeekly($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeSumTimeMonthly($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeSumTimeYearly($request, $gameInstanceRoundPlayersManager),
            $dateRangesManager->summarizeSumTimeAllTime($request, $gameInstanceRoundPlayersManager)
        );

        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_NEW_USERS,
            $dateRangesManager->summarizeCountDaily($request, $usersManager, DBField::JOIN_DATE, null),
            $dateRangesManager->summarizeCountWeekly($request, $usersManager, DBField::JOIN_DATE, null),
            $dateRangesManager->summarizeCountMonthly($request, $usersManager, DBField::JOIN_DATE, null),
            $dateRangesManager->summarizeCountYearly($request, $usersManager, DBField::JOIN_DATE, null),
            $dateRangesManager->summarizeCountAllTime($request, $usersManager, DBField::JOIN_DATE, null)
        );

        $guestExtraFilter = $guestsManager->filters->NotEq(DBField::HTTP_USER_AGENT, $guestsManager->getExcludedKpiUserAgents());
        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_GUESTS,
            $dateRangesManager->summarizeCountDaily($request, $guestsManager, DBField::CREATE_TIME, null, $guestExtraFilter),
            $dateRangesManager->summarizeCountWeekly($request, $guestsManager, DBField::CREATE_TIME, null, $guestExtraFilter),
            $dateRangesManager->summarizeCountMonthly($request, $guestsManager, DBField::CREATE_TIME, null, $guestExtraFilter),
            $dateRangesManager->summarizeCountYearly($request, $guestsManager, DBField::CREATE_TIME, null, $guestExtraFilter),
            $dateRangesManager->summarizeCountAllTime($request, $guestsManager, DBField::CREATE_TIME, null, $guestExtraFilter)
        );

        $sessionExtraFilter = $sessionsManager->filters->NotEq(DBField::HTTP_USER_AGENT, $guestsManager->getExcludedKpiUserAgents());
        $this->storeSummaries(
            $request,
            KpiSummariesTypesManager::ID_SESSIONS,
            $dateRangesManager->summarizeCountDaily($request, $sessionsManager, DBField::CREATE_TIME, null, $sessionExtraFilter),
            $dateRangesManager->summarizeCountWeekly($request, $sessionsManager, DBField::CREATE_TIME, null, $sessionExtraFilter),
            $dateRangesManager->summarizeCountMonthly($request, $sessionsManager, DBField::CREATE_TIME, null, $sessionExtraFilter),
            $dateRangesManager->summarizeCountYearly($request, $sessionsManager, DBField::CREATE_TIME, null, $sessionExtraFilter),
            $dateRangesManager->summarizeCountAllTime($request, $sessionsManager, DBField::CREATE_TIME, null, $sessionExtraFilter)
        );

        $dailyRequestsSummary = $requestsManager->summarizeAppRequestsByDateRangeTypeId($request, DateRangesTypesManager::ID_DAILY);
        $weeklyRequestsSummary = $requestsManager->summarizeAppRequestsByDateRangeTypeId($request, DateRangesTypesManager::ID_WEEKLY);
        $monthlyRequestsSummary = $requestsManager->summarizeAppRequestsByDateRangeTypeId($request, DateRangesTypesManager::ID_MONTHLY);
        $yearlyRequestsSummary = $requestsManager->summarizeAppRequestsByDateRangeTypeId($request, DateRangesTypesManager::ID_YEARLY);
        $allTimeRequestsSummary = $requestsManager->summarizeAppRequestsByDateRangeTypeId($request, DateRangesTypesManager::ID_ALL_TIME);

        if ($dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW]) {
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_WWW,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW]
            );
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_WWW_MS,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW_MS],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW_MS],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW_MS],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW_MS],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_WWW_MS]
            );
        }

        if ($dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY]) {
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_PLAY,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY]
            );
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_PLAY_MS]
            );
        }

        if ($dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API]) {
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_API,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API]
            );
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_API_MS,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API_MS],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API_MS],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API_MS],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API_MS],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_API_MS]
            );
        }

        if ($dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES]) {
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_IMAGES,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES]
            );

            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_IMAGES_MS]
            );
        }

        if ($dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO]) {
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_GO,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO]
            );

            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_GO_MS,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO_MS],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO_MS],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO_MS],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO_MS],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_GO_MS]
            );
        }

        if ($dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP]) {
            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_DEVELOP,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP]
            );

            $this->storeSummaries(
                $request,
                KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS,
                $dailyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS],
                $weeklyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS],
                $monthlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS],
                $yearlyRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS],
                $allTimeRequestsSummary[KpiSummariesTypesManager::ID_REQUESTS_DEVELOP_MS]
            );
        }


        $durationMs = get_milliseconds_elapsed($startTime, false, 0);

        $varsManager->createUpdateVarKey($request, VarsManager::KEY_KPI_REGEN, $request->getCurrentSqlTime());
        $varsManager->createUpdateVarKey($request, VarsManager::KEY_KPI_REGEN_TYPE, $regenType);
        $varsManager->createUpdateVarKey($request, VarsManager::KEY_KPI_REGEN_DURATION_MS, $durationMs);

        $request->cache->delete($this->generateKpiProcessingStatusCacheKey());
        $request->cache->delete($this->generateSortedXtdSummariesCacheKey());
    }

    /**
     * @param Request $request
     * @param $kpiSummaryTypeId
     * @param array[] ...$sources
     */
    public function storeSummaries(Request $request, $kpiSummaryTypeId, ...$sources)
    {
        $dateRangesManager = $request->managers->dateRanges();
        $kpiSummariesTypesManager = $request->managers->kpiSummariesTypes();

        $kpiSummaryType = $kpiSummariesTypesManager->getKpiSummaryTypeById($request, $kpiSummaryTypeId);

        $summaryDbData = [];

        foreach ($sources as $summaries) {
            foreach ($summaries as $summary) {
                $value = $summary[$dateRangesManager->getSumField()] ? $summary[$dateRangesManager->getSumField()] : 0;
                $summaryDbData[] = [
                    DBField::DATE_RANGE_ID => $summary[$dateRangesManager->getPkField()],
                    DBField::KPI_SUMMARY_TYPE_ID => $kpiSummaryType->getPk(),
                    $kpiSummaryType->getSummaryField() => $value
                ];
            }
        }

        $this->query($request->db)->add_multiple($summaryDbData);
    }

    /**
     * @param Request $request
     * @param string $summaryField
     * @param null $dateRangeTypeId
     * @return SqlQuery
     */
    protected function queryJoinDateRanges(Request $request, $dateRangeTypeId = null, $summaryField = DBField::VAL_INT)
    {
        $dateRangesManager = $request->managers->dateRanges();

        $fields = [
            $dateRangesManager->field(DBField::START_TIME),
            $this->aliasField($summaryField, DBField::VALUE),
        ];

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($dateRangesManager)
            ->filter($dateRangesManager->filters->byDateRangeTypeId($dateRangeTypeId));
    }


    /**
     * @param Request $request
     * @param DateTime|null $before
     * @return array
     */
    public function getAllPeriodToDateSummaries(Request $request, DateTime $before = null)
    {
        if (!$before) {
            try {
                $sortedSummaries = $request->cache[$this->generateSortedXtdSummariesCacheKey()];
            } catch (CacheEntryNotFound $c) {
                $sortedSummaries = $this->fetchPeriodToDateSummaries($request);
                $c->set($sortedSummaries, ONE_HOUR);
            }
        } else {
            $sortedSummaries = $this->fetchPeriodToDateSummaries($request, $before);
        }

        return $sortedSummaries;
    }

    /**
     * @param Request $request
     * @param null $before
     * @return array
     */
    protected function fetchPeriodToDateSummaries(Request $request, DateTime $before = null)
    {
        $connection = $request->db->get_connection();

        if (!$before) {
            $wtdDt = new DateTime();
            $mtdDt = new DateTime();
            $ytdDt = new DateTime();
            $ytdDate = date('Y');
        } else {
            $beforeStart = clone $before;
            $beforeStart->modify("-1 minute");

            $wtdDt = new DateTime($beforeStart->format(SQL_DATETIME));
            $mtdDt = new DateTime($beforeStart->format(SQL_DATETIME));
            $ytdDt = new DateTime($beforeStart->format(SQL_DATETIME));

            $ytdDate = date('Y', strtotime($beforeStart->format(SQL_DATETIME)));
        }

        $week_start = get_start_of_week_date($wtdDt)->format(SQL_DATE);
        $wtdSummaries = $this->getPeriodToDateSummaries($request, $connection, $week_start, DateRangesTypesManager::ID_WEEKLY, $before);

        $mtdDt->modify("first day of this month");
        $mtdSummaries = $this->getPeriodToDateSummaries($request, $connection, $mtdDt->format(SQL_DATE), DateRangesTypesManager::ID_MONTHLY, $before);

        $ytdDt->modify('first day of January ' . $ytdDate);
        $ytdSummaries = $this->getPeriodToDateSummaries($request, $connection, $ytdDt->format(SQL_DATE), DateRangesTypesManager::ID_YEARLY, $before);

        $sortedSummaries = [];

        $floatSlugs = ['game-instance-hours', 'player-hours'];

        $value = 0;
        $slug = null;

        foreach ($wtdSummaries as $kpiSummary) {
            $slug = slugify($kpiSummary['kpi_type']);
            $value = $kpiSummary['value'];

            if (in_array($slug, $floatSlugs)) {
                $value = ((intval($value) / 60) / 60);
            }
            $sortedSummaries[$slug]['name'] = $kpiSummary['kpi_type'];
            $sortedSummaries[$slug]['wtd'] = $value;
        }

        $value = 0;
        $slug = null;

        foreach ($mtdSummaries as $kpiSummary) {
            $slug = slugify($kpiSummary['kpi_type']);
            $value = $kpiSummary['value'];

            if (in_array($slug, $floatSlugs)) {
                $value = ((intval($value) / 60) / 60);
            }

            $sortedSummaries[$slug]['mtd'] = $value;
        }

        $value = 0;
        $slug = null;

        foreach ($ytdSummaries as $kpiSummary) {
            $slug = slugify($kpiSummary['kpi_type']);
            $value = $kpiSummary['value'];

            if (in_array($slug, $floatSlugs)) {
                $value = ((intval($value) / 60) / 60);
            }

            $sortedSummaries[$slug]['ytd'] = $value;
        }

        $value = 0;
        $slug = null;


        $allTimeSummaries = $this->getAllTimeSummaries($request, $connection, $before);
        foreach ($allTimeSummaries as $allTimeSummary) {
            $slug = slugify($allTimeSummary['kpi_type']);
            $value = $allTimeSummary['value'];

            if (in_array($slug, $floatSlugs)) {
                $value = ((intval($value) / 60) / 60);
            }
            $sortedSummaries[$slug]['all'] = $value;
        }

        return $sortedSummaries;
    }


    /**
     * @param Request $request
     * @param DBBackend $connection
     * @param $startDate
     * @param $dateRangeTypeId
     * @param DateTime $before
     * @return array
     */
    public function getPeriodToDateSummaries(Request $request, DBBackend $connection, $startDate, $dateRangeTypeId, DateTime $before = null)
    {
        $beforeString = "";

        if ($before)
            $beforeString = "and dr.start_time < {$connection->quote_value($before->format(SQL_DATE))}";

        $sql = "
            select 
              kst.kpi_summary_type_id,
              kst.display_name as kpi_type, 
              sum(coalesce(ks.val_int, ks.val_float, ks.val_currency)) as value
            from kpi_summary ks
            join kpi_summary_type kst
              on kst.kpi_summary_type_id = ks.kpi_summary_type_id
            join date_range dr
              on ks.date_range_id = dr.date_range_id
            where 
              dr.start_time >= {$connection->quote_value($startDate)}
              {$beforeString}
              and dr.date_range_type_id = {$connection->quote_value($dateRangeTypeId)}
              and kst.is_active = 1
              and kst.display_in_dashboard = 1
            group by 1
            order by 1 asc;
        ";

        $kpiSummaries = $this->query($request->db)
            ->set_connection($connection)
            ->sql($sql);

        return $kpiSummaries;
    }

    /**
     * @param Request $request
     * @param DBBackend $connection
     * @param $startDate
     * @param $dateRangeTypeId
     * @return array
     */
    protected function getAllTimeSummaries(Request $request, DBBackend $connection, DateTime $before = null)
    {
        $dateRangeTypeId = DateRangesTypesManager::ID_ALL_TIME;

        $beforeString = '';
        if ($before)
            $beforeString = "and dr.start_time < {$connection->quote_value($before->format(SQL_DATETIME))}";

        $sql = "
            select 
              kst.display_name as kpi_type, 
              sum(coalesce(ks.val_int, ks.val_float, ks.val_currency)) as value
            from kpi_summary ks
            join kpi_summary_type kst
              on kst.kpi_summary_type_id = ks.kpi_summary_type_id
            join date_range dr
              on ks.date_range_id = dr.date_range_id
            where 
              dr.start_time >= {$connection->quote_value('2018-07-01')}
              {$beforeString}
              and dr.date_range_type_id = {$connection->quote_value($dateRangeTypeId)}
              and kst.is_active = 1
              and kst.display_in_dashboard = 1
            group by 1
            order by 1 asc;
        ";

        $kpiSummaries = $this->query($request->db)
            ->set_connection($connection)
            ->sql($sql);

        return $kpiSummaries;
    }


    /**
     * @param Request $request
     * @param $kpiSummaryTypeId
     * @param $dateRangeTypeId
     * @param $startTime
     * @return array
     */
    public function getSummariesByTypeAndInterval(Request $request, $kpiSummaryTypeId, $dateRangeTypeId, $startTime)
    {
        return $this->queryJoinDateRanges($request, $dateRangeTypeId)
            ->filter($this->filters->byKpiSummaryTypeId($kpiSummaryTypeId))
            ->filter($this->filters->Gte(DBField::START_TIME, $startTime))
            ->sort_asc($this->field(DBField::START_TIME))
            ->get_objects($request);
    }

}

class KpiSummariesTypesManager extends BaseEntityManager {

    protected $pk = DBField::KPI_SUMMARY_TYPE_ID;
    protected $entityClass = KpiSummaryTypeEntity::class;
    protected $table = Table::KpiSummaryType;
    protected $table_alias = TableAlias::KpiSummaryType;

    /** @var KpiSummaryTypeEntity[] $kpiSummaryTypes */
    protected $kpiSummaryTypes = [];

    const ID_HOST_LOCATIONS = 1;
    const ID_GAME_LICENSES = 2;
    const ID_HOST_INSTANCES = 3;
    const ID_GAME_INSTANCES = 4;
    const ID_PLAYERS = 5;
    const ID_GAME_INSTANCE_HOURS = 6;
    const ID_PLAYER_HOURS = 7;
    const ID_AVG_PLAYERS_HOST_INSTANCE = 8;
    const ID_AVG_PLAYERS_GAME_INSTANCE = 9;
    const ID_NEW_USERS = 10;
    const ID_UNIQUE_GAME_PLAYERS = 11;
    const ID_GUESTS = 12;
    const ID_SESSIONS = 13;

    const ID_REQUESTS_WWW = 14;
    const ID_REQUESTS_PLAY = 15;
    const ID_REQUESTS_API = 16;
    const ID_REQUESTS_IMAGES = 17;
    const ID_REQUESTS_GO = 18;
    const ID_REQUESTS_DEVELOP = 19;

    const ID_REQUESTS_WWW_MS = 20;
    const ID_REQUESTS_PLAY_MS = 21;
    const ID_REQUESTS_API_MS = 22;
    const ID_REQUESTS_IMAGES_MS = 23;
    const ID_REQUESTS_GO_MS = 24;
    const ID_REQUESTS_DEVELOP_MS = 25;


    public static $fields = [
        DBField::KPI_SUMMARY_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::SUMMARY_FIELD,
        DBField::DISPLAY_IN_DASHBOARD,
        DBField::IS_ACTIVE
    ];

    public function getAllKpiSummaryTypes(Request $request)
    {
        if (!$this->kpiSummaryTypes) {
            /** @var KpiSummaryTypeEntity[] $kpiSummaryTypes */
            $kpiSummaryTypes = $this->query($request->db)->get_entities($request);

            $this->kpiSummaryTypes = array_index($kpiSummaryTypes, $this->getPkField());
        }

        return $this->kpiSummaryTypes;
    }

    /**
     * @param Request $request
     * @param $kpiSummaryTypeId
     * @return KpiSummaryTypeEntity
     */
    public function getKpiSummaryTypeById(Request $request, $kpiSummaryTypeId)
    {
        return $this->getAllKpiSummaryTypes($request)[$kpiSummaryTypeId];
    }
}