<?php
// Example controller for the home page.
// Defines handle_request($action, $request, $params)

function handle_request($action, $request, $params)
{
    if ($action === 'index' || $action === '') {
        // Render the main page view. You can pass data as associative array.
        render_view('main_page.html', ['title' => 'Main Page']);
        return null; // indicate response already sent
    }

    // Unknown action: return 404
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    echo 'Not found';
    return null;
}
