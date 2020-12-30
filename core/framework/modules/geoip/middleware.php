<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 2/21/18
 * Time: 5:19 PM
 */

class GeoIpMapperMiddleware extends Middleware {

    /**
     * @param Request $request
     */
    public function process_request(Request $request)
    {
        $geoIpMapperManager = $request->managers->geoIpMapper();
        $geoRegionsManager = $request->managers->geoRegions();

        $geoIpMapping = $geoIpMapperManager->getGeoIpMappingByIp($request, $request->getRealIp());
        $geoRegion = $geoRegionsManager->getGeoRegionByCountryId($request, $geoIpMapping->getCountryId());

        $request->geoIpMapping = $geoIpMapping;
        $request->geoRegion = $geoRegion;
    }
}