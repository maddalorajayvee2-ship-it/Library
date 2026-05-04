<?php
// setup.php
require_once __DIR__ . '/src/library.php';

try {
    addSampleBooks();
    jsonResponse(['message' => 'Database initialized and sample books added']);
} catch (Exception $ex) {
    jsonResponse(['error' => $ex->getMessage()], 500);
}
