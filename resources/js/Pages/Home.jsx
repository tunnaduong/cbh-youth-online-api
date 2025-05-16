import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";

export default function Home({
    mainCategories,
    latestPosts,
    stats,
    latestUser,
}) {
    return (
        <HomeLayout>
            <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
            <>
                <div className="pt-4 !px-2.5">
                    <div className="max-w-[775px] mx-auto">
                        <div className="mb-4">
                            {/* ads_top_below_navbar */}
                            <ins
                                className="adsbygoogle"
                                style={{ display: "inline-block", height: 90 }}
                                data-ad-client="ca-pub-3425905751761094"
                                data-ad-slot={6534092486}
                            />
                        </div>
                        <div className="border dark:!border-[#585857] rounded bg-white dark:!bg-[var(--main-white)]">
                            <div className="flex flex-wrap items-stretch">
                                <a
                                    href="?sort=latest"
                                    className="px-4 text-sm flex items-center hover:bg-gray-50 tab-button dark:hover:bg-neutral-500
  tab-button-active "
                                >
                                    <span className="py-2">Bài mới</span>
                                </a>
                                <a
                                    href="?sort=most_viewed"
                                    className="hidden sm:flex px-4 text-sm items-center bor-left hover:bg-gray-50 tab-button dark:border-[#585857] dark:hover:bg-neutral-500
 "
                                >
                                    <span className="py-2">
                                        Chủ đề xem nhiều
                                    </span>
                                </a>
                                <a
                                    href="?sort=most_engaged"
                                    className="px-4 text-sm hidden sm:flex items-center bor-right bor-left hover:bg-gray-50 tab-button dark:border-[#585857] dark:hover:bg-neutral-500
 "
                                >
                                    <span className="py-2">
                                        Tương tác nhiều
                                    </span>
                                </a>
                                <div>
                                    <button
                                        className="h-9 w-9 border-l items-center justify-center tab-button bor-right flex sm:hidden hover:bg-gray-50 dark:border-neutral-500 dark:hover:bg-neutral-500"
                                        id="dropdownMenu"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                    >
                                        <ion-icon
                                            name="menu-outline"
                                            className="text-xl"
                                        />
                                    </button>
                                    <ul
                                        className="dropdown-menu"
                                        aria-labelledby="dropdownMenu"
                                    >
                                        <li>
                                            <a
                                                className="dropdown-item"
                                                href="?sort=most_viewed"
                                            >
                                                Chủ đề xem nhiều
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                className="dropdown-item"
                                                href="?sort=most_engaged"
                                            >
                                                Tương tác nhiều
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div className="ml-auto flex">
                                    <button
                                        className="h-9 w-9 border-l dark:border-[#585857] flex items-center justify-center tab-button hover:bg-gray-50 dark:hover:bg-neutral-500"
                                        onclick="location.reload()"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width={24}
                                            height={24}
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth={2}
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            className="lucide lucide-refresh-cw-icon lucide-refresh-cw h-4 w-4"
                                        >
                                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                            <path d="M21 3v5h-5" />
                                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                            <path d="M8 16H3v5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-red-600 text-white text-[11px] font-medium">
                                            1
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/Nigger69/posts/213122"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            Cho e xin ebook ạ
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        1 tuần trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/Nigger69"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Nigger
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-red-400 dark:bg-[#b04848] text-white text-[11px] font-medium">
                                            2
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/Admin/posts/213120"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            🌙 CBH Youth Online chính thức ra
                                            mắt Dark Mode
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        2 tuần trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/Admin"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                CBH Youth Online
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-red-200 dark:bg-[#683f3f] text-white text-[11px] font-medium">
                                            3
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/DoanTruongCBH/posts/213118"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            Học sinh THPT Chuyên Biên Hoà được
                                            triệu tập tham dự kỳ thi Olympic Hóa
                                            học Quốc tế
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        3 tuần trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/DoanTruongCBH"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Đoàn trường THPT Chuyên Biên Hòa
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            4
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/yangsongying/posts/213117"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            我住在河南，越南。很高兴认识你！
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        3 tuần trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/yangsongying"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                羊松英
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            5
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/Chocobaiii/posts/213116"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            “Người Sót Lại Của Rừng Cười”: Cái
                                            Cười Méo Mó Man Dại Của Chiến Tranh
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        3 tuần trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/Chocobaiii"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Chocobaiii
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            6
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/kienthuctonghop/posts/213115"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            Forum và Mạng Xã hội – Bạn đồng hành
                                            hay kẻ thù không đội trời chung?
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        1 tháng trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/kienthuctonghop"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Kiến thức tổng hợp
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            7
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/DoanTruongCBH/posts/213111"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            NGÀY HỘI STEM NĂM HỌC 2024-2025
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        1 tháng trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/DoanTruongCBH"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Đoàn trường THPT Chuyên Biên Hòa
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            8
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/Chocobaiii/posts/213110"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            Thơ ba câu của Mai Văn Phấn gây
                                            tranh cãi
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        1 tháng trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/Chocobaiii"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Chocobaiii
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            9
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/DoanTruongCBH/posts/213109"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            RECAP VĂN NGHỆ CHÀO MỪNG 26.03 - ĐẶC
                                            SẮC NGHỆ THUẬT DÂN GIAN
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        1 tháng trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/DoanTruongCBH"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Đoàn trường THPT Chuyên Biên Hòa
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600">
                                    <div className="pr-2 align-top text-center w-8 flex items-center">
                                        <span className="inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-200 dark:bg-[#282828] text-green-600 text-[11px] font-medium">
                                            10
                                        </span>
                                    </div>
                                    <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                                        <a
                                            href="/haoquangrucro/posts/213108"
                                            className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                                        >
                                            Chi tiết lịch nghỉ hè 2025 của học
                                            sinh 63 tỉnh, thành cả nước
                                        </a>
                                    </div>
                                    <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                                        1 tháng trước
                                    </div>
                                    <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                                        <div className="flex items-center justify-end">
                                            <a
                                                href="/haoquangrucro"
                                                className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                            >
                                                Hào quang rực rỡ
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="flex flex-1 !p-6 !px-2.5 items-center flex-col -mb-8">
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/thong-bao"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Thông báo
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/thong-bao/tin-tuc-tu-ban-quan-tri"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Tin tức từ Ban quản trị
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            6
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            6
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/Admin/posts/213120"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            🌙 CBH Youth Online chính thức ra
                                            mắt Dark Mode
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/Admin"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            CBH Youth Online
                                        </a>
                                        <ion-icon
                                            name="checkmark-circle"
                                            className="text-[15px] leading-5 ml-0.5 shrink-0"
                                        />
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 2 tuần trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/thong-bao/quy-dinh-va-huong-dan"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Quy định và hướng dẫn
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            3
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            7
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/Admin/posts/213101"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Cách Tính Điểm Xếp Hạng Thành Viên
                                            Trên CBH Youth Online
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/Admin"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            CBH Youth Online
                                        </a>
                                        <ion-icon
                                            name="checkmark-circle"
                                            className="text-[15px] leading-5 ml-0.5 shrink-0"
                                        />
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/thong-bao/tin-tuc-doan"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Tin tức Đoàn
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            5
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            1
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/DoanTruongCBH/posts/213118"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Học sinh THPT Chuyên Biên Hoà được
                                            triệu tập tham dự kỳ thi Olympic Hóa
                                            học Quốc tế
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/DoanTruongCBH"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Đoàn trường THPT Chuyên Biên Hòa
                                        </a>
                                        <ion-icon
                                            name="checkmark-circle"
                                            className="text-[15px] leading-5 ml-0.5 shrink-0"
                                        />
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 3 tuần trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/hoc-tap"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Học tập
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoc-tap/trung-hoc-pho-thong"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Trung học phổ thông
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            4
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            27
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/haoquangrucro/posts/213108"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Chi tiết lịch nghỉ hè 2025 của học
                                            sinh 63 tỉnh, thành cả nước
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/haoquangrucro"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Hào quang rực rỡ
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoc-tap/trung-hoc-co-so"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Trung học cơ sở
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            34
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/tunnaduong/posts/213053"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Bí quyết thi đỗ vào lớp 10 trường
                                            chuyên
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/tunnaduong"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Dương Tùng Anh (Tunna Duong)
                                        </a>
                                        <ion-icon
                                            name="checkmark-circle"
                                            className="text-[15px] leading-5 ml-0.5 shrink-0"
                                        />
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoc-tap/tieng-anh"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Tiếng Anh
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            3
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/Admin/posts/213051"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            [THÔNG BÁO] Tổ chức cuộc thi Tiếng
                                            Anh
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/Admin"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            CBH Youth Online
                                        </a>
                                        <ion-icon
                                            name="checkmark-circle"
                                            className="text-[15px] leading-5 ml-0.5 shrink-0"
                                        />
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoc-tap/ebook-giao-trinh"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Ebook - Giáo trình
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            3
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/Nigger69/posts/213122"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Cho e xin ebook ạ
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/Nigger69"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Nigger
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tuần trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoc-tap/ngoai-ngu"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Học ngoại ngữ
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            40
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/yangsongying/posts/213117"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            我住在河南，越南。很高兴认识你！
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/yangsongying"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            羊松英
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 3 tuần trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoc-tap/van-hoc-nghe-thuat"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Văn học - Nghệ thuật
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            4
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            11
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/Chocobaiii/posts/213116"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            “Người Sót Lại Của Rừng Cười”: Cái
                                            Cười Méo Mó Man Dại Của Chiến Tranh
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/Chocobaiii"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Chocobaiii
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 3 tuần trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/giai-tri-xa-hoi"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Giải trí - Xã hội
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giai-tri-xa-hoi/am-nhac"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Âm nhạc
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            1
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/ndhai/posts/213058"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Top EDM TikTok Hay Nhất 2023 ♫ BXH
                                            Nhạc Trẻ Remix Mới Nhất ♫ NONSTOP
                                            2023 VINAHOUSE BASS CỰC MẠNH
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/ndhai"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Nguyễn Đặng Hải
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giai-tri-xa-hoi/hinh-anh-dep"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Hình ảnh đẹp
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/107695892262678988251/posts/213075"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            ảnh đẹp
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/107695892262678988251"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Linh Chi Phạm
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giai-tri-xa-hoi/thu-gian-do-vui"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Thư giãn - Đố vui
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            2
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/107695892262678988251/posts/213076"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Đố vui
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/107695892262678988251"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Linh Chi Phạm
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giai-tri-xa-hoi/the-gioi-game"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Thế giới Game
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/107695892262678988251/posts/213077"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Game vui
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/107695892262678988251"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Linh Chi Phạm
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giai-tri-xa-hoi/kien-thuc-thu-vi"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Kiến thức thú vị
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            3
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            1
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/kienthuctonghop/posts/213115"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Forum và Mạng Xã hội – Bạn đồng hành
                                            hay kẻ thù không đội trời chung?
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/kienthuctonghop"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Kiến thức tổng hợp
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giai-tri-xa-hoi/chuyen-showbiz"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Chuyện Showbiz
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/haoquangrucro/posts/213099"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Sự thay đổi khó tin của nữ diễn viên
                                            xinh đẹp sau khi tăng cân mất kiểm
                                            soát: Thương cô ấy thật nhiều
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/haoquangrucro"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Hào quang rực rỡ
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/hoat-dong-ngoai-khoa"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Hoạt động ngoại khóa
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoat-dong-ngoai-khoa/su-kien-truong"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Sự kiện trường tổ chức
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/107695892262678988251/posts/213078"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Tết
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/107695892262678988251"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Linh Chi Phạm
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoat-dong-ngoai-khoa/cau-lac-bo"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Câu lạc bộ
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/techplusvn/posts/213090"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Câu lạc bộ Bóng bàn THPT Chuyên Biên
                                            Hoà tuyển thành viên!!!
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/techplusvn"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Tech Plus VN
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoat-dong-ngoai-khoa/tinh-nguyen-va-cong-dong"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Tình nguyện và cộng đồng
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/chi/posts/213080"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Chương trình tình nguyện Tết
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/chi"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            nguyẽn kim chi
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/hoat-dong-ngoai-khoa/the-thao-va-suc-khoe"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Thể thao và sức khỏe
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/chi/posts/213081"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Tập luyện thể thao nâng cao sức
                                            khỏe!
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/chi"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            nguyẽn kim chi
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/ky-nang-song"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Góc kỹ năng sống
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/ky-nang-song/ky-nang-mem"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Kỹ năng mềm
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            1
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/tunganhdzaii/posts/213063"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            VÌ SAO NGƯỜI THẦY TỐT LẠI LÀM CHO
                                            HỌC TRÒ KHÔNG CẦN ĐẾN MÌNH?
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/tunganhdzaii"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Tùng Anh Dương
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/ky-nang-song/huong-nghiep"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Hướng nghiệp
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/chi/posts/213082"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Hướng nghiệp năm 2025
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/chi"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            nguyẽn kim chi
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/ky-nang-song/suc-khoe-va-tam-ly"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Sức khỏe và tâm lý
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            2
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/ngaymaithanhcong/posts/213097"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Cảnh báo khẩn: Chiếc cốc nhiều người
                                            Việt dùng uống nước mỗi ngày không
                                            khác gì 'uống chất đ.ộ.c'
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/ngaymaithanhcong"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Ngày mai thành công
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 1 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/giao-luu"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Giao lưu
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giao-luu/ky-tuc-xa-va-doi-song"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Ký túc xá &amp; đời sống học sinh
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/daomeomeoh/posts/213074"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            KTX – Ở Là Quen, Về Là Nhớ!
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/daomeomeoh"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Phạm Xuân Đào
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giao-luu/cuu-hoc-sinh"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Kết nối cựu học sinh
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            4
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/messironaldo/posts/213065"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Theo Tây Ban Nha ăn luôn ae ạ
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/messironaldo"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Nguyễn văn mesis
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giao-luu/tam-su"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Góc tâm sự
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/Tunna/posts/213071"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Từ trường con người: Gặp người tử
                                            tế, năng lượng tích cực
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/Tunna"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Tunna Duong
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/giao-luu/tim-do-that-lac"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Tìm đồ thất lạc
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            1
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/TuanAnhDaDen/posts/213068"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Giúp mình tìm chìa khóa xe với
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/TuanAnhDaDen"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            quên rồi
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/mua-ban-trao-doi"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Mua bán và trao đổi
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/mua-ban-trao-doi/sach-cu"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Mua bán sách cũ
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            1
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/daomeomeoh/posts/213073"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Sale sách cũ - Giá mềm như bông!
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/daomeomeoh"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Phạm Xuân Đào
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/mua-ban-trao-doi/do-dung-hoc-tap"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Đồ dùng học tập
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            2
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/daomeomeoh/posts/213084"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Đồ Dùng Học Tập – Bạn Đồng Hành Tri
                                            Thức!
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/daomeomeoh"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Phạm Xuân Đào
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 5 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/mua-ban-trao-doi/trao-doi-vat-dung-ca-nhan"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Trao đổi vật dụng cá nhân
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            3
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            8
                                        </span>
                                    </span>
                                </div>
                                <div
                                    style={{ maxWidth: "calc(42%)" }}
                                    className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                                >
                                    <div className="flex">
                                        <span className="whitespace-nowrap mr-1">
                                            Mới nhất:
                                        </span>
                                        <a
                                            href="/1704050350443629/posts/213087"
                                            className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                                        >
                                            Mở cửa hàng bia hơi
                                        </a>
                                    </div>
                                    <div className="flex items-center mt-1 text-[#319528]">
                                        <a
                                            href="/1704050350443629"
                                            className="hover:text-[#319528] hover:underline truncate"
                                        >
                                            Tùng Anh Dương
                                        </a>
                                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                            , 2 tháng trước
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {/* Section */}
                    <div className="max-w-[775px] w-[100%] mb-6">
                        <a
                            href="/forum/gop-y-bao-loi"
                            className="text-lg font-semibold px-4 uppercase"
                        >
                            Góp ý và báo lỗi
                        </a>
                        <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/gop-y-bao-loi/phan-hoi-ve-dien-dan"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Phản hồi về diễn đàn
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            0
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <hr />
                            <div className="flex flex-row items-center min-h-[78px] pr-2">
                                <ion-icon
                                    name="chatbubbles"
                                    className="text-[#319528] text-3xl p-4"
                                />
                                <div className="flex flex-col flex-1">
                                    <a
                                        href="/forum/gop-y-bao-loi/bao-loi-ky-thuat"
                                        className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                                    >
                                        Báo lỗi kỹ thuật
                                    </a>
                                    <span className="text-sm text-gray-500">
                                        Bài viết:{" "}
                                        <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                            0
                                        </span>
                                        Bình luận:{" "}
                                        <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                            0
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div className="w-full flex justify-center">
                                {/* AdSense manual ad code */}
                                <ins
                                    className="adsbygoogle"
                                    style={{ display: "block" }}
                                    data-ad-format="fluid"
                                    data-ad-layout-key="-hl+a-w-1e+66"
                                    data-ad-client="ca-pub-3425905751761094"
                                    data-ad-slot={6807515102}
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div className="px-2.5 relative z-10 mb-4 mt-2">
                    <div className="max-w-[775px] mx-auto bg-white dark:!bg-[var(--main-white)] p-6 rounded-lg long-shadow">
                        <div className="flex flex-row items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold uppercase">
                                Thống kê diễn đàn
                            </h2>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div className="bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:hover:!bg-[#4a4a4a] hover:bg-green-100 shadow-md rounded-lg p-4 text-center">
                                <ion-icon
                                    name="newspaper-outline"
                                    className="text-[30px] text-green-600"
                                />
                                <h3 className="text-xl font-semibold">65</h3>
                                <p className="text-gray-500">Bài viết</p>
                            </div>
                            <div className="bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:hover:!bg-[#4a4a4a] hover:bg-green-100 shadow-md rounded-lg p-4 text-center">
                                <ion-icon
                                    name="chatbox-ellipses-outline"
                                    className="text-[30px] text-green-600"
                                />
                                <h3 className="text-xl font-semibold">246</h3>
                                <p className="text-gray-500">Bình luận</p>
                            </div>
                            <div className="bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:hover:!bg-[#4a4a4a] hover:bg-green-100 shadow-md rounded-lg p-4 text-center">
                                <ion-icon
                                    name="person-outline"
                                    className="text-[30px] text-green-600"
                                />
                                <h3 className="text-xl font-semibold">118</h3>
                                <p className="text-gray-500">Người dùng</p>
                            </div>
                        </div>
                        <div className="mt-6">
                            <p className="text-gray-600 dark:!text-gray-50">
                                Chúng ta cùng chào mừng thành viên mới nhất đã
                                tham gia diễn đàn:
                                <a
                                    href="/yousobadye"
                                    className="hover:underline font-bold text-green-600"
                                >
                                    hg
                                </a>
                            </p>
                            <p className="text-gray-600 my-2 dark:!text-gray-50">
                                Tổng cộng có
                                <span className="font-bold text-green-600">
                                    1
                                </span>{" "}
                                người dùng trực tuyến:
                                <span className="font-semibold">0</span> đã đăng
                                ký,
                                <span className="font-semibold">0</span> ẩn và
                                <span className="font-semibold">1</span> khách
                            </p>
                            <p className="text-gray-600 dark:!text-gray-50">
                                Số người dùng trực tuyến nhiều nhất là
                                <span className="font-semibold text-green-600">
                                    3077
                                </span>
                                vào
                                <span>
                                    Thứ Bảy, ngày 3 tháng 5 năm 2025, 09:53 CH
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </>
        </HomeLayout>
    );
}
