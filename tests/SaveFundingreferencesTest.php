<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use mysqli_sql_exception;

require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../save/formgroups/save_fundingreferences.php';

class SaveFundingreferencesTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        global $connection;
        if (!$connection) {
            $connection = connectDb();
        }
        $this->connection = $connection;

        $dbname = 'geobrains-msl-test';
        if ($this->connection->select_db($dbname) === false) {
            $this->markTestSkipped("Testdatenbank '$dbname' ist nicht verfügbar. Bitte überprüfen Sie die Datenbankverbindung.");
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }

    private function cleanupTestData()
    {
        $this->connection->query("DELETE FROM Resource_has_Funding_Reference");
        $this->connection->query("DELETE FROM Funding_Reference");
        $this->connection->query("DELETE FROM Title");
        $this->connection->query("DELETE FROM Resource");
    }

    /**
     * Speicherung einer vollständigen Funding Reference inkl. Funder ID
     */
    public function testSaveCompleteFundingReferenceWithFunderId()
    {
        $resourceData = [
            "doi" => "10.5880/GFZ.TEST.COMPLETE.FUNDING",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Test Complete Funding Reference"],
            "titleType" => [1]
        ];
        $resource_id = saveResourceInformationAndRights($this->connection, $resourceData);

        $postData = [
            "funder" => ["Gordon and Betty Moore Foundation"],
            "funderId" => ["100000936"],
            "grantNummer" => ["GBMF3859.01"],
            "grantName" => ["Socioenvironmental Monitoring of the Amazon Basin and Xingu"]
        ];

        saveFundingReferences($this->connection, $postData, $resource_id);

        // Check if the Funding Reference was saved correctly
        $stmt = $this->connection->prepare("SELECT * FROM Funding_Reference WHERE funder = ?");
        $stmt->bind_param("s", $postData["funder"][0]);
        $stmt->execute();
        $fundingReference = $stmt->get_result()->fetch_assoc();

        $this->assertNotNull($fundingReference, "Die Funding Reference sollte gespeichert worden sein.");

        // Extrahiere die letzten 10 Ziffern der erwarteten FunderId
        $expectedFunderId = substr($postData["funderId"][0], -10);

        $this->assertEquals($expectedFunderId, $fundingReference["funderid"], "Die Funder ID sollte korrekt gespeichert sein.");
        $this->assertEquals("Crossref Funder ID", $fundingReference["funderidtyp"], "Der Funder ID Type sollte 'Crossref Funder ID' sein.");
        $this->assertEquals($postData["grantNummer"][0], $fundingReference["grantnumber"]);
        $this->assertEquals($postData["grantName"][0], $fundingReference["grantname"]);

        // Check if the relation to the resource was created
        $stmt = $this->connection->prepare("SELECT * FROM Resource_has_Funding_Reference WHERE Resource_resource_id = ? AND Funding_Reference_funding_reference_id = ?");
        $stmt->bind_param("ii", $resource_id, $fundingReference["funding_reference_id"]);
        $stmt->execute();
        $relation = $stmt->get_result()->fetch_assoc();

        $this->assertNotNull($relation, "Die Verknüpfung zwischen Resource und Funding Reference sollte existieren.");
    }

    /**
     * Speicherung einer vollständigen Funding Reference ohne Funder ID
     */
    public function testSaveCompleteFundingReferenceWithoutFunderId()
    {
        $resourceData = [
            "doi" => "10.5880/GFZ.TEST.COMPLETE.FUNDING.NO.ID",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Test Complete Funding Reference No ID"],
            "titleType" => [1]
        ];
        $resource_id = saveResourceInformationAndRights($this->connection, $resourceData);

        $postData = [
            "funder" => ["Ford Foundation"],
            "funderId" => [""],
            "grantNummer" => ["FORD123"],
            "grantName" => ["Grantmaking at a glance"]
        ];

        saveFundingReferences($this->connection, $postData, $resource_id);

        // Check if the Funding Reference was saved correctly
        $stmt = $this->connection->prepare("SELECT * FROM Funding_Reference WHERE funder = ?");
        $stmt->bind_param("s", $postData["funder"][0]);
        $stmt->execute();
        $fundingReference = $stmt->get_result()->fetch_assoc();

        $this->assertNotNull($fundingReference, "Die Funding Reference sollte gespeichert worden sein.");
        $this->assertNull($fundingReference["funderId"], "Die Funder ID sollte null sein.");
        $this->assertNull($fundingReference["funderidtyp"], "Der Funder ID Type sollte null sein.");
        $this->assertEquals($postData["grantNummer"][0], $fundingReference["grantnumber"]);
        $this->assertEquals($postData["grantName"][0], $fundingReference["grantname"]);

        // Check if the relation to the resource was created
        $stmt = $this->connection->prepare("SELECT * FROM Resource_has_Funding_Reference WHERE Resource_resource_id = ? AND Funding_Reference_funding_reference_id = ?");
        $stmt->bind_param("ii", $resource_id, $fundingReference["funding_reference_id"]);
        $stmt->execute();
        $relation = $stmt->get_result()->fetch_assoc();

        $this->assertNotNull($relation, "Die Verknüpfung zwischen Resource und Funding Reference sollte existieren.");
    }

    /**
     * Versuch, eine Funding Reference ohne Pflichtfelder zu speichern
     */
    public function testSaveIncompleteFundingReference()
    {
        $resourceData = [
            "doi" => "10.5880/GFZ.TEST.INCOMPLETE.FUNDING",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Test Incomplete Funding Reference"],
            "titleType" => [1]
        ];
        $resource_id = saveResourceInformationAndRights($this->connection, $resourceData);

        $postData = [
            "funder" => [""],
            "funderId" => [""],
            "grantNummer" => ["INCOMPLETE123"],
            "grantName" => ["Incomplete Grant"]
        ];

        $result = saveFundingReferences($this->connection, $postData, $resource_id);

        $this->assertFalse($result, "Die Funktion sollte false zurückgeben.");

        // Check that no Funding Reference was saved
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Funding_Reference");
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        $this->assertEquals(0, $count, "Es sollten keine Funding References gespeichert worden sein.");
    }

    /**
     * Speicherung von zwei vollständigen und einer unvollständigen Funding Reference
     */
    public function testSaveMixedFundingReferences()
    {
        $resourceData = [
            "doi" => "10.5880/GFZ.TEST.MIXED.FUNDING",
            "year" => 2023,
            "dateCreated" => "2023-06-01",
            "resourcetype" => 1,
            "language" => 1,
            "Rights" => 1,
            "title" => ["Test Mixed Funding References"],
            "titleType" => [1]
        ];
        $resource_id = saveResourceInformationAndRights($this->connection, $resourceData);

        $postData = [
            "funder" => ["Gordon and Betty Moore Foundation", "", "Ford Foundation"],
            "funderId" => ["https://doi.org/10.13039/100000936", "", ""],
            "grantNummer" => ["GBMF3859.01", "INCOMPLETE123", "FORD123"],
            "grantName" => ["Socioenvironmental Monitoring", "Incomplete Grant", "Grantmaking at a glance"]
        ];

        saveFundingReferences($this->connection, $postData, $resource_id);

        // Check that only two Funding References were saved
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Funding_Reference");
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        $this->assertEquals(2, $count, "Es sollten genau zwei Funding References gespeichert worden sein.");

        // Check that only two relations to the resource were created
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM Resource_has_Funding_Reference WHERE Resource_resource_id = ?");
        $stmt->bind_param("i", $resource_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        $this->assertEquals(2, $count, "Es sollten genau zwei Verknüpfungen zwischen Resource und Funding Reference existieren.");

        // Check that the correct Funding References were saved
        $stmt = $this->connection->prepare("SELECT funder FROM Funding_Reference");
        $stmt->execute();
        $result = $stmt->get_result();
        $savedFunders = [];
        while ($row = $result->fetch_assoc()) {
            $savedFunders[] = $row['funder'];
        }

        $this->assertContains("Gordon and Betty Moore Foundation", $savedFunders, "Die erste vollständige Funding Reference sollte gespeichert worden sein.");
        $this->assertContains("Ford Foundation", $savedFunders, "Die dritte vollständige Funding Reference sollte gespeichert worden sein.");
        $this->assertNotContains("", $savedFunders, "Die unvollständige Funding Reference sollte nicht gespeichert worden sein.");
    }
}