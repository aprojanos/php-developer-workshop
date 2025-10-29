<?php
// legacy/LegacyAccidentManager.php
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/storage.php';

class LegacyAccidentManager {
    public function createFromArray(array $data) {
        $a = new Accident();
        $a->id = rand(1000,9999);
        $a->occurredAt = $data['occurredAt'] ?? date('c');
        $a->location = $data['location'] ?? 'unknown';
        $a->severity = $data['severity'] ?? 'minor'; // 'minor', 'serious', 'severe', 'fatal'
        $a->type = $data['type'] ?? 'PDO'; // 'PDO', 'Injury'
        $a->cost = $data['cost'] ?? 0;
        $a->roadSegmentId = $data['roadSegmentId'] ?? null;
        $a->intersectionId = $data['intersectionId'] ?? null;

        legacy_save_accident($a);
        
        $this->sendMail('safety@example.com', 'New accident', json_encode($data));
        
        $logFolder = __DIR__ . '/storage/logs/';
        @ mkdir($logFolder, 0755, true);
        file_put_contents($logFolder . 'app.txt', "Accident Created with ID: {$a->id}\n", FILE_APPEND);

        return $a;
    }

    public function reportAllToCsv() {
        
        $rows = legacy_get_all_accidents();
        
        $outFolder = __DIR__ . '/storage/export/';
        @ mkdir($outFolder, 0755, true);
        $outFile = $outFolder . 'export.csv';
        $out = fopen($outFile,'w');
        foreach ($rows as $r) {
            fputcsv($out, (array)$r);
        }
        fclose($out);

        return $outFile;
    }

    public function estimateTotalCost() {

        $rows = legacy_get_all_accidents();
        $sum = 0;
        foreach ($rows as $r) {
            $sum += $r->cost;
            if ($r->type == 'Injury') {
                $cost = 10000;
                switch($r->severity) {
                    case 'major':
                        $cost = 20000;
                        break;
                    case 'severe':
                        $cost = 40000;
                        break;
                    case 'fatal':
                        $cost = 100000;
                        break;
                }
                $sum += $cost;
            }
        }
        
        return $sum;
    }

    protected function sendMail($email, $subject, $body) {
        $mailFolder = __DIR__ . '/storage/logs/';
        @ mkdir($mailFolder, 0755, true);
        file_put_contents($mailFolder . 'mail.txt', "Mail sent to: {$email}, subject: {$subject}\n{$body}\n-----------------------------\n", FILE_APPEND);
    }
}
