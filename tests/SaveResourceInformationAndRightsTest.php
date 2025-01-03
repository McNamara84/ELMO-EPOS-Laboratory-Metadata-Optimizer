<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use mysqli_sql_exception;

require_once __DIR__ . '/../settings.php';

/**
 * Testklasse für die Funktionalität zum Speichern von Ressourceninformationen und Rechten.
 * 
 * Diese Klasse enthält verschiedene Testfälle, die die korrekte Funktionsweise
 * der saveResourceInformationAndRights-Funktion unter verschiedenen Bedingungen überprüfen.
 */
class SaveResourceInformationAndRightsTest extends TestCase
{
    private $connection;

    /**
     * Setzt die Testumgebung auf.
     * 
     * Stellt eine Verbindung zur Testdatenbank her und überspringt den Test,
     * falls die Datenbank nicht verfügbar ist.
     */
    protected function setUp(): void
    {
        global $connection;
        if (!$connection) {
            $connection = connectDb();
        }
        $this->connection = $connection;
        // Überprüfen, ob die Testdatenbank verfügbar ist
        $dbname = 'mde2-msl-test';
        if ($this->connection->select_db($dbname) === false) {
            // Testdatenbank erstellen
            $connection->query("CREATE DATABASE " . $dbname);
            $connection->select_db($dbname);
            // install.php ausführen
            require 'install.php';
        }
    }

