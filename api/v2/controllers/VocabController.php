<?php
use EasyRdf\Graph;
/**
 * VocabController.php
 *
 * This controller provides endpoints for fetching vocabularies via the API.
 *
 */

// Include settings.php so that variables are available
require_once __DIR__ . '/../../../settings.php';

/**
 * Class VocabController
 *
 * Handles vocabulary-related API requests.
 */
class VocabController
{
    /**
     * @var string The URL for MSL Labs data.
     */
    private $url;

    /**
     * @var string The base URL for MSL vocabularies.
     */
    private $mslVocabsUrl;

    /**
     * VocabController constructor.
     *
     * Initializes URLs using global variables.
     */
    public function __construct()
    {
        global $mslLabsUrl;
        global $mslVocabsUrl;
        $this->url = $mslLabsUrl;
        $this->mslVocabsUrl = $mslVocabsUrl;
    }

    /**
     * Retrieves relation data from the database and returns it as JSON.
     *
     * @return void
     */
    public function getRelations()
    {
        global $connection;
        $stmt = $connection->prepare('SELECT relation_id, name, description FROM Relation');

        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to prepare statement: ' . $connection->error]);
            return;
        }

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to execute statement: ' . $stmt->error]);
            return;
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $relations = [];
            while ($row = $result->fetch_assoc()) {
                $relations[] = [
                    'id' => $row['relation_id'],
                    'name' => $row['name'],
                    'description' => $row['description']
                ];
            }
            echo json_encode(['relations' => $relations]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No relations found']);
        }

