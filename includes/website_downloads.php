<?php
// Shared by the admin form and public download buttons.
function website_download_url($value) {
    if (!is_string($value)) throw new InvalidArgumentException('Link tải phải là chuỗi URL.');
    $url = trim($value);
    if ($url === '') return '';
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Link tải phải bắt đầu bằng http:// hoặc https://.');
    }
    return $url;
}

function website_read_settings($db) {
    $result = $db->query('SELECT * FROM settings LIMIT 1');
    if (!$result) throw new RuntimeException('Không thể đọc cài đặt Website.');
    return $result->fetch_assoc() ?: [];
}

function website_save_settings($db, array $values) {
    foreach (['Android', 'Windows', 'IPhone'] as $platform) {
        $values[$platform] = website_download_url($values[$platform] ?? '');
    }
    // Determine whether UPDATE or INSERT is needed; unchanged values are also successful.
    $existing = website_read_settings($db);
    $sql = $existing
        ? 'UPDATE settings SET Title = ?, ServerName = ?, Fanpage = ?, Zalo = ?, Android = ?, Windows = ?, IPhone = ? LIMIT 1'
        : 'INSERT INTO settings (Title, ServerName, Fanpage, Zalo, Android, Windows, IPhone) VALUES (?, ?, ?, ?, ?, ?, ?)';
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Không thể lưu cài đặt Website.');
    try {
        $stmt->bind_param('sssssss', $values['Title'], $values['ServerName'], $values['Fanpage'], $values['Zalo'], $values['Android'], $values['Windows'], $values['IPhone']);
        if (!$stmt->execute()) throw new RuntimeException('Không thể lưu cài đặt Website.');
    } finally {
        $stmt->close();
    }
}
