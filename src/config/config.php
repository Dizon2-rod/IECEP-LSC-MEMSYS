<?php
require_once __DIR__ . '/bootstrap.php';
// config.php - Redirect to the actual config file after restructuring
// This file exists to maintain compatibility with vendor/autoload.php

// Load the actual configuration from the new location
require_once __DIR__ . '/../../includes/config.php';
?>
