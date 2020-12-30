<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/26/18
 * Time: 11:37 AM
 */

Entities::uses('urls');

class ShortUrlsManager extends BaseEntityManager
{
    protected $entityClass = ShortUrlEntity::class;
    protected $pk = DBField::SHORT_URL_ID;
    protected $table = Table::ShortUrl;
    protected $table_alias = TableAlias::ShortUrl;

    public static $fields = [
        DBField::SHORT_URL_ID,
        DBField::SLUG,
        DBField::SCHEME,
        DBField::HOST,
        DBField::URI,
        DBField::PARAMS,
        DBField::ACQ_MEDIUM,
        DBField::ACQ_SOURCE,
        DBField::ACQ_CAMPAIGN,
        DBField::ACQ_TERM,
        DBField::TOTAL_VIEWS,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param ShortUrlEntity $data
     * @param Request $request
     * @return ShortUrlEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        // Target Url Construction
        $url = "{$data->getScheme()}://{$data->getHost()}{$data->getUri()}";

        if ($params = json_decode($data->getParams(), true))
            $url .= $request->buildQuery($params);

        $data->updateField(VField::TARGET_URL, $url);

        // Short Url Construction
        $urlHost = $request->config['hosts']['go'];
        $url = "{$request->scheme}://{$urlHost}/{$data->getSlug()}";
        $data->updateField(VField::URL, $url);

        return $data;
    }

    /**
     * @param $slug
     * @return bool
     */
    public function checkSlugExists($slug)
    {
        return $this->query()
            ->filter($this->filters->bySlug($slug))
            ->exists();
    }

    /**
     * @param $targetUrl
     * @return bool
     */
    public function checkIsValidUrlForShortener($targetUrl)
    {
        $urlParts = parse_url($targetUrl);

        if (!isset($urlParts[Request::SCHEME]))
            return false;

        if (!isset($urlParts[Request::HOST]))
            return false;

        return true;
    }

    /**
     * @param Request $request
     * @param $targetUrl
     * @param null $slug
     * @return ShortUrlEntity
     */
    public function createNewShortUrl(Request $request, $targetUrl, $slug = null)
    {
        if (!$slug) {
            $slug = generate_random_string(6);

            $exists = $this->checkSlugExists($slug);

            while ($exists) {
                $slug = generate_random_string(6);
                $exists = $this->checkSlugExists($slug);
            }
        }

        $urlParts = parse_url($targetUrl);

        $scheme = isset($urlParts[Request::SCHEME]) ? $urlParts[Request::SCHEME] : 'https';
        $host = isset($urlParts[Request::HOST]) ? $urlParts[Request::HOST] : '';
        $uri = isset($urlParts[Request::PATH]) ? $urlParts[Request::PATH] : '/';

        $params = [];
        if (isset($urlParts[Request::QUERY]))
            parse_str($urlParts[Request::QUERY], $params);

        $acqMedium = isset($params[GetRequest::PARAM_UTM_MEDIUM]) ? $params[GetRequest::PARAM_UTM_MEDIUM] : null;
        $acqSource = isset($params[GetRequest::PARAM_UTM_SOURCE]) ? $params[GetRequest::PARAM_UTM_SOURCE] : null;
        $acqCampaign = isset($params[GetRequest::PARAM_UTM_CAMPAIGN]) ? $params[GetRequest::PARAM_UTM_CAMPAIGN] : null;
        $acqTerm = isset($params[GetRequest::PARAM_UTM_TERM]) ? $params[GetRequest::PARAM_UTM_TERM] : null;

        $data = [
            DBField::SLUG => $slug,
            DBField::SCHEME => $scheme,
            DBField::HOST => $host,
            DBField::URI => $uri,
            DBField::PARAMS => json_encode($params),
            DBField::ACQ_MEDIUM => $acqMedium,
            DBField::ACQ_SOURCE => $acqSource,
            DBField::ACQ_CAMPAIGN => $acqCampaign,
            DBField::ACQ_TERM => $acqTerm,
            DBField::TOTAL_VIEWS => 0,
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime()
        ];

        /** @var ShortUrlEntity $shortUrl */
        $shortUrl = $this->query($request->db)->createNewEntity($request, $data);

        return $shortUrl;
    }

    /**
     * @param Request $request
     * @param $shortUrlId
     * @return array|ShortUrlEntity
     */
    public function getShortUrlById(Request $request, $shortUrlId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($shortUrlId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $slug
     * @return ShortUrlEntity
     */
    public function getShortUrlBySlug(Request $request, $slug)
    {
        return $this->query($request->db)
            ->filter($this->filters->bySlug($slug))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param ShortUrlEntity $shortUrl
     */
    public function incrementShortUrlViewCount(Request $request, ShortUrlEntity $shortUrl)
    {
        try {
            $this->query($request->db)
                ->filter($this->filters->byPk($shortUrl->getPk()))
                ->increment(DBField::TOTAL_VIEWS);

            $totalViews = (int)$shortUrl->getTotalViews() + 1;
            $shortUrl->updateField(DBField::TOTAL_VIEWS, $totalViews);

        } catch (DBError $e) {

        }
    }
}