<?php

function record_site_analytics(
    PDO $connection,
    string $eventType = 'page_view',
    ?string $pagePath = null
): bool
{
    if (!in_array(
        $eventType,
        ['page_view', 'assistant_message', 'stayed_30_sec', 'scrolled_to_end'],
        true
    )) {
        throw new InvalidArgumentException('Unsupported analytics event type.');
    }

    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    if (
        ($eventType === 'page_view' && $requestMethod !== 'GET') ||
        ($eventType !== 'page_view' && $requestMethod !== 'POST')
    ) {
        return false;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!is_string($scriptName) || strpos(str_replace('\\', '/', $scriptName), '/admin/') !== false) {
        return false;
    }

    $visitorId = $_COOKIE['site_visitor_id'] ?? '';
    if (!is_string($visitorId) || preg_match('/^[a-f0-9]{64}$/', $visitorId) !== 1) {
        $visitorId = bin2hex(random_bytes(32));
        setcookie('site_visitor_id', $visitorId, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    if ($pagePath === null && $eventType === 'page_view') {
        $pagePath = '/' . ltrim(str_replace('\\', '/', $scriptName), '/');
    }

    try {
        $statement = $connection->prepare(
            'INSERT INTO site_analytics_events (visitor_hash, event_type, page_path)
             VALUES (:visitor_hash, :event_type, :page_path)'
        );
        $statement->execute([
            ':visitor_hash' => hash('sha256', $visitorId),
            ':event_type' => $eventType,
            ':page_path' => $pagePath !== null ? substr($pagePath, 0, 190) : null
        ]);
        return true;
    } catch (PDOException $exception) {
        error_log('Site analytics event could not be recorded: ' . $exception->getMessage());
        return false;
    }
}
