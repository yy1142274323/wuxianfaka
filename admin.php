<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_admin();

if (isset($_GET['logout'])) {
    logout_admin();
    redirect('admin_login.php');
}

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'settings', 'products', 'cards', 'orders'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_settings') {
            foreach (['site_name', 'site_title', 'site_description', 'notice', 'theme_color'] as $key) {
                save_setting($pdo, $key, trim((string)($_POST[$key] ?? '')));
            }
            flash_set('站点设置已保存。');
            redirect('admin.php?page=settings');
        }

        if ($action === 'add_product' || $action === 'edit_product') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            $type = (int)($_POST['type'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '' || $price < 0) {
                throw new RuntimeException('商品名称和价格不正确。');
            }

            if ($action === 'add_product') {
                $stmt = $pdo->prepare('INSERT INTO products (name, description, price, type, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $description, $price, $type, $active, time()]);
                flash_set('商品已添加。');
            } else {
                $stmt = $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, type = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$name, $description, $price, $type, $active, (int)$_POST['id']]);
                flash_set('商品已更新。');
            }
            redirect('admin.php?page=products');
        }

        if ($action === 'delete_product') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM cards WHERE pid = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            flash_set('商品和对应卡密已删除。');
            redirect('admin.php?page=products');
        }

        if ($action === 'add_cards') {
            $pid = (int)($_POST['pid'] ?? 0);
            $lines = preg_split('/\R/u', (string)($_POST['content'] ?? '')) ?: [];
            $stmt = $pdo->prepare('INSERT INTO cards (pid, card_info, status, created_at) VALUES (?, ?, 0, ?)');
            $count = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $stmt->execute([$pid, $line, time()]);
                $count++;
            }
            flash_set('已导入 ' . $count . ' 条卡密。');
            redirect('admin.php?page=cards');
        }

        if ($action === 'delete_card') {
            $pdo->prepare('DELETE FROM cards WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            flash_set('卡密已删除。');
            redirect('admin.php?page=cards');
        }

        if ($action === 'confirm_order') {
            $result = deliver_paid_order($pdo, (string)($_POST['order_no'] ?? ''));
            flash_set($result['message'], $result['ok'] ? 'success' : 'error');
            redirect('admin.php?page=orders');
        }

        if ($action === 'delete_order') {
            $orderNo = (string)($_POST['order_no'] ?? '');
            $pdo->prepare('UPDATE cards SET status = 0, order_id = NULL WHERE order_id = ?')->execute([$orderNo]);
            $pdo->prepare('DELETE FROM orders WHERE out_trade_no = ?')->execute([$orderNo]);
            flash_set('订单已删除，相关一次性卡密已释放。');
            redirect('admin.php?page=orders');
        }
    } catch (Throwable $e) {
        flash_set($e->getMessage(), 'error');
        redirect('admin.php?page=' . urlencode($page));
    }
}

$settings = load_settings($pdo);
$flash = flash_get();
$siteName = setting($settings, 'site_name', 'OOOAI 发卡');
$today = strtotime(date('Y-m-d'));
$todayIncome = $pdo->query('SELECT COALESCE(SUM(money), 0) FROM orders WHERE status = 1 AND pay_time >= ' . (int)$today)->fetchColumn();
$todayOrders = $pdo->query('SELECT COUNT(*) FROM orders WHERE create_time >= ' . (int)$today)->fetchColumn();
$stockTotal = $pdo->query('SELECT COUNT(*) FROM cards WHERE status = 0')->fetchColumn();
$pendingOrders = $pdo->query('SELECT COUNT(*) FROM orders WHERE status = 0')->fetchColumn();
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理后台 - <?php echo e($siteName); ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="admin-body">
<aside class="admin-sidebar">
    <a class="brand admin-brand" href="admin.php"><span class="brand-icon">O</span><span><?php echo e($siteName); ?></span></a>
    <nav>
        <a class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>" href="admin.php?page=dashboard">仪表盘</a>
        <a class="<?php echo $page === 'settings' ? 'active' : ''; ?>" href="admin.php?page=settings">站点设置</a>
        <a class="<?php echo $page === 'products' ? 'active' : ''; ?>" href="admin.php?page=products">商品管理</a>
        <a class="<?php echo $page === 'cards' ? 'active' : ''; ?>" href="admin.php?page=cards">卡密库存</a>
        <a class="<?php echo $page === 'orders' ? 'active' : ''; ?>" href="admin.php?page=orders">订单管理</a>
    </nav>
    <div class="sidebar-foot">
        <a href="index.php" target="_blank">打开前台</a>
        <a href="admin.php?logout=1">退出登录</a>
    </div>
</aside>

