<?php
/**
 * CBL Fee Calculator
 * Automated fee calculation based on member count bracketing
 */

function calculateAffiliationFee($memberCount) {
    if ($memberCount <= 50) return 1500;
    if ($memberCount <= 100) return 2000;
    if ($memberCount <= 150) return 2500;
    return 3000;
}

function calculateTotalFee($memberCount) {
    $affiliationFee = calculateAffiliationFee($memberCount);
    $operationalFee = 800;
    return $affiliationFee + $operationalFee;
}

function getFeeBracket($memberCount) {
    if ($memberCount <= 50) return ['bracket' => '1-50', 'affiliation' => 1500];
    if ($memberCount <= 100) return ['bracket' => '51-100', 'affiliation' => 2000];
    if ($memberCount <= 150) return ['bracket' => '101-150', 'affiliation' => 2500];
    return ['bracket' => '151+', 'affiliation' => 3000];
}
