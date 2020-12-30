<?php

class ElasticSearchClient
{
    public $client;

    public function __construct($config)
    {
        $this->client = new Elastica\Client($config['servers']);
    }

    public function search()
    {
        return new Elastica\Search($this->client);
    }

    public function query()
    {
        return new Elastica\Query();
    }

    public function queryBuilder()
    {
        return new Elastica\QueryBuilder();
    }
    public function makeDocument($data = [], $id = '') {
        return new \Elastica\Document('', $data);
    }
}

if (Modules::is_loaded('http')) {
    require "middleware.php";
    Http::register_middleware(new ElasticSearchMiddleware());
}

