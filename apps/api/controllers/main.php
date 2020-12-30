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

    protected $pages = [
        '' => 'handle_index',
        'v1' => 'handle_v1',
    ];

    /**
     * @param Request $request
     * @return HttpResponse
     */
    protected function handle_index(Request $request)
    {
        $viewData = [
            TemplateVars::PAGE_DESCRIPTION => 'ESC API Index Description',
            TemplateVars::PAGE_TITLE => 'ESC API Index Title',
            TemplateVars::PAGE_IDENTIFIER => 'homepage',
            TemplateVars::PAGE_CANONICAL => '/',
            TemplateVars::PAGE_OG_IMAGE => '',
        ];

        // $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->renderPageResponse($request, $viewData, "index.twig");
    }
}