<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * includes/helpers/validation.php - Input Validation Helper Functions
 * 
 * Provides centralized input validation and sanitization
 */

/**
 * Validate and sanitize email
 * 
 * @param string $email Email address
 * @return string|false Valid email or false
 */
function validate_email($email) {
    $email = trim($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return strtolower($email);
    }
    return false;
}

/**
 * Validate and sanitize string
 * 
 * @param string $str String to validate
 * @param int $min_length Minimum length
 * @param int $max_length Maximum length
 * @return string|false Sanitized string or false
 */
function validate_string($str, $min_length = 1, $max_length = 255) {
    $str = trim($str);
    $len = strlen($str);
    
    if ($len < $min_length || $len > $max_length) {
        return false;
    }
    
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize integer
 * 
 * @param mixed $value Value to validate
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return int|false Valid integer or false
 */
function validate_int($value, $min = PHP_INT_MIN, $max = PHP_INT_MAX) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $int = intval($value);
    
    if ($int < $min || $int > $max) {
        return false;
    }
    
    return $int;
}

/**
 * Validate UUID v4 format
 * 
 * @param string $uuid UUID to validate
 * @return bool
 */
function validate_uuid($uuid) {
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    return preg_match($pattern, $uuid) === 1;
}

/**
 * Validate file upload
 * 
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return bool
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 5242880) { // 5MB default
    if (!isset($file['tmp_name']) || !isset($file['size']) || !isset($file['type'])) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        error_log("File size exceeds limit: {$file['size']} > $max_size");
        return false;
    }
    
    // Check file type
    if (!empty($allowed_types)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            error_log("File MIME type not allowed: $mime_type");
            return false;
        }
    }
    
    // Check if file is readable
    if (!is_readable($file['tmp_name'])) {
        error_log("File is not readable: {$file['tmp_name']}");
        return false;
    }
    
    return true;
}

/**
 * Validate CSV file structure
 * 
 * @param string $file_path Path to CSV file
 * @param array $required_columns Required column headers
 * @return array|false Array with data or false on error
 */
function validate_csv_structure($file_path, $required_columns = []) {
    if (!is_readable($file_path)) {
        error_log("CSV file not readable: $file_path");
        return false;
    }
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        error_log("Cannot open CSV file: $file_path");
        return false;
    }
    
    $headers = fgetcsv($handle);
    
    // Check if required columns exist
    foreach ($required_columns as $col) {
        if (!in_array($col, $headers)) {
            fclose($handle);
            error_log("CSV missing required column: $col");
            return false;
        }
    }
    
    $data = [];
    $row_num = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;
        
        // Skip empty rows
        if (count(array_filter($row)) === 0) {
            continue;
        }
        
        $row_data = [];
        foreach ($headers as $key => $header) {
            $row_data[$header] = isset($row[$key]) ? trim($row[$key]) : '';
        }
        
        $data[] = $row_data;
    }
    
    fclose($handle);
    
    return [
        'headers' => $headers,
        'data' => $data,
        'row_count' => $row_num - 2 // Exclude header and off-by-one
    ];
}

/**
 * Validate date format
 * 
 * @param string $date Date string
 * @param string $format Expected date format
 * @return bool
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = \DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate phone number (basic validation)
 * 
 * @param string $phone Phone number
 * @return string|false Valid phone or false
 */
function validate_phone($phone) {
    $phone = preg_replace('/[^0-9+\-\s]/', '', $phone);
    $phone = trim($phone);
    
    if (strlen($phone) < 7 || strlen($phone) > 20) {
        return false;
    }
    
    return $phone;
}

/**
 * Sanitize HTML (allows safe tags)
 * 
 * @param string $html HTML string
 * @return string Sanitized HTML
 */
function sanitize_html($html) {
    $allowed_tags = '<b><i><strong><em><u><p><br><ul><ol><li><a><img>';
    return strip_tags($html, $allowed_tags);
}

/**
 * Validate JSON string
 * 
 * @param string $json JSON string
 * @return array|false Decoded JSON or false
 */
function validate_json($json) {
    $decoded = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    return $decoded;
}
?>
