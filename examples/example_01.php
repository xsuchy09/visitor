<?php
/******************************************************************************
 * Author: Petr Suchy (xsuchy09) <suchy@wamos.cz> <http://www.wamos.cz>
 * Subject: WAMOS <http://www.wamos.cz>
 * Project: utmcookie
 * Copyright: (c) Petr Suchy (xsuchy09) <suchy@wamos.cz> <http://www.wamos.cz>
 *****************************************************************************/

require_once __DIR__ . '/../src/Visitor/Visitor.php';

use Visitor\Visitor;

// pdo - fill your credentials
$pdo = $PDO = new PDO(sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', 'host', 5432, 'dbname', 'user', 'password'));

// just init (read utm params and cookie and save new values)
$visitor = new Visitor($pdo, 'HashidsKey', 8, 'data.visitor', 'my_visitor', new DateInterval('P1Y'), '/', '', true, true);
$visitor->addVisit(); // add visit

$firstVisitDate = $visitor->getVisitorFirstVisitDate(); // get DateTime of first visit of user
$visitorHashids = $visitor->getVisitorHashids(); // get visitor hashids - safe hash of ID in database - @see https://hashids.org/
$visitorId = $visitor->getVisitorId(); // get visitor id in db
$visitorData = $visitor->getVisitorData(); // get all data about visitor
$visitorIsBot = $visitor->botDetected(); // bot or cli run
