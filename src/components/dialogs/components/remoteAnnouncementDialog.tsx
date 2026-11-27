import React from "react";
import { ScrollView, StyleSheet, View } from "react-native";
import ThemeText from "@/components/base/themeText";
import Button from "@/components/base/textButton";
import openUrl from "@/utils/openUrl";
import rpx, { vh } from "@/utils/rpx";
import Dialog from "./base";
import { hideDialog } from "../useDialog";

interface IRemoteAnnouncementDialogProps {
    title: string;
    content: string;
    buttons?: Array<{
        name: string;
        url: string;
        show?: boolean;
        order?: number;
    }>;
}

export default function RemoteAnnouncementDialog(props: IRemoteAnnouncementDialogProps) {
    const buttons = (props.buttons ?? [])
        .filter(item => item.show !== false && item.name && /^https?:\/\//i.test(item.url))
        .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));

    return (
        <Dialog onDismiss={hideDialog}>
            <Dialog.Title stringContent>{props.title}</Dialog.Title>
            <ScrollView style={styles.content}>
                <ThemeText style={styles.text}>{props.content}</ThemeText>
            </ScrollView>
            {buttons.length > 0 ? (
                <View style={styles.buttons}>
                    {buttons.map((item, index) => (
                        <Button key={index} style={styles.button} onPress={() => {
                                hideDialog();
                                openUrl(item.url);
                            }}>
                            {item.name}
                        </Button>
                    ))}
                </View>
            ) : null}
            <Dialog.Actions actions={[{ title: "知道了", type: "primary", onPress: hideDialog }]} />
        </Dialog>
    );
}

const styles = StyleSheet.create({
    content: { maxHeight: vh(50), paddingHorizontal: rpx(26) },
    text: { lineHeight: rpx(42) },
    buttons: { flexDirection: "row", flexWrap: "wrap", paddingHorizontal: rpx(20), paddingTop: rpx(12) },
    button: { marginHorizontal: rpx(6), marginVertical: rpx(6) },
});
