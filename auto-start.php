<?php
/**
 * Simplified auto-start for WebSocket server
 */

// Only start a session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a flag indicating this file was loaded
define('AUTO_START_LOADED', true);
?>
