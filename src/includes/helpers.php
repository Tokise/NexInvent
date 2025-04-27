<?php
/**
 * Helper functions for the application
 */

require_once __DIR__ . '/settings.php';

/**
 * Format amount according to system currency settings
 * 
 * @param float $amount The amount to format
 * @param bool $includeCurrency Whether to include the currency symbol
 * @return string Formatted amount
 */
function formatAmount($amount, $includeCurrency = true) {
    // Get currency settings
    $currency = getCurrencySettings();
    
    // Format the number with 2 decimal places
    $formattedAmount = number_format((float)$amount, 2, '.', ',');
    
    if (!$includeCurrency) {
        return $formattedAmount;
    }
    
    // Add currency symbol in correct position
    if ($currency['position'] === 'before') {
        return $currency['symbol'] . $formattedAmount;
    } else {
        return $formattedAmount . ' ' . $currency['symbol'];
    }
}

/**
 * Format a date in the system's standard format
 * 
 * @param string $date Date string
 * @param string $format Format string (default: Y-m-d H:i:s)
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    
    return date($format, $timestamp);
}

/**
 * Sanitize input data
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function getClientIP() {
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    return 'UNKNOWN';
}

/**
 * Convert amount to user's local currency
 * 
 * @param float $amount Amount in system currency
 * @param string $targetCurrency Target currency code (null = auto-detect)
 * @return string Formatted amount in target currency
 */
function convertToLocalCurrency($amount, $targetCurrency = null) {
    $converted = convertCurrency($amount, $targetCurrency);
    
    if ($converted === null) {
        // If conversion failed, return original amount
        return formatAmount($amount);
    }
    
    // If detected currency is different, show both currencies
    $systemCurrency = getSetting('currency_code', 'USD');
    $detectedCurrency = $targetCurrency ?: detectCurrencyFromIP()['code'];
    
    if ($systemCurrency !== $detectedCurrency) {
        // Get currency settings for detected currency
        return formatAmount($amount) . ' (' . formatAmount($converted) . ')';
    }
    
    return formatAmount($amount);
} 