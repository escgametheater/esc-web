<?php
/**
 * Content default class.
 *
 */
require "../../core/domain/controllers/base.php";

use PhpOffice\PhpSpreadsheet\Reader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class Main extends BaseContent
{
    protected $pages = [
        // Pages
        '' => 'index',
        'docs' => 'docs',
        'search' => 'search',

        // Sitemaps
        'sitemap.xml' => 'sitemap_index',
        'sitemap-root.xml' => 'sitemap_root',
    ];


    /**
     * Homepage.
     * 
     * @param Request $request
     * @return HtmlResponse|HttpResponseRedirect
     */
    protected function index(Request $request)
    {
        $options = [];

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Team Name', 32, true, 'This is the name of your team/organization.'),
            new SelectField(DBField::TYPE, 'Team Type', $options, true, 'Choose your desired team/organization type.')
        ];

        $defaults = [];

        $form = new PostForm($fields, $request, $defaults);

        $viewData = [
            TemplateVars::PAGE_DESCRIPTION => "ESC Developer Portal",
            TemplateVars::PAGE_TITLE => "ESC Developer Portal",
            TemplateVars::PAGE_IDENTIFIER => 'homepage-developer',
            TemplateVars::PAGE_CANONICAL => $request->getDevelopUrl('/'),
            TemplateVars::FORM => $form
        ];

        return $this->setIsMarketingPage()->renderPageResponse($request, $viewData, 'index-develop.twig');
    }

    /**
     * @param Request $request
     * @return JSONResponse
     */
    protected function search(Request $request)
    {
        $organizationsManager = $request->managers->organizations();
        $gamesManager = $request->managers->games();
        $modsManager = $request->managers->gamesMods();

        $query = $request->get->query();

        $results = [];

        if ($query && $request->user->is_authenticated()) {

            $userId = $request->user->id;
            $isOrgAdmin = $request->user->permissions->has(RightsManager::RIGHT_ORGANIZATIONS, Rights::MODERATE);
            $isGameAdmin = $request->user->permissions->has(RightsManager::RIGHT_GAMES, Rights::MODERATE);
            $isModAdmin = $request->user->permissions->has(RightsManager::RIGHT_GAMES, Rights::MODERATE);

            $organizations = [];
            $games = [];
            $mods = [];

            $maxResults = 15;
            $maxOrgResults = 3;
            $maxGameResults = 5;
            $maxModResults = 10;
            $remainingResults = $maxResults;

            $organizations = $organizationsManager->searchAutoCompleteOrganizations(
                $request,
                $query,
                $maxOrgResults,
                $userId,
                $isOrgAdmin
            );

            $remainingResults = $remainingResults - count($organizations);

            $maxGameResults = $remainingResults > $maxGameResults ? $maxGameResults : $remainingResults;
            if ($maxGameResults) {
                $games = $gamesManager->searchAutoCompleteGames(
                    $request,
                    $query,
                    $maxGameResults,
                    $userId,
                    $isGameAdmin
                );
            }

            $remainingResults = $remainingResults - count($games);

            $maxModResults = $remainingResults > $maxModResults ? $remainingResults : $remainingResults;
            if ($maxModResults) {
                $mods = $modsManager->searchAutoCompleteGamesMods(
                    $request,
                    $query,
                    $maxModResults,
                    $userId,
                    $isModAdmin
                );
            }

            $results = [
                VField::GAMES => DBManagerEntity::extractJsonDataArrays(array_values($games)),
                VField::GAME_MODS => DBManagerEntity::extractJsonDataArrays(array_values($mods)),
                VField::ORGANIZATIONS => DBManagerEntity::extractJsonDataArrays(array_values($organizations))
            ];
        }

        return $this->renderJsonResponse($results);
    }

    /**
     * URLs for /.
     *
     * @param Request $request
     * @return XmlResponse
     */
    protected function sitemap_root(Request $request)
    {
        prevent_slash($request);

        $t = new Template();
        $t['domain'] = $request->settings()->getWebsiteDomain();

        return new XmlResponse($t->render_template('pages/sitemap.xml.twig'));
    }

    /**
     * Listing of all the sitemaps.
     *
     * @param Request $request
     * @return XmlResponse
     * @throws Exception
     */
    protected function sitemap_index(Request $request)
    {
        prevent_slash($request);
        $t = new Template();
        $t->assign([
            'domain' => $request->settings()->getWebsiteDomain(),
        ]);

        return new XmlResponse($t->render_template('pages/sitemap-index.xml.twig'));
    }
}
