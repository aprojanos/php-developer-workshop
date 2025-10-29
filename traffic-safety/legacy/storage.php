<?php

// CSV tároló
function legacy_save_accident($acc) {
    $line = implode(',', [
        $acc->id,
        $acc->occurredAt,
        str_replace(',', ';', $acc->location),
        $acc->severity,
        $acc->type,
        $acc->cost,
        $acc->roadSegmentId,
        $acc->intersectionId
    ]) . PHP_EOL;

    $dataFolder = __DIR__ . '/storage/data/';
    @ mkdir($dataFolder, 0755, true);
    file_put_contents($dataFolder . 'accidents.csv', $line, FILE_APPEND);
}

function legacy_get_all_accidents() {
    $f = __DIR__ . '/storage/data/accidents.csv';
    $rows = [];
    if (!file_exists($f)) return [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$id,$occurredAt,$location,$severity,$type,$cost,$roadSegmentId,$intersectionId] = explode(',', $line);
        $a = new Accident();
        $a->id = $id; $a->occurredAt = $occurredAt; $a->location = $location;
        $a->severity = $severity; $a->type = $type; $a->cost = (float)$cost;
        $a->roadSegmentId = $roadSegmentId; $a->intersectionId = $intersectionId;
        $rows[] = $a;
    }
    return $rows;
}
