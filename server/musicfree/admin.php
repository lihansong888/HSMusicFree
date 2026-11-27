<?php
declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

$base = __DIR__;
$apiDir = $base . '/api';
$uploadDir = $base . '/uploads';
$configPath = $base . '/admin-config.php';
if (!is_dir($apiDir)) mkdir($apiDir, 0755, true);
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$config = require $configPath;
if (empty($config['password_hash'])) {
    $config['password_hash'] = password_hash('admin123456', PASSWORD_DEFAULT);
    if (file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
        http_response_code(500);
        exit('后台配置文件不可写，请检查 musicfree 目录权限。');
    }
    if (function_exists('opcache_invalidate')) opcache_invalidate($configPath, true);
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function read_json_file(string $path, array $fallback): array {
    if (!is_file($path)) return $fallback;
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : $fallback;
}
function write_json_file(string $path, array $data): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($path, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('写入文件失败，请检查目录权限。');
    }
}
function csrf_check(): void {
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
        throw new RuntimeException('页面已过期，请刷新后重试。');
    }
}

$loggedIn = !empty($_SESSION['musicfree_admin']);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'login') {
            if (password_verify((string)($_POST['password'] ?? ''), $config['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['musicfree_admin'] = true;
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                $loggedIn = true;
                $message = '登录成功。';
            } else {
                throw new RuntimeException('密码错误。');
            }
        } elseif (($_POST['action'] ?? '') === 'logout') {
            csrf_check();
            $_SESSION = [];
            session_destroy();
            header('Location: admin.php');
            exit;
        } else {
            if (!$loggedIn) throw new RuntimeException('请先登录。');
            csrf_check();
            $action = (string)($_POST['action'] ?? '');

            if ($action === 'save_plugins') {
                $raw = (string)($_POST['plugins_json'] ?? '');
                $data = json_decode($raw, true);
                if (!is_array($data) || !isset($data['plugins']) || !is_array($data['plugins'])) {
                    throw new RuntimeException('JSON 格式错误，或缺少 plugins 数组。');
                }
                foreach ($data['plugins'] as $index => $plugin) {
                    if (!is_array($plugin) || empty($plugin['name']) || empty($plugin['url']) ||
                        !filter_var($plugin['url'], FILTER_VALIDATE_URL) ||
                        !preg_match('/^https?:\/\//i', $plugin['url'])) {
                        throw new RuntimeException('第 ' . ($index + 1) . ' 个插件缺少有效名称或 HTTP(S) URL。');
                    }
                }
                // 保存原始 JSON 文本，保留用户的字段和格式。
                json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('JSON 格式错误：' . json_last_error_msg());
                if (file_put_contents($apiDir . '/plugins.json', rtrim($raw) . "\n", LOCK_EX) === false) {
                    throw new RuntimeException('保存失败，请检查 api 目录权限。');
                }
                $message = '音乐插件配置已保存。';
            } elseif ($action === 'save_announcement') {
                $announcementTitle = trim((string)($_POST['announcement_title'] ?? ''));
                $announcementContent = trim((string)($_POST['announcement_content'] ?? ''));
                if (isset($_POST['announcement_enabled']) && ($announcementTitle === '' || $announcementContent === '')) {
                    throw new RuntimeException('发布公告前，请填写公告标题和正文。');
                }
                $buttons = [];
                $names = $_POST['button_name'] ?? [];
                $urls = $_POST['button_url'] ?? [];
                $shows = $_POST['button_show'] ?? [];
                $orders = $_POST['button_order'] ?? [];
                foreach ($names as $i => $name) {
                    $name = trim((string)$name);
                    $url = trim((string)($urls[$i] ?? ''));
                    if ($name === '' && $url === '') continue;
                    if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
                        throw new RuntimeException('公告按钮名称或网址无效，请检查第 ' . ($i + 1) . ' 行。');
                    }
                    $buttons[] = [
                        'name' => $name,
                        'url' => $url,
                        'show' => isset($shows[$i]),
                        'order' => (int)($orders[$i] ?? $i),
                    ];
                }
                write_json_file($apiDir . '/announcement.json', [
                    'enabled' => isset($_POST['announcement_enabled']),
                    'title' => $announcementTitle,
                    'content' => $announcementContent,
                    'buttons' => $buttons,
                ]);
                $message = '公告设置已保存。';
            } elseif ($action === 'save_update') {
                $old = read_json_file($apiDir . '/version.json', ['version' => '0.6.2', 'changeLog' => [], 'download' => []]);
                $version = trim((string)($_POST['version'] ?? ''));
                if ($version === '') throw new RuntimeException('请填写软件版本号。');
                $download = ($version === (string)($old['version'] ?? '')) ? ($old['download'] ?? []) : [];
                if (!empty($_FILES['apk']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['apk']['name'], PATHINFO_EXTENSION));
                    if ($ext !== 'apk' || ($_FILES['apk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('APK 上传失败，请选择有效的 .apk 文件。');
                    }
                    $safeVersion = preg_replace('/[^0-9A-Za-z._-]/', '_', $version);
                    $filename = 'musicfree-' . $safeVersion . '.apk';
                    if (!move_uploaded_file($_FILES['apk']['tmp_name'], $uploadDir . '/' . $filename)) {
                        throw new RuntimeException('无法保存 APK，请检查 uploads 目录权限。');
                    }
                    $download = ['https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/musicfree/uploads/' . rawurlencode($filename)];
                }
                $published = isset($_POST['update_published']);
                if ($published && empty($download)) throw new RuntimeException('发布更新前，请先上传 APK。');
                $changeLog = preg_split('/\r\n|\r|\n/', trim((string)($_POST['change_log'] ?? '')));
                $changeLog = array_values(array_filter(array_map('trim', $changeLog), fn($line) => $line !== ''));
                write_json_file($apiDir . '/version.json', [
                    'published' => $published,
                    'version' => $version,
                    'changeLog' => $changeLog,
                    'download' => $download,
                ]);
                $message = $published ? '软件更新已发布。' : '软件更新已撤回。';
            } elseif ($action === 'change_password') {
                $oldPassword = (string)($_POST['old_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                if (!password_verify($oldPassword, $config['password_hash'])) throw new RuntimeException('当前密码不正确。');
                if (strlen($newPassword) < 8) throw new RuntimeException('新密码至少需要 8 位。');
                $config['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                if (file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
                    throw new RuntimeException('无法保存新密码，请检查 musicfree 目录权限。');
                }
                if (function_exists('opcache_invalidate')) opcache_invalidate($configPath, true);
                $message = '后台密码已修改，请记住新密码。';
            } else {
                throw new RuntimeException('未知操作。');
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($loggedIn && empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf'] ?? '';
$pluginsRaw = is_file($apiDir . '/plugins.json') ? (string)file_get_contents($apiDir . '/plugins.json') : "{\n    \"desc\": \"0.2.0\",\n    \"plugins\": []\n}";
$announcement = read_json_file($apiDir . '/announcement.json', ['enabled' => false, 'title' => '', 'content' => '', 'buttons' => []]);
$update = read_json_file($apiDir . '/version.json', ['published' => false, 'version' => '0.6.2', 'changeLog' => [], 'download' => []]);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MusicFree 管理后台</title>
<style>
:root{font-family:system-ui,-apple-system,"Microsoft YaHei",sans-serif;color:#20242b;background:#f3f5f8}
*{box-sizing:border-box}body{margin:0;padding:24px}.wrap{max-width:980px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px}h1{font-size:24px;margin:0}h2{font-size:19px;margin:0 0 16px}.card{background:#fff;border:1px solid #e4e8ee;border-radius:14px;padding:22px;margin:16px 0;box-shadow:0 3px 14px #15223808}.hint{color:#6c7583;font-size:13px;line-height:1.7}.field{margin:14px 0}label{display:block;font-weight:600;margin-bottom:7px}input[type=text],input[type=password],input[type=file],textarea{width:100%;padding:11px 12px;border:1px solid #ccd3dd;border-radius:8px;font:inherit;background:#fff}textarea{min-height:120px;resize:vertical;font-family:ui-monospace,Consolas,monospace}button,.btn{border:0;border-radius:8px;padding:10px 16px;background:#2563eb;color:white;font:inherit;cursor:pointer}button.secondary{background:#e9edf3;color:#263244}button.danger{background:#b42318}.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.notice{padding:12px 14px;border-radius:8px;background:#eaf7ef;color:#17643a;margin:12px 0}.error{background:#fff0ef;color:#a32318}.check{display:flex;align-items:center;gap:9px;font-weight:500}.check input{width:18px;height:18px}.button-row{display:grid;grid-template-columns:1fr 2fr 80px 80px auto;gap:8px;align-items:center;margin:8px 0}.button-row input{min-width:0;padding:9px;border:1px solid #ccd3dd;border-radius:7px}.section-title{display:flex;justify-content:space-between;align-items:center}.status{font-size:13px;color:#667085}@media(max-width:650px){body{padding:12px}.card{padding:16px}.button-row{grid-template-columns:1fr 1fr}.button-row .wide{grid-column:1/-1}}
</style>
</head>
<body><main class="wrap">
<div class="top"><div><h1>MusicFree 管理后台</h1><div class="hint">音乐插件 · 公告 · 软件更新 · 基本设置</div></div>
<?php if ($loggedIn): ?><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="logout"><button class="secondary">退出登录</button></form><?php endif; ?></div>
<?php if ($message): ?><div class="notice"><?=h($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?=h($error)?></div><?php endif; ?>
<?php if (!$loggedIn): ?>
<section class="card"><h2>管理员登录</h2><form method="post"><input type="hidden" name="action" value="login"><div class="field"><label>后台密码</label><input type="password" name="password" required autofocus></div><button>登录</button></form><p class="hint">首次登录默认密码：admin123456。登录后请立即在基本设置中修改。</p></section>
<?php else: ?>
<section class="card"><h2>一、音乐插件配置</h2><p class="hint">直接编辑完整 JSON。保存时检查 JSON 格式和插件名称、网址；不会限制插件数量，也不会擅自改动其他字段。</p>
<form id="pluginsForm" method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="save_plugins"><div class="field"><textarea id="pluginsJson" name="plugins_json" rows="22" spellcheck="false"><?=h($pluginsRaw)?></textarea></div><button>校验并保存插件配置</button></form></section>

<section class="card"><h2>二、公告管理</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="save_announcement">
<label class="check"><input type="checkbox" name="announcement_enabled" value="1" <?=!empty($announcement['enabled'])?'checked':''?>> 发布公告（关闭后 APP 不弹出）</label>
<div class="field"><label>公告标题</label><input type="text" name="announcement_title" value="<?=h($announcement['title']??'')?>"></div>
<div class="field"><label>公告正文</label><textarea name="announcement_content" rows="6"><?=h($announcement['content']??'')?></textarea></div>
<div class="field"><div class="section-title"><label>公告按钮</label><button type="button" class="secondary" onclick="addButton()">＋ 添加按钮</button></div><div id="buttons">
<?php foreach (($announcement['buttons']??[]) as $i=>$b): ?><div class="button-row"><input class="wide" name="button_name[]" placeholder="按钮名称" value="<?=h($b['name']??'')?>"><input class="wide" name="button_url[]" placeholder="https://..." value="<?=h($b['url']??'')?>"><label class="check"><input type="checkbox" name="button_show[<?=$i?>]" value="1" <?=($b['show']??true)?'checked':''?>>显示</label><input name="button_order[]" type="text" value="<?=h($b['order']??$i)?>" placeholder="排序"><button type="button" class="danger" onclick="this.parentNode.remove()">删除</button></div><?php endforeach; ?>
</div></div><button>保存公告</button></form></section>

<section class="card"><h2>三、软件更新</h2><p class="hint">上传 APK 并发布后，APP 的自动检查和侧边栏“检查更新”都会读取此处。撤回发布不会删除已上传的 APK。</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="save_update">
<div class="field"><label>版本号</label><input type="text" name="version" value="<?=h($update['version']??'0.6.2')?>" required></div>
<div class="field"><label>更新说明（每行一条）</label><textarea name="change_log" rows="4"><?=h(implode("\n",$update['changeLog']??[]))?></textarea></div>
<div class="field"><label>APK 文件</label><input type="file" name="apk" accept=".apk"><div class="hint">当前下载地址：<?=h(implode('；',$update['download']??[]))?></div></div>
<label class="check"><input type="checkbox" name="update_published" value="1" <?=!empty($update['published'])?'checked':''?>> 发布此版本更新</label>
<div class="row" style="margin-top:14px"><button>保存软件更新设置</button><span class="status">当前状态：<?=!empty($update['published'])?'已发布':'未发布 / 已撤回'?></span></div></form></section>

<section class="card"><h2>四、基本设置</h2><p class="hint">服务器接口运行方式：静态 JSON + PHP 管理页面，无需数据库。PHP 版本：<?=h(PHP_VERSION)?>；插件配置文件：<?=is_writable($apiDir.'/plugins.json')?'可写':'请检查 api 目录权限'?>。</p>
<form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="change_password">
<div class="field"><label>当前密码</label><input type="password" name="old_password" required></div><div class="field"><label>新密码（至少 8 位）</label><input type="password" name="new_password" minlength="8" required></div><button>修改后台密码</button></form></section>
<?php endif; ?>
</main>
<script>
document.getElementById('pluginsForm')?.addEventListener('submit',function(event){
    const editor=document.getElementById('pluginsJson');
    try {
        const config=JSON.parse(editor.value);
        if(!config || !Array.isArray(config.plugins)) throw new Error('缺少 plugins 数组');
    } catch(error) {
        event.preventDefault();
        const message=String(error && error.message ? error.message : error);
        const match=message.match(/(?:position|at)\s+(\d+)/i);
        let detail=message;
        if(match){
            const position=Number(match[1]);
            const before=editor.value.slice(0,position);
            const line=before.split(String.fromCharCode(10)).length;
            const column=position-before.lastIndexOf(String.fromCharCode(10));
            detail='第 '+line+' 行，第 '+column+' 列';
        }
        alert('JSON 格式错误：'+detail);
    }
});
function addButton(){const box=document.getElementById('buttons');const i=box.querySelectorAll('.button-row').length;const row=document.createElement('div');row.className='button-row';row.innerHTML='<input class="wide" name="button_name[]" placeholder="按钮名称"><input class="wide" name="button_url[]" placeholder="https://..."><label class="check"><input type="checkbox" name="button_show['+i+']" value="1" checked>显示</label><input name="button_order[]" type="text" value="'+i+'" placeholder="排序"><button type="button" class="danger" onclick="this.parentNode.remove()">删除</button>';box.appendChild(row);}
</script></body></html>
