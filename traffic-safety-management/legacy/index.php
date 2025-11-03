<?php
// legacy/index.php
// Simple script to simulate legacy behaviour by creating some accidents
require_once __DIR__ . '/LegacyAccidentManager.php';

    $manager = new LegacyAccidentManager();

    $samples = [
        [
            'occurredAt' => '2025-10-28',
            'location' => 'Rákóczu út 3.',
            'severity' => 'minor',
            'type' => 'PDO',
            'cost' => 250.0,
            'roadSegmentId' => 10,
        ],
        [
            'occurredAt' => '2025-10-29',
            'location' => 'Zsolnay u. 12.',
            'severity' => 'minor',
            'type' => 'Injury',
            'cost' => 150.0,
            'roadSegmentId' => 3,
            'intersectionId' => 7,
        ],
        [
            'occurredAt' => '2025-10-31',
            'location' => 'Mártírok útja 30',
            'severity' => 'severe',
            'type' => 'Injury',
            'cost' => 400.0,
            'intersectionId' => 12,        
        ]
    ];

    echo "Creating sample accidents...\n";
    foreach ($samples as $s) {
        $acc = $manager->createFromArray($s);
        echo "Created accident with id: {$acc->id}, type: {$acc->type}, cost: {$acc->cost}\n";
    }

    echo "\nEstimating total cost:\n";
    echo $manager->estimateTotalCost() . "\n";

    $csv = $manager->reportAllToCsv();
    echo "Exported CSV to: {$csv}\n";

    echo "Done.\n";
