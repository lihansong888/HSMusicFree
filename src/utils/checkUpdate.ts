import axios from "axios";
import { compare } from "compare-versions";
import DeviceInfo from "react-native-device-info";
import { REMOTE_API } from "@/constants/remoteServer";

interface IUpdateInfo {
    needUpdate: boolean;
    data: {
        version: string;
        changeLog: string[];
        download: string[];
    };
}

export default async function checkUpdate(): Promise<IUpdateInfo | undefined> {
    const currentVersion = DeviceInfo.getVersion();
    try {
        const rawInfo = (await axios.get(REMOTE_API.update, {
            timeout: 10000,
            headers: { "Cache-Control": "no-cache", Pragma: "no-cache" },
        })).data;

        if (!rawInfo || typeof rawInfo.version !== "string") {
            return undefined;
        }

        const needUpdate =
            rawInfo.published === false
                ? false
                : compare(rawInfo.version, currentVersion, ">");
        const download = Array.isArray(rawInfo.download)
            ? rawInfo.download.filter((url: unknown) =>
                typeof url === "string" && /^https?:\/\//i.test(url),
            )
            : [];

        if (needUpdate && download.length === 0) {
            return undefined;
        }

        return {
            needUpdate,
            data: {
                version: rawInfo.version,
                changeLog: Array.isArray(rawInfo.changeLog) ? rawInfo.changeLog : [],
                download,
            },
        };
    } catch {
        return undefined;
    }
}
