<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/5/18
 * Time: 4:06 PM
 */

Entities::uses('applications');

class ApplicationsManager extends BaseEntityManager {

    const ID_ESC_API = 1;

    protected $entityClass = ApplicationEntity::class;
    protected $table = Table::Application;
    protected $table_alias = TableAlias::Application;
    protected $pk = DBField::APPLICATION_ID;

    protected $foreign_managers = [];

    public static $fields = [
        DBField::APPLICATION_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }
}

class ApplicationsUsersManager extends BaseEntityManager {

    protected $entityClass = ApplicationUserEntity::class;
    protected $table = Table::ApplicationUser;
    protected $table_alias = TableAlias::ApplicationUser;
    protected $pk = DBField::APPLICATION_USER_ID;

    protected $foreign_managers = [
        ApplicationsManager::class => DBField::APPLICATION_ID
    ];

    public static $fields = [
        DBField::APPLICATION_USER_ID,
        DBField::APPLICATION_ID,
        DBField::USER_ID,
        DBField::API_KEY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @param Request $request
     * @param $token
     * @return array|ApplicationUserEntity
     */
    public function getApplicationUserByToken(Request $request, $token)
    {
        $applicationsManager = $request->managers->applications();

        return $this->query($request->db)
            ->inner_join($applicationsManager)
            ->filter($applicationsManager->filters->isActive())
            ->filter($this->filters->byToken($token))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $applicationId
     * @param $userId
     * @return array|ApplicationUserEntity
     */
    public function getApplicationUser(Request $request, $applicationId, $userId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byApplicationId($applicationId))
            ->filter($this->filters->byUserId($userId))
            ->get_entity($request);
    }

    /**
     * @param $token
     * @return bool
     */
    public function checkApiKeyIsInUse($apiKey)
    {
        return $this->query()
            ->filter($this->filters->byApiKey($apiKey))
            ->exists();
    }

    /**
     * @param Request $request
     * @param $applicationId
     * @param $userId
     * @return ApplicationUserEntity
     */
    public function createNewApplicationUser(Request $request, $applicationId, $userId)
    {
        $apiKey = generate_random_string(32);
        $apiKeyIsInUse = $this->checkApiKeyIsInUse($apiKey);

        while ($apiKeyIsInUse) {
            $apiKey = generate_random_string(32);
            $apiKeyIsInUse = $this->checkApiKeyIsInUse($apiKey);
        }

        $data = [
            DBField::APPLICATION_ID => $applicationId,
            DBField::USER_ID => $userId,
            DBField::API_KEY => $apiKey,
            DBField::IS_ACTIVE => 1
        ];

        /** @var ApplicationUserEntity $applicationUser */
        $applicationUser = $this->query($request->db)->createNewEntity($request, $data);

        return $applicationUser;
    }

}


class ApplicationsUsersAccessTokensManager extends BaseEntityManager
{
    protected $entityClass = ApplicationUserAccessTokenEntity::class;
    protected $table = Table::ApplicationUserAccessToken;
    protected $table_alias = TableAlias::ApplicationUserAccessToken;
    protected $pk = DBField::APPLICATION_USER_ACCESS_TOKEN_ID;

    protected $foreign_managers = [
        ApplicationsUsersManager::class => DBField::APPLICATION_USER_ID
    ];

    public static $fields = [
        DBField::APPLICATION_USER_ACCESS_TOKEN_ID,
        DBField::APPLICATION_USER_ID,
        DBField::USER_ID,
        DBField::TOKEN,
        DBField::EXPIRES_ON,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::USER, []);
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinApplicationsUsers(Request $request)
    {
        $applicationsManager = $request->managers->applications();
        $applicationsUsersManager = $request->managers->applicationsUsers();

        $joinApplicationsFilter = $applicationsUsersManager->filters->byApplicationId($applicationsManager->createPkField());

        return $this->query($request->db)
            ->inner_join($applicationsUsersManager)
            ->inner_join($applicationsManager, $joinApplicationsFilter);
    }

