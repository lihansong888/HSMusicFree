/**
 * MusicFree 远程管理服务器地址。
 * 将来更换朋友的域名时，只需要修改此文件。
 */
export const REMOTE_SERVER_BASE_URL = "https://lihansong.com";

export const REMOTE_API = {
    plugins: `${REMOTE_SERVER_BASE_URL}/musicfree/api/plugins.json`,
    update: `${REMOTE_SERVER_BASE_URL}/musicfree/api/version.json`,
    announcement: `${REMOTE_SERVER_BASE_URL}/musicfree/api/announcement.json`,
};
