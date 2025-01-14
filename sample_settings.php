<?php
/**
 *
 * Contains database connection settings, API keys, and application configuration variables.
 *
 */

/**
 * Establishes a connection to the database.
 *
 * @return mysqli The MySQLi connection object.
 */
function connectDb()
{
    $host = "localhost";
    $username = "your_database_username";
    $password = "your_database_password";
    $database = "your_database_name";
    $conn = new mysqli($host, $username, $password, $database);
    return $conn;
}

/**
 * Outputs the Google Maps API key in JSON format.
 *
 * @return void
 */
function getApiKey()
{
    // Google Maps API Key
    $apiKeyGoogleMaps = 'your_google_maps_api_key';
    // Return API key as JSON
    echo json_encode(['apiKey' => $apiKeyGoogleMaps]);
}

// Check if the file is accessed directly via an HTTP request
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getApiKey();
    }
}

// Establish the database connection
$connection = connectDb();

// API Key for https://timezonedb.com/
$apiKeyTimezone = 'your_timezone_api_key';

// Maximum number of titles that can be entered
$maxTitles = 2;

// URL to the source with all laboratories for MSL
$mslLabsUrl = 'https://raw.githubusercontent.com/UtrechtUniversity/msl_vocabularies/main/vocabularies/labs/labnames.json';

// Display the feedback link (true to display, false to hide)
$showFeedbackLink = true;

// Settings for sending mail with SMTP
$smtpHost = 'your_smtp_host';
$smtpPort = 465;
$smtpUser = 'your_smtp_username';
$smtpPassword = 'your_smtp_password';
$smtpSender = 'your_smtp_sender_email';

// Target address for feedback
$feedbackAddress = 'feedback@example.com';

// Target address for XML submit
$xmlSubmitAddress = 'xmlsubmit@example.com';