    /**
     * Bereinigt die Testdaten nach jedem Test.
     */
    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }

    /**
     * Löscht alle Testdaten aus der Datenbank.
     */
    private function cleanupTestData()
    {
        $this->connection->query("SET FOREIGN_KEY_CHECKS=0");
        $this->connection->query("DELETE FROM Resource_has_Spatial_Temporal_Coverage");
        $this->connection->query("DELETE FROM Resource_has_Thesaurus_Keywords");
        $this->connection->query("DELETE FROM Resource_has_Related_Work");
        $this->connection->query("DELETE FROM Resource_has_Originating_Laboratory");
        $this->connection->query("DELETE FROM Resource_has_Funding_Reference");
        $this->connection->query("DELETE FROM Resource_has_Contact_Person");
        $this->connection->query("DELETE FROM Resource_has_Contributor_Person");
        $this->connection->query("DELETE FROM Resource_has_Contributor_Institution");
        $this->connection->query("DELETE FROM Resource_has_Author");
        $this->connection->query("DELETE FROM Resource_has_Free_Keywords");
        $this->connection->query("DELETE FROM Author_has_Affiliation");
        $this->connection->query("DELETE FROM Contact_Person_has_Affiliation");
        $this->connection->query("DELETE FROM Contributor_Person_has_Affiliation");
        $this->connection->query("DELETE FROM Contributor_Institution_has_Affiliation");
        $this->connection->query("DELETE FROM Originating_Laboratory_has_Affiliation");
        $this->connection->query("DELETE FROM Free_Keywords");
        $this->connection->query("DELETE FROM Affiliation");
        $this->connection->query("DELETE FROM Title");
        $this->connection->query("DELETE FROM Description");
        $this->connection->query("DELETE FROM Spatial_Temporal_Coverage");
        $this->connection->query("DELETE FROM Thesaurus_Keywords");
        $this->connection->query("DELETE FROM Related_Work");
        $this->connection->query("DELETE FROM Originating_Laboratory");
        $this->connection->query("DELETE FROM Funding_Reference");
        $this->connection->query("DELETE FROM Contact_Person");
        $this->connection->query("DELETE FROM Contributor_Person");
        $this->connection->query("DELETE FROM Contributor_Institution");
        $this->connection->query("DELETE FROM Author");
        $this->connection->query("DELETE FROM Resource");
        $this->connection->query("SET FOREIGN_KEY_CHECKS=1");
    }

    /**
     * Testet das Speichern von Ressourceninformationen und Rechten mit allen Feldern.
     */
    public function testSaveResourceInformationAndRights()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        $postData = [
            "doi" => "10.5880/GFZ",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "dateEmbargo" => "2024-12-31",
            "resourcetype" => 1,
            "version" => 1.0,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Testing Dataset for Unit Test"],
            "titleType" => [1]
        ];

        $resource_id = saveResourceInformationAndRights($this->connection, $postData);

        $this->assertIsInt($resource_id, "Die Funktion sollte eine gültige Resource ID zurückgeben.");
        $this->assertGreaterThan(0, $resource_id, "Die zurückgegebene Resource ID sollte größer als 0 sein.");

        // Überprüfen, ob die Daten korrekt in die Datenbank eingetragen wurden
        $stmt = $this->connection->prepare("SELECT * FROM Resource WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $this->assertEquals($postData["doi"], $row["doi"], "Die DOI wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["year"], $row["year"], "Das Jahr wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["dateCreated"], $row["dateCreated"], "Das Erstellungsdatum wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["dateEmbargo"], $row["dateEmbargoUntil"], "Das Embargodatum wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["resourcetype"], $row["Resource_Type_resource_name_id"], "Der Ressourcentyp wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["version"], $row["version"], "Die Version wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["language"], $row["Language_language_id"], "Die Sprache wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["Rights"], $row["Rights_rights_id"], "Die Rechte wurden nicht korrekt gespeichert.");

        // Überprüfen, ob der Titel korrekt eingetragen wurde
        $stmt = $this->connection->prepare("SELECT * FROM Title WHERE Resource_resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $this->assertEquals($postData["title"][0], $row["text"], "Der Titel wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["titleType"][0], $row["Title_Type_fk"], "Der Titeltyp wurde nicht korrekt gespeichert.");
    }

    /**
     * Testet das Speichern von Ressourceninformationen mit drei Titeln.
     */
    public function testSaveResourceInformationAndRightsWithThreeTitles()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        $postData = [
            "doi" => "10.5880/GFZ.45.57",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "dateEmbargo" => "2024-12-31",
            "resourcetype" => 1,
            "version" => 1.1,
            "language" => 2,
            "Rights" => 2,
            "title" => [
                "Main Title for Multiple Title Test",
                "Subtitle for Multiple Title Test",
                "Alternative Title for Multiple Title Test"
            ],
            "titleType" => [1, 2, 3]  // Angenommen, 1 = Main, 2 = Subtitle, 3 = Alternative
        ];

        $resource_id = saveResourceInformationAndRights($this->connection, $postData);

        $this->assertIsInt($resource_id, "Die Funktion sollte eine gültige Resource ID zurückgeben.");
        $this->assertGreaterThan(0, $resource_id, "Die zurückgegebene Resource ID sollte größer als 0 sein.");

        // Überprüfen, ob die Ressource-Daten korrekt in die Datenbank eingetragen wurden
        $stmt = $this->connection->prepare("SELECT * FROM Resource WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $this->assertEquals($postData["doi"], $row["doi"], "Die DOI wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["year"], $row["year"], "Das Jahr wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["dateCreated"], $row["dateCreated"], "Das Erstellungsdatum wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["dateEmbargo"], $row["dateEmbargoUntil"], "Das Embargodatum wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["resourcetype"], $row["Resource_Type_resource_name_id"], "Der Ressourcentyp wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["version"], $row["version"], "Die Version wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["language"], $row["Language_language_id"], "Die Sprache wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["Rights"], $row["Rights_rights_id"], "Die Rechte wurden nicht korrekt gespeichert.");

        // Überprüfen, ob alle drei Titel korrekt eingetragen wurden
        $stmt = $this->connection->prepare("SELECT * FROM Title WHERE Resource_resource_id = ? ORDER BY Title_Type_fk");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $this->assertEquals(3, $result->num_rows, "Es sollten genau drei Titel gespeichert worden sein");

        $index = 0;
        while ($row = $result->fetch_assoc()) {
            $this->assertEquals($postData["title"][$index], $row["text"], "Der Titel an Position $index stimmt nicht überein");
            $this->assertEquals($postData["titleType"][$index], $row["Title_Type_fk"], "Der Titeltyp an Position $index stimmt nicht überein");
            $index++;
        }
    }

    /**
     * Testet das Speichern von Ressourceninformationen mit Null-Werten.
     */
    public function testSaveResourceInformationAndRightsWithNullValues()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        $postData = [
            "doi" => null,
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "dateEmbargo" => null,
            "resourcetype" => 4,
            "version" => null,
            "language" => 2,
            "Rights" => 3,
            "title" => ["Testing Title"],
            "titleType" => [1]
        ];

        $resource_id = saveResourceInformationAndRights($this->connection, $postData);

        $this->assertIsInt($resource_id, "Die Funktion sollte eine gültige Resource ID zurückgeben.");
        $this->assertGreaterThan(0, $resource_id, "Die zurückgegebene Resource ID sollte größer als 0 sein.");

        // Überprüfen, ob die Daten korrekt in die Datenbank eingetragen und abgerufen wurden
        $stmt = $this->connection->prepare("SELECT * FROM Resource WHERE resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $this->assertNull($row["doi"], "Die DOI sollte null sein.");
        $this->assertEquals($postData["year"], $row["year"], "Das Jahr wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["dateCreated"], $row["dateCreated"], "Das Erstellungsdatum wurde nicht korrekt gespeichert.");
        $this->assertNull($row["dateEmbargoUntil"], "Das Embargodatum sollte null sein.");
        $this->assertEquals($postData["resourcetype"], $row["Resource_Type_resource_name_id"], "Der Ressourcentyp wurde nicht korrekt gespeichert.");
        $this->assertNull($row["version"], "Die Version sollte null sein.");
        $this->assertEquals($postData["language"], $row["Language_language_id"], "Die Sprache wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["Rights"], $row["Rights_rights_id"], "Die Rechte wurden nicht korrekt gespeichert.");

        // Überprüfen, ob der Titel korrekt eingetragen wurde
        $stmt = $this->connection->prepare("SELECT * FROM Title WHERE Resource_resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $this->assertEquals($postData["title"][0], $row["text"], "Der Titel wurde nicht korrekt gespeichert.");
        $this->assertEquals($postData["titleType"][0], $row["Title_Type_fk"], "Der Titeltyp wurde nicht korrekt gespeichert.");
    }

    /**
     * Testet das Verhalten bei leeren Pflichtfeldern.
     */
    public function testSaveResourceInformationAndRightsWithEmptyRequiredFields()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        $postData = [
            "doi" => null,
            "year" => null,
            "dateCreated" => null,
            "dateEmbargo" => null,
            "resourcetype" => null,
            "version" => null,
            "language" => null,
            "Rights" => null,
            "title" => [],
            "titleType" => []
        ];

        // Zählen der bestehenden Datensätze vor dem Test
        $countBefore = $this->connection->query("SELECT COUNT(*) as count FROM Resource")->fetch_assoc()['count'];

        try {
            $result = saveResourceInformationAndRights($this->connection, $postData);

            $this->assertFalse($result, "Die Methode sollte false zurückgeben, wenn Pflichtfelder leer sind");

            // Zählen der Datensätze nach dem Test
            $countAfter = $this->connection->query("SELECT COUNT(*) as count FROM Resource")->fetch_assoc()['count'];

            $this->assertEquals($countBefore, $countAfter, "Es sollte kein neuer Datensatz angelegt worden sein");

            // Überprüfen, ob kein neuer Titel angelegt wurde
            $titleCount = $this->connection->query("SELECT COUNT(*) as count FROM Title")->fetch_assoc()['count'];
            $this->assertEquals(0, $titleCount, "Es sollte kein neuer Titel angelegt worden sein");
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), "Column") !== false && strpos($e->getMessage(), "cannot be null") !== false) {
                $this->fail("Die Funktion saveResourceInformationAndRights() versucht einen unvollständigen Datensatz in der Datenbank zu speichern!");
            } else {
                throw $e; // Andere SQL-Ausnahmen werfen
            }
        }
    }

    /**
     * Testet die Verhinderung von doppelten Ressourcen-Speicherungen.
     */
    public function testPreventDuplicateResourceSave()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        $postData = [
            "doi" => "10.5880/GFZ.DUPLICATE.TEST",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "dateEmbargo" => "2024-12-31",
            "resourcetype" => 1,
            "version" => 1.0,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Duplicate Test Dataset"],
            "titleType" => [1]
        ];

        // Ersten Datensatz speichern
        $first_resource_id = saveResourceInformationAndRights($this->connection, $postData);
        $this->assertIsInt($first_resource_id, "Die erste Speicherung sollte eine gültige Resource ID zurückgeben.");
        $this->assertGreaterThan(0, $first_resource_id, "Die erste Resource ID sollte größer als 0 sein.");

        // Versuchen, denselben Datensatz erneut zu speichern
        $second_resource_id = saveResourceInformationAndRights($this->connection, $postData);

        $this->assertFalse($second_resource_id, "Die Funktion sollte false zurückgeben, wenn versucht wird, eine doppelte DOI zu speichern");

        // Überprüfen, ob nur ein Datensatz in der Datenbank existiert
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Resource WHERE doi = ?");
        $stmt->bind_param("s", $postData["doi"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        $this->assertEquals(1, $count, "Es sollte nur ein Datensatz mit dieser DOI in der Datenbank existieren");
    }

    /**
     * Testet die Handhabung von doppelten Titeln.
     */
    public function testHandleDuplicateTitles()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        $postData = [
            "doi" => "10.5880/GFZ.DUPLICATE.TITLE.TEST",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "dateEmbargo" => "2024-12-31",
            "resourcetype" => 1,
            "version" => 1.0,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Duplicate Title", "Duplicate Title", "Unique Title"],
            "titleType" => [1, 1, 2]
        ];

        $resource_id = saveResourceInformationAndRights($this->connection, $postData);
        $this->assertIsInt($resource_id, "Die Funktion sollte eine gültige Resource ID zurückgeben.");
        $this->assertGreaterThan(0, $resource_id, "Die zurückgegebene Resource ID sollte größer als 0 sein.");

        // Überprüfen, ob nur zwei Titel gespeichert wurden (ein Duplikat entfernt)
        $stmt = $this->connection->prepare("SELECT * FROM Title WHERE Resource_resource_id = ? ORDER BY Title_Type_fk");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $this->assertEquals(2, $result->num_rows, "Es sollten genau zwei Titel gespeichert worden sein");

        $titles = [];
        while ($row = $result->fetch_assoc()) {
            $titles[] = $row;
        }

        $this->assertEquals("Duplicate Title", $titles[0]['text'], "Der erste Titel sollte 'Duplicate Title' sein");
        $this->assertEquals(1, $titles[0]['Title_Type_fk'], "Der erste Titel sollte den Typ 1 haben");
        $this->assertEquals("Unique Title", $titles[1]['text'], "Der zweite Titel sollte 'Unique Title' sein");
        $this->assertEquals(2, $titles[1]['Title_Type_fk'], "Der zweite Titel sollte den Typ 2 haben");
    }

    /**
     * Testet ob doppelte DOIs abgelehnt, und doppelte leere DOIs und null-DOIs sind erlaubt.
     */
    public function testEmptyDois()
    {
        if (!function_exists('saveResourceInformationAndRights')) {
            require_once __DIR__ . '/../save/formgroups/save_resourceinformation_and_rights.php';
        }

        // Test 1: Verhindern von doppelten DOIs
        $postDataWithDOI = [
            "doi" => "10.5880/GFZ.DOI.TEST",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["DOI Test Dataset"],
            "titleType" => [1]
        ];

        // Ersten Datensatz mit DOI speichern
        $first_id = saveResourceInformationAndRights($this->connection, $postDataWithDOI);
        $this->assertIsInt($first_id, "Die erste Speicherung sollte eine gültige Resource ID zurückgeben.");

        // Versuchen, denselben Datensatz erneut zu speichern
        $duplicate_id = saveResourceInformationAndRights($this->connection, $postDataWithDOI);
        $this->assertFalse($duplicate_id, "Die Funktion sollte false zurückgeben bei doppelter DOI");

        // Test 2: Mehrere Datensätze ohne DOI (null) sollten erlaubt sein
        $postDataWithNullDOI = [
            "doi" => null,
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Dataset with null DOI"],
            "titleType" => [1]
        ];

        // Ersten Datensatz mit null DOI speichern
        $first_null_id = saveResourceInformationAndRights($this->connection, $postDataWithNullDOI);
        $this->assertIsInt($first_null_id, "Die erste Speicherung mit null DOI sollte eine gültige Resource ID zurückgeben.");

        // Zweiten Datensatz mit null DOI speichern
        $second_null_id = saveResourceInformationAndRights($this->connection, $postDataWithNullDOI);
        $this->assertIsInt($second_null_id, "Die zweite Speicherung mit null DOI sollte eine gültige Resource ID zurückgeben.");
        $this->assertNotEquals($first_null_id, $second_null_id, "Die IDs der Datensätze mit null DOI sollten unterschiedlich sein.");

        // Test 3: Mehrere Datensätze mit leerem String als DOI sollten erlaubt sein
        $postDataWithEmptyDOI = [
            "doi" => "",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Dataset with empty DOI"],
            "titleType" => [1]
        ];

        // Ersten Datensatz mit leerem String als DOI speichern
        $first_empty_id = saveResourceInformationAndRights($this->connection, $postDataWithEmptyDOI);
        $this->assertIsInt($first_empty_id, "Die erste Speicherung mit leerem DOI sollte eine gültige Resource ID zurückgeben.");

        // Zweiten Datensatz mit leerem String als DOI speichern
        $second_empty_id = saveResourceInformationAndRights($this->connection, $postDataWithEmptyDOI);
        $this->assertIsInt($second_empty_id, "Die zweite Speicherung mit leerem DOI sollte eine gültige Resource ID zurückgeben.");
        $this->assertNotEquals($first_empty_id, $second_empty_id, "Die IDs der Datensätze mit leerem DOI sollten unterschiedlich sein.");

        // Überprüfen der Anzahl der Datensätze mit verschiedenen DOI-Werten
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Resource WHERE doi = ?");
        $doi = "10.5880/GFZ.DOI.TEST";
        $stmt->bind_param("s", $doi);
        $stmt->execute();
        $count_with_doi = $stmt->get_result()->fetch_assoc()['count'];
        $this->assertEquals(1, $count_with_doi, "Es sollte genau ein Datensatz mit der spezifischen DOI existieren");

        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Resource WHERE doi IS NULL");
        $stmt->execute();
        $count_null_doi = $stmt->get_result()->fetch_assoc()['count'];
        $this->assertEquals(2, $count_null_doi, "Es sollten genau zwei Datensätze mit null DOI existieren");

        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Resource WHERE doi = ''");
        $stmt->execute();
        $count_empty_doi = $stmt->get_result()->fetch_assoc()['count'];
        $this->assertEquals(2, $count_empty_doi, "Es sollten genau zwei Datensätze mit leerem DOI existieren");

        // Überprüfen der Gesamtanzahl der Datensätze
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Resource");
        $stmt->execute();
        $total_count = $stmt->get_result()->fetch_assoc()['count'];
        $this->assertEquals(5, $total_count, "Es sollten insgesamt fünf Datensätze existieren");
    }
}