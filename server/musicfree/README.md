# MusicFree 远程管理后台

将本目录中的 `musicfree` 文件夹上传到网站根目录，确保访问路径为：

- 管理后台：`https://你的域名/musicfree/admin.php`
- 插件配置：`https://你的域名/musicfree/api/plugins.json`
- 软件更新：`https://你的域名/musicfree/api/version.json`
- 公告：`https://你的域名/musicfree/api/announcement.json`
- 关于页面：`https://你的域名/musicfree/api/about.json`

## 环境要求

- PHP 7.4 或更高版本，宝塔网站需要启用 PHP 解析。
- 不需要 MySQL 数据库。
- `musicfree` 目录及 `api`、`uploads` 子目录需要允许 PHP 写入。
- 在宝塔 PHP 设置中，建议将 `upload_max_filesize` 和 `post_max_size` 调整到至少 `100M`，否则较大的 APK 可能上传失败。

## 首次登录

默认密码：`admin123456`。首次登录后请在“基本设置”区域立即修改。

## 使用说明

- **音乐插件配置**：后台直接编辑 `desc` + `plugins` JSON 配置文件，APP 启动时自动读取并按配置加载插件。每项的 `url` 是插件 JS 脚本文件地址，不是音乐 API 接口；普通用户无需填写或连接接口。保存时校验 JSON 格式、插件名称和脚本地址，不限制插件数量。若插件 JS 内容在原地址更新，请修改该项的 `version` 字段，触发 APP 重新读取配置并检查插件。
- **关于页面自定义**：在后台编辑 `about.json`，可自定义头像、作者介绍、联系信息、文字段落、小标题、图片、超链接和分隔线；`enabled` 设为 `true` 后，APP 关于页面读取远程内容，保留原有页面布局和横竖屏适配。图片及链接填写公开可访问的 HTTP(S) 地址。
- **公告管理**：启用公告后，APP 启动时显示；按钮可以添加、隐藏、调整顺序。
- **软件更新**：版本号使用 `1.2.3` 或 `1.2.3-beta.1` 格式，填写更新说明并上传 APK 后发布。撤回时取消“发布更新”即可。
- **基本设置**：修改后台密码，查看后台文件目录和 PHP 状态。

## 更换服务器域名

APP 当前测试域名统一配置在 `src/constants/remoteServer.ts`。以后换成朋友的域名时，只需修改该文件中的 `REMOTE_SERVER_BASE_URL`，三个 API 地址会自动跟随。

## Nginx 提醒

请确认宝塔网站已启用 PHP，并为 `/musicfree/uploads/` 设置可写权限（建议目录权限 755，文件 644）。后台不需要数据库。

若服务器的 Nginx 配置没有执行 PHP，请先修复 PHP 解析配置再使用后台。API JSON 文件由 Nginx 直接提供，APP 不访问后台登录页。

建议把本目录的 `nginx-security.conf` 内容复制到宝塔该站点的 Nginx `server {}` 配置块中，禁止直接访问密码配置文件，并阻止上传目录执行 PHP。上传目录的 PHP 拦截规则应放在通用 PHP 解析规则之前。修改后执行 Nginx 配置检查并重载。
