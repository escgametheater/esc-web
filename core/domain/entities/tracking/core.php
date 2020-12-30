<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/29/15
 * Time: 4:48 AM
 */


class GuestEntity extends BaseDeviceTrackingEntity {

    use
        hasFirstUserIdField,
        hasCreateTimeField;

    /**
     * @var SessionEntity
     */
    public $session;

    /** @var bool  */
    public $stores_cookies = true;

    /**
     * @param SessionEntity $session
     */
    public function setSessionEntity(SessionEntity $session)
    {
        $this->session = $session;
        $this->dataArray[VField::SESSION] = $session;
        $this->dataArray[DBField::SESSION_ID] = $session[DBField::SESSION_ID];
        $this->dataArray[DBField::SESSION_HASH] = $session[DBField::SESSION_HASH];
    }

    /**
     * @return SessionEntity
     */
    public function getSessionEntity()
    {
        return $this->session;
    }

    /**
     * @param Request $request
     * @param $user_id
     * @return int
     */
    public function updateGuestRecordFirstUserId(Request $request, $user_id)
    {
        $guestTrackingManager = $request->managers->guestTracking();

        $isBot = $guestTrackingManager->getIsBot($this->getDBField(DBField::HTTP_USER_AGENT), $request->getRealIp());
        $table = $guestTrackingManager->getTableByIsBot($isBot);

        $this->dataArray[DBField::FIRST_USER_ID] = $user_id;

        $request->cache->set(
            $guestTrackingManager->generateEntityIdCacheKey($this->dataArray[DBField::GUEST_ID], $this->dataArray[DBField::GUEST_HASH]),
            $this->dataArray,
            GuestTrackingManager::GUEST_RECORD_CACHE_TIME
        );

        return $guestTrackingManager->query($request->db)
            ->table($table)
            ->filter(Q::Eq(DBField::GUEST_ID, $this->dataArray[DBField::GUEST_ID]))
            ->update([DBField::FIRST_USER_ID => $user_id]);
    }

}
class SessionEntity extends BaseDeviceTrackingEntity {

    use
        hasSessionIdField,
        hasSessionHashField,
        hasFirstUserIdField,
        hasAcqMediumField,
        hasAcqSourceField,
        hasAcqCampaignField,
        hasAcqTermField,
        hasEtIdField,
        hasCreateTimeField;
}
