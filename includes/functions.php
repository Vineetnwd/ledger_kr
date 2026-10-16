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

/**
 * Calculate pagination offsets and counts
 */
function get_pagination_params($total_records, $default_limit = 20, $page_param = 'p', $limit_param = 'limit')
{
    $limit = isset($_GET[$limit_param]) ? (int)$_GET[$limit_param] : $default_limit;
    if ($limit < 5 || $limit > 200) {
        $limit = $default_limit;
    }

    $total_pages = $total_records > 0 ? (int)ceil($total_records / $limit) : 1;
    $current_page = isset($_GET[$page_param]) ? (int)$_GET[$page_param] : 1;
    if ($current_page < 1) {
        $current_page = 1;
    }
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    $offset = ($current_page - 1) * $limit;

    return [
        'limit'         => $limit,
        'page'          => $current_page,
        'total_pages'   => $total_pages,
        'total_records' => $total_records,
        'offset'        => $offset,
        'start_record'  => $total_records > 0 ? $offset + 1 : 0,
        'end_record'    => min($offset + $limit, $total_records),
        'page_param'    => $page_param,
        'limit_param'   => $limit_param
    ];
}

/**
 * Render responsive pagination HTML with record counts, per-page selector, and navigation
 */
function render_pagination_bar($p, $page_param = 'p', $limit_param = 'limit')
{
    if ($p['total_records'] == 0) {
        return '';
    }

    // Build URL helper preserving all current GET query parameters
    $build_url = function($page_num) use ($page_param) {
        $params = $_GET;
        $params[$page_param] = $page_num;
        return '?' . http_build_query($params);
    };

    $html = '<div class="pagination-container">';
    
    // Left: Record counter and Per-Page selector
    $html .= '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">';
    $html .= '<div class="pagination-info">Showing <strong>' . $p['start_record'] . '</strong>&ndash;<strong>' . $p['end_record'] . '</strong> of <strong>' . number_format($p['total_records']) . '</strong> records</div>';
    
    // Quick per-page dropdown form
    $html .= '<form method="GET" class="pagination-size-form" style="display:inline-flex;align-items:center;gap:6px;margin:0;">';
    foreach ($_GET as $k => $v) {
        if ($k !== $limit_param && $k !== $page_param && is_scalar($v)) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
        }
    }
    $html .= '<span class="text-muted fs-11" style="font-weight:600;">PER PAGE:</span>';
    $html .= '<select name="' . htmlspecialchars($limit_param) . '" class="no-tomselect pagination-limit-select" onchange="this.form.submit()">';
    foreach ([5, 10, 20, 50, 100] as $lim) {
        $html .= '<option value="' . $lim . '" ' . ($p['limit'] == $lim ? 'selected' : '') . '>' . $lim . '</option>';
    }
    $html .= '</select>';
    $html .= '</form>';
    $html .= '</div>';

    // Right: Page buttons
    if ($p['total_pages'] > 1) {
        $html .= '<div class="pagination-controls">';

        // First & Prev
        if ($p['page'] > 1) {
            $html .= '<a href="' . htmlspecialchars($build_url(1)) . '" class="page-btn" title="First Page">&laquo;</a>';
            $html .= '<a href="' . htmlspecialchars($build_url($p['page'] - 1)) . '" class="page-btn" title="Previous Page">&lsaquo;</a>';
        } else {
            $html .= '<span class="page-btn disabled">&laquo;</span>';
            $html .= '<span class="page-btn disabled">&lsaquo;</span>';
        }

        // Windowed page numbers
        $start_p = max(1, $p['page'] - 2);
        $end_p   = min($p['total_pages'], $p['page'] + 2);

        if ($start_p > 1) {
            $html .= '<a href="' . htmlspecialchars($build_url(1)) . '" class="page-btn">1</a>';
            if ($start_p > 2) {
                $html .= '<span class="page-btn disabled" style="border:none;background:transparent;">&hellip;</span>';
            }
        }

        for ($i = $start_p; $i <= $end_p; $i++) {
            if ($i == $p['page']) {
                $html .= '<span class="page-btn active">' . $i . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($build_url($i)) . '" class="page-btn">' . $i . '</a>';
            }
        }

        if ($end_p < $p['total_pages']) {
            if ($end_p < $p['total_pages'] - 1) {
                $html .= '<span class="page-btn disabled" style="border:none;background:transparent;">&hellip;</span>';
            }
            $html .= '<a href="' . htmlspecialchars($build_url($p['total_pages'])) . '" class="page-btn">' . $p['total_pages'] . '</a>';
        }

        // Next & Last
        if ($p['page'] < $p['total_pages']) {
            $html .= '<a href="' . htmlspecialchars($build_url($p['page'] + 1)) . '" class="page-btn" title="Next Page">&rsaquo;</a>';
            $html .= '<a href="' . htmlspecialchars($build_url($p['total_pages'])) . '" class="page-btn" title="Last Page">&raquo;</a>';
        } else {
            $html .= '<span class="page-btn disabled">&rsaquo;</span>';
            $html .= '<span class="page-btn disabled">&raquo;</span>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
