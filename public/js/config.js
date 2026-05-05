<?php
// config.js - JavaScript configuration with PHP path constants
// This file outputs JavaScript variables with the centralized path constants
require_once __DIR__ . '/../../includes/paths.php';
?>
// IECEP-LSC MEMSYS - JavaScript Path Configuration
// Auto-generated from paths.php

const IECEP_PATHS = {
    BASE_URL: '<?php echo BASE_URL; ?>',
    PUBLIC_URL: '<?php echo PUBLIC_URL; ?>',
    PORTAL_URL: '<?php echo PORTAL_URL; ?>',
    ASSETS_URL: '<?php echo ASSETS_URL; ?>',
    CSS_URL: '<?php echo CSS_URL; ?>',
    JS_URL: '<?php echo JS_URL; ?>',
    API_URL: '<?php echo API_URL; ?>'
};

// API endpoints
const API_ENDPOINTS = {
    affiliate: '<?php echo API_URL; ?>/affiliate.php',
    compliance: '<?php echo API_URL; ?>/compliance.php',
    pro: '<?php echo API_URL; ?>/pro.php',
    // Add more endpoints as needed
};

// Helper function to get full URL for an API endpoint
function getApiUrl(endpoint) {
    return API_ENDPOINTS[endpoint] || (API_URL + '/' + endpoint + '.php');
}