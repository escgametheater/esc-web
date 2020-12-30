<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 6:28 PM
 */

require "../../core/domain/controllers/base.php";

class Main extends BaseContent {

    protected $url_key = 1;

    protected $pages = [];

    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return HtmlResponse|HttpResponseRedirect
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        $shortUrlsManager = $request->managers->shortUrls();

        $slug = $request->getSlugForEntity($this->url_key);

        if (!$slug) {
            return $this->redirect($request->getWwwUrl("?e=noslug"));
        }

        $shortUrl = $shortUrlsManager->getShortUrlBySlug($request, $slug);

        if ($shortUrl) {

            $shortUrlsManager->incrementShortUrlViewCount($request, $shortUrl);

            return $this->redirect($shortUrl->getTargetUrl());

        } else {
            return $this->render_404($request);
        }

    }
}