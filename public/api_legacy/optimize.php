<?php
// api/optimize.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$text = $_POST['text'] ?? '';

if (empty($text)) {
    echo json_encode(['error' => 'No text provided']);
    exit;
}

$pythonScript = __DIR__ . '/../../src/python/optimizer.py';
$command = "python " . escapeshellarg($pythonScript);

$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open($command, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Write input to stdin
    fwrite($pipes[0], json_encode(['text' => $text]));
    fclose($pipes[0]);

    // Read output from stdout
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    // Read error from stderr
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $return_value = proc_close($process);

    if ($return_value === 0) {
        // Try to find JSON in the output (in case of noise)
        $jsonStart = strpos($output, '{');
        $jsonEnd = strrpos($output, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
            $jsonOutput = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($jsonOutput['optimized_text'])) {
                echo json_encode(['optimized_text' => $jsonOutput['optimized_text']]);
            } else {
                // JSON valid but missing field or other error
                echo json_encode(['error' => 'Invalid response structure from optimizer']);
            }
        } else {
            // Fallback if no JSON found
            echo json_encode(['optimized_text' => trim($output)]);
        }
    } else {
        echo json_encode(['error' => 'Optimization failed', 'details' => $error]);
    }
} else {
    echo json_encode(['error' => 'Could not start optimization process']);
}
