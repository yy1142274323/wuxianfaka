<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function app_installed(): bool
{
    return is_file(__DIR__ . '/../config.php') && filesize(__DIR__ . '/../config.php') > 0;
}

function setting(array $settings, string $key, string $default = ''): string
{
    return isset($settings[$key]) ? (string)$settings[$key] : $default;
}

function load_settings(PDO $pdo): array
{
    $settings = [];
    foreach ($pdo->query('SELECT `k`, `v` FROM settings') as $row) {
        $settings[$row['k']] = $row['v'];
    }
    return $settings;
}

function save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('REPLACE INTO settings (`k`, `v`) VALUES (?, ?)');
    $stmt->execute([$key, $value]);
}

function generate_order_no(): string
{
    return date('YmdHis') . random_int(100000, 999999);
}

function product_stock(PDO $pdo, array $product): int
{
    if ((int)$product['type'] === 1) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE pid = ?');
        $stmt->execute([$product['id']]);
        return $stmt->fetchColumn() ? 999 : 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE pid = ? AND status = 0');
    $stmt->execute([$product['id']]);
    return (int)$stmt->fetchColumn();
}

function deliver_paid_order(PDO $pdo, string $orderNo): array
{
    $orderNo = trim($orderNo);
    if ($orderNo === '') {
        return ['ok' => false, 'message' => '订单号为空'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE out_trade_no = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderNo]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '订单不存在'];
        }

        $productStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $productStmt->execute([$order['pid']]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '商品不存在'];
        }

        if ((int)$product['type'] === 0) {
            $bound = $pdo->prepare('SELECT id FROM cards WHERE order_id = ? LIMIT 1');
            $bound->execute([$orderNo]);
            if (!$bound->fetchColumn()) {
                $card = $pdo->prepare('SELECT id FROM cards WHERE pid = ? AND status = 0 ORDER BY id ASC LIMIT 1 FOR UPDATE');
                $card->execute([$order['pid']]);
                $cardId = $card->fetchColumn();
                if (!$cardId) {
                    $pdo->rollBack();
                    return ['ok' => false, 'message' => '库存不足，未发货'];
                }

                $bind = $pdo->prepare('UPDATE cards SET status = 1, order_id = ? WHERE id = ?');
                $bind->execute([$orderNo, $cardId]);
            }
        } else {
            $card = $pdo->prepare('SELECT id FROM cards WHERE pid = ? ORDER BY id ASC LIMIT 1');
            $card->execute([$order['pid']]);
            if (!$card->fetchColumn()) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => '循环卡密未配置'];
            }
        }

        if ((int)$order['status'] !== 1) {
            $update = $pdo->prepare('UPDATE orders SET status = 1, pay_time = ? WHERE id = ?');
            $update->execute([time(), $order['id']]);
        }

        $pdo->commit();
        return ['ok' => true, 'message' => '订单已确认并发货'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => '发货失败'];
    }
}

function order_cards(PDO $pdo, array $order): array
{
    if ((int)$order['status'] !== 1) {
        return [];
    }

    $productStmt = $pdo->prepare('SELECT type FROM products WHERE id = ? LIMIT 1');
    $productStmt->execute([$order['pid']]);
    $type = (int)$productStmt->fetchColumn();

    if ($type === 1) {
        $stmt = $pdo->prepare('SELECT card_info FROM cards WHERE pid = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$order['pid']]);
    } else {
        $stmt = $pdo->prepare('SELECT card_info FROM cards WHERE order_id = ? ORDER BY id ASC');
        $stmt->execute([$order['out_trade_no']]);
    }

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function format_time(?int $timestamp): string
{
    return $timestamp ? date('Y-m-d H:i', $timestamp) : '-';
}

function short_text(string $value, int $width = 48): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $width, '...', 'UTF-8');
    }
    return strlen($value) > $width ? substr($value, 0, $width - 3) . '...' : $value;
}

function flash_set(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
