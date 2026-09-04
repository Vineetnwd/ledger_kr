<?php
/**
 * KRNC Expense Ledger — Helper Functions
 */

/**
 * Return a formatted currency string (₹)
 */
function format_currency($amount)
{
    return '₹' . number_format($amount, 2);
}

/**
 * Return a friendly formatted date string
 */
function format_date($date_string)
{
    return date('d M Y', strtotime($date_string));
}

/**
 * Get current user's session ID
 */
function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Redirect with a flash message
 */
function redirect_with_message($url, $type, $message)
{
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit();
}
