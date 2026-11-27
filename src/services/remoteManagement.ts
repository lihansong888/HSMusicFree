import axios from "axios";
import { REMOTE_API } from "@/constants/remoteServer";

export interface IRemotePluginConfig {
    desc?: string;
    plugins: Array<{
        name: string;
        url: string;
        version?: string;
        [key: string]: any;
    }>;
}

export interface IRemoteAnnouncement {
    enabled: boolean;
    title?: string;
    content?: string;
    buttons?: Array<{
        name: string;
        url: string;
        show?: boolean;
        order?: number;
    }>;
}

export async function fetchRemotePluginConfig(): Promise<IRemotePluginConfig> {
    const response = await axios.get(REMOTE_API.plugins, {
        timeout: 10000,
        headers: { "Cache-Control": "no-cache", Pragma: "no-cache" },
    });
    const data = response.data;
    if (!data || !Array.isArray(data.plugins)) {
        throw new Error("远程音乐插件配置格式错误");
    }
    for (const item of data.plugins) {
        if (!item || typeof item.name !== "string" ||
            typeof item.url !== "string" ||
            !/^https?:\/\//i.test(item.url)) {
            throw new Error("远程音乐插件配置包含无效项目");
        }
    }
    return data as IRemotePluginConfig;
}

export async function fetchRemoteAnnouncement(): Promise<IRemoteAnnouncement | null> {
    try {
        const response = await axios.get(REMOTE_API.announcement, {
            timeout: 8000,
            headers: { "Cache-Control": "no-cache", Pragma: "no-cache" },
        });
        const data = response.data;
        if (!data || data.enabled !== true ||
            typeof data.title !== "string" ||
            typeof data.content !== "string") {
            return null;
        }
        return data as IRemoteAnnouncement;
    } catch {
        return null;
    }
}
