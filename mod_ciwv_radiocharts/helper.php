<?php
defined('_JEXEC') or die;

class ModCiwvRadiochartsHelper 
{
    public static $categories = [
        'ADD', 'OUT', 'A1', 'A2', 'B', 'C', 'D', 'GOLD', 'J', 'P', 'PC2', 'PC3', 'Q', 'HOLD'
    ];

    public static $csvNames = [
        'national' => 'NationalPlaylist',
        'station' => 'StationPlaylist',
        'streaming_station' => 'StreamingDataStation',
        'streaming_market' => 'StreamingDataMarket',
        'musicmaster' => 'MusicMasterCSV',
        'billboard' => 'BillboardChart'
    ];

    // Handles file upload & storage
    public static function handleUpload($file, $type, $dataDir, $allowedTypes) {
        if (!in_array($type, $allowedTypes)) return 'Type not allowed.';
        if ($file['error'] !== UPLOAD_ERR_OK) return 'Upload error.';
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'csv') return 'Only CSV files allowed.';
        $dest = $dataDir . '/' . self::$csvNames[$type] . '_'.date('Ymd_His').'.csv';
        if (!is_dir($dataDir)) mkdir($dataDir, 0775, true);
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Optionally parse and save summary, import to DB, etc.
            return 'Uploaded ' . basename($dest);
        } else {
            return 'Move failed.';
        }
    }

    // Loads and merges dashboard data
    public static function getDashboardData($station, $dataDir) {
        // For simplicity, load the latest uploaded files per type, then merge/parse
        // This function should be refactored for real DB use as needed
        $data = [];
        foreach (self::$csvNames as $type => $prefix) {
            $files = glob($dataDir . '/' . $prefix . '_*.csv');
            if ($files) {
                $lastFile = end($files);
                $data[$type] = self::parseCSV($lastFile);
            }
        }
        // Merge across files (song-title/artist fuzzy match, dedupe, etc)
        // ...build merged row for dashboard. See comments in next step.
        return self::mergeData($data, $station);
    }

    public static function parseCSV($file) {
        $rows = [];
        if (($fh = fopen($file, 'r')) !== false) {
            while (($row = fgetcsv($fh, 10000)) !== false) {
                $rows[] = $row;
            }
            fclose($fh);
        }
        return $rows;
    }

    public static function mergeData($data, $station) {
        // Implement fuzzy matching, TW/NW category logic (carryover, new song handling), column mapping, etc.
        // This is the heart of your dashboard logic.
        // For brevity, return raw data keys here - develop in detail in project source.
        return $data;
    }
}