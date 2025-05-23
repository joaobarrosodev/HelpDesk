<?php
session_start();

echo "<h1>Session Debug Information</h1>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Status:</strong> " . session_status() . "<br>";
echo "<strong>Session Data:</strong><pre>" . print_r($_SESSION, true) . "</pre>";
echo "<strong>Current URL:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<strong>Referrer:</strong> " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'None') . "<br>";

if (isset($_SESSION['usuario_id'])) {
    echo "<p style='color: green;'>✅ User is logged in</p>";
} else {
    echo "<p style='color: red;'>❌ User is NOT logged in</p>";
}
?>
