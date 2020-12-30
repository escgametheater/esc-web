<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:18 PM
 */

class PayoutStatusEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields;
}

class PayoutServiceEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasMinimumPayoutAmountField,
        hasIsPublicField,
        hasIsActiveField,
        hasAuditFields;

    /** @var  PayoutsServicesManager */
    protected $manager;

    /**
     * @return PayoutServiceInterface
     */
    public function getPayoutServiceHandler()
    {
        return $this->manager->getPayoutServiceHandlerForPayoutService($this);
    }
}