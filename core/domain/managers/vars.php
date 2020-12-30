<?php
/**
 * Settings Manager
 *
 * @package managers
 */

class VarsManager extends BaseEntityManager
{
    protected $entityClass = VarEntity::class;
    protected static $right_required = 'admin';
    protected $table = Table::Vars;
    protected $table_alias = TableAlias::Vars;
    protected $root = '/admin/';
    protected $pk = DBField::NAME;

    const KEY_KPI_REGEN = 'kpi_regen';
    const KEY_KPI_REGEN_TYPE = 'kpi_regen_type';
    const KEY_KPI_REGEN_DURATION_MS = 'kpi_regen_duration_ms';

    public static $fields = [
        DBField::NAME,
        DBField::VALUE
    ];

    /**
     * @param Request $request
     * @param $varKey
     * @return array|VarEntity
     */
    public function getVarByKey(Request $request, $varKey)
    {
        return $this->query($request->db)
            ->filter($this->filters->byName($varKey))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $varKey
     * @param $value
     * @return VarEntity
     */
    public function createNewVar(Request $request, $varKey, $value)
    {
        $data = [
            DBField::NAME => $varKey,
            DBField::VALUE => $value
        ];

        /** @var VarEntity $var */
        $var = $this->query($request->db)->createNewEntity($request, $data, false);

        return $var;
    }

    /**
     * @param Request $request
     * @param $varKey
     * @param $newValue
     * @return VarEntity
     */
    public function createUpdateVarKey(Request $request, $varKey, $newValue)
    {
        if (!$var = $this->getVarByKey($request, $varKey)) {

            $var = $this->createNewVar($request, $varKey, $newValue);

        } else {
            $var->updateField(DBField::VALUE, $newValue)->saveEntityToDb($request);
        }

        return $var;
    }
}