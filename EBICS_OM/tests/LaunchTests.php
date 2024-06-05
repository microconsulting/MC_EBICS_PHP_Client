<?php
include_once("ConnectionTests.php");

// Launch the connection tests
echo "*** Connection Tests *** <BR><BR>";
$total_tests = 20;
$passed_tests = 0;
$test = new ConnectionTests();

// ----------------- Tests no parameter -----------------

// Test n°1 : execution of the INI script without a credentials file in parameter
$result = $test->testInvalidNumberParameter('INI', ["EBICS_OM/INI.php"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°1. <BR>";
}

// Test n°2 : execution of the HIA script without a credentials file in parameter
$result = $test->testInvalidNumberParameter('HIA', ["EBICS_OM/HIA.php"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°2. <BR>";
}

// Test n°3 : execution of the HPB script without a credentials file in parameter
$result = $test->testInvalidNumberParameter('HPB', ["EBICS_OM/HBP.php"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°3. <BR>";
}


// ----------------- Tests to much parameters -----------------

// Test n°4 : execution of the INI script without to much parameters
$result = $test->testInvalidNumberParameter('INI', ["EBICS_OM/INI.php", "0", "1"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°4. <BR>";
}

// Test n°5 : execution of the HIA script without to much parameters
$result = $test->testInvalidNumberParameter('HIA', ["EBICS_OM/HIA.php", "0", "1"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°5. <BR>";
}

// Test n°6 : execution of the HPB script without to much parameters
$result = $test->testInvalidNumberParameter('HPB', ["EBICS_OM/HPB.php", "0", "1"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°6. <BR>";
}


// ----------------- Tests wrong value in parameter -----------------

// Test n°7 : execution of the INI script with 0 in parameter
$result = $test->testIncorrectValueParameter('INI', ["EBICS_OM/INI.php", "0"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°7. <BR>";
}

// Test n°8 : execution of the HIA script with a string in parameter
$result = $test->testIncorrectValueParameter('HIA', ["EBICS_OM/HIA.php", "abc"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°8. <BR>";
}

// Test n°8 : execution of the HPB script with a space in parameter
$result = $test->testIncorrectValueParameter('HPB', ["EBICS_OM/HPB.php", " "]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°9. <BR>";
}


// ----------------- Tests file not found in parameter -----------------

// Test n°10 : execution of the INI script with 0 in parameter
$result = $test->testFileNotFoundParameter('INI', ["EBICS_OM/INI.php", "99"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°10. <BR>";
}

// Test n°11 : execution of the HIA script with a string in parameter
$result = $test->testFileNotFoundParameter('HIA', ["EBICS_OM/HIA.php", "99"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°11. <BR>";
}

// Test n°12 : execution of the HPB script with a space in parameter
$result = $test->testFileNotFoundParameter('HPB', ["EBICS_OM/HPB.php", "99"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°12. <BR>";
}


// ----------------- Tests file incomplete in parameter -----------------

// Test n°13 : execution of the INI script with an incomplete credential file
$result = $test->testMissValue('INI', ["EBICS_OM/INI.php", "11"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°13. <BR>";
}

// Test n°14 : execution of the HIA script with an incomplete credential file
$result = $test->testMissValue('HIA', ["EBICS_OM/HIA.php", "11"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°14. <BR>";
}

// Test n°15 : execution of the HPB script with an incomplete credential file
$result = $test->testMissValue('HPB', ["EBICS_OM/HPB.php", "11"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°15. <BR>";
}


// ----------------- Tests file with wrong value in parameter -----------------

// Test n°16 : execution of the INI script with wrong HostID
$result = $test->testWrongValue('INI', ["EBICS_OM/INI.php", "12"]);
if ($result==1) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°16. <BR>";
}

// Test n°17 : execution of the HIA script with wrong url
$result = $test->testWrongValue('HIA', ["EBICS_OM/INI.php", "13"]);
if ($result==1) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°17. <BR>";
}

// Test n°18 : execution of the INI script with wrong PartnerID
$result = $test->testWrongValue('INI', ["EBICS_OM/INI.php", "14"]);
if ($result==2) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°18. <BR>";
}


// ----------------- Tests resend INI and HIA order -----------------

// Test n°19 : resend INI order
$result = $test->testResendOrder('INI', ["EBICS_OM/INI.php", "15"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°19. <BR>";
}

// Test n°20 : resend HIA order
$result = $test->testResendOrder('HIA', ["EBICS_OM/HIA.php", "15"]);
if ($result) {
    $passed_tests++;
}
else {
    echo "Erreur dans le test n°20. <BR>";
}

echo "<BR> TOTAL : ", $passed_tests, "/", $total_tests, " <BR>";

?>