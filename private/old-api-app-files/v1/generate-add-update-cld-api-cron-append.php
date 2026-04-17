<?php

require_once __DIR__.'/classes/cld-api.class.php';
$manualList = [
    15394,
    15396,
    15395,
];
$cldapi->cronJobGenerateAddUpdateCldApiDataFromList($manualList);
