import { Link } from "@inertiajs/react";
import {
    ChatboxEllipses,
    Telescope,
    Megaphone,
    Newspaper,
    Bookmark,
    HelpCircle,
    Chatbubbles,
} from "react-ionicons";

export default function LeftSidebar() {
    const iconColor = "#CACACA";
    const activeIconColor = "#319527";
    const iconSize = "18px";

    return (
        <>
            {/* Left side bar */}
            <div
                className="w-[260px] hidden xl:flex flex-col p-6! sticky top-[69px] h-min"
                id="left-sidebar"
            >
                <p className="text-sm font-semibold text-[#6b6b6b] dark:text-neutral-400 pb-3 ml-2.5">
                    MENU
                </p>
                <Link
                    href="/"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 hover:text-[#319527] text-[#319527] bg-[#E4EEE3] dark:bg-[#495648]"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#BFE5BB]! dark:border-[#4f7b50]! bg-[#CDEBCA] dark:bg-[#1d2a1c]">
                        <ChatboxEllipses
                            color={activeIconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#319527]">Diễn đàn</div>
                </Link>
                <Link
                    href="/feed"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:border-neutral-500!">
                        <Telescope
                            color={iconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#6B6B6B] dark:text-[#CACACA]">
                        Bảng tin
                    </div>
                </Link>
                <Link
                    href="/recordings"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:border-neutral-500!">
                        <Megaphone
                            color={iconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#6B6B6B] dark:text-[#CACACA]">
                        Loa lớn
                    </div>
                </Link>
                <Link
                    href="/youth-news"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:border-neutral-500!">
                        <Newspaper
                            color={iconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#6B6B6B] dark:text-[#CACACA]">
                        Tin tức Đoàn
                    </div>
                </Link>
                <Link
                    href="/saved"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:border-neutral-500!">
                        <Bookmark
                            color={iconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#6B6B6B] dark:text-[#CACACA]">
                        Đã lưu
                    </div>
                </Link>
                <hr className="my-3 dark:border-neutral-600" />
                <Link
                    href="/help"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:border-neutral-500!">
                        <HelpCircle
                            color={iconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#6B6B6B] dark:text-[#CACACA]">
                        Trợ giúp
                    </div>
                </Link>
                <a
                    href="https://forms.gle/XJ3v1vN82BxLUVWo9"
                    className="mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5 text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <div className="text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:border-neutral-500!">
                        <Chatbubbles
                            color={iconColor}
                            height={iconSize}
                            width={iconSize}
                        />
                    </div>
                    <div className="text-[#6B6B6B] dark:text-[#CACACA]">
                        Góp ý
                    </div>
                </a>
            </div>
        </>
    );
}