        $stmt->close();
        exit();
    }

    /**
     * Fetches MSL Labs data from a remote URL, processes it, and returns the necessary fields.
     *
     * @return array Processed MSL Labs data.
     * @throws Exception If fetching or decoding the data fails.
     */
    public function fetchAndProcessMslLabs()
    {
        // Fetch data from the URL with a custom User-Agent
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: PHP Script'
            ]
        ];
        $context = stream_context_create($opts);
        $jsonData = file_get_contents($this->url, false, $context);

        if ($jsonData === false) {
            throw new Exception('Error fetching data from GitHub: ' . error_get_last()['message']);
        }

        // Correct character encoding
        $jsonData = mb_convert_encoding($jsonData, 'UTF-8', mb_detect_encoding($jsonData, 'UTF-8, ISO-8859-1', true));

        // Decode JSON data
        $labs = json_decode($jsonData, true);

        if ($labs === null) {
            throw new Exception('Error decoding JSON data: ' . json_last_error_msg());
        }

        // Process data and retain only necessary fields
        $processedLabs = array_map(function ($lab) {
            return [
                'id' => $lab['id'],
                'name' => $lab['lab_editor_name'],
                'affiliation' => $lab['affiliation']
            ];
        }, $labs);

        return $processedLabs;
    }

    /**
     * Gets the latest version number for the combined vocabulary file.
     *
     * @param string $baseUrl The base URL for vocabularies.
     * @return string|false The latest version string or false if not found.
     */
    private function getLatestVersion($baseUrl)
    {
        // Direkt Version 1.3 prüfen, da wir wissen dass diese existiert
        $url = "{$baseUrl}1.3/editor_1-3.json";
        error_log("Checking URL directly: " . $url);

        $headers = @get_headers($url);
        if ($headers && strpos($headers[0], '200') !== false) {
            error_log("Found version 1.3");
            return "1.3";
        }

        // Falls 1.3 nicht gefunden wurde, systematisch suchen
        $versions = [];
        for ($i = 1; $i <= 10; $i++) {
            $url = "{$baseUrl}1.{$i}/editor_1-{$i}.json";
            error_log("Checking URL: " . $url);

            $headers = @get_headers($url);
            if ($headers && strpos($headers[0], '200') !== false) {
                $versions[] = "1.{$i}";
                error_log("Found version 1.{$i}");
            }
        }

        $latestVersion = end($versions);
        error_log("Latest version found: " . ($latestVersion ?: "none"));

        return $latestVersion;
    }

    /**
     * Processes vocabulary items recursively and transform synonyms to description.
     *
     * @param array $item The item to process
     * @return array The processed item
     */
    private function processItem($item)
    {
        error_log("Processing item: " . json_encode($item));

        // Synonyms as description
        $description = '';
        if (isset($item['synonyms']) && is_array($item['synonyms']) && !empty($item['synonyms'])) {
            $description = implode(', ', $item['synonyms']);
        }

        $newItem = [
            'id' => $item['extra']['uri'] ?? '',
            'text' => $item['text'] ?? '',
            'language' => 'en',
            'scheme' => $item['extra']['vocab_uri'] ?? '',
            'schemeURI' => $item['extra']['vocab_uri'] ?? '',
            'description' => $description,
            'children' => []
        ];

        if (isset($item['children']) && !empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $newItem['children'][] = $this->processItem($child);
            }
        }

        error_log("Processed item: " . json_encode($newItem));
        return $newItem;
    }

    /**
     * Retrieves and updates MSL vocabulary data.
     *
     * @param array $vars An associative array of parameters (not used anymore)
     * @return void
     */
    public function getMslVocab($vars = [])
    {
        try {
            error_log("Starting getMslVocab");
            error_log("Base URL: " . $this->mslVocabsUrl);

            $jsonDir = __DIR__ . '/../../../json/';
            $outputFile = $jsonDir . 'msl-vocabularies.json';

            if (!file_exists($jsonDir)) {
                error_log("Creating JSON directory: " . $jsonDir);
                mkdir($jsonDir, 0755, true);
            }

            // Get latest version
            error_log("Getting latest version...");
            $latestVersion = $this->getLatestVersion($this->mslVocabsUrl);
            if (!$latestVersion) {
                throw new Exception("No vocabulary version found");
            }

            // Construct URL for the latest version
            $url = "{$this->mslVocabsUrl}{$latestVersion}/editor_" . str_replace('.', '-', $latestVersion) . ".json";
            error_log("Constructed URL: " . $url);

            // Download content
            $jsonContent = $this->downloadContent($url);
            if ($jsonContent === false) {
                throw new Exception("Failed to download vocabulary data from URL: " . $url);
            }

            error_log("Downloaded content length: " . strlen($jsonContent));

            // Decode JSON
            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Failed to parse vocabulary data: " . json_last_error_msg());
            }

            error_log("Decoded JSON data count: " . count($data));

            // Process each root item
            $processedData = [];
            foreach ($data as $item) {
                $processedData[] = $this->processItem($item);
            }

            error_log("Processed items count: " . count($processedData));

            // Save processed data
            if (file_put_contents($outputFile, json_encode($processedData, JSON_PRETTY_PRINT)) === false) {
                throw new Exception("Failed to save processed vocabulary data");
            }

            error_log("Successfully saved vocabulary data to: " . $outputFile);

            // Return success response
            header('Content-Type: application/json');
            echo json_encode([
                'message' => "Successfully updated MSL vocabularies to version {$latestVersion}",
                'version' => $latestVersion,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            error_log("Error in getMslVocab: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * Downloads content from a given URL.
     *
     * @param string $url The URL to download content from.
     * @return string|false The content if successful, or false on failure.
     */
    private function downloadContent($url)
    {
        error_log("Downloading content from: " . $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Für Entwicklungszwecke

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        error_log("HTTP Code: " . $httpCode);
        if ($content === false) {
            error_log("Curl error: " . curl_error($ch));
        }

        curl_close($ch);

        return ($httpCode == 200) ? $content : false;
    }

    /**
     * Retrieves GCMD Science Keywords from a local JSON file and returns them as JSON.
     *
     * @return void
     */
    public function getGcmdScienceKeywords()
    {
        try {
            $jsonPath = __DIR__ . '/../../../json/gcmdScienceKeywords.json';
            if (!file_exists($jsonPath)) {
                throw new Exception("Science Keywords file not found");
            }
            $json = file_get_contents($jsonPath);
            if ($json === false) {
                throw new Exception("Error reading Science Keywords file");
            }
            header('Content-Type: application/json');
            echo $json;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Updates the MSL Labs vocabulary by fetching and processing data, then saving it as JSON.
     *
     * @return void
     */
    public function updateMslLabs()
    {
        try {
            $mslLabs = $this->fetchAndProcessMslLabs();
            $jsonString = json_encode($mslLabs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($jsonString === false) {
                throw new Exception('Error encoding data to JSON: ' . json_last_error_msg());
            }

            $result = file_put_contents(__DIR__ . '/../../../json/msl-labs.json', $jsonString);

            if ($result === false) {
                throw new Exception('Error saving JSON file: ' . error_get_last()['message']);
            }

            header('Content-Type: application/json');
            echo json_encode(['message' => 'MSL Labs vocabulary successfully updated']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieves roles from the database based on the specified type and returns them as JSON.
     *
     * @param array $vars An associative array of parameters.
     * @return void
     */
    public function getRoles($vars)
    {
        global $connection;
        $type = $vars['type'] ?? $_GET['type'] ?? 'all';

        // SQL query based on the type
        if ($type == 'all') {
            $sql = 'SELECT * FROM Role';
        } elseif ($type == 'person') {
            $sql = 'SELECT * FROM Role WHERE forInstitutions = 0';
        } elseif ($type == 'institution') {
            $sql = 'SELECT * FROM Role WHERE forInstitutions = 1';
        } elseif ($type == 'both') {
            $sql = 'SELECT * FROM Role WHERE forInstitutions = 2';
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid roles type specified']);
            return;
        }

        if ($stmt = $connection->prepare($sql)) {
            $stmt->execute();
            $result = $stmt->get_result();
            $rolesList = $result->fetch_all(MYSQLI_ASSOC);

            if ($rolesList) {
                header('Content-Type: application/json');
                echo json_encode($rolesList);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No roles found']);
            }

            $stmt->close();
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $connection->error]);
        }
    }

    /**
     * Updates timezone data by fetching it from an external API and saving it as JSON.
     *
     * @return void
     */
    public function updateTimezones()
    {
        global $apiKeyTimezone;

        try {
            // The TimeZoneDB API URL to fetch timezone data
            $apiUrl = 'http://api.timezonedb.com/v2.1/list-time-zone?key=' . urlencode($apiKeyTimezone) . '&format=json';

            // Fetch data from the external API
            $response = file_get_contents($apiUrl);
            if ($response === FALSE) {
                throw new Exception('Error fetching data from TimeZoneDB API.');
            }

            // Decode response into an array
            $data = json_decode($response, true);
            if ($data['status'] != 'OK') {
                throw new Exception('Error occurred: ' . $data['message']);
            }

            // Format timezones as UTC+X (Zone)
            $formattedTimezones = [];
            foreach ($data['zones'] as $zone) {
                $offsetHours = floor($zone['gmtOffset'] / 3600);
                $offsetMinutes = abs($zone['gmtOffset'] % 3600 / 60);
                $offset = sprintf('%+03d:%02d', $offsetHours, $offsetMinutes);
                $formattedTimezones[] = [
                    'value' => $zone['zoneName'],
                    'label' => sprintf('UTC%s (%s)', $offset, $zone['zoneName'])
                ];
            }

            // Cache data as a JSON string on the server
            $jsonDir = __DIR__ . '/../../../json/';
            if (!file_exists($jsonDir)) {
                mkdir($jsonDir, 0755, true);
            }
            $result = file_put_contents($jsonDir . 'timezones.json', json_encode($formattedTimezones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($result === false) {
                throw new Exception('Error saving JSON file: ' . error_get_last()['message']);
            }

            header('Content-Type: application/json');
            echo json_encode([
                'message' => 'Timezones successfully updated',
                'timezones' => $formattedTimezones
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieves licenses from the database, filtered by type, and returns them as JSON.
     *
     * @param bool $forSoftwareOnly If true, retrieves licenses only for software; otherwise, retrieves all licenses.
     * @return void
     */
    private function getLicensesByType($forSoftwareOnly = false)
    {
        try {
            $sql = $forSoftwareOnly
                ? 'SELECT * FROM Rights WHERE forSoftware = 1'
                : 'SELECT * FROM Rights';

            $result = $GLOBALS['connection']->query($sql);

            if (!$result) {
                throw new Exception("Database query failed");
            }

            $licenses = [];
            while ($row = $result->fetch_assoc()) {
                $licenses[] = $row;
            }

            if (empty($licenses)) {
                http_response_code(404);
                echo json_encode([
                    'error' => $forSoftwareOnly
                        ? 'No software licenses found'
                        : 'No licenses found'
                ]);
                return;
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($licenses);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieves all licenses and returns them as JSON.
     *
     * @return void
     */
    public function getAllLicenses()
    {
        error_log("getAllLicenses called");
        try {
            error_log("Database connection status: " . ($GLOBALS['connection']->ping() ? "connected" : "not connected"));
            $this->getLicensesByType(false);
        } catch (Exception $e) {
            error_log("Error in getAllLicenses: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieves software licenses and returns them as JSON.
     *
     * @return void
     */
    public function getSoftwareLicenses()
    {
        error_log("getSoftwareLicenses called");
        try {
            error_log("Database connection status: " . ($GLOBALS['connection']->ping() ? "connected" : "not connected"));
            $this->getLicensesByType(true);
        } catch (Exception $e) {
            error_log("Error in getSoftwareLicenses: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Fetches RDF data from NASA GCMD API with pagination support
     *
     * @param string $conceptScheme The concept scheme to fetch (instruments, sciencekeywords, platforms)
     * @param int $pageNum The page number for pagination
     * @param int $pageSize The number of items per page
     * @return string The raw RDF data response
     * @throws Exception If the HTTP request fails
     */
    private function fetchRdfData($conceptScheme, $pageNum, $pageSize)
    {
        $url = "https://gcmd.earthdata.nasa.gov/kms/concepts/concept_scheme/{$conceptScheme}?format=rdf&page_num={$pageNum}&page_size={$pageSize}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Error fetching thesaurus keywords. HTTP status code: {$httpCode}");
        }

        return $response;
    }

    /**
     * Recursively sorts children nodes alphabetically by their text property
     *
     * @param array &$nodes Reference to the array of nodes to sort
     * @return void
     */
    private function sortChildrenRecursively(&$nodes)
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                usort($node['children'], function ($a, $b) {
                    return strcasecmp($a['text'], $b['text']);
                });
                $this->sortChildrenRecursively($node['children']);
            }
        }
    }

    /**
     * Builds a hierarchical structure from RDF graph data
     * Filters out "NOT APPLICABLE" entries and includes alternative labels
     *
     * @param Graph $graph The RDF graph object containing concept data
     * @param string $conceptScheme The concept scheme identifier
     * @param string $schemeName The human-readable name of the scheme
     * @return array The hierarchical structure of concepts
     */
    private function buildHierarchy($graph, $conceptScheme, $schemeName)
    {
        $hierarchy = [];
        $concepts = $graph->allOfType('skos:Concept');
        $conceptMap = [];

        $schemeURI = "https://gcmd.earthdata.nasa.gov/kms/concepts/concept_scheme/{$conceptScheme}";

        // Create concept map without "NOT APPLICABLE" entries
        foreach ($concepts as $concept) {
            $uri = $concept->getUri();
            $label = $concept->getLiteral('skos:prefLabel') ? $concept->getLiteral('skos:prefLabel')->getValue() : '';

            // Skip concepts with "NOT APPLICABLE" label
            if ($label === 'NOT APPLICABLE') {
                continue;
            }

            $lang = $concept->getLiteral('skos:prefLabel') ? $concept->getLiteral('skos:prefLabel')->getLang() : '';
            $description = $concept->getLiteral('skos:definition', 'en') ?
                $concept->getLiteral('skos:definition', 'en')->getValue() : '';

            // Add optional alternative labels
            $altLabels = [];
            foreach ($concept->allResources('skos:altLabel') as $altLabel) {
                $altLabels[] = $altLabel->getValue();
            }

            // Append alternative labels to description if present
            if (!empty($altLabels)) {
                $description .= "\nAlternative labels: " . implode(', ', $altLabels);
            }

            $conceptMap[$uri] = [
                'id' => $uri,
                'text' => $label,
                'language' => $lang,
                'scheme' => $schemeName,
                'schemeURI' => $schemeURI,
                'description' => $description,
                'children' => []
            ];
        }

        // Build hierarchy
        foreach ($concepts as $concept) {
            $uri = $concept->getUri();

            // Skip if concept is not in map (was "NOT APPLICABLE")
            if (!isset($conceptMap[$uri])) {
                continue;
            }

            $broader = $concept->getResource('skos:broader');
            if ($broader) {
                $broaderUri = $broader->getUri();
                // Check if parent concept exists
                if (isset($conceptMap[$broaderUri])) {
                    $conceptMap[$broaderUri]['children'][] = &$conceptMap[$uri];
                } else {
                    // If parent concept was "NOT APPLICABLE",
                    // add this concept to root level
                    $hierarchy[] = &$conceptMap[$uri];
                }
            } else {
                $hierarchy[] = &$conceptMap[$uri];
            }
        }

        // Sort concepts alphabetically
        usort($hierarchy, function ($a, $b) {
            return strcasecmp($a['text'], $b['text']);
        });

        // Sort children recursively
        $this->sortChildrenRecursively($hierarchy);

        return $hierarchy;
    }

    /**
     * Processes GCMD keywords for a specific concept scheme
     * Fetches data paginated, builds hierarchy, and saves to JSON file
     *
     * @param string $conceptScheme The concept scheme to process
     * @param string $schemeName The name of the scheme
     * @param string $outputFile The path to the output JSON file
     * @return bool True if successful, false otherwise
     * @throws Exception If data fetching or processing fails
     */
    private function processGcmdKeywords($conceptScheme, $schemeName, $outputFile)
    {
        $pageNum = 1;
        $pageSize = 2000;
        $graph = new Graph();

        while (true) {
            try {
                $data = $this->fetchRdfData($conceptScheme, $pageNum, $pageSize);
                $tempGraph = new Graph();
                $tempGraph->parse($data, 'rdf');

                foreach ($tempGraph->resources() as $resource) {
                    foreach ($tempGraph->properties($resource) as $property) {
                        foreach ($tempGraph->all($resource, $property) as $value) {
                            $graph->add($resource, $property, $value);
                        }
                    }
                }

                if (strpos($data, '<skos:Concept') === false) {
                    break;
                }
                $pageNum++;
            } catch (Exception $e) {
                if ($pageNum == 1) {
                    throw $e;
                }
                break;
            }
        }

        $hierarchicalData = $this->buildHierarchy($graph, $conceptScheme, $schemeName);
        file_put_contents($outputFile, json_encode($hierarchicalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return true;
    }

    /**
     * Updates all GCMD vocabularies (Science Keywords, Instruments, and Platforms)
     * Downloads latest versions from NASA's GCMD repository and saves them as JSON files
     *
     * @return void
     * @throws Exception If the update process fails
     */
    public function updateGcmdVocabs()
    {
        // Temporarily adjust error reporting
        $originalErrorReporting = error_reporting();
        error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $jsonDir = __DIR__ . '/../../../json/';
            if (!file_exists($jsonDir)) {
                mkdir($jsonDir, 0755, true);
            }

            $conceptSchemes = [
                [
                    'scheme' => 'instruments',
                    'name' => 'NASA/GCMD Instruments',
                    'output' => $jsonDir . 'gcmdInstrumentsKeywords.json'
                ],
                [
                    'scheme' => 'sciencekeywords',
                    'name' => 'NASA/GCMD Earth Science Keywords',
                    'output' => $jsonDir . 'gcmdScienceKeywords.json'
                ],
                [
                    'scheme' => 'platforms',
                    'name' => 'NASA/GCMD Earth Platforms Keywords',
                    'output' => $jsonDir . 'gcmdPlatformsKeywords.json'
                ]
            ];

            $results = [];
            foreach ($conceptSchemes as $scheme) {
                try {
                    $success = $this->processGcmdKeywords(
                        $scheme['scheme'],
                        $scheme['name'],
                        $scheme['output']
                    );
                    $results[$scheme['scheme']] = $success ? 'Updated successfully' : 'Update failed';
                } catch (Exception $e) {
                    $results[$scheme['scheme']] = 'Error: ' . $e->getMessage();
                }
            }

            // Reset error reporting
            error_reporting($originalErrorReporting);

            header('Content-Type: application/json');
            echo json_encode([
                'message' => 'GCMD vocabularies update completed',
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            // Reset error reporting
            error_reporting($originalErrorReporting);

            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }
}
