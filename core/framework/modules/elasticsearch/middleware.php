<?php
/**
 * Elastic search module middleware
 *
 * @package elasticsearch
 */

class ElasticSearchMiddleware extends Middleware
{
    public function process_request(Request $request)
    {
        $request->es = new ElasticSearchClient($request->config['elastic_search']);
    }
}
