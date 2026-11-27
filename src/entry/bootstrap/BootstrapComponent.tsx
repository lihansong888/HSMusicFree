import { useAppConfig } from "@/core/appConfig";
import Theme from "@/core/theme";
import useCheckUpdate from "@/hooks/useCheckUpdate";
import { useListenOrientationChange } from "@/hooks/useOrientation";
import { getDefaultStore, useAtomValue } from "jotai";
import { useEffect, useRef } from "react";
import { AppState, NativeEventSubscription, useColorScheme } from "react-native";
import bootstrapAtom from "./bootstrap.atom";
import { initTrackPlayer } from "./bootstrap";
import { getCurrentDialog, showDialog } from "@/components/dialogs/useDialog";
import { fetchRemoteAnnouncement } from "@/services/remoteManagement";
import i18n from "@/core/i18n";

export function BootstrapComponent() {
    const bootstrapState = useAtomValue(bootstrapAtom);
    const announcementRequested = useRef(false);

    useListenOrientationChange();
    const checkUpdateNow = useCheckUpdate(false);

    useEffect(() => {
        if (bootstrapState.state !== "Done" || announcementRequested.current) {
            return;
        }
        announcementRequested.current = true;
        let active = true;

        const showStartupAnnouncement = async () => {
            // 并行获取更新和公告，待更新检查完成后再决定是否显示公告，避免弹窗互相覆盖。
            const [, announcement] = await Promise.all([
                checkUpdateNow(false, true),
                fetchRemoteAnnouncement(),
            ]);
            if (!active || !announcement || getCurrentDialog()?.name) {
                return;
            }
            showDialog("RemoteAnnouncementDialog", {
                title: announcement.title,
                content: announcement.content,
                buttons: announcement.buttons ?? [],
            });
        };

        void showStartupAnnouncement();
        return () => {
            active = false;
        };
    }, [bootstrapState.state, checkUpdateNow]);

    const followSystem = useAppConfig("theme.followSystem");

    const colorScheme = useColorScheme();

    useEffect(() => {
        if (followSystem) {
            if (colorScheme === "dark") {
                Theme.setTheme("p-dark");
            } else if (colorScheme === "light") {
                Theme.setTheme("p-light");
            }
        }
    }, [colorScheme, followSystem]);

    useEffect(() => {
        let appStateEventSubscription: NativeEventSubscription | null = null;

        const reinitializeTrackPlayerWithDialog = () => {
            showDialog("LoadingDialog", {
                title: i18n.t("dialog.loading.reinitializeTrackPlayer"), 
                promise: initTrackPlayer(),
                onResolve(data, hideDialog) {
                    hideDialog();
                },
                onReject(reason, hideDialog) {
                    hideDialog();
                },
            });
        };

        if (bootstrapState.state === "TrackPlayerError") {
            if (AppState.currentState === "active") {
                reinitializeTrackPlayerWithDialog();
            } else {
                appStateEventSubscription = AppState.addEventListener("change", (nextState) => {
                    if (nextState === "active" && getDefaultStore().get(bootstrapAtom).state === "TrackPlayerError") {
                        reinitializeTrackPlayerWithDialog();
                    }
                });
            }
        }

        return () => {
            if (appStateEventSubscription) {
                appStateEventSubscription.remove();
            }
        };
    }, [bootstrapState]);

    return null;
}
