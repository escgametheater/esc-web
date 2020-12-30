<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/14/18
 * Time: 4:54 PM
 */

Entities::uses('sso');

class SSOServicesManager extends BaseEntityManager
{
    const ID_GOOGLE = 1;
    const ID_FACEBOOK = 2;
    const ID_TWITTER = 3;

    protected $entityClass = SSOServiceEntity::class;
    protected $pk = DBField::SSO_SERVICE_ID;
    protected $table = Table::SSOService;
    protected $table_alias = TableAlias::SSOService;

    const GNS_KEY_PREFIX = GNS_ROOT.'.sso-services';

    public static $fields = [
        DBField::SSO_SERVICE_ID,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return SSOServiceEntity[]
     */
    public function getAllSSOServices(Request $request)
    {
        $ssoServices = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_WEEK)
            ->get_entities($request);

        if ($ssoServices)
            $ssoServices = array_index($ssoServices, $this->getPkField());

        return $ssoServices;
    }

    /**
     * @param Request $request
     * @return SSOServiceEntity[]
     */
    public function getAllActiveSSOServices(Request $request)
    {
        $activeSSOServices = [];

        foreach ($this->getAllSSOServices($request) as $ssoService) {
            if ($ssoService->is_active())
                $activeSSOServices[$ssoService->getPk()] = $ssoService;
        }
        return $activeSSOServices;
    }

    /**
     * @param Request $request
     * @param $ssoServiceId
     * @return SSOServiceEntity
     */
    public function getSsoServiceById(Request $request, $ssoServiceId)
    {
        return $this->getAllSSOServices($request)[$ssoServiceId];
    }
}


class UsersSSOServicesManager extends BaseEntityManager
{
    protected $entityClass = UserSSOServiceEntity::class;
    protected $pk = DBField::USER_SSO_SERVICE_ID;
    protected $table = Table::UserSSOService;
    protected $table_alias = TableAlias::UserSSOService;

    public static $fields = [
        DBField::USER_SSO_SERVICE_ID,
        DBField::SSO_SERVICE_ID,
        DBField::USER_ID,
        DBField::DISPLAY_NAME,
        DBField::SSO_ACCOUNT_ID,
        DBField::SCOPE,
        DBField::TOKEN,
        DBField::EXPIRES_ON,
        DBField::REFRESH_TOKEN,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param $ssoServiceId
     * @param $userId
     * @param null $ssoAccountId
     * @param null $token
     * @param null $expiresOn
     * @param null $scope
     * @param null $refreshToken
     * @return UserSSOServiceEntity
     */
    public function linkSsoServiceToUser(Request $request, $ssoServiceId, $userId, $displayName = null, $ssoAccountId = null, $token = null, $expiresOn = null, $scope = null, $refreshToken = null)
    {
        $data = [
            DBField::SSO_SERVICE_ID => $ssoServiceId,
            DBField::USER_ID => $userId,
            DBField::SSO_ACCOUNT_ID => $ssoAccountId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::TOKEN => $token,
            DBField::EXPIRES_ON => $expiresOn,
            DBField::SCOPE => $scope,
            DBField::REFRESH_TOKEN => $refreshToken,
            DBField::IS_ACTIVE => 1,
        ];

        /** @var UserSSOServiceEntity $userSsoService */
        $userSsoService = $this->query($request->db)->createNewEntity($request, $data);

        return $userSsoService;
    }

    /**
     * @param Request $request
     * @param $ssoServiceId
     * @param $userId
     * @return UserSSOServiceEntity[]
     */
    public function getUserSsoServices(Request $request, $ssoServiceId, $userId)
    {
        return $this->query($request->db)
            ->filter($this->filters->bySsoServiceId($ssoServiceId))
            ->filter($this->filters->byUserId($userId))
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $ssoServiceId
     * @param $userId
     * @param $ssoAccountId
     * @return array|UserSSOServiceEntity
     */
    public function getUserSsoServiceBySsoAccountId(Request $request, $ssoServiceId, $ssoAccountId)
    {
        return $this->query($request->db)
            ->filter($this->filters->bySsoServiceId($ssoServiceId))
            ->filter($this->filters->bySsoAccountId($ssoAccountId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }
}