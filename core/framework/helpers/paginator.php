<?php

class Paginator {

    protected $get;
    protected $path;
    protected $params = [];
    protected $fragment;
    protected $translations;
    protected $totalPages = 1;
    protected $currentPage = 1;
    protected $edgeCount = 3;
    protected $nearCount = 5;
    protected $templateFile = 'blocks/paginator.twig';
    protected $template;
    protected $includePrevNext;


    /**
     * GcPaginator constructor.
     * @param Request $request
     * @param int $totalPages
     * @param int $currentPage
     * @param int $edgeCount
     * @param int $nearCount
     * @param string $path
     * @param string $templateFile
     */
    function __construct(Request $request, $totalPages = 1, $currentPage = 1, $edgeCount = 1, $nearCount = 3, $includePrevNext = true)
    {
        $this->translations = $request->translations;

        $this->get = $request->get;
        $this->path = $request->path;
        $this->fragment = $request->anchor;

        $this->totalPages = $totalPages;
        $this->currentPage = $currentPage;
        $this->edgeCount = $edgeCount;
        $this->nearCount = $nearCount;
        $this->includePrevNext = $includePrevNext;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setUrl($path = '')
    {
        $url = parse_url($path);

        $query = [];

        if (!empty($url['query'])) {
            parse_str($url['query'], $query);
            $this->params = array_merge($this->params, $query);
        }

        if(isset($url['path']))
            $this->path = $url['path'];

        if(isset($url['fragment']))
            $this->fragment = $url['fragment'];

        return $this;
    }

    /**
     * @param int $totalPages
     * @return $this
     */
    public function setTotalPages($totalPages = 1)
    {
        $this->totalPages = $totalPages;
        return $this;
    }

    /**
     * @param int $currentPage
     * @return $this
     */
    public function setCurrentPage($currentPage = 1)
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams($params = [])
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param $templateFile
     */
    public function setTemplateFile($templateFile)
    {
        $this->templateFile = $templateFile;
    }

    /**
     * @param int $pageNum
     * @return string
     */
    public function computeUrl($pageNum = 1)
    {
        $params = array_merge($this->params, [
            TemplateVars::P => $pageNum
        ]);

        $fragmentString = $this->fragment ? "#{$this->fragment}" : '';

        return $this->path.$this->get->buildUrlQuery($params).$fragmentString;
    }

    /**
     * @param string $templateFile
     * @return string
     */
    public function render($templateFile = '')
    {
        if (!$templateFile)
            $templateFile = $this->templateFile;

        $viewData = [
            TemplateVars::I18N => $this->translations,
            TemplateVars::REQUEST_GET_PARAMS => $this->get,
            TemplateVars::TOTAL_PAGES => $this->totalPages,
            TemplateVars::REQUEST_PATH => $this->path,
            TemplateVars::P => $this->currentPage,
            TemplateVars::EDGE_COUNT => $this->edgeCount,
            TemplateVars::NEAR_COUNT => $this->nearCount,
            TemplateVars::PAGINATOR => $this,
            TemplateVars::INCLUDE_PREVNEXT => $this->includePrevNext
        ];

        $this->template = new Template($viewData);

        return $this->template->render_template($templateFile);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}