    /**
     * @param Request $request
     * @param $applicationId
     * @param $userId
     * @return array|ApplicationUserAccessTokenEntity
     */
    public function getActiveAccessTokenForUserByApplicationId(Request $request, $applicationId, $userId)
    {
        $applicationsManager = $request->managers->applications();
        $applicationsUsersManager = $request->managers->applicationsUsers();

        $expiresFilter = $this->filters->Or_(
            $this->filters->IsNull(DBField::EXPIRES_ON),
            $this->filters->Gt(DBField::EXPIRES_ON, $request->getCurrentSqlTime())
        );

        return $this->queryJoinApplicationsUsers($request)
            ->filter($applicationsUsersManager->filters->isActive())
            ->filter($applicationsManager->filters->byPk($applicationId))
            ->filter($applicationsManager->filters->isActive())
            ->filter($expiresFilter)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $applicationUserAccessTokenId
     * @return array|ApplicationUserAccessTokenEntity
     */
    public function getApplicationUserAccessTokenById(Request $request, $applicationUserAccessTokenId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($applicationUserAccessTokenId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $token
     * @return array|ApplicationUserAccessTokenEntity
     */
    public function getApplicationUserAccessTokenByToken(Request $request, $applicationId, $token)
    {
        $applicationsManager = $request->managers->applications();
        $applicationsUsersManager = $request->managers->applicationsUsers();


        $expiresFilter = $this->filters->Or_(
            $this->filters->IsNull(DBField::EXPIRES_ON),
            $this->filters->Gt(DBField::EXPIRES_ON, $request->getCurrentSqlTime())
        );

        $applicationUserAccessToken = $this->queryJoinApplicationsUsers($request)
            ->filter($applicationsUsersManager->filters->isActive())
            ->filter($applicationsManager->filters->byPk($applicationId))
            ->filter($applicationsManager->filters->isActive())
            //->cache("esc.access-tokens.{$applicationId}.{$token}", ONE_HOUR)
            ->filter($expiresFilter)
            ->filter($this->filters->byToken($token))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        return $applicationUserAccessToken;
    }

    /**
     * @param $token
     * @return bool
     */
    public function checkTokenExists($token)
    {
        return $this->query()
            ->filter($this->filters->byToken($token))
            ->exists();
    }

    /**
     * @param Request $request
     * @param ApplicationUserEntity $applicationUser
     * @param null $expires
     * @return ApplicationUserAccessTokenEntity
     */
    public function createNewAccessToken(Request $request, ApplicationUserEntity $applicationUser, $expires = null)
    {
        $token = generate_random_string(32);

        $tokenExists = $this->checkTokenExists($token);

        while ($tokenExists) {
            $token = generate_random_string(32);
            $tokenExists = $this->checkTokenExists($token);
        }

        $data = [
            DBField::APPLICATION_USER_ID => $applicationUser->getPk(),
            DBField::USER_ID => $applicationUser->getUserId(),
            DBField::TOKEN => $token,
            DBField::EXPIRES_ON => $expires,
            DBField::IS_ACTIVE => 1
        ];

        /** @var ApplicationUserAccessTokenEntity $accessToken */
        $accessToken = $this->query($request->db)->createNewEntity($request, $data);

        return $accessToken;
    }

    /**
     * @param Request $request
     * @param $applicationId
     * @param $userId
     */
    public function deactivateAllTokensForUserApplication(Request $request, $applicationId, $userId)
    {
        $applicationsUsersManager = $request->managers->applicationsUsers();

        $updatedData = [
            DBField::IS_ACTIVE => 0,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::DELETED_BY => $request->requestId
        ];

        $this->queryJoinApplicationsUsers($request)
            ->filter($applicationsUsersManager->filters->byApplicationId($applicationId))
            ->filter($this->filters->byUserId($userId))
            ->update($updatedData);
    }
}