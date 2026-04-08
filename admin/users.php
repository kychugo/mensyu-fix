<?php
/**
 * 文樞 — 管理：用戶管理
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db  = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 驗證失敗。'; $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'set_role') {
            $uid  = (int)$_POST['user_id'];
            $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            if ($uid === getCurrentUserId()) {
                $msg = '不可更改自己的角色。'; $msgType = 'error';
            } else {
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
                $msg = '用戶角色已更新。';
            }
        }

        if ($action === 'delete') {
            $uid = (int)$_POST['user_id'];
            if ($uid === getCurrentUserId()) {
                $msg = '不可刪除自己的帳號。'; $msgType = 'error';
            } else {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                $msg = '用戶已刪除。';
            }
        }
    }
}

$users = $db->query('SELECT id, username, email, role, xp, last_login, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$csrfToken  = getCsrfToken();
$pageTitle  = '用戶管理';
$activePage = 'admin';
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-container">
    <div style="margin-bottom:24px;">
        <h1 class="page-title"><i class="fas fa-users" style="color:#f0932b;margin-right:8px;"></i>用戶管理</h1>
        <a href="<?= BASE_URL ?>/admin/index.php" style="font-size:0.82rem;color:var(--dark-color);"><i class="fas fa-arrow-left"></i> 返回後台</a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:rgba(127,179,213,0.08);font-size:0.82rem;font-weight:700;color:var(--dark-color);">
                    <th style="padding:12px 16px;text-align:left;">用戶名</th>
                    <th style="padding:12px 16px;text-align:left;">電郵</th>
                    <th style="padding:12px 16px;text-align:center;">角色</th>
                    <th style="padding:12px 16px;text-align:center;">XP</th>
                    <th style="padding:12px 16px;text-align:left;">最後登入</th>
                    <th style="padding:12px 16px;text-align:left;">注冊日期</th>
                    <th style="padding:12px 16px;text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr style="border-top:1px solid rgba(127,179,213,0.1);font-size:0.88rem;">
                    <td style="padding:10px 16px;font-weight:600;"><?= e($u['username']) ?></td>
                    <td style="padding:10px 16px;"><?= e($u['email']) ?></td>
                    <td style="padding:10px 16px;text-align:center;">
                        <?= $u['role'] === 'admin'
                            ? '<span class="badge badge-warning"><i class="fas fa-shield-alt"></i> 管理員</span>'
                            : '<span class="badge badge-primary">用戶</span>' ?>
                    </td>
                    <td style="padding:10px 16px;text-align:center;font-weight:700;"><?= $u['xp'] ?></td>
                    <td style="padding:10px 16px;"><?= $u['last_login'] ? timeAgo($u['last_login']) : '從未' ?></td>
                    <td style="padding:10px 16px;"><?= formatTimestamp($u['created_at']) ?></td>
                    <td style="padding:10px 16px;text-align:center;">
                        <?php if ($u['id'] !== getCurrentUserId()): ?>
                        <div style="display:flex;gap:6px;justify-content:center;">
                            <!-- 切換角色 -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action"  value="set_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="role"    value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                <button type="submit" class="btn-sm btn-sm-outline" title="切換角色">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </form>
                            <!-- 刪除 -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('確定刪除此用戶？')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-sm btn-sm-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span style="font-size:0.75rem;color:#aaa;">（自己）</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" style="padding:32px;text-align:center;color:#aaa;">尚無用戶</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
