<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 12/29/18
 * Time: 1:48 AM
 */

echo "* {$request->getCurrentSqlTime()} - started regenerating KPI Summaries\n";

$kpiSummariesManager = $request->managers->kpiSummaries();
$conn = $request->db->get_connection();

echo "* {$request->getCurrentSqlTime()} - started KPI summaries regen transaction\n";

$conn->begin();

$kpiSummariesManager->regenerateSummaries($request);

$conn->commit();

echo "* {$request->getCurrentSqlTime()} - committed KPI summaries regen transaction\n";

echo "* {$request->getCurrentSqlTime()} - ended regenerating KPI Summaries\n";