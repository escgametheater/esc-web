<?php
/**
 * Settings Manager
 *
 * @package managers
 */

class SettingsManager extends BaseEntityManager
{
    const GNS_KEY_PREFIX = GNS_ROOT.'.settings';

    protected $entityClass = SettingEntity::class;
    protected static $right_required = 'admin';
    protected $table = Table::Settings;
    protected $table_alias = TableAlias::Settings;
    protected $root = '/admin/';
    protected $pk = DBField::NAME;

    public static $fields = [
        DBField::NAME,
        DBField::VALUE
    ];

    const ETL_PREFIX = 'etl.';

    /**
     * @return string
     */
    public function generateEtlCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.etls';
    }

    /**
     * @param Request $request
     * @param $etl
     */
    public function completeEtl(Request $request, $etl)
    {
        $etlName = self::ETL_PREFIX.$etl;

        $etlSettingData = [
            DBField::NAME => $etlName,
            DBField::VALUE => 1
        ];

        $this->query($request->db)->byPk($etlName)->replace($etlSettingData);

        //$this->bustEtlCache($request);
    }

    /**
     * @param Request $request
     * @param array $excludeEtls
     * @return SettingEntity[]
     */
    public function getAllRunEtls(Request $request)
    {
        /** @var SettingEntity[] $settings */
        $settings =  $this->query($request->db)
            //->local_cache($this->generateEtlCacheKey(), ONE_DAY)
            ->filter($this->filters->StartsWith(DBField::NAME, self::ETL_PREFIX))
            ->get_entities($request);

        return $settings;
    }


    /**
     * @param Request $request
     */
    public function bustEtlCache(Request $request)
    {
        $request->local_cache->delete($this->generateEtlCacheKey());
    }
}
