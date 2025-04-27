<?php
/**
 * Settings management functions for NexInvent
 * 
 * This file contains functions for managing system settings, including
 * currency detection based on user location.
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Get a system setting by key
 * 
 * @param string $key The setting key
 * @param mixed $default Default value if setting doesn't exist
 * @return mixed The setting value
 */
function getSetting($key, $default = null) {
    try {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $value = fetchValue($sql, [$key]);
        
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        error_log("Error fetching setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Update a system setting
 * 
 * @param string $key The setting key
 * @param string $value The setting value
 * @return bool Success status
 */
function updateSetting($key, $value) {
    try {
        $pdo = getDBConnection();
        $sql = "INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$key, $value, $value]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Get currency settings
 * 
 * @return array Currency settings (code, symbol, position)
 */
function getCurrencySettings() {
    return [
        'code' => getSetting('currency_code', 'USD'),
        'symbol' => getSetting('currency_symbol', '$'),
        'position' => getSetting('currency_position', 'before')
    ];
}

/**
 * Update currency settings
 * 
 * @param string $code Currency code (e.g. USD)
 * @param string $symbol Currency symbol (e.g. $)
 * @param string $position Symbol position (before or after)
 * @return bool Success status
 */
function updateCurrencySettings($code, $symbol, $position = 'before') {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        updateSetting('currency_code', $code);
        updateSetting('currency_symbol', $symbol);
        updateSetting('currency_position', $position);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating currency settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's currency based on IP address using geoPlugin
 * 
 * @param string $ip IP address (optional, uses current user's IP if not provided)
 * @return array Currency information (code, symbol, detected)
 */
function detectCurrencyFromIP($ip = null) {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    try {
        // Call geoPlugin API to get location data
        $geoData = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=' . $ip));
        
        if (isset($geoData['geoplugin_currencyCode']) && $geoData['geoplugin_currencyCode'] !== '') {
            return [
                'code' => $geoData['geoplugin_currencyCode'],
                'symbol' => $geoData['geoplugin_currencySymbol'],
                'country' => $geoData['geoplugin_countryName'],
                'detected' => true
            ];
        }
    } catch (Exception $e) {
        error_log("Error detecting currency: " . $e->getMessage());
    }
    
    // Default to system settings if detection fails
    $currency = getCurrencySettings();
    return [
        'code' => $currency['code'],
        'symbol' => $currency['symbol'],
        'country' => 'Unknown',
        'detected' => false
    ];
}

/**
 * Auto-update currency settings based on user's IP
 * 
 * @param bool $force Force update even if already set
 * @return bool True if currency was updated, false if no change or error
 */
function autoUpdateCurrencyFromIP($force = false) {
    $currentSettings = getCurrencySettings();
    $detectedCurrency = detectCurrencyFromIP();
    
    // Only update if forced or currency code is different
    if ($force || $detectedCurrency['detected'] && $detectedCurrency['code'] !== $currentSettings['code']) {
        return updateCurrencySettings(
            $detectedCurrency['code'],
            $detectedCurrency['symbol'],
            $currentSettings['position'] // Keep current position preference
        );
    }
    
    return false;
}

/**
 * Get currency exchange rate from base currency to target currency
 * 
 * @param string $from Base currency code (e.g. USD)
 * @param string $to Target currency code (e.g. EUR)
 * @return float|null Exchange rate or null if error
 */
function getCurrencyExchangeRate($from, $to) {
    try {
        // Use geoPlugin for exchange rates
        $geoData = unserialize(file_get_contents(
            'http://www.geoplugin.net/php.gp?base_currency=' . urlencode($from)
        ));
        
        if (isset($geoData['geoplugin_currencyConverter']) && $to === $geoData['geoplugin_currencyCode']) {
            return floatval($geoData['geoplugin_currencyConverter']);
        }
        
        // If direct conversion not available, try reverse lookup
        return null;
    } catch (Exception $e) {
        error_log("Error getting exchange rate: " . $e->getMessage());
        return null;
    }
}

/**
 * Convert amount from system currency to target currency
 * 
 * @param float $amount Amount in system currency
 * @param string $targetCurrency Target currency code
 * @return float|null Converted amount or null if conversion failed
 */
function convertCurrency($amount, $targetCurrency = null) {
    $systemCurrency = getSetting('currency_code', 'USD');
    
    if (!$targetCurrency) {
        $detectedCurrency = detectCurrencyFromIP();
        $targetCurrency = $detectedCurrency['code'];
    }
    
    // If same currency, no conversion needed
    if ($systemCurrency === $targetCurrency) {
        return $amount;
    }
    
    $rate = getCurrencyExchangeRate($systemCurrency, $targetCurrency);
    
    if ($rate !== null) {
        return $amount * $rate;
    }
    
    return null;
} 