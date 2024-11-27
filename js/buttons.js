$(document).ready(function () {
  /**
   * Event handler for the "Send Feedback" button click.
   * Collects feedback data and sends it via AJAX to the server.
   */
  $("#sendFeedback").click(function (event) {
    event.preventDefault();
    var feedbackForm = $("#feedbackForm");
    var feedbackData = feedbackForm.serialize();


    // Disable the button and show a loading spinner
    $("#sendFeedback")
      .prop("disabled", true)
      .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...');


    $.ajax({
      url: "send_feedback_mail.php",
      type: "POST",
      data: feedbackData,
      success: function (response) {

        // Formular ausblenden
        feedbackForm.hide();

        // Erfolgsmeldung und Danke-Nachricht anzeigen
        $("#thankYouMessage").show();
        $("#feedbackStatus").html('<div class="alert alert-success">Feedback sent successfully!</div>');

        // Modal schließen nach 3 Sekunden
        setTimeout(function () {
          $("#feedbackModal").modal("hide");
        }, 3000);


      },
      error: function (xhr, status, error) {
        // Display error message
        $("#feedbackStatus").html(
          '<div class="alert alert-danger">Error when sending feedback: ' + error + "</div>"
        );
        // Enable the send button
        $("#sendFeedback").prop("disabled", false).html("Send");
      },
      complete: function () {
        // Modal zurücksetzen, wenn es geschlossen wird
        $("#feedbackModal").on("hidden.bs.modal", function () {
          feedbackForm[0].reset();
          feedbackForm.show();
          $("#thankYouMessage").hide();
          $("#feedbackStatus").html("");
          $("#sendFeedback").prop("disabled", false).html("Senden");
        });
      }
    });
  });


  // Optional: Formular zurücksetzen, wenn das Modal geöffnet wird
  $('#feedbackModal').on('show.bs.modal', function () {
    $("#feedbackForm")[0].reset();
    $("#feedbackForm").show();
    $("#thankYouMessage").hide();
    $("#feedbackStatus").html("");
    $("#sendFeedback").prop("disabled", false).html("Senden");
  });
  // Tooltip initialisieren
  $('[data-bs-toggle="tooltip"]').tooltip();

  //////////////////////////// ADD AND REMOVE BUTTONS ///////////////////////////////////////////////////////////////
  //Remove  Button anlegen, der in Formgroups Authors, Contact Persons, Contributors genutzt wird
  var removeButton = '<button type="button" class="btn btn-danger removeButton" style="width: 36px">-</button>';
  /**
 * HTML markup for the title type options, copied from the initial dropdown.
 * @type {string}
 */

  var optionTitleTypeHTML = $("#titleType").html();

  /**
   * Counter for the number of titles currently added.
   * @type {number}
   */
  var titlesNumber = 1;

  /**
   * Stores the main title type, which is set for the first title row.
   * @type {string}
   */
  var mainTitleType = "";

  /**
   * Click event handler for the "Add Title" button.
   * Adds a new title row if the maximum number of titles has not been reached.
   */
  $("#addTitle").click(function () {
    /**
     * Reference to the "Add Title" button.
     * @type {jQuery}
     */
    var $addTitleBtn = $(this);

    // Check if the current number of titles is below the allowed maximum.
    if (titlesNumber < maxTitles) {
      // Clone the existing title row and reset its input fields.
      var newTitleRow = $addTitleBtn.closest(".row").clone();

      // Remove help buttons from the cloned row.
      deleteHelpButtonFromClonedRows(newTitleRow);
      $(newTitleRow).find("input").val("");

      // Adjust the column layout classes for the cloned row.
      newTitleRow.find(".col-12.col-sm-12.col-md-11.col-lg-11")
        .removeClass("col-md-11 col-lg-11")
        .addClass("col-md-8 col-lg-8");

      // Control the visibility of the title type dropdown.
      if (titlesNumber === 0) {
        // Show the dropdown for the first title.
        $("#titleTypeContainer").show();
      } else {
        // Ensure the dropdown is visible for subsequent titles.
        $(newTitleRow).find("#titleTypeContainer").show();
      }

      // Capture the main title type for the first row.
      if (titlesNumber === 1) {
        mainTitleType = $(newTitleRow).find("select").val();
      }

      // Populate the title type dropdown with options and remove the main title type.
      var $select = $(newTitleRow).find("select");
      $select.html(optionTitleTypeHTML);
      $select.find("option[value='" + mainTitleType + "']").remove(); // Remove the main title type
      $select.val(""); // Reset the dropdown selection

      // Create a remove button for the new row.
      var removeBtn = $("<button/>", {
        text: "-",
        type: "button",
        class: "btn btn-danger removeTitle",
      }).css("width", "36px");

      // Event handler for the remove button.
      removeBtn.click(function () {
        // Remove the current row and decrement the titles counter.
        $(this).closest(".row").remove();
        titlesNumber--;

        // Enable the "Add Title" button if below the maximum limit.
        if (titlesNumber < maxTitles) {
          $addTitleBtn.prop("disabled", false);
        }
      });

      // Replace the "Add Title" button in the cloned row with the remove button.
      $(newTitleRow).find(".addTitle").replaceWith(removeBtn);

      // Append the new title row to the DOM.
      $addTitleBtn.closest(".row").parent().append(newTitleRow);
      titlesNumber++;

      // Disable the "Add Title" button if the maximum number of titles is reached.
      if (titlesNumber === maxTitles) {
        $addTitleBtn.prop("disabled", true);
      }
    } else {
      // Log a message if the maximum number of titles is reached.
      console.log("Maximum number of titles reached: " + maxTitles);
    }
  });

  /**
   * Event handler for the "Add Author" button click.
   * Clones the first author row, resets input fields, and appends it to the author group.
   */
  $("#addAuthor").click(function () {
    var authorGroup = $("#authorGroup");
    // First row to be used as a template
    var firstAuthorLine = authorGroup.children().first();

    // Clone the template
    var newAuthorRow = firstAuthorLine.clone();

    // Clear input fields and remove validation feedback
    newAuthorRow.find("input").val("").removeClass("is-invalid is-valid");
    newAuthorRow.find(".invalid-feedback, .valid-feedback").hide();

    // Generate unique IDs for cloned input elements
    var uniqueSuffix = new Date().getTime();
    newAuthorRow
      .find("#inputAuthorAffiliation")
      .attr("id", "inputAuthorAffiliation" + uniqueSuffix);
    newAuthorRow.find("#hiddenAuthorRorId").attr("id", "hiddenAuthorRorId" + uniqueSuffix);

    // Remove old Tagify elements (will be re-initialized in autocompleteAffiliation)
    newAuthorRow.find(".tagify").remove();

    // Replace the add button with the remove button
    newAuthorRow.find(".addAuthor").replaceWith(removeButton);

    // Remove help buttons
    deleteHelpButtonFromClonedRows(newAuthorRow);

    // Append the new author row to the DOM
    authorGroup.append(newAuthorRow);

    // Apply Tagify to the new Author Affiliations field
    autocompleteAffiliations(
      "inputAuthorAffiliation" + uniqueSuffix,
      "hiddenAuthorRorId" + uniqueSuffix,
      affiliationsData
    );

    // Event handler for the remove button
    newAuthorRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });

  /**
   * Event handler for the "Add Contact Person" button click.
   * Clones the first contact person row, resets input fields, and appends it to the contact persons group.
   */
  $("#addCP").click(function () {
    var CPGroup = $("#contactpersonsGroup");

    // First row to be used as a template
    var firstCPLine = CPGroup.children().first();

    // Clone the template
    var newCPRow = firstCPLine.clone();

    // Clear input fields and remove validation feedback
    newCPRow.find("input").val("").removeClass("is-invalid is-valid");
    newCPRow.find(".invalid-feedback, .valid-feedback").hide();

    var uniqueSuffix = new Date().getTime();
    newCPRow.find("#inputCPAffiliation").attr("id", "inputCPAffiliation" + uniqueSuffix);
    newCPRow.find("#hiddenCPRorId").attr("id", "hiddenCPRorId" + uniqueSuffix);

    // Remove old Tagify elements (will be re-initialized in autocompleteAffiliation)
    newCPRow.find(".tagify").remove();

    // Replace the add button with the remove button
    newCPRow.find(".addCP").replaceWith(removeButton);

    // Remove help buttons
    deleteHelpButtonFromClonedRows(newCPRow);

    CPGroup.append(newCPRow);

    // Apply autocomplete to the Affiliation field
    autocompleteAffiliations(
      "inputCPAffiliation" + uniqueSuffix,
      "hiddenCPRorId" + uniqueSuffix
    );

    // Event handler for the remove button
    newCPRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });

  /**
 * Event handler for the "Add Contributor Person" button click.
 * Clones the first contributor person row, resets input fields,
 * updates IDs and labels to ensure uniqueness,
 * and appends it to the contributors group.
 */
  $("#addContributorPerson").click(function () {
    /**
     * The contributors group where new contributor person rows are appended.
     * @type {jQuery}
     */
    var contributorGroup = $("#contributorsGroup");

    /**
     * The first contributor person row used as a template for cloning.
     * @type {jQuery}
     */
    var firstContributorRow = contributorGroup.children().first();

    // Clone the template
    var newContributorRow = firstContributorRow.clone();

    // Reset values and validation feedback in the cloned element
    newContributorRow.find("input").val("").removeClass("is-invalid is-valid");
    newContributorRow.find(".tagify").remove();
    newContributorRow.find(".invalid-feedback, .valid-feedback").hide();

    // Remove help buttons from the cloned row
    deleteHelpButtonFromClonedRows(newContributorRow);

    // Hide the row label in cloned rows
    newContributorRow.find(".row-label").hide();

    /**
     * Unique suffix to avoid duplicate IDs.
     * @type {number}
     */
    var uniqueSuffix = new Date().getTime();

    // Update the IDs of input fields to ensure uniqueness
    newContributorRow
      .find("#inputContributorAffiliation")
      .attr("id", "inputContributorAffiliation" + uniqueSuffix);
    newContributorRow
      .find("#hiddenContributorRorId")
      .attr("id", "hiddenContributorRorId" + uniqueSuffix);
    newContributorRow
      .find("#inputContributorsPerRole")
      .attr("id", "inputContributorsPerRole" + uniqueSuffix);
    newContributorRow
      .find("#inputContributorORCID")
      .attr("id", "inputContributorORCID" + uniqueSuffix);
    newContributorRow
      .find("#inputContributorLastname")
      .attr("id", "inputContributorLastname" + uniqueSuffix);
    newContributorRow
      .find("#inputContributorFirstname")
      .attr("id", "inputContributorFirstname" + uniqueSuffix);

    // Update the corresponding 'for' attributes in labels
    newContributorRow
      .find("label[for='inputContributorORCID']")
      .attr("for", "inputContributorORCID" + uniqueSuffix);
    newContributorRow
      .find("label[for='inputContributorLastname']")
      .attr("for", "inputContributorLastname" + uniqueSuffix);
    newContributorRow
      .find("label[for='inputContributorFirstname']")
      .attr("for", "inputContributorFirstname" + uniqueSuffix);

    // Replace the add button with the remove button
    newContributorRow.find(".addContributorPerson").replaceWith(removeButton);

    // Append the new contributor row to the DOM
    contributorGroup.append(newContributorRow);

    // Apply Tagify to the new Contributor Affiliations field
    autocompleteAffiliations(
      "inputContributorAffiliation" + uniqueSuffix,
      "hiddenContributorRorId" + uniqueSuffix,
      affiliationsData
    );

    // Initialize Tagify for the new Roles field
    setupRolesDropdown(["person", "both"], "#inputContributorsPerRole" + uniqueSuffix);

    // Event handler for the remove button in the new row
    newContributorRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });


  /**
 * Event handler for the "Add Contributor Organization" button click.
 * Clones the first contributor organization row, resets input fields,
 * updates IDs and labels to ensure uniqueness,
 * and appends it to the contributor organization group.
 */
  $("#addContributor").click(function () {
    /**
     * The contributor organization group where new contributor organization rows are appended.
     * @type {jQuery}
     */
    var contributorGroup = $("#contributorOrganisationGroup");

    /**
     * The first contributor organization row used as a template for cloning.
     * @type {jQuery}
     */
    var firstContributorRow = contributorGroup.children().first();

    // Clone the template
    var newContributorRow = firstContributorRow.clone();

    // Reset values and validation feedback in the cloned element
    newContributorRow.find("input").val("").removeClass("is-invalid is-valid");
    newContributorRow.find(".tagify").remove();
    newContributorRow.find(".invalid-feedback, .valid-feedback").hide();

    // Remove help buttons from the cloned row
    deleteHelpButtonFromClonedRows(newContributorRow);

    // Hide the row label in cloned rows
    newContributorRow.find(".row-label").hide();

    /**
     * Unique suffix to avoid duplicate IDs.
     * @type {number}
     */
    var uniqueSuffix = new Date().getTime();

    // Update the IDs of input fields to ensure uniqueness
    newContributorRow
      .find("#inputOrganisationAffiliation")
      .attr("id", "inputOrganisationAffiliation" + uniqueSuffix);
    newContributorRow
      .find("#hiddenOrganisationRorId")
      .attr("id", "hiddenOrganisationRorId" + uniqueSuffix);
    newContributorRow
      .find("#inputContributorOrgaRole")
      .attr("id", "inputContributorOrgaRole" + uniqueSuffix);
    newContributorRow
      .find("#inputOrganisationName")
      .attr("id", "inputOrganisationName" + uniqueSuffix);

    // Update the corresponding 'for' attributes in labels
    newContributorRow
      .find("label[for='inputOrganisationName']")
      .attr("for", "inputOrganisationName" + uniqueSuffix);

    // Replace the add button with the remove button
    newContributorRow.find(".addContributor").replaceWith(removeButton);

    // Append the new contributor row to the DOM
    contributorGroup.append(newContributorRow);

    // Apply Tagify to the new Organization Affiliations field
    autocompleteAffiliations(
      "inputOrganisationAffiliation" + uniqueSuffix,
      "hiddenOrganisationRorId" + uniqueSuffix,
      affiliationsData
    );

    // Initialize Tagify for the new Roles field
    setupRolesDropdown(["institution", "both"], "#inputContributorOrgaRole" + uniqueSuffix);

    // Event handler for the remove button in the new row
    newContributorRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });


  /**
   * Event handler for the "Add TSC" button click.
   * Clones the first TSC row, resets input fields, and appends it to the TSC group.
   */
  $("#tscAddButton").click(function () {
    var tscGroup = $("#tscGroup");
    var firsttscLine = tscGroup.children().first();

    // Finding highest ID
    var maxId = 0;
    tscGroup.find("[tsc-row]").each(function () {
      var currentId = parseInt($(this).attr("tsc-row-id"));
      if (currentId > maxId) maxId = currentId;
    });

    // Clone the template
    var newtscLine = firsttscLine.clone();

    // Increment the ID for the new TSC line
    var newtscLineId = (maxId + 1).toString();
    newtscLine.attr("tsc-row-id", newtscLineId);

    // Reset values and validation feedback for all form elements
    newtscLine.find("input, select, textarea").val("").removeClass("is-invalid is-valid");
    newtscLine.find(".invalid-feedback, .valid-feedback").hide();

    // Remove help buttons
    deleteHelpButtonFromClonedRows(newtscLine);

    // Replace add button with remove button
    newtscLine.find("#tscAddButton").replaceWith(removeButton);

    // Append the new TSC line
    tscGroup.append(newtscLine);

    // Event handler for remove button
    newtscLine.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });

  /**
   * Event handler for the "Add Related Work" button click.
   * Clones the first related work row, resets input fields, and appends it to the related work group.
   */
  $("#addRelatedWork").click(function () {
    var relatedworkGroup = $("#relatedworkGroup");
    // First row used as a template
    var firstRelatedWorkLine = relatedworkGroup.children().first();

    // Clone the template
    var newRelatedWorkRow = firstRelatedWorkLine.clone();

    // Clear input fields and remove validation feedback
    newRelatedWorkRow.find("input").val("").removeClass("is-invalid");
    newRelatedWorkRow.find(".invalid-feedback").hide();

    // Remove help buttons in cloned row
    deleteHelpButtonFromClonedRows(newRelatedWorkRow);

    // Replace the add button with the remove button
    newRelatedWorkRow.find("#addRelatedWork").replaceWith(removeButton);

    // Append the new related work row to the DOM
    relatedworkGroup.append(newRelatedWorkRow);

    // Event handler for the remove button
    newRelatedWorkRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });

  /**
   * Event handler for the "Add Funding Reference" button click.
   * Clones the first funding reference row, resets input fields, and appends it to the funding reference group.
   */
  $("#addFundingReference").click(function () {
    var fundingreferenceGroup = $("#fundingreferenceGroup");
    // First row used as a template
    var firstFundingReferenceLine = fundingreferenceGroup.children().first();

    // Clone the template
    var newFundingReferenceRow = firstFundingReferenceLine.clone();

    // Clear input fields and remove validation feedback
    newFundingReferenceRow.find("input").val("").removeClass("is-invalid");
    newFundingReferenceRow.find(".invalid-feedback").hide();

    // Replace the add button with the remove button
    newFundingReferenceRow.find(".addFundingReference").replaceWith(removeButton);

    // Append the new funding reference row to the DOM
    fundingreferenceGroup.append(newFundingReferenceRow);

    // Remove help buttons in cloned row
    deleteHelpButtonFromClonedRows(newFundingReferenceRow);

    // Event handler for the remove button
    newFundingReferenceRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });

    // Initialize autocomplete for the new input field
    setUpAutocompleteFunder(newFundingReferenceRow.find(".inputFunder"));

    setUpAutocompleteFunder();
  });

  var labData;

  // Load lab data from JSON and initialize Tagify on the first laboratory row
  $.getJSON("json/msl-labs.json", function (data) {
    labData = data;
    var firstRow = $("#laboratoryGroup .row").first();
    initializeTagify(firstRow, data);
  });

  var rowCounter = 1;

  /**
   * Event handler for the "Add Laboratory" button click.
   * Clones the first laboratory row, resets input fields, updates IDs, and appends it to the laboratory group.
   */
  $("#addLaboratory").click(function () {
    var laboratoryGroup = $("#laboratoryGroup");
    var firstOriginatingLaboratoryLine = laboratoryGroup.children().first();

    var newOriginatingLaboratoryRow = firstOriginatingLaboratoryLine.clone();

    // Clear input fields and remove validation feedback
    newOriginatingLaboratoryRow.find("input").val("").removeClass("is-invalid is-valid");
    newOriginatingLaboratoryRow.find(".invalid-feedback, .valid-feedback").hide();

    // Remove old Tagify elements
    newOriginatingLaboratoryRow.find(".tagify").remove();

    // Update IDs
    rowCounter++;
    newOriginatingLaboratoryRow.find("[id]").each(function () {
      var oldId = $(this).attr("id");
      var newId = oldId + "_" + rowCounter;
      $(this).attr("id", newId);
    });

    // Replace the add button with the remove button
    newOriginatingLaboratoryRow.find(".addLaboratory").replaceWith(removeButton);

    // Append the new laboratory row to the DOM
    laboratoryGroup.append(newOriginatingLaboratoryRow);

    // Remove help buttons in cloned row
    deleteHelpButtonFromClonedRows(newOriginatingLaboratoryRow);

    // Initialize Tagify for the new row
    initializeTagify(newOriginatingLaboratoryRow, labData);

    // Event handler for the remove button
    newOriginatingLaboratoryRow.on("click", ".removeButton", function () {
      $(this).closest(".row").remove();
    });
  });

  /**
   * Initializes Tagify on the laboratory name and affiliation fields.
   *
   * @param {jQuery} row - The row element containing the input fields.
   * @param {Object[]} data - The lab data array used for autocompletion.
   * @returns {Object} - An object containing the Tagify instances for name and affiliation fields.
   */
  function initializeTagify(row, data) {
    var inputName = row.find('input[name="laboratoryName[]"]')[0];
    var inputAffiliation = row.find('input[name="laboratoryAffiliation[]"]')[0];
    var hiddenRorId = row.find('input[name="laboratoryRorIds[]"]')[0];
    var hiddenLabId = row.find('input[name="LabId[]"]')[0];

    /**
     * Finds a lab object by its name.
     *
     * @param {string} name - The name of the lab to find.
     * @returns {Object|undefined} - The lab object if found, otherwise undefined.
     */
    function findLabByName(name) {
      return data.find((lab) => lab.name === name);
    }

    var tagifyName = new Tagify(inputName, {
      whitelist: data.map((item) => item.name),
      enforceWhitelist: true,
      maxTags: 1,
      dropdown: {
        maxItems: 20,
        closeOnSelect: true,
        highlightFirst: true,
      },
      delimiters: null,
      mode: "select",
    });

    var tagifyAffiliation = new Tagify(inputAffiliation, {
      whitelist: data.map((item) => item.affiliation),
      enforceWhitelist: true,
      maxTags: 1,
      dropdown: {
        maxItems: 20,
        closeOnSelect: true,
        highlightFirst: true,
      },
      delimiters: null,
      mode: "select",
    });

    tagifyName.on("add", function (e) {
      var labName = e.detail.data.value;
      var lab = findLabByName(labName);
      if (lab) {
        tagifyAffiliation.removeAllTags();
        tagifyAffiliation.addTags([lab.affiliation]);
        hiddenRorId.value = lab.ror_id || "";
        hiddenLabId.value = lab.id;
        tagifyAffiliation.setReadOnly(true);
      } else {
        tagifyAffiliation.removeAllTags();
        hiddenRorId.value = "";
        hiddenLabId.value = "";
        tagifyAffiliation.setReadOnly(false);
      }
    });

    tagifyName.on("remove", function () {
      tagifyAffiliation.removeAllTags();
      hiddenRorId.value = "";
      hiddenLabId.value = "";
      tagifyAffiliation.setReadOnly(false);
    });

    tagifyName.on("input", function (e) {
      var value = e.detail.value;
      if (value) {
        var lab = findLabByName(value);
        if (!lab) {
          tagifyAffiliation.removeAllTags();
          hiddenRorId.value = "";
          tagifyAffiliation.setReadOnly(false);
        }
      }
    });

    tagifyAffiliation.on("input", function (e) {
      var value = e.detail.value;
      if (value && !tagifyAffiliation.state.readonly) {
        tagifyAffiliation.addTags([value]);
      }
    });

    return { tagifyName, tagifyAffiliation };
  }

  /////////////////////////////// HELP BUTTONS /////////////////////////////////////////////////////////////////

  /**
   * Removes help buttons from cloned rows and adjusts CSS classes for input elements.
   *
   * @param {jQuery} row - The cloned row from which to remove help buttons.
   * @param {string} [roundCornersClass="input-right-with-round-corners"] - The CSS class to add to input elements.
   */
  function deleteHelpButtonFromClonedRows(
    row,
    roundCornersClass = "input-right-with-round-corners"
  ) {
    row.find("span.input-group-text:has(i.bi-question-circle-fill)").each(function () {
      $(this).remove();
    });

    row
      .find(".input-with-help")
      .removeClass("input-right-no-round-corners")
      .addClass(roundCornersClass);
  }

  let hoverCount = 0;
  let timer = null;

  /**
   * Resets the hover count to zero.
   */
  function resetHoverCount() {
    hoverCount = 0;
  }

  /**
   * Event handler for hover over help buttons.
   * Tracks hover events and opens an Easter egg if hovered over 30 times within 1 second intervals.
   */
  $("#buttonHelp, #bd-theme").hover(function () {
    hoverCount++;

    if (hoverCount === 30) {
      window.open(
        "doc/egg.html",
        "Egg",
        "width=650,height=450,scrollbars=no,resizable=no,location=no"
      );
      resetHoverCount();
    }

    clearTimeout(timer);
    timer = setTimeout(resetHoverCount, 1000); // Set timer to reset hover count after 1 second
  });

  // Check if the input group text visibility setting is saved
  if (localStorage.getItem("inputGroupTextVisible") === "false") {
    $(".input-group-text").hide();
  }

  /**
   * Event handler to show help elements when the "Help On" button is clicked.
   */
  $("#buttonHelpOn").click(function () {
    $(".input-group-text").show();
    localStorage.setItem("inputGroupTextVisible", "true");
  });

  /**
   * Event handler to hide help elements when the "Help Off" button is clicked.
   */
  $("#buttonHelpOff").click(function () {
    $(".input-group-text").hide();
    localStorage.setItem("inputGroupTextVisible", "false");
  });

  //////////////////////////////// LANGUAGE BUTTONS ///////////////////////////////////////////////////////////

  /**
   * Sets the language preference in localStorage and reloads the page with the selected language.
   *
   * @param {string} language - The language code to set (e.g., 'en', 'de').
   */
  function setLanguage(language) {
    localStorage.setItem("userLanguage", language);
    window.location.href = "?lang=" + language;
  }

  /**
   * Automatically sets the language based on the browser's language settings.
   */
  function setAutoLanguage() {
    var userLang = navigator.language || navigator.userLanguage;
    userLang = userLang.substring(0, 2);
    if (userLang !== "en" && userLang !== "de") {
      userLang = "en"; // Default to English if the language is not supported
    }
    localStorage.setItem("userLanguage", userLang);
    window.location.href = "?lang=" + userLang;
  }

  // Check if a language is set in localStorage; if not, default to English
  if (!localStorage.getItem("userLanguage")) {
    localStorage.setItem("userLanguage", "en");
  }

  /**
   * Event handler for clicks on language selection buttons.
   * Sets the language or auto-detects it based on browser settings.
   */
  $("[data-bs-language-value]").click(function (event) {
    event.preventDefault();
    var language = $(this).data("bs-language-value");
    if (language === "auto") {
      setAutoLanguage();
    } else {
      setLanguage(language);
    }
  });

  // Initialize tooltips
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  const tooltipList = [...tooltipTriggerList].map(
    (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl)
  );
});