<main class="admin-main">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>

    <?php if ($page === 'dashboard'): ?>
        <section class="admin-title"><h1>仪表盘</h1><p>空白安装版不含支付接口，订单由后台确认后发货。</p></section>
        <div class="metric-grid">
            <article><span>今日确认金额</span><strong>¥<?php echo e((string)$todayIncome); ?></strong></article>
            <article><span>今日订单</span><strong><?php echo (int)$todayOrders; ?></strong></article>
            <article><span>待确认</span><strong><?php echo (int)$pendingOrders; ?></strong></article>
            <article><span>可用卡密</span><strong><?php echo (int)$stockTotal; ?></strong></article>
        </div>
        <section class="panel">
            <div class="panel-head"><h2>最近订单</h2><a href="admin.php?page=orders">查看全部</a></div>
            <?php
            $recentOrders = $pdo->query('SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN products p ON p.id = o.pid ORDER BY o.id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
            include __DIR__ . '/includes/orders_table.php';
            ?>
        </section>

    <?php elseif ($page === 'settings'): ?>
        <section class="admin-title"><h1>站点设置</h1><p>这里不包含任何支付设置，适合公开源码分发。</p></section>
        <section class="panel">
            <form method="post" class="form-grid wide">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_settings">
                <label>网站名称<input name="site_name" value="<?php echo e(setting($settings, 'site_name', 'OOOAI 发卡')); ?>"></label>
                <label>浏览器标题<input name="site_title" value="<?php echo e(setting($settings, 'site_title', 'OOOAI 发卡系统')); ?>"></label>
                <label>主题色<input name="theme_color" value="<?php echo e(setting($settings, 'theme_color', '#176B87')); ?>"></label>
                <label class="span-2">SEO 描述<input name="site_description" value="<?php echo e(setting($settings, 'site_description', '轻量级自动发卡系统。')); ?>"></label>
                <label class="span-2">公告<textarea name="notice" rows="4"><?php echo e(setting($settings, 'notice', '欢迎光临，请选择商品并提交订单。')); ?></textarea></label>
                <button class="primary-btn">保存设置</button>
            </form>
        </section>

    <?php elseif ($page === 'products'): ?>
        <section class="admin-title"><h1>商品管理</h1><p>添加商品后，再到卡密库存导入对应内容。</p></section>
        <div class="admin-grid two">
            <section class="panel">
                <h2>添加商品</h2>
                <form method="post" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_product">
                    <label>名称<input name="name" required></label>
                    <label>价格<input name="price" type="number" step="0.01" min="0" required></label>
                    <label>类型<select name="type"><option value="0">一次性卡密</option><option value="1">循环卡密</option></select></label>
                    <label class="check-row"><input type="checkbox" name="is_active" checked> 上架显示</label>
                    <label class="span-2">描述<textarea name="description" rows="3"></textarea></label>
                    <button class="primary-btn">添加商品</button>
                </form>
            </section>
            <section class="panel">
                <h2>商品列表</h2>
                <?php $products = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM cards WHERE pid = p.id AND status = 0) AS stock FROM products p ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC); ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>ID</th><th>名称</th><th>价格</th><th>库存</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo (int)$product['id']; ?></td>
                                <td><?php echo e($product['name']); ?></td>
                                <td>¥<?php echo e((string)$product['price']); ?></td>
                                <td><?php echo (int)$product['stock']; ?></td>
                                <td><?php echo (int)$product['is_active'] ? '上架' : '隐藏'; ?></td>
                                <td>
                                    <details class="row-actions">
                                        <summary>编辑</summary>
                                        <form method="post" class="mini-form">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="edit_product">
                                            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                            <input name="name" value="<?php echo e($product['name']); ?>">
                                            <input name="price" type="number" step="0.01" min="0" value="<?php echo e((string)$product['price']); ?>">
                                            <textarea name="description"><?php echo e($product['description'] ?? ''); ?></textarea>
                                            <select name="type"><option value="0" <?php echo (int)$product['type'] === 0 ? 'selected' : ''; ?>>一次性</option><option value="1" <?php echo (int)$product['type'] === 1 ? 'selected' : ''; ?>>循环</option></select>
                                            <label class="check-row"><input type="checkbox" name="is_active" <?php echo (int)$product['is_active'] ? 'checked' : ''; ?>> 上架</label>
                                            <button class="small-btn">保存</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('确定删除商品和对应卡密？')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                            <button class="danger-btn">删除</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    <?php elseif ($page === 'cards'): ?>
        <section class="admin-title"><h1>卡密库存</h1><p>一次性商品会逐条消耗卡密，循环商品会重复展示第一条卡密。</p></section>
        <div class="admin-grid two">
            <section class="panel">
                <h2>导入卡密</h2>
                <form method="post" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_cards">
                    <label>商品<select name="pid" required>
                        <?php foreach ($pdo->query('SELECT id, name FROM products ORDER BY id DESC') as $product): ?>
                            <option value="<?php echo (int)$product['id']; ?>"><?php echo e($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label>卡密内容<textarea name="content" rows="10" placeholder="一行一个卡密"></textarea></label>
                    <button class="primary-btn">导入库存</button>
                </form>
            </section>
            <section class="panel">
                <h2>库存列表</h2>
                <?php $cards = $pdo->query('SELECT c.*, p.name AS product_name FROM cards c LEFT JOIN products p ON p.id = c.pid ORDER BY c.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC); ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>商品</th><th>卡密</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>
                        <?php foreach ($cards as $card): ?>
                            <tr>
                                <td><?php echo e($card['product_name'] ?? '未知商品'); ?></td>
                                <td><code><?php echo e(short_text((string)$card['card_info'], 48)); ?></code></td>
                                <td><?php echo (int)$card['status'] ? '已售' : '未售'; ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('确定删除这条卡密？')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_card">
                                        <input type="hidden" name="id" value="<?php echo (int)$card['id']; ?>">
                                        <button class="danger-btn">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    <?php elseif ($page === 'orders'): ?>
        <section class="admin-title"><h1>订单管理</h1><p>确认订单会标记为已确认，并自动绑定可用卡密。</p></section>
        <section class="panel">
            <?php
            $recentOrders = $pdo->query('SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN products p ON p.id = o.pid ORDER BY o.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
            include __DIR__ . '/includes/orders_table.php';
            ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
