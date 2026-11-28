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
    title: string;
    content: string;
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

        const buttons = Array.isArray(data.buttons)
            ? data.buttons.filter((item: any) =>
                item &&
                typeof item.name === "string" &&
                typeof item.url === "string" &&
                /^https?:\/\//i.test(item.url),
            )
            : [];

        return { ...data, buttons } as IRemoteAnnouncement;
    } catch {
        return null;
    }
}


export interface IRemoteAboutConfig {
    enabled: boolean;
    header: {
        avatar?: string;
        name?: string;
        contacts?: Array<{ label: string; value: string; url?: string }>;
    };
    body: Array<{
        type: "heading" | "text" | "link" | "image" | "divider";
        text?: string;
        bold?: boolean;
        label?: string;
        url?: string;
        caption?: string;
    }>;
}

export async function fetchRemoteAboutConfig(): Promise<IRemoteAboutConfig | null> {
    try {
        const response = await axios.get(REMOTE_API.about, {
            timeout: 8000,
            headers: { "Cache-Control": "no-cache", Pragma: "no-cache" },
        });
        const data = response.data;
        if (!data || data.enabled !== true ||
            !data.header || typeof data.header !== "object" ||
            !Array.isArray(data.body)) {
            return null;
        }
        const validUrl = (url: unknown) =>
            typeof url === "string" && /^https?:\/\//i.test(url);
        const contacts = Array.isArray(data.header.contacts)
            ? data.header.contacts.filter((item: any) =>
                item && typeof item.label === "string" &&
                typeof item.value === "string" &&
                (!item.url || validUrl(item.url)))
            : [];
        const body = data.body.filter((item: any) => {
            if (!item || typeof item.type !== "string") return false;
            if (item.type === "heading" || item.type === "text") return typeof item.text === "string";
            if (item.type === "link") return typeof item.label === "string" && validUrl(item.url);
            if (item.type === "image") return validUrl(item.url);
            return item.type === "divider";
        });
        return {
            enabled: true,
            header: {
                avatar: validUrl(data.header.avatar) ? data.header.avatar : undefined,
                name: typeof data.header.name === "string" ? data.header.name : "",
                contacts,
            },
            body,
        };
    } catch {
        return null;
    }
}
