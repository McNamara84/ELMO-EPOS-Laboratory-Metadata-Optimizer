<?php
require_once 'save_affiliations.php';

/**
 * Saves contact person information in the database.
 *
 * This function processes input data for contact persons, saves it in the database,
 * and creates corresponding entries for affiliations, avoiding duplicates.
 *
 * @param mysqli $connection The database connection.
 * @param array  $postData   The POST data from the form. Expected keys are:
 *                           - cpLastname: array (optional)
 *                           - cpFirstname: array (optional)
 *                           - cpPosition: array (optional)
 *                           - cpEmail: array (optional)
 *                           - cpOnlineResource: array (optional)
 *                           - cpAffiliation: array (optional)
 *                           - hiddenCPRorId: array (optional)
 * @param int    $resource_id The ID of the associated resource.
 *
 * @return void
 *
 * @throws mysqli_sql_exception If a database error occurs.
 */
function saveContactPerson($connection, $postData, $resource_id)
{
    $familynames = $postData['cpLastname'] ?? [];
    $givennames = $postData['cpFirstname'] ?? [];
    $positions = $postData['cpPosition'] ?? [];
    $emails = $postData['cpEmail'] ?? [];
    $websites = $postData['cpOnlineResource'] ?? [];
    $affiliations = $postData['cpAffiliation'] ?? [];
    $rorIds = $postData['hiddenCPRorId'] ?? [];

    $maxLen = max(
        count($familynames),
        count($givennames),
        count($positions),
        count($emails),
        count($websites),
        count($affiliations),
        count($rorIds)
    );

    for ($i = 0; $i < $maxLen; $i++) {
        $familyname = trim($familynames[$i] ?? '');
        $givenname = trim($givennames[$i] ?? '');
        $position = trim($positions[$i] ?? '');
        $email = trim($emails[$i] ?? '');
        $website = isset($websites[$i]) ? preg_replace('#^https?://#', '', $websites[$i]) : '';
        $affiliation_data = $affiliations[$i] ?? '';
        $rorId_data = $rorIds[$i] ?? '';

        // Skip completely empty entries
        if (empty($familyname) && empty($givenname) && empty($position) && empty($email) && empty($website)) {
            continue;
        }

        // Check if a contact person with the exact data already exists
        $stmt = $connection->prepare("
            SELECT contact_person_id FROM Contact_Person 
            WHERE familyname = ? AND givenname = ? AND position = ? AND email = ? AND website = ?
        ");
        $stmt->bind_param("sssss", $familyname, $givenname, $position, $email, $website);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Exact match found, skip saving
            $row = $result->fetch_assoc();
            $contact_person_id = $row['contact_person_id'];
            $stmt->close();
        } else {
            // No match found, insert new contact person
            $stmt->close();
            $stmt = $connection->prepare("INSERT INTO Contact_Person (familyname, givenname, position, email, website) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $familyname, $givenname, $position, $email, $website);
            $stmt->execute();
            $contact_person_id = $stmt->insert_id;
            $stmt->close();
        }

        // Insert into Resource_has_Contact_Person
        $stmt = $connection->prepare("INSERT IGNORE INTO Resource_has_Contact_Person (Resource_resource_id, Contact_Person_contact_person_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $resource_id, $contact_person_id);
        $stmt->execute();
        $stmt->close();

        // Save affiliations if any
        if (!empty($affiliation_data) || !empty($rorId_data)) {
            saveAffiliations($connection, $contact_person_id, $affiliation_data, $rorId_data, 'Contact_Person_has_Affiliation', 'Contact_Person_contact_person_id');
        }
    }
}
