<?php if (empty($recentOrders)): ?>
    <div class="empty-state compact"><h3>暂无订单</h3><p>前台提交订单后会出现在这里。</p></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>订单号</th><th>商品</th><th>联系</th><th>金额</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $order): ?>
            <tr>
                <td><code><?php echo e($order['out_trade_no']); ?></code></td>
                <td><?php echo e($order['product_name'] ?? '未知商品'); ?></td>
                <td><?php echo e($order['contact']); ?></td>
                <td>¥<?php echo e((string)$order['money']); ?></td>
                <td><span class="status <?php echo (int)$order['status'] === 1 ? 'paid' : 'pending'; ?>"><?php echo (int)$order['status'] === 1 ? '已确认' : '待确认'; ?></span></td>
                <td><?php echo e(format_time((int)$order['create_time'])); ?></td>
                <td class="actions-cell">
                    <?php if ((int)$order['status'] !== 1): ?>
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="confirm_order">
                            <input type="hidden" name="order_no" value="<?php echo e($order['out_trade_no']); ?>">
                            <button class="small-btn">确认发货</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('确定删除订单？')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_order">
                        <input type="hidden" name="order_no" value="<?php echo e($order['out_trade_no']); ?>">
                        <button class="danger-btn">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
