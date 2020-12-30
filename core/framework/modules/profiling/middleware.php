<?php
/**
 * Profiling Middleware
 *
 * @package profiling
 */
class ProfilingMiddleware extends Middleware
{
    /**
     * Response processing
     */
    public function process_response(Request $request, HttpResponse $response)
    {
        $url = $request->path;
        $url .= ($request->get->params() ? '?'.http_build_query($request->get->params()) : '');

        $total_time = (microtime(true) - TIME_NOW);
        $view_time  = $total_time - $response->template_time;


        $entry = [
            'date'              => date(SQL_DATETIME, TIME_NOW),
            'url'               => $url,
            'total_time'        => $total_time,
            'view_time'         => $view_time,
            'template_time'     => $response->template_time,
            'sql_queries'       => $request->db->get_sql_queries(),
            'cache_get_queries' => $request->cache->get_get_queries(),
            'cache_set_queries' => $request->cache->get_set_queries(),
            'local_cache_get_queries' => $request->local_cache->get_get_queries(),
            'local_cache_set_queries' => $request->local_cache->get_set_queries(),
            'headers'           => $request->headers,
            'session_id'        => $request->user->session->getSessionId(),
            'user'              => ['id' => $request->user->getId(),
                                    'name' => $request->user->getUsername()],
            'session_data'      => $request->user->session->getSessionData(),
        ];

        // if (!$request->user->guest->is_bot)
        //     TasksManager::add('app_performance', ['entry' => $entry], false);
    }
}